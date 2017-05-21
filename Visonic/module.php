<?php
//

class VisonicGateway extends IPSModule {

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
       $this->RegisterMessage(0, 10100 );
       $this->RegisterPropertyBoolean('Active', false);


   }

   // Overrides the intere IPS_ApplyChanges ($ id) function
   public function ApplyChanges( )  {
       // Do not delete this line
       parent::ApplyChanges();
       $this->RequireParent("{3AB77A94-3467-4E66-8A73-840B4AD89582}");
       $this->ConnectParent("{3AB77A94-3467-4E66-8A73-840B4AD89582}");
       $this->RegisterMessage(0, 10100 );

   }


   public function MessageSink($TimeStamp, $SenderID, $Message, $Data) {
//            $this->debug(__FUNCTION__,"entered");
        $id=$SenderID;
        Debug::debug("TS: $TimeStamp SenderID ".$SenderID." with MessageID ".$Message." Data: ".print_r($Data, true));
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
        IPS_LogMessage("Visonic RERV", utf8_decode($data->Buffer));

        $this->store .= utf8_decode($data->Buffer);
        $this->parseData ();
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
