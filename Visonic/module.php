<?php
//

define("MSG_DL_SERIAL",1);
define("MSG_DL_ZONESTR",2);
define("MSG_DL_PANELFW",3);

class Debug
{
     function __construct()
     {

     }
     public static function debug($str)
     {
          IPS_LogMessage("Visonic DEBUG",$str);
     }
}

class VisonicGateway extends IPSModule {

     var $node = 0;
     var $pin1 = 0x80;
     var $pin2 = 0x82;
     var $store;
     var $queue = array ();
     var $logedin = 0;
     var $db;

    var $statusTxt="Not Connected";
    var $statusZone=0;
    var $StatusState="";
    var $statusZoneTxt="";
    var $StatusFlag=0;
    var $StatusStateTxt="";
    var $event;
    var $currentState;
    var $keepalive = 15;



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
       $this->RegisterMessage(0, self::IPS_KERNELMESSAGE );


   }

   // Overrides the intere IPS_ApplyChanges ($ id) function
   public function ApplyChanges( )  {
       // Do not delete this line
       parent::ApplyChanges();
       $this->RegisterMessage(0, self::IPS_KERNELMESSAGE );
       $this->init();
      $this->RegisterTimerNow('sendAck', $this->keepalive*1000,  'VISONIC_TimerEvent('.$this->InstanceID.');');


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

   public function TimerEvent() {
        Debug::debug("Timer event send Ack");  // Debug Fenster            // Selbsterstellter Code
        $this->sendAck ();
        $this->checkPacket();
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

   private function addToQueue($packet, $desc)
   {

        $a = array ();
        $a ["packet"] = $packet;
        $a ["desc"] = $desc;
        $this->queue [] = $a;
   }

   private function getFromQueue()
   {

        return array_shift ( $this->queue );
   }


   private function init()
   {

        $this->currentState = - 1;
        $this->sendDownload(MSG_DL_SERIAL);
        $this->sendDownload(MSG_DL_PANELFW);
        $this->sendDownload(MSG_DL_ZONESTR);
        $this->sendStart();
        $this->sendExitDownload();
        $this->sendPinInit ();
        $this->statusTxt="Connected";

   }
   private function findPostamble()
   {

        $ll = strlen ( $this->store );
        for($i = 0; $i < $ll; $i ++)
        {
             if ($this->store [$i] == chr ( 0x0A ) && ($this->store [$i - 2] == chr ( 0x43 ) || $this->store [$i - 1] == chr ( 0xfd )))
             {
                  return $i + 1;
             }
        }
        return - 1;
   }

   private function parseData()
   {

        Debug::debug ( "Start Parse -----------==========" );
        while ( strlen ( $this->store ) > 0 )
        {
             if ($this->store [0] != chr ( 0x0D ))
                  Debug::debug ( "Oops, no 0x0D!!" );

             $post = $this->findPostamble ();

             $debug = "";
             $ll = strlen ( $this->store );
             for($i = 0; $i < $ll; $i ++)
             {
                  $debug .= sprintf ( "%02x", ord ( $this->store [$i] ) ) . " ";
             }
             Debug::debug ( "Q($ll) $post: " . $debug );

             if ($post < 4)
             {
                  Debug::debug ( "Nu full packet, continue" );
                  return;
             }
             else
             {
                  $ll = $post;
                  $debug = "";
                  $str = "";
                  for($i = 0; $i < $ll; $i ++)
                  {
                       $debug .= sprintf ( "%02x", ord ( $this->store [$i] ) ) . " ";
                       $str .= $this->store [$i];
                  }
                  Debug::debug ( "PR($ll): " . $debug );

                  $checksum = $this->checksum ( $str, strlen ( $str ) );
                  Debug::debug ( ord ( $checksum ) );
                  if ($str [strlen ( $str ) - 2] == $checksum)
                  {
                       Debug::debug ( "checksum ok" );
                       $this->parsePacket ( $str );
                       $this->checkPacket ();
                  }
                  $store = $this->store;
                  $this->store = "";
                  Debug::debug ( $post . " len: " . strlen ( $store ) );
                  for($i = $post; $i < strlen ( $store ); $i ++)
                  {
                       $this->store .= $store [$i];
                  }
                  Debug::debug ( "PQ(" . strlen ( $this->store ) . ") " . $this->store );

             }
        }
        Debug::debug ( "done parse -----------==========" );
   }

   private function checkPacket()
   {

        //$this->readData ();
        while (count ( $this->queue ) > 0)
        {
             //usleep ( 1000 );
             //$this->readData ();
             Debug::debug ( "We have packets todo: " . count ( $this->queue ) );
             $buffer = $this->getFromQueue ();
             $buf = $buffer ["packet"];
             Debug::debug ( "Sending: " . $buffer ["desc"] );
             //$this->writeData ( $buf, strlen ( $buf ) );

             $c=$this->SendDataToParent(json_encode(Array("DataID"=>"{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}","Buffer"=>utf8_encode($buf))));
             Debug::debug ( "Send done, wait and read-----------" );
             //usleep ( 1000 );
           /*  $timeout = 0.;
             $start = microtime ( true );
             $time = true;
             while ( $time )
             {
                  if (microtime ( true ) - $start > $timeout)
                       $time = false;
*/
        //usleep ( 10000 );


             //Debug::debug(microtime()-$start. " - ". $timeout);
//             }

             Debug::debug ( "end wait -----------" );

        }
   }
   private function parsePacket($str)
   { //1 9 0


        /* ack: 0d 02 43 ba 0a */
        $len = strlen ( $str );
        Debug::debug ( "Start parse packet: len: $len  command: " . dechex ( ord ( $str [1] ) ) );

        //if ($str [1] != chr ( 0xAB ) && $str [1] != chr ( 0x02 ))
        $this->sendAck ();
        switch ($str [1])
        {
             case chr ( 0x02 ) :
                  {
                       Debug::debug ( "Got Ack" );
                       if ($this->logedin == 0)
                       {
                            $this->sendDateAndTime ();
                            $this->sendStatusUpdate ();
                            $this->logedin = 1;
                       }
                       break;
                  }
             case chr ( 0xAB ) :
                  {
                       Debug::debug ( "Got Init PowerLink" );
                       $this->sendPinInit ();
                       break;
                  }
             case chr ( 0xA5 ) :
                  {
                       Debug::debug ( "Got General event description" );
                       /*
                        * 0x01 Log event ?
   0x02 Status message
   0x03 Tamper event
   0x04 Zone event
                        */
                       switch ($str [3])
                       {
                            case chr ( 0x01 ) :
                                 Debug::debug ( "Log Event?" );
                                 break;
                            case chr ( 0x02 ) :
                                 Debug::debug ( "Status message" );

                                 /*
                                  *
   Byte 3 indicates the status of zone 1 - 8 - when a bit is set it indicates the corresponding zone is open
   Byte 4 indicates the status of zone 9 - 16
   Byte 5 indicates the status of zone 17 - 24
   Byte 6 indicates the status of zone 25 - 30
   Byte 7 indicates the battery condition of zone 1 - 8 - when a bit is set it indicates the corresponding zone has a low battery
   Byte 8 indicates the battery condition of zone 9 - 16
   Byte 9 indicates the battery condition of zone 17 - 24
   Byte 10 indicates the battery condition of zone 25 - 30

   Each bit represents a zone, e.g. bit 0 of byte 3 (Zone 1-8) represents zone 1 and bit 7 represents zone 8. When a bit is set it indicates the corresponding zone is opened or a low battery.
   Note: bits are numbered from right to left starting at 0.
                                  */
                                 $b3 = $str [4];
                                 $b4 = $str [5];
                                 $b5 = $str [6];
                                 $b6 = $str [7];
                                 $b7 = $str [8];
                                 $b8 = $str [9];
                                 $b9 = $str [10];
                                 $b10 = $str [11];

                                 $this->detectStatus ( array (
                                                                    $b3,
                                                                    $b4,
                                                                    $b5,
                                                                    $b6 ), array (
                                                                                   $b7,
                                                                                   $b8,
                                                                                   $b9,
                                                                                   $b10 ) );

                                 break;
                            case chr ( 0x03 ) :
                                 Debug::debug ( "Tamper event" );
                                 /*
                                  *
   Byte 3 indicates the status of zone 1 - 8
   Byte 4 indicates the status of zone 9 - 16
   Byte 5 indicates the status of zone 17 - 24
   Byte 6 indicates the status of zone 25 - 30
   Byte 7 indicates the tamper status of zone 1 - 8
   Byte 8 indicates the tamper status of zone 9 - 16
   Byte 9 indicates the tamper status of zone 17 - 24
   Byte 10 indicates the tamper status of zone 25 - 30

   Each bit represents a zone, e.g. bit 0 of byte 3 (Zone 1-8) represents zone 1 and bit 7 represents zone 8. When a bit is set it indicates the corresponding zone is tampered or inactive.
   Note: bits are numbered from right to left starting at 0.

                                  */
                                 $b3 = $str [4];
                                 $b4 = $str [5];
                                 $b5 = $str [6];
                                 $b6 = $str [7];
                                 $b7 = $str [8];
                                 $b8 = $str [9];
                                 $b9 = $str [10];
                                 $b10 = $str [11];
                                 $this->detectTamper ( array (
                                                                    $b3,
                                                                    $b4,
                                                                    $b5,
                                                                    $b6 ), array (
                                                                                   $b7,
                                                                                   $b8,
                                                                                   $b9,
                                                                                   $b10 ) );
                                 break;
                            case chr ( 0x04 ) :
                                 Debug::debug ( "Zone event" );
                                 /*
                                  * Byte 3 indicates the system status (See appendix A)
   Byte 4 contains the system state flags (See appendix B)
   Byte 5 indicates the zone triggering the event (only when bit 5 of Byte 4 is set)
   Byte 6 indicates the type of zone event (only when bit 5 of Byte 4 is set). A complete list can be found in appendix D.
   Byte 8: ? (reported values: 0x01, 0x05)
   Byte 9: ? (reported values: 0x22, 0x30, 0x32, 0x34, 0x36)

   // press unarm
   *     0  1  2  3  4  5  6  7  8  9 10 11 12 13 14
   * 0d a5 00 04 00 45 00 00 10 00 00 00 43 bd 0a
   *
   * zone 1 detector
   * 0d a5 00 04 00 25 01 05 10 00 00 00 43 d7 0a
                                  */
                                 $b3 = $str [4];
                                 $b4 = $str [5];
                                 $b5 = $str [6];
                                 $b6 = $str [7];
                                 $b8 = $str [9];
                                 $b9 = $str [10];

                                 Debug::debug ( "System Status: " . ord ( $b3 ) . " - " . nameSystemState ( ord ( $b3 ) ) );
                                 Debug::debug ( "System Flags: " . ord ( $b4 ) . " - " . nameStateFlags ( ord ( $b4 ) ) );
                                 $zone = 0;
                                 if ((ord ( $b4 ) & 32) == 32)
                                 {
                                      $zone = ord ( $b5 );
                                      Debug::debug ( "Zone trigger: " . ord ( $b5 ) );



                                 }
                                 $type=0;
                                 if ((ord ( $b4 ) & 32) == 32)
                                 {
                                      Debug::debug ( "Type of zone event: " . nameZoneEventType ( ord ( $b6 ) ) );
                                      $type=ord ( $b6 );
                                 }

                                 $newState = ord ( $b3 );
                                 $newFlag = ord ( $b4 );
                                 if (isset ( $this->event ))
                                 {
                                      $c = array (
                                                     "class" => "visonic",
                                                     "type" => "zoneEvent",
                                                     "value" => array (
                                                                         "status" => $newState,
                                                                         "flags" => $newFlag,
                                                                         "zone" => $zone,
                                                                         "type" => $type
                                                                          ),
                                                     "params" => array (
                                                                         "zoneTxt" => $this->db->getZoneName ( $zone ) . "[$zone]",
                                                                         "statusTxt" => nameSystemState ( ord ( $b3 ) ),
                                                                         "flagTxt" => nameStateFlags ( ord ( $b4 ) ),
                                                                         "zoneEventTxt" => nameZoneEventType ( $type ) )

                                                      );
                                      $this->event->emit ( $c );
                                 }

                          $msg=nameSystemState ( ord ( $b3 ) )." - ".nameStateFlags ( ord ( $b4 ));

                          $this->statusTxt=$msg;
                          $this->statusZone=$zone;
                          $this->StatusState=$newState;
                          $this->StatusStateTxt=nameSystemState ( ord ( $b3 ) );

                          $this->StatusFlag=$newFlag;

                                 Debug::debug ( "Byte 8: " . chr ( $b8 ) );
                                 Debug::debug ( "Byte 9: " . chr ( $b9 ) );
                                 //NEW $this->sendPinInit ();
                                 break;
                       }
                  }
             case chr ( 0xA7 ) :
                  {
                       Debug::debug ( "Panel status change" );
                       $b2 = $str [3];
                       $b3 = $str [4];
                       $b4 = $str [5];
                       Debug::debug ( "B2: " . ord ( $b2 ) . " B3: " . ord ( $b3 ) . " B4: " . ord ( $b4 ) );
                  }
        }

   }

   private function sendFunction($send, $len, $desc = "", $direct = true)
   {

        $plen = $len + 2;
        $buf = "";
        $buf = chr ( 0x0D );
        $buf .= $send;
        $buf .= $this->checksum ( $buf, strlen ( $buf ) + 2 );
        $buf .= chr ( 0x0A );
        if ($direct == false)
             $this->addToQueue ( $buf, $desc );
        else
        {
             Debug::debug ( "Write packet: $desc" );


             $c=$this->SendDataToParent(json_encode(Array("DataID"=>"{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}","Buffer"=>utf8_encode($buf))));

             //$c = $this->writeData ( $buf, strlen ( $buf ) );
        }
        //$this->readData();
        return 1;
   }

   private function checksum($buf, $len)
   {

        $checksum = 0;
        for($t = 1; $t < $len - 2; $t ++)
        {
             $checksum += ord ( $buf [$t] );
        }
        $checksum = $checksum % 255;
        if ($checksum % 0xFF != 0)
        {
             $checksum = $checksum ^ 0xFF;
        }
        return chr ( $checksum );
   }

   private function sendAck()
   {

        $buf = "\x02\x43";

        $this->sendFunction ( $buf, strlen ( $buf ), "send Ack", true );


   } //0d 02 43 ba 0a

   private function getStatus()
   {

      $stat = array(
          "StatusText" => $this->statusTxt,
          "StatusZone" => $this->statusZone,
          "StatusState" => $this->StatusState,
          "StatusStateText" => $this->StatusStateTxt,
          "StatusZoneText" => $this->statusZoneTxt,
          "StatusFlag" => $this->StatusFlag);
      return $stat;

   }

   private function detectTamper($zone, $power)
   {

        $zones = 0;
        foreach ( $zone as $z )
        {
             $z = ord ( $z );
             for($i = 0; $i <= 7; $i ++)
             {
                  $zones ++;
                  $a = 0;
                  if (($z & pow ( 2, $i )) == pow ( 2, $i ))
                  {
                       Debug::debug ( "Zone [$zones] status: inactive" );
                       $a = 1;
                  }
                  else
                  {
                       Debug::debug ( "Zone [$zones] status: active" );
                       $a = 0;
                  }

             }
        }
        $zones = 0;
        foreach ( $power as $z )
        {
             $z = ord ( $z );
             for($i = 0; $i <= 7; $i ++)
             {
                  $zones ++;
                  $a = 0;
                  if (($z & pow ( 2, $i )) == pow ( 2, $i ))
                  {
                       Debug::debug ( "Zone [$zones] Tamper: tampered" );
                       $a = 1;
                  }
                  else
                  {
                       Debug::debug ( "Zone [$zones] Tamper: not tampered" );
                       $a = 0;
                  }

             }
        }
   }

   private function detectStatus($zone, $power)
   {

        $zones = 0;
        foreach ( $zone as $z )
        {
             $z = ord ( $z );
             for($i = 0; $i <= 7; $i ++)
             {
                  $zones ++;
                  $a = 0;
                  if (($z & pow ( 2, $i )) == pow ( 2, $i ))
                  {
                       Debug::debug ( "Zone [$zones] status: Open" );

                       $a = 1;
                  }
                  else
                  {
                       Debug::debug ( "Zone [$zones] status: Close" );

                       $a = 0;
                  }

             }
        }
        $zones = 0;
        foreach ( $power as $z )
        {
             $z = ord ( $z );
             for($i = 0; $i <= 7; $i ++)
             {
                  $zones ++;
                  $a = 0;

                  if (($z & pow ( 2, $i )) == pow ( 2, $i ))
                  {
                       Debug::debug ( "Zone [$zones] Battery: Low" );
                       $a = 1;
                  }
                  else
                  {
                       Debug::debug ( "Zone [$zones] Battery: Ok" );
                       $a = 0;
                  }

             }
        }
   }


   private function sendDownload($type)
   {

        $str="";
        /*
         * define(MSG_DL_SERIAL,1);
   define(MSG_DL_ZONESTR,2);
   define(MSG_DL_PANELFW,3);
         */
        switch ($type)
        {
             case MSG_DL_SERIAL:
                  $str=chr(0x30);
                  $str.=chr(0x04);
                  $str.=chr(0x08);
                  $str.=chr(0x00);
                  break;
             case MSG_DL_ZONESTR:
                  $str=chr(0x00);
                  $str.=chr(0x19);
                  $str.=chr(0x00);
                  $str.=chr(0x02);
                  break;
             case MSG_DL_PANELFW:
                  $str=chr(0x00);
                  $str.=chr(0x04);
                  $str.=chr(0x20);
                  $str.=chr(0x00);
                  break;
        }

        Debug::debug ( "send Download $type" );

        $buf = "";
   //string.char(0x3E) .. "item" .. string.char(0xB0, 0x00, 0x00, 0x00, 0x00, 0x00), 0x3F},

        $buf = chr ( 0x3E );
        $buf .= $str;
        $buf .= chr ( 0xB0 );

        $buf .= chr ( 0x00 );
        $buf .= chr ( 0x00 );
        $buf .= chr ( 0x00 );
        $buf .= chr ( 0x00 );
        $buf .= chr ( 0x00 );


        $this->sendFunction ( $buf, 11, "send download $type" );
   }
   private function sendStart()
   {
        //MSG_START
        Debug::debug ( "send start" );

        $buf = "";
        //{ string.char(0x0A), 0x33 },
        $buf = chr ( 0x0A );


        $this->sendFunction ( $buf, 1, "send start" );
   }

   private function sendExitDownload()
   {

        Debug::debug ( "send exit download" );
        $buf = "";
        $buf = chr ( 0x0F );
        $this->sendFunction ( $buf, 1, "send exit download" );
   }

   private function sendPinInitTimer()
   {
        $this->sendRestore();
        //NEW
        return;
        Debug::debug ( "TIMER EVENT!!! -=-=-=-=--=-=-=--==--==-=-=-=-=-=-=-" );
        $this->sendPinInit();
        $this->sendStatusUpdate();
        $this->sendDateAndTime();
        Debug::debug ( "-=-=-=-=--=-=-=--==--==-=-=-=-=-=-=-" );
   }
   private function sendPinInit()
   {

        Debug::debug ( "send pin init" );

        $buf = "";
        //0xAB 0x0A 0x00 0x00 <pin1> <pin2> 0x00 0x00 0x00 0x00 0x00 0x43
        $buf = chr ( 0xAB );
        $buf .= chr ( 0x0A );
        $buf .= chr ( 0x00 );
        $buf .= chr ( 0x00 );
        $buf .= chr ( $this->pin1 );
        $buf .= chr ( $this->pin2 );
        $buf .= chr ( 0x00 );
        $buf .= chr ( 0x00 );
        $buf .= chr ( 0x00 );
        $buf .= chr ( 0x00 );
        $buf .= chr ( 0x00 );
        $buf .= chr ( 0x43 );

        $this->sendFunction ( $buf, 12, "send Pin Init" );
   }

   private function sendDateAndTime()
   {

        Debug::debug ( "send Date and Time" );
        $buf = "";
        //0x46 0xF8 0x00 0x00 <Minutes> <Hours> <Day> <Month> <Year> 0xFF 0xFF
        $buf = chr ( 0x46 );
        $buf .= chr ( 0xF8 );
        $buf .= chr ( 0x00 );
        $buf .= chr ( 0x00 );
        $buf .= chr ( date ( "i" ) ); //min
        $buf .= chr ( date ( "G" ) ); //hours
        $buf .= chr ( date ( "j" ) ); //day
        $buf .= chr ( date ( "n" ) ); //month
        $buf .= chr ( date ( "y" ) ); //year
        $buf .= chr ( 0xFF );
        $buf .= chr ( 0xFF );

        $this->sendFunction ( $buf, 11, "send Date and Time" );
   }

   private function sendStatusUpdate()
   {

        Debug::debug ( "send request status update" );
        $buf = "";
        //0xA2 0x00 0x00 0x00 0x00 0x00 0x00 0x00 0x00 0x00 0x00 0x43
        $buf = chr ( 0xA2 );
        $buf .= chr ( 0x00 );
        $buf .= chr ( 0x00 );
        $buf .= chr ( 0x00 );
        $buf .= chr ( 0x00 );
        $buf .= chr ( 0x00 );
        $buf .= chr ( 0x00 );
        $buf .= chr ( 0x00 );
        $buf .= chr ( 0x00 );
        $buf .= chr ( 0x00 );
        $buf .= chr ( 0x00 );
        $buf .= chr ( 0x43 );

        $this->sendFunction ( $buf, 12, "send request status update" );
   }
   private function sendRestore()
   {
   //string.char(0xAB, 0x06, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x43)
        Debug::debug ( "send request status update" );
        $buf = "";

        $buf = chr ( 0xAB );
        $buf .= chr ( 0x06 );
        $buf .= chr ( 0x00 );
        $buf .= chr ( 0x00 );
        $buf .= chr ( 0x00 );
        $buf .= chr ( 0x00 );
        $buf .= chr ( 0x00 );
        $buf .= chr ( 0x00 );
        $buf .= chr ( 0x00 );
        $buf .= chr ( 0x00 );
        $buf .= chr ( 0x00 );
        $buf .= chr ( 0x43 );

        $this->sendFunction ( $buf, 12, "send restore" );
   }
   private function armAway()
   {

        $this->sendArmDisarm ( 0x05 );
   }

   private function armHome()
   {

        $this->sendArmDisarm ( 0x04 );
   }

   private function armHomeNow()
   {

        $this->sendArmDisarm ( 0x14 );
   }

   private function disarm()
   {

        $this->sendArmDisarm ( 0x00 );
   }

   private function sendArmDisarm($i)
   {

        Debug::debug ( "send Arm Disarm $i" );
        $buf = "";
        // a1   00    00   05       36      78    00   00   00   00   00   43
        //0xA1 0x00 0x00 <Byte3> <Byte4> <Byte5> 0x00 0x00 0x00 0x00 0x00 0x43
        /*
         * 0x00 Disarm
   0x04 Arm home
   0x05 Arm away
   0x14 Arm home instantly
         */
        $buf = chr ( 0xA1 );
        $buf .= chr ( 0x00 );
        $buf .= chr ( 0x00 );
        $buf .= chr ( $i );
        $buf .= chr ( $this->pin1 );
        $buf .= chr ( $this->pin2 );
        $buf .= chr ( 0x00 );
        $buf .= chr ( 0x00 );
        $buf .= chr ( 0x00 );
        $buf .= chr ( 0x00 );
        $buf .= chr ( 0x00 );

        $buf .= chr ( 0x43 );

        $this->sendFunction ( $buf, 12, "send Arm Disarm $i" );
   }

   private function sendGetCapabilities($node)
   {

        Debug::debug ( "send Get Capalibities for $node" );
        $buf = "";
        $buf = chr ( FUNC_ID_SERIAL_API_GET_CAPABILITIES );
        $buf .= chr ( $node );
        $this->sendFunction ( $buf, 2, REQUEST, 0, "send Get Capalibities for $node" );
   }

   protected function RegisterTimerNow($Ident, $Milliseconds, $Action) {
               //search for already available scripts with proper ident
               $eid = @IPS_GetObjectIDByIdent($Ident, $this->InstanceID);
               //properly update eventID
               if($eid === false) {
                       $eid = 0;
               } else if(IPS_GetEvent($eid)['EventType'] <> 1) {
                       IPS_DeleteEvent($eid);
                       $eid = 0;
               }
               //we need to create one
               if ($eid == 0) {
                       $eid = IPS_CreateEvent(1);
                       IPS_SetParent($eid, $this->InstanceID);
                       IPS_SetIdent($eid, $Ident);
                       IPS_SetName($eid, $Ident);
                       IPS_SetHidden($eid, true);
                       IPS_SetEventScript($eid, $Action);
               } else {
                       if(IPS_GetEvent($eid)['EventScript'] != $Action) {
                               IPS_SetEventScript($eid, $Action);
                       }
               }
               if($Milliseconds > 0) {
                       $now = time();
                       $Hour = date("H",$now);
                       $Minute = date("i",$now);
                       $Second = date("s",$now);
                       IPS_SetEventCyclicTimeFrom($eid, $Hour, $Minute, $Second);
                       IPS_SetEventCyclic($eid, 0, 0, 0, 0, 1, round($Milliseconds/1000));
                       IPS_SetEventActive($eid, true);
               } else {
                       IPS_SetEventActive($eid, false);
               }
       }

}
?>
