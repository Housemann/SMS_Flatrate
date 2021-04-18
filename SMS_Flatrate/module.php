<?php
    
    require_once __DIR__ . '/../libs/helper_variables.php';
    
    // Klassendefinition
    class SMS_Flatrate extends IPSModule {

        use SMSF_HelperVariables;
 
        // Überschreibt die interne IPS_Create($id) Funktion
        public function Create() {
            // Diese Zeile nicht löschen.
            parent::Create();

            // Propertys
            $this->RegisterPropertyString("HandyNumber", "");
            $this->RegisterPropertyString("APIKey", "YourApyKey");
            $this->RegisterPropertyFloat("MinCredits", 0.5);
            $this->RegisterPropertyInteger("UpdateInterval", 5);
            $this->RegisterPropertyString("TestMessage", "TestMessage");

            // VariablenProfil für Credits und Waehrung anlegen
            $this->RegisterProfileFloat("SMSF.Currency", "", "", " €", 0, 0, 0, 2);
            $this->RegisterProfileIntegerEx('SMSF.Balance'  , '', '', '', Array(
              Array(0 , $this->translate('No credit')       , '', '0xFF6464'),
              Array(1 , $this->translate('Low')             , '', '0xFFBE80'),
              Array(2 , $this->translate('Available')       , '', '0x64FF64')
            ));

            // Variablen
            $this->RegisterVariableFloat("Credits",$this->translate("Available Balance"),"SMSF.Currency",0);
            $this->RegisterVariableInteger("CreditWarning",$this->translate("Credits"),"SMSF.Balance",1);
            $this->RegisterVariableString("ReturnValues",$this->translate("Return Values"),"~TextBox",2);

            // Timer anlegen
            $this->RegisterTimer ("TimerReturnValues", 0, 'SMSF_GetStatusRequest($_IPS[\'TARGET\']);');

            // Attributes
            $this->RegisterAttributeString("ReturnArray","");
 
        }
 
        // Überschreibt die intere IPS_ApplyChanges($id) Funktion
        public function ApplyChanges() {
            // Diese Zeile nicht löschen
            parent::ApplyChanges();

            // Guthaben holen
            $this->GetCredits();

            // Timer zum Mediathek Update
            $this->SetTimerInterval("TimerReturnValues", $this->ReadPropertyInteger("UpdateInterval") * 60 * 1000);
        }


        public function Destroy() 
        {
            // Remove variable profiles from this module if there is no instance left
            $InstancesAR = IPS_GetInstanceListByModuleID('{72568873-AA5E-71E3-90FC-1EE1C203CE84}');
            if ((@array_key_exists('0', $InstancesAR) === false) || (@array_key_exists('0', $InstancesAR) === NULL)) {
                $VarProfileAR = array('SMSF.Balance','SMSF.Currency');
                foreach ($VarProfileAR as $VarProfileName) {
                    @IPS_DeleteVariableProfile($VarProfileName);
                }
            }
            parent::Destroy();
        }  
        

        public function SendSMS(string $HandyNumbers, string $Message) 
        {

          $ApiKey = $this->ReadPropertyString("APIKey");
          $ArrayHandyNumbers = explode(";",$HandyNumbers);
          $CountArrayHandyNumbers = count($ArrayHandyNumbers);

          $ArrayAllNumbers = array();
          foreach($ArrayHandyNumbers as $HandyNumber) {
            $Url = "https://www.smsflatrate.net/schnittstelle.php?key=".$ApiKey."&from=IP-Symcon&to=".$HandyNumber."&text=".urlencode($Message)."&type=1&cost=1&status=1";

            $OutputSendSMS = $this->SendCurl($Url);
            array_unshift($OutputSendSMS,$HandyNumber);

            // ReturnPro Nummer sammeln
            $ArrayAllNumbers[] = array(
              "HandyNumber"   => @$OutputSendSMS[0],
              "Date"          => time(),
              "StatusCode"    => @$OutputSendSMS[1],
              "RequestSmsId"  => @$OutputSendSMS[2],
              "Price"         => @$OutputSendSMS[3]
            );
            
            // Schnittstellenbegrenzung: Maximal 10 Aufrufe pro Sekunde / max. 10 request per secound
            if($CountArrayHandyNumbers>10)
              IPS_Sleep(1000);
          }
          // json in Attribute schreiben
          $this->WriteAttributeString("ReturnArray",json_encode($ArrayAllNumbers));

          // aktuelles guthaben abfragen
          $ReturnCredits = $this->GetCredits();

          // Guthaben an Array anfügen
          array_push($ArrayAllNumbers,array("Credits" => $ReturnCredits['Credits']));
          return $ArrayAllNumbers;
        }


        // guthaben abfrage
        public function GetCredits() 
        {
          $ApiKey = $this->ReadPropertyString("APIKey");
          $MinCredits = $this->ReadPropertyFloat("MinCredits");

          $Url = "https://www.smsflatrate.net/schnittstelle.php?key=".$ApiKey."&request=credits";
          $OutputCredits = $this->SendCurl($Url);

          // Variable füllen
          if(!empty(@$OutputCredits[0]))
            $this->SetValue("Credits",@$OutputCredits[0]);

          // Status Var setzten
          if( floatval(@$OutputCredits[0]) < floatval($MinCredits) ) {
            $this->SetValue("CreditWarning",1);
          } elseif ( floatval(@$OutputCredits[0]) == 0 ) {
            $this->SetValue("CreditWarning",0);
          } else {
            $this->SetValue("CreditWarning",2);
          }
          

          return array(
            "Credits" => @$OutputCredits[0]
          );
        }

        public function GetStatusSmsId(string $SmsId)
        {
          $Url = "https://www.smsflatrate.net/status.php?id=".$SmsId;
          $OutputStatus = $this->SendCurl ($Url);

          return $OutputStatus;
        }


        // status ueber SMSid holen
        public function GetStatusRequest()
        {
          // Regest SMS ids auslesen und prüfen ob angekommen
          $ReturnValues = $this->ReadAttributeString("ReturnArray");
          $ReturnValues = json_decode($ReturnValues,true);
          $this->SendDebug(__FUNCTION__ . ' ReturnValues: ', json_encode($ReturnValues), 0);

          $MaxCount = 0;
          if(is_countable($ReturnValues)) {
            $MaxCount = count($ReturnValues);  
          }
          $this->SendDebug(__FUNCTION__ . ' MaxCount: ', $MaxCount, 0);
          #$MaxCount = count($ReturnValues);
          $Message = "";
          
          $Cnt = 0;
          if(is_countable($ReturnValues)) {
            foreach($ReturnValues as $Values) {
              
              // curl aufrum um request abzufragen
              $Url = "https://www.smsflatrate.net/status.php?id=".$Values['RequestSmsId'];
              $OutputStatus = $this->SendCurl ($Url);
              $this->SendDebug(__FUNCTION__ . ' OutputStatus: ', json_encode($OutputStatus), 0);
              
              // Output in array schrieben
              $OutputStatus = array(
                "StatusCode"  => @$OutputStatus[0],
                "Date"        => @$OutputStatus[1]
              );
              $this->SendDebug(__FUNCTION__ . ' OutputStatus2: ', json_encode($OutputStatus), 0);
            
              // request daten DATE und STATUSCODE im Array ändern mit neuen werten
              foreach($Values as $key => $value) {
                switch ($key) {
                  case 'Date':
                    $Values['Date'] = $OutputStatus['Date'];
                    break;
                  case 'StatusCode':
                    $Values['StatusCode'] = $OutputStatus['StatusCode'];
                    break;
                }
              }
              $this->SendDebug(__FUNCTION__ . ' OutputStatus2: ', $Values['Date']." | ".$Values['StatusCode'], 0);

              // prüfen ob int
              $StatusCode = $Values['StatusCode'];
              $this->SendDebug(__FUNCTION__ . ' StatusCode1: ', $StatusCode, 0);

              if(!is_int($StatusCode))
                $StatusCode = 0;

              $this->SendDebug(__FUNCTION__ . ' StatusCode2: ', $StatusCode, 0);

              // wenn 109 oder 0 dann Datum auf leer ansonsten Datum
              #$date = $StatusCode==109 ? "" : date("d.m.Y - H:i:s",$Values['Date'])." ".$this->translate("Clock");
              if($StatusCode==109) {
                $date = "";
              } elseif($StatusCode==0 || $StatusCode==null) {
                $date = "";
              } else {
                $date = date("d.m.Y - H:i:s",$Values['Date'])." ".$this->translate("Clock");
              }


              // ergebnis
              #if($StatusCode<>0) {
                $Message = $Message. $this->translate("HandyNumber:")." ".$Values['HandyNumber']."\n";
                $Message = $Message. $this->translate("Date:")." ".$date."\n";
                $Message = $Message. $this->translate("Status:")." ".$StatusCode."\n";
                $Message = $Message. $this->translate("Status Message:")." ".$this->translate(@$this->ErrorCodes($StatusCode))."\n";
                $Message = $Message. $this->translate("Price:")." ".round($Values['Price'],2)." €"."\n";
              #}
              
              $Cnt++;

              if($Cnt < $MaxCount) {
                $Message = $Message. "\n";
              } elseif($Cnt == $MaxCount) {
                $Message = $Message. "";
              }
            }
          }
          // Message in TextBox schreiben
          SetValue($this->GetIDForIdent("ReturnValues"),$Message);

          // aktuelles guthaben abfragen
          $this->GetCredits();

          return $ReturnValues;
        }

        // Curl Aufruf
        private function SendCurl (string $Url) {
          $ch = curl_init();
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
          curl_setopt($ch, CURLOPT_URL, $Url);
          $output = curl_exec($ch);
          curl_close($ch);
        
          return explode(",",$output);
        }

        // StatusCodes
        private function ErrorCodes(int $Code) 
        {
          // Error codes
          $ErrorCodes = array 
          (
            0   => "No Values given",
            100 => "SMS successfully transmitted to the gateway",
            101 => "SMS was delivered",
            102 => "SMS has not been delivered yet (e.g. cell phone off or temporarily unavailable)",
            103 => "SMS probably could not be delivered (wrong phone number, SIM not active)",
            104 => "SMS still could not be delivered after 48 hours.The return value 102 becomes status 104 after 2 days.",
            109 => "SMS ID expired or invalid (manual status query)",
            110 => "Wrong interface key or your account is locked",
            120 => "Credit is not enough",
            130 => "Incorrect data transfer (e.g. sender is missing)",
            131 => "Receiver not correct",
            132 => "Sender not correct",
            133 => "Message text not correct",
            140 => "Wrong AppKey or your account is locked",
            150 => "You have tried to send to an international cell phone number of a gateway that is exclusively for sending to Germany. Please use international gateway or auto type function.",
            170 => "Parameter time= is not correct. Please in format: DD.MM.YYY-SS:MM or remove parameter for immediate shipping.",
            171 => "Parameter time= is scheduled too far in the future (max. 360 days)",
            180 => "Account not yet completely activated Volume restriction still active Please request activation in the Customer Center so that unlimited messaging is possible.",
            231 => "No smsflatrate.net group available or not correct",
            404 => "Unknown error. Please contact support (ticket@smsflatrate.net) urgently."
          );

          return $ErrorCodes[$Code];
        }

        public function SendTestMessage() 
        {
          $HandyNumber  = $this->ReadPropertyString("HandyNumber");
          $ApiKey       = $this->ReadPropertyString("APIKey");
          $Message      = $this->ReadPropertyString("TestMessage");

          if(!empty($HandyNumber) && !preg_match('/^[0-9]+$/', $HandyNumber)) {
              echo $this->translate("HandyNumber is wrong!");
          } elseif(!empty($HandyNumber) && preg_match('/^[0-9]+$/', $HandyNumber)) {
              $Output = $this->SendSMS($HandyNumber, $Message);
              echo $this->translate("Status:")." ".$Output[0]['StatusCode']."\n".$this->translate("Status Message:")." ".$this->translate($this->ErrorCodes($Output[0]['StatusCode']))."\n".$this->translate("Price:")." ".str_replace(".",",",round($Output[0]['Price'],2));
          } elseif(empty($HandyNumber)) {
              echo $this->translate("Handynumber is empty!");
          }
        }
            
    }