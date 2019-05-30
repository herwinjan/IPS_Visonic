<?php

if (@constant('IPS_BASE') == null) { //Nur wenn Konstanten noch nicht bekannt sind.
    define('IPS_BASE', 10000); //Base Message
    define('IPS_DATAMESSAGE', IPS_BASE + 1100); //Data Handler Message
    define('DM_CONNECT', IPS_DATAMESSAGE + 1); //On Instance Connect
    define('DM_DISCONNECT', IPS_DATAMESSAGE + 2); //On Instance Disconnect
    define('IPS_INSTANCEMESSAGE', IPS_BASE + 500); //Instance Manager Message
    define('IM_CHANGESTATUS', IPS_INSTANCEMESSAGE + 5); //Status was Changed
    define('IM_CHANGESETTINGS', IPS_INSTANCEMESSAGE + 6); //Settings were Changed
    define('IPS_VARIABLEMESSAGE', IPS_BASE + 600); //Variable Manager Message
    define('VM_CREATE', IPS_VARIABLEMESSAGE + 1); //Variable Created
    define('VM_DELETE', IPS_VARIABLEMESSAGE + 2); //Variable Deleted
    define('VM_UPDATE', IPS_VARIABLEMESSAGE + 3); //On Variable Update
    define('VM_CHANGEPROFILENAME', IPS_VARIABLEMESSAGE + 4); //On Profile Name Change
    define('VM_CHANGEPROFILEACTION', IPS_VARIABLEMESSAGE + 5); //On Profile Action Change
}

$zoneEventType = array(
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
    0x14 => "Siren AC Fail");

$systemState = array(
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
    0x0D => "Not Ready",
);
$zoneEventType = array(
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
    0x14 => "Siren AC Fail",
);
$stateFlags = array(
    1 => "Klaar om in te schakelen",
    2 => "Alert in geheugen",
    4 => "Probleem",
    8 => "Bypass On",
    16 => "Last 10 seconds of entry or exit delay",
    32 => "Zone event",
    64 => "Arm, disarm event",
    128 => "Alarm!",
);

