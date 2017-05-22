<?php
//


if (@constant('IPS_BASE') == null) //Nur wenn Konstanten noch nicht bekannt sind.
{
define('IPS_BASE', 10000);                             //Base Message
define('IPS_DATAMESSAGE', IPS_BASE + 1100);             //Data Handler Message
define('DM_CONNECT', IPS_DATAMESSAGE + 1);             //On Instance Connect
define('DM_DISCONNECT', IPS_DATAMESSAGE + 2);          //On Instance Disconnect
 define('IPS_INSTANCEMESSAGE', IPS_BASE + 500);         //Instance Manager Message
define('IM_CHANGESTATUS', IPS_INSTANCEMESSAGE + 5);    //Status was Changed
   define('IM_CHANGESETTINGS', IPS_INSTANCEMESSAGE + 6);  //Settings were Changed
}





trait InstanceStatus
{
    /**
     * Ermittelt den Parent und verwaltet die Einträge des Parent im MessageSink
     * Ermöglicht es das Statusänderungen des Parent empfangen werden können.
     *
     * @access private
     */
    protected function GetParentData()
    {
        $OldParentId = $this->Parent;
        $ParentId = @IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($OldParentId > 0)
            $this->UnregisterMessage($OldParentId, IM_CHANGESTATUS);
        if ($ParentId > 0)
        {
            $this->RegisterMessage($ParentId, IM_CHANGESTATUS);
            $this->Parent = $ParentId;
        }
        else
            $this->Parent = 0;
        return $ParentId;
    }
    /**
     * Setzt den Status dieser Instanz auf den übergebenen Status.
     * Prüft vorher noch ob sich dieser vom aktuellen Status unterscheidet.
     *
     * @access protected
     * @param int $InstanceStatus
     */
    protected function SetStatus($InstanceStatus)
    {
        if ($InstanceStatus <> IPS_GetInstance($this->InstanceID)['InstanceStatus'])
            parent::SetStatus($InstanceStatus);
    }
    /**
     * Prüft den Parent auf vorhandensein und Status.
     *
     * @access protected
     * @return bool True wenn Parent vorhanden und in Status 102, sonst false.
     */
    protected function HasActiveParent()
    {
        $instance = @IPS_GetInstance($this->InstanceID);
        if ($instance['ConnectionID'] > 0)
        {
            $parent = IPS_GetInstance($instance['ConnectionID']);
            if ($parent['InstanceStatus'] == 102)
                return true;
        }
        return false;
    }
}
/**
 * WebsocketClient Klasse implementiert das Websocket Protokoll als HTTP-Client
 * Erweitert IPSModule.
 *
 * @package VisonicGateway
 * @property int $Parent
 */
class VisonicGateway extends IPSModule {
     use
        InstanceStatus;

        var $Parent;
        var $ParentID;
        var $alarmID;

   // The constructor of the module
   // Overrides the default constructor of IPS
   public function __construct($InstanceID)  {
       // Do not delete this row
       parent::__construct($InstanceID);

       // Self-service code
   }

   // Overrides the internal IPS_Create ($ id) function
   public function Create( )  {
       // Do not delete this row.
       parent::Create();
       $this->RequireParent("{3AB77A94-3467-4E66-8A73-840B4AD89582}");
       $this->ConnectParent("{3AB77A94-3467-4E66-8A73-840B4AD89582}");
       $this->RegisterMessage(0, 10100 );
       $this->RegisterPropertyBoolean('Active', false);
       IPS_LogMessage("Visonic DEBUG", "Create!");

       $sid=@IPS_GetObjectIDByIdent("VisonicAlarm",0);
       if ($sid==false)
       {
            $sid=IPS_CreateInstance("{485D0419-BE97-4548-AA9C-C083EB82E61E}");
            IPS_SetName($sid,"Visonic Alarm");
            IPS_SetIdent($sid,"VisonicAlarm");
            IPS_ApplyChanges($sid);
            $this->alarmID=$sid;

            $this->RegisterVariableInteger("VisonicAlarmStatus","Status","",$sid);
            $this->RegisterVariableInteger("VisonicAlarmFlag","Flag","",$sid);

       }
       else
       {
            $this->alarmID=$sid;
       }

   }

   // Overrides the intere IPS_ApplyChanges ($ id) function
   public function ApplyChanges( )  {
       // Do not delete this line
       IPS_LogMessage("Visonic DEBUG", "Apply changes!");
       parent::ApplyChanges();
       $this->ParentID = $this->GetParentData();
       IPS_LogMessage("Visonic PID", $this->ParentID);
       IPS_LogMessage("Visonic PID", IPS_GetProperty($this->ParentID, 'Open'));
       $this->RegisterMessage($this->InstanceID, DM_CONNECT);
       $this->RegisterMessage($this->InstanceID, DM_DISCONNECT);

       //$this->RegisterMessage(0, 10100 );

   }


