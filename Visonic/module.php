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

   }

   // Overrides the intere IPS_ApplyChanges ($ id) function
   public function ApplyChanges( )  {
       // Do not delete this line
       parent::ApplyChanges();
   }


   public function ReceiveData($JSONString)
   {
        $data = json_decode($JSONString);
        IPS_LogMessage("Visonic RERV", utf8_decode($data->Buffer));
        //Parse and write values to our variables

   }
   public function ForwardData($JSONString) {
          parent::ForwardData($JSONString);
          $data = json_decode($JSONString);
	     IPS_LogMessage("Visonic FRWD", utf8_decode($data->Buffer));

   }

   /**
   * The following functions are automatically available when the module has been inserted via the "Module Control".
   * The functions, with the self-defined prefix, are provided in PHP and JSON-RPC as follows:
   *
   * ABC_MyFirstElement ($ id);
   *
   */
   Public function setStatus($status)  {
       // Self-definedCode
       IPS_LogMessage("Visonic", "Set Status to $status");
   }
}
?>