trait InstanceStatus
{
    /**
     * Ermittelt den Parent und verwaltet die Einträge des Parent im MessageSink
     * Ermöglicht es das Statusänderungen des Parent empfangen werden können.
     *
     * @access private
     */
    protected function _GetParentData()
    {
        $OldParentId = $this->Parent;
        $ParentId = @IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($OldParentId > 0) {
            $this->UnregisterMessage($OldParentId, IM_CHANGESTATUS);
        }
        if ($ParentId > 0) {
            $this->RegisterMessage($ParentId, IM_CHANGESTATUS);
            $this->Parent = $ParentId;
        } else {
            $this->Parent = 0;
        }
        return $ParentId;
    }
    /**
     * Setzt den Status dieser Instanz auf den übergebenen Status.
     * Prüft vorher noch ob sich dieser vom aktuellen Status unterscheidet.
     *
     * @access protected
     * @param int $InstanceStatus
     */
    protected function _SetStatus($InstanceStatus)
    {
        if ($InstanceStatus != IPS_GetInstance($this->InstanceID)['InstanceStatus']) {
            parent::SetStatus($InstanceStatus);
        }
    }
    /**
     * Prüft den Parent auf vorhandensein und Status.
     *
     * @access protected
     * @return bool True wenn Parent vorhanden und in Status 102, sonst false.
     */
    protected function _HasActiveParent()
    {
        $instance = @IPS_GetInstance($this->InstanceID);
        if ($instance['ConnectionID'] > 0) {
            $parent = IPS_GetInstance($instance['ConnectionID']);
            if ($parent['InstanceStatus'] == 102) {
                return true;
            }
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
class VisonicAlarmDevice extends IPSModule
{
    use
        InstanceStatus;

    public $Parent;
    public $ParentID;
    public $status = 0;
    public $flag = 0;
    public $alarm = false;
    public $zones = array();
    private $__usertoken = "";
    private $__progtoken = "";
    private $__debug = false;

    // The constructor of the module
    // Overrides the default constructor of IPS
    public function __construct($InstanceID)
    {
        // Do not delete this row
        parent::__construct($InstanceID);

        // Self-service code
    }

    // Overrides the internal IPS_Create ($ id) function
    public function Create()
    {
        // Do not delete this row.
        parent::Create();

        $this->RequireParent("{3AB77A94-3467-4E66-8A73-840B4AD89582}");
        $this->ConnectParent("{3AB77A94-3467-4E66-8A73-840B4AD89582}");
        // $this->RegisterMessage(0, 'DM_CONNECT');
        $this->RegisterMessage(0, 10503);
        $this->RegisterMessage(0, 10504);
        $this->RegisterMessage(0, 11101);

        $this->RegisterPropertyString("UserToken", "");
        $this->RegisterPropertyString("ProgToken", "");
        $this->RegisterPropertyBoolean("debug", false);
        $this->__debug = $this->ReadPropertyBoolean("debug");

        IPS_LogMessage("Visonic DEBUG", "Create! -> " . $this->InstanceID);

        if (!IPS_VariableProfileExists("VisonicStatusProfile")) {
            IPS_CreateVariableProfile("VisonicStatusProfile", 1);
            IPS_SetVariableProfileAssociation("VisonicStatusProfile", 0, "Uitgeschakeld", "", -1);
            IPS_SetVariableProfileAssociation("VisonicStatusProfile", 1, "Wegloop vertraging", "", -1);
            IPS_SetVariableProfileAssociation("VisonicStatusProfile", 2, "Wegloop vertraging", "", -1);
            IPS_SetVariableProfileAssociation("VisonicStatusProfile", 3, "Binnekomst vertraging", "", -1);
            IPS_SetVariableProfileAssociation("VisonicStatusProfile", 4, "Ingeschakeld (Thuis)", "", -1);
            IPS_SetVariableProfileAssociation("VisonicStatusProfile", 4, "Ingeschakeld (Weg)", "", -1);

        }
        if (!IPS_VariableProfileExists("VisonicZoneProfile")) {
            IPS_CreateVariableProfile("VisonicZoneProfile", 1);
            global $zoneEventType;
            foreach ($zoneEventType as $key => $value) {
                IPS_SetVariableProfileAssociation("VisonicZoneProfile", $key, $value, "", -1);
            }
        }
        if (!IPS_VariableProfileExists("VisonicControlProfile")) {
            IPS_CreateVariableProfile("VisonicControlProfile", 1);
            IPS_SetVariableProfileAssociation("VisonicControlProfile", 0, "Uitgeschakelen", "", -1);
            IPS_SetVariableProfileAssociation("VisonicControlProfile", 4, "Ingeschakelen (Thuis)", "", -1);
            IPS_SetVariableProfileAssociation("VisonicControlProfile", 5, "Ingeschakelen (Weg)", "", -1);

        }
        if (!IPS_VariableProfileExists("VisonicZoneBatteryProfile")) {
            IPS_CreateVariableProfile("VisonicZoneBatteryProfile", 1);
            IPS_SetVariableProfileAssociation("VisonicZoneBatteryProfile", 0, "Vol", "", -1);
            IPS_SetVariableProfileAssociation("VisonicZoneBatteryProfile", 1, "Leeg", "", -1);

        }

//CreateVariable($Name, $Type, $Value, $Ident = '', $ParentID = 0)
        $id = $this->__CreateVariable("Alarm Status", 1, 0, "VisonicStatus", $this->InstanceID);
        IPS_SetVariableCustomProfile($id, "VisonicStatusProfile");
        $id = $this->__CreateVariable("Alarm Mededeling", 3, 0, "VisonicFlag", $this->InstanceID);

        $id = $this->__CreateVariable("Alarm Control", 1, 0, "VisonicControl", $this->InstanceID);
        IPS_SetVariableCustomProfile($id, "VisonicControlProfile");
        $this->EnableAction("VisonicControl");

        return true;
    }

    // Overrides the intere IPS_ApplyChanges ($ id) function
    public function ApplyChanges()
    {
        // Do not delete this line
        IPS_LogMessage("Visonic DEBUG", "Apply changes!");
        parent::ApplyChanges();
        $this->ParentID = $this->_GetParentData();
        IPS_LogMessage("Visonic PID", $this->ParentID);

        // $this->__usertoken=$this->ReadPropertyString("UserToken");
        // $this->__progtoken=$this->ReadPropertyString("ProgToken");
        $this->__debug = $this->ReadPropertyBoolean("debug");

        IPS_LogMessage("Visonic PID", IPS_GetProperty($this->ParentID, 'Open'));
        IPS_LogMessage("Visonic Debug", "Debug turned " . $this->__debug == true ? "on" : "off");

        $this->RegisterMessage($this->InstanceID, 10503);
        $this->RegisterMessage($this->InstanceID, 10504);

        //$this->RegisterMessage(0, 10100 );
    }

    public function RequestAction($ident, $value)
    {

        switch ($ident) {
            case "VisonicControl":
                $this->setStatus($value);

                break;
            default:
                throw new Exception("Invalid ID");
        }
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        //            $this->__debug(__FUNCTION__,"entered");
        $id = $SenderID;
        if ($this->__debug) {
            IPS_LogMessage("Visonic DEBUG", "TS: $TimeStamp SenderID " . $SenderID . " with MessageID " . $Message . " Data: " . print_r($Data, true));
        }

        switch ($Message) {

            default:
                if ($this->__debug) {
                    IPS_LogMessage(__CLASS__, __FUNCTION__ . " Unknown Message $Message");
                }

                break;
        }
    }

    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString);
        // IPS_LogMessage("Visonic RERV", utf8_decode($data->Buffer));

        $dt = json_decode($data->Buffer, true);

        if (isset($dt)) {
            switch ($dt["action"]) {
                case "zones":
                    print_r($dt["data"]);
                    if ($this->__debug) {
                        IPS_LogMessage("Visonic DEBUG", "got zone " . $dt["data"][1]["name"]);
                    }

                    $cat = $this->__CreateCategory("Zones", "VisonicZones", $this->InstanceID);
                    $bat = $this->__CreateCategory("Battery", "VisonicZoneBattery", $this->InstanceID);

                    $zones = array();
                    foreach ($dt["data"] as $key => $z) {
                        if ($cat) {
                            //if ($this->__debug) {
                            IPS_LogMessage("Visonic DEBUG", "Create Zone: " . $z["name"] . " key: " . $key . " in $cat");
                            //}

                            $id = $this->__CreateVariable($z["name"], 1, 0, "VisonicZone" . $key, $cat);
                            $idb = $this->__CreateVariable($z["name"], 1, 0, "VisonicZoneBattery" . $key, $bat);

                            IPS_SetVariableCustomProfile($id, "VisonicZoneProfile");
                            IPS_SetVariableCustomProfile($idb, "VisonicZoneBatteryProfile");

                        }
                        $this->zones[$key] = $z["name"];
                    }
                    break;
                case "ping":
                    if ($this->__debug) {
                        IPS_LogMessage("Visonic DEBUG", "got ping!");
                    }

                    break;
                case "state":
                    $sid = @IPS_GetObjectIDByIdent("VisonicStatus", $this->InstanceID);
                    $this->satus = $dt["data"];
                    if ($sid) {
                        SetValue($sid, $dt["data"]);
                    }
                    if ($dt["data"] == 0 || $dt["data"] == 4 || $dt["data"] == 5) {
                        $sid = @IPS_GetObjectIDByIdent("VisonicControl", $this->InstanceID);
                        if ($sid) {
                            SetValue($sid, $dt["data"]);
                        }
                    }
                    if ($this->__debug) {
                        IPS_LogMessage("Visonic DEBUG", "State " . $dt["data"]);
                    }

                    break;
                case "zonestate":
                    break;
                case "zoneType":
                    break;
                case "zonealarm":
                    $int = $dt["flag"];
                    $z = $dt["id"];

                    if (($int & 128) == 128) {
                        if ($this->alarm == false) {
                            $this->alarm = true;
                            IPS_LogMessage("Visonic DEBUG", "ALARM GAAT AF!!");
                            $id = @IPS_GetObjectIDByIdent("VisonicZones", $this->InstanceID);
                            $sid = @IPS_GetObjectIDByIdent("VisonicZone" . $z, $id);
                            $zone = @IPS_GetObject($sid);
                            $this->sendPushoverMessage("<b>Alarm gaat af!!!</b>Alarm in zone " . $zone["ObjectName"] . " ($z)!!", 2, "siren");
                        }
                    } else {
                        $this->alarm = false;
                    }
                    break;
                case "flag":
                    if ($this->__debug) {
                        IPS_LogMessage("Visonic DEBUG", "Flag " . $dt["data"]);
                    }

                    $this->flag = $dt["data"];

                    $int = $dt["data"];

                    $sid = @IPS_GetObjectIDByIdent("VisonicFlag", $this->InstanceID);
                    if ($sid) {
                        global $stateFlags;
                        $str = "";
                        $first = true;
                        foreach ($stateFlags as $i => $v) {
                            if (($int & $i) == $i) {
                                if (!$first) {
                                    $str .= " | ";
                                }

                                $str .= $stateFlags[$i];
                                $first = false;
                            }
                        }
                        SetValue($sid, $str);
                    }
                    break;
                case "zonestatus":

                    if (isset($dt["status"])) {
                        if ($this->__debug) {
                            IPS_LogMessage("Visonic DEBUG", "Zone: " . $dt["id"] . " status: " . $dt["status"]);
                        }

                        $id = @IPS_GetObjectIDByIdent("VisonicZones", $this->InstanceID);
                        $sid = @IPS_GetObjectIDByIdent("VisonicZone" . $dt["id"], $id);
                        if ($this->__debug) {
                            IPS_LogMessage("Visonic DEBUG", "ident $sid");
                        }

                        if ($sid !== false) {
                            if ($this->__debug) {
                                IPS_LogMessage("Visonic DEBUG", "got ID for ZOne " . $sid);
                            }

                            $b = GetValue($sid);
                            if ($this->__debug) {
                                IPS_LogMessage("Visonic DEBUG", "status nu: " . $b . " new: " . $dt["status"]);
                            }

                            if ($b != $dt["status"]) {
                                SetValue($sid, $dt["status"]);
                            }
                        }
                    } elseif (isset($dt["battery"])) {
                        if ($this->__debug) {
                            IPS_LogMessage("Visonic DEBUG", "Zone: " . $dt["id"] . " Battery: " . $dt["battery"]);
                        }

                        $id = @IPS_GetObjectIDByIdent("VisonicZoneBattery", $this->InstanceID);
                        $sid = @IPS_GetObjectIDByIdent("VisonicZoneBattery" . $dt["id"], $id);
                        //IPS_LogMessage("Visonic DEBUG", "ident $sid");
                        if ($sid !== false) {
                            if ($this->__debug) {
                                IPS_LogMessage("Visonic DEBUG", "got ID for ZOne " . $sid);
                            }

                            $b = GetValue($sid);
                            if ($this->__debug) {
                                IPS_LogMessage("Visonic DEBUG", "status nu: " . $b . " new: " . $dt["battery"]);
                            }

                            if ($b != $dt["battery"]) {
                                SetValue($sid, $dt["battery"]);
                                if ($dt["battery"] > 0) {
                                    $z = $dt["id"];

                                    $id = @IPS_GetObjectIDByIdent("VisonicZones", $this->InstanceID);
                                    $sid = @IPS_GetObjectIDByIdent("VisonicZone" . $z, $id);
                                    $zone = @IPS_GetObject($sid);

                                    $this->sendPushoverMessage("<b>Battery bijna leeg.</b>Battery is bijna leeg in zone " . $zone["ObjectName"] . " ($z)!!", 0, "");
                                }
                            }
                        }
                    } else {
                        if ($this->__debug) {
                            IPS_LogMessage("Visonic DEBUG", "Zone: Unknown action => " . $dt["id"]);
                        }

                    }
                    break;
                default:
                    if ($this->__debug) {
                        IPS_LogMessage("Visonic DEBUG", "unknown action: " . utf8_decode($dt["data"]));
                    }

            }
        }

        //Parse and write values to our variables
    }
    /*  public function ForwardData($JSONString)
    {
    parent::ForwardData($JSONString);
    $data = json_decode($JSONString);
    IPS_LogMessage("Visonic FRWD", utf8_decode($data->Buffer));
    return "String data for the device instance!";
    }*/
    private function __CreateCategory($Name, $Ident = '', $ParentID = 0)
    {
        $RootCategoryID = $this->InstanceID;
        IPS_LogMessage("Visonic DEBUG", "CreateCategory: ( $Name, $Ident, $ParentID ) \n");
        if ('' != $Ident) {
            $CatID = @IPS_GetObjectIDByIdent($Ident, $ParentID);
            if (false !== $CatID) {
                $Obj = IPS_GetObject($CatID);
                if (0 == $Obj['ObjectType']) { // is category?
                    return $CatID;
                }
            }
        }
        $CatID = IPS_CreateCategory();
        IPS_SetName($CatID, $Name);
        IPS_SetIdent($CatID, $Ident);
        if (0 == $ParentID) {
            if (IPS_ObjectExists($RootCategoryID)) {
                $ParentID = $RootCategoryID;
            }
        }
        IPS_SetParent($CatID, $ParentID);
        return $CatID;
    }

    private function __CreateVariable($Name, $Type, $Value, $Ident = '', $ParentID = 0)
    {
        IPS_LogMessage("Visonic DEBUG", "CreateVariable: ( $Name, $Type, $Value, $Ident, $ParentID ) \n");
        if ('' != $Ident) {
            $VarID = @IPS_GetObjectIDByIdent($Ident, $ParentID);
            if (false !== $VarID) {
                $this->__SetVariable($VarID, $Type, $Value);
                return $VarID;
            }
        }
        $VarID = @IPS_GetObjectIDByName($Name, $ParentID);
        if (false !== $VarID) { // exists?
            $Obj = IPS_GetObject($VarID);
            if (2 == $Obj['ObjectType']) { // is variable?
                $Var = IPS_GetVariable($VarID);
                if ($Type == $Var['VariableValue']['ValueType']) {
                    $this->__SetVariable($VarID, $Type, $Value);
                    return $VarID;
                }
            }
        }
        $VarID = IPS_CreateVariable($Type);
        IPS_SetParent($VarID, $ParentID);
        IPS_SetName($VarID, $Name);
        if ('' != $Ident) {
            IPS_SetIdent($VarID, $Ident);
        }
        $this->__SetVariable($VarID, $Type, $Value);
        return $VarID;
    }

    private function __SetVariable($VarID, $Type, $Value)
    {
        switch ($Type) {
            case 0: // boolean
                SetValueBoolean($VarID, $Value);
                break;
            case 1: // integer
                SetValueInteger($VarID, $Value);
                break;
            case 2: // float
                SetValueFloat($VarID, $Value);
                break;
            case 3: // string
                SetValueString($VarID, $Value);
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
    public function setStatus($status)
    {
        // Self-definedCode
        if ($this->__debug) {
            IPS_LogMessage("Visonic", "Set Status to $status");
        }

        $this->SendDataToParent(json_encode(array("DataID" => "{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}", "Buffer" => "$status")));

    }

    public function sendPushoverMessage(string $message, int $priority, string $sound
    ) {
        $this->__usertoken = $this->ReadPropertyString("UserToken");
        $this->__progtoken = $this->ReadPropertyString("ProgToken");
        IPS_LogMessage("PushOver", "Send: $this->__progtoken, $this->__usertoken, $message, $priority, $sound");
        curl_setopt_array($ch = curl_init(), array(
            CURLOPT_URL => "https://api.pushover.net/1/messages.json",
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_POSTFIELDS => array(
                "token" => $this->__progtoken,
                "user" => $this->__usertoken,
                "message" => $message,
                "sound" => $sound,
                "html" => 1,
                "priority" => "$priority",
                "retry" => "30",
                "expire" => "3600",
            )));
        curl_exec($ch);
        curl_close($ch);
    }
}
