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
   define('IPS_VARIABLEMESSAGE', IPS_BASE + 600);              //Variable Manager Message
   define('VM_CREATE', IPS_VARIABLEMESSAGE + 1);               //Variable Created
   define('VM_DELETE', IPS_VARIABLEMESSAGE + 2);               //Variable Deleted
   define('VM_UPDATE', IPS_VARIABLEMESSAGE + 3);               //On Variable Update
   define('VM_CHANGEPROFILENAME', IPS_VARIABLEMESSAGE + 4);    //On Profile Name Change
   define('VM_CHANGEPROFILEACTION', IPS_VARIABLEMESSAGE + 5);  //On Profile Action Change

}

$systemState = array (
	0x00 => "Uitgeschakeld",
	0x01 => "Exit Delay",
	0x02 => "Exit Delay",
	0x03 => "Entry Delay",
	0x04 => "Ingeschakeld (Thuis)",
	0x05 => "Ingeschakeld (Volledig)",
	0x06 => "User Test",
	0x07 => "Downloading",
	0x08 => "Programming",
	0x09 => "Installer",
	0x0A => "Home Bypass",
	0x0B => "Away Bypass",
	0x0C => "Ready",
	0x0D => "Not Ready"
);
$zoneEventType = array (
	0x00 => "None",
	0x01 => "Tamper Alarm",
	0x02 => "Tamper Restore",
	0x03 => "Open",
	0x04 => "Closed",
	0x05 => "Violated (Motion)",
	0x06 => "Panic Alarm",
	0x07 => "RF Jamming",
	0x08 => "Tamper Open",
	0x09 => "Communication Failure",
	0x0A => "Line Failure",
	0x0B => "Fuse",
	0x0C => "Not Active",
	0x0D => "Low Battery",
	0x0E => "AC Failure",
	0x0F => "Fire Alarm",
	0x10 => "Emergency",
	0x11 => "Siren Tamper",
	0x12 => "Siren Tamper Restore",
	0x13 => "Siren Low Battery",
	0x14 => "Siren AC Fail"
);
$stateFlags = array (
	1 => "Klaar om in te schakelen",
	2 => "Alert in geheugen",
	4 => "Probleem",
	8 => "Bypass On",
	16 => "Last 10 seconds of entry or exit delay",
	32 => "Zone event",
	64 => "Arm, disarm event",
	128 => "Alarm!"
);

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
class VisonicAlarmDevice extends IPSModule {
     use
        InstanceStatus;

        var $Parent;
        var $ParentID;


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
       $this->RegisterMessage(0, DM_CONNECT );
       $this->RegisterMessage(0, 10503 );
       $this->RegisterMessage(0, 11101 );


       $this->RegisterPropertyBoolean('Active', false);
       IPS_LogMessage("Visonic DEBUG", "Create!");


       $sid=$sid=@IPS_GetObjectIDByIdent("VisonicAlarmStatus",0);
       if ($sid==false)
       {
            $this->RegisterVariableInteger("VisonicAlarmStatus","Status","",0);
            $this->RegisterVariableInteger("VisonicAlarmFlag","Flag","",0);
       }
       else
       {

       }
       return true;
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
                    $cat=$this->CreateCategory("Zones","VisonicZones",$this->InstanceID);
                    if ($cat) {
                    foreach ($dt["data"] as $key=>$z)
                    {
                         IPS_LogMessage("Visonic DEBUG","Create Zone: ".$z["name"]." key: ".$key." in $cat");
                         $this->CreateVariable($z["name"],1,0,"VisonicZone".$key,$cat);
                    }
               }
                    break;
                   case "ping":
                    IPS_LogMessage("Visonic DEBUG","got ping!");
                    break;
                   case "state";
                    $sid=@IPS_GetObjectIDByIdent("VisonicAlarmStatus",$this->InstanceID);
                    if ($sid) SetValue($sid,$dt["data"]);
                   IPS_LogMessage("Visonic DEBUG","State ".$dt["data"]);
                   break;
                   case "zonestate";
                   break;
                   case "zoneType";
                   break;
                   case "flag";
                   IPS_LogMessage("Visonic DEBUG","Flag ".$dt["data"]);
                   $sid=@IPS_GetObjectIDByIdent("VisonicAlarmFlag",$this->InstanceID);
                   if ($sid) SetValue($sid,$dt["data"]);
                   break;
                   case "zonestatus";