   public function MessageSink($TimeStamp, $SenderID, $Message, $Data) {
//            $this->debug(__FUNCTION__,"entered");
        $id=$SenderID;
        IPS_LogMessage("Visonic DEBUG", "TS: $TimeStamp SenderID ".$SenderID." with MessageID ".$Message." Data: ".print_r($Data, true));
        switch ($Message) {
            case self::VM_UPDATE:
                $this->Publish($id,$Data);
                break;
            case self::VM_DELETE:
                $this->UnSubscribe($id);
                break;
            case self::IM_CHANGESTATUS:
                switch ($Data[0]) {
                    case self::ST_AKTIV:
                        $this->debug(__CLASS__,__FUNCTION__."I/O Modul > Aktiviert");
                       // $this->MQTTDisconnect();
                        break;
                    case self::ST_INACTIV:
                        $this->debug(__CLASS__,__FUNCTION__."I/O Modul > Deaktiviert");
                        //$this->MQTTDisconnect();
                        break;
                    case self::ST_ERROR_0:
                        $this->debug(__CLASS__,__FUNCTION__."I/O Modul > Fehler");
                        //$this->MQTTDisconnect();
                        break;
                    default:
                        IPS_LogMessage(__CLASS__,__FUNCTION__."I/O Modul unbekantes Ereignis ".$Data[0]);
                        break;
                }
                break;
            case self::IPS_KERNELMESSAGE:
                $kmsg=$Data[0];
                switch ($kmsg) {
                    case self::KR_READY:
                        IPS_LogMessage(__CLASS__,__FUNCTION__." KR_Ready ->reconect");
                        //$this->MQTTDisconnect();
                        break;
/*
                    case self::KR_UNINIT:
                        // not working :(
                        $msgid=$this->GetBuffer("MsgID");
                        IPS_SetProperty($this->InstanceID,'MsgID',(Integer)$msgid);
                        IPS_ApplyChanges($this->InstanceID);
                        IPS_LogMessage(__CLASS__,__FUNCTION__." KR_UNINIT ->disconnect()");
                        break;  */

                    default:
                        IPS_LogMessage(__CLASS__,__FUNCTION__." Kernelmessage unhahndled, ID".$kmsg);
                        break;
                }
                break;
            default:
                IPS_LogMessage(__CLASS__,__FUNCTION__." Unknown Message $Message");
                break;
        }

   }

   public function ReceiveData($JSONString)
   {
        $data = json_decode($JSONString);
       // IPS_LogMessage("Visonic RERV", utf8_decode($data->Buffer));

        $dt=json_decode($data->Buffer,true);

        if (isset($dt))
        {
             switch($dt["action"])
             {
                  case "zones":
                    print_r($dt["data"]);
                    IPS_LogMessage("Visonic DEBUG","got zone ".$dt["data"][1]["name"]);
                    break;
                   case "ping":
                    IPS_LogMessage("Visonic DEBUG","got ping!");
                    break;
                   case "state";
                   IPS_LogMessage("Visonic DEBUG","State ".$dt["data"]);
                   break;
                   case "zonestate";
                   break;
                   case "zoneType";
                   break;
                   case "flag";
                   IPS_LogMessage("Visonic DEBUG","Flag ".$dt["data"]);
                   break;
                   case "zonestatus";

                   if (isset($dt["status"]))
                   {
                        IPS_LogMessage("Visonic DEBUG","Zone: ".$dt["id"]." status: ".$dt["status"]);
                   }
                   else
                   IPS_LogMessage("Visonic DEBUG","Zone: Unknown action => ".$dt["id"]);
                   break;
                   default:
                    IPS_LogMessage("Visonic DEBUG","unknown action: ".utf8_decode($dt["data"]));
             }


        }


        //Parse and write values to our variables

   }
   public function ForwardData($JSONString) {
          parent::ForwardData($JSONString);
          $data = json_decode($JSONString);
	     IPS_LogMessage("Visonic FRWD", utf8_decode($data->Buffer));
          return "String data for the device instance!";

   }

   /**
   * The following functions are automatically available when the module has been inserted via the "Module Control".
   * The functions, with the self-defined prefix, are provided in PHP and JSON-RPC as follows:
   *
   * ABC_MyFirstElement ($ id);
   *
   */
   public function setStatus($status)  {
       // Self-definedCode
       IPS_LogMessage("Visonic", "Set Status to $status");
   }


}
?>