                   if (isset($dt["status"]))
                   {
                        IPS_LogMessage("Visonic DEBUG","Zone: ".$dt["id"]." status: ".$dt["status"]);
                         $sid=@IPS_GetObjectIDByIdent("VisonicZone".$dt["id"],0);
                         IPS_LogMessage("Visonic DEBUG","ident $sid");
                         if ($sid !== false)
                         {
                              IPS_LogMessage("Visonic DEBUG","got ID for ZOne ".$sid);
                              $b=GetValue($sid);
                              IPS_LogMessage("Visonic DEBUG","status nu: ".$b." new: ".$st["status"]);
                              if ($b!=$dt["status"])
                                   SetValue($sid,$dt["status"]);
                         }
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
   private function CreateCategory( $Name, $Ident = '', $ParentID = 0 )
{
	$RootCategoryID=$this->InstanceID;
	echo "CreateCategory: ( $Name, $Ident, $ParentID ) \n";
	if ( '' != $Ident )
	{
		$CatID = @IPS_GetObjectIDByIdent( $Ident, $ParentID );
		if ( false !== $CatID )
		{
		   $Obj = IPS_GetObject( $CatID );
		   if ( 0 == $Obj['ObjectType'] ) // is category?
		      return $CatID;
		}
	}
	$CatID = IPS_CreateCategory();
	IPS_SetName( $CatID, $Name );
   IPS_SetIdent( $CatID, $Ident );
	if ( 0 == $ParentID )
		if ( IPS_ObjectExists( $RootCategoryID ) )
			$ParentID = $RootCategoryID;
	IPS_SetParent( $CatID, $ParentID );
	return $CatID;
}

   private function CreateVariable( $Name, $Type, $Value, $Ident = '', $ParentID = 0 )
{
	echo "CreateVariable: ( $Name, $Type, $Value, $Ident, $ParentID ) \n";
	if ( '' != $Ident )
	{
		$VarID = @IPS_GetObjectIDByIdent( $Ident, $ParentID );
		if ( false !== $VarID )
		{
		   SetVariable( $VarID, $Type, $Value );
		   return;
		}
	}
	$VarID = @IPS_GetObjectIDByName( $Name, $ParentID );
	if ( false !== $VarID ) // exists?
	{
	   $Obj = IPS_GetObject( $VarID );
	   if ( 2 == $Obj['ObjectType'] ) // is variable?
		{
		   $Var = IPS_GetVariable( $VarID );
		   if ( $Type == $Var['VariableValue']['ValueType'] )
			{
			   SetVariable( $VarID, $Type, $Value );
			   return;
			}
		}
	}
	$VarID = IPS_CreateVariable( $Type );
	IPS_SetParent( $VarID, $ParentID );
	IPS_SetName( $VarID, $Name );
	if ( '' != $Ident )
	   IPS_SetIdent( $VarID, $Ident );
	$this->SetVariable( $VarID, $Type, $Value );
}

private function SetVariable( $VarID, $Type, $Value )
{
	switch( $Type )
	{
	   case 0: // boolean
	      SetValueBoolean( $VarID, $Value );
	      break;
	   case 1: // integer
	      SetValueInteger( $VarID, $Value );
	      break;
	   case 2: // float
	      SetValueFloat( $VarID, $Value );
	      break;
	   case 3: // string
	      SetValueString( $VarID, $Value );
	      break;
	}
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
