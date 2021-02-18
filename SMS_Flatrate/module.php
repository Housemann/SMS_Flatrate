<?php
    // Klassendefinition
    class SMS_Flatrate extends IPSModule {
 
        // Überschreibt die interne IPS_Create($id) Funktion
        public function Create() {
            // Diese Zeile nicht löschen.
            parent::Create();

            // Propertys
            $this->RegisterPropertyString("HandyNumber", "");
            $this->RegisterPropertyString("APIKey", "YourApyKey");
            $this->RegisterPropertyFloat("MinCredits", 0.5);
            $this->RegisterPropertyInteger("UpdateInterval", 60);
            $this->RegisterPropertyString("TestMessage", "TestMessage");

            // Variablen
            $this->RegisterVariableString("ReturnCode","Return Code");
            $this->RegisterVariableString("ReturnMessage","Return Message");
            $this->RegisterVariableFloat("ReturnPrice","Return Price");
            $this->RegisterVariableFloat("Credits","Credits");

            $this->RegisterVariableString("SMSid_1","SMSid_1");
            $this->RegisterVariableString("SMSid_2","SMSid_2");

            // Timer anlegen
            $this->RegisterTimer ("TimerReturnValues", 0, 'SMSF_GetStatusRequest($_IPS[\'TARGET\']);');

            // Attributes
            $this->RegisterAttributeString("ReturnSmsID","");
 
        }
 
        // Überschreibt die intere IPS_ApplyChanges($id) Funktion
        public function ApplyChanges() {
            // Diese Zeile nicht löschen
            parent::ApplyChanges();


            // Timer zum Mediathek Update
            $this->SetTimerInterval("TimerReturnValues", $this->ReadPropertyInteger("UpdateInterval") * 60 * 1000);
        }
 
        


        public function SendSMS(string $HandyNumbers, string $Message) 
        {
          $ApiKey = $this->ReadPropertyString("APIKey");
          $Type	 = "1";
          ##&bulk=1
          $Url = "https://www.smsflatrate.net/schnittstelle.php?key=".$ApiKey."&from=smsflatrate&to=".$HandyNumbers."&text=".urlencode($Message)."&type=".$Type."&cost=1&status=1";
          $OutputSendSMS = $this->SendCurl($Url);

          $this->WriteAttributeString("ReturnSmsID",@$OutputSendSMS[1]);
          
          $this->SetValue("ReturnPrice",@$OutputSendSMS[2]);
          $this->SetValue("ReturnCode",@$OutputSendSMS[0]);
          $this->SetValue("ReturnMessage",$this->ErrorCodes(@$OutputSendSMS[0]));

          $this->GetCredits();

          return array(
            "StatusCode"    => @$OutputSendSMS[0],
            "RequestSmsId"  => @$OutputSendSMS[1],
            "Price"         => @$OutputSendSMS[2]
          );
        }

        public function GetCredits() 
        {
          $ApiKey = $this->ReadPropertyString("APIKey");

          $Url = "https://www.smsflatrate.net/schnittstelle.php?key=".$ApiKey."&request=credits";
          $OutputCredits = $this->SendCurl($Url);

          $this->SetValue("Credits",@$OutputCredits[0]);

          #return array(
          #  "Credits" => @$OutputCredits[0]
          #);
        }

        public function GetStatusRequest()
        {
          // Regest SMS id auslesen und prüfen ob angekommen
          $ReturnSMSid = $this->ReadAttributeString("ReturnSmsID");
          
          // Status ueber SMSid pruefen aus dem SMS versand
          $Url = "https://www.smsflatrate.net/status.php?id=".$ReturnSMSid;
          $OutputStatus = $this->SendCurl ($Url);

          $this->SetValue("ReturnCode",@$OutputStatus[0]);
          $this->SetValue("ReturnMessage",$this->ErrorCodes(@$OutputStatus[0]));
          
          #return array (
          #  "StatusCode"      =>  @$OutputStatus[0],
          #  "TimeStampUnix"   =>  @$OutputStatus[1]
          #);
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
            100 => "SMS erfolgreich an das Gateway übertragen",
            101 => "SMS wurde zugestellt",
            102 => "SMS wurde noch nicht zugestellt (z.B. Handy aus oder temporär nicht erreichbar",
            103 => "SMS konnte vermutlich nicht zugestellt werden (Rufnummer falsch, SIM nicht aktiv)",
            104 => "SMS konnte nach Ablauf von 48 Stunden noch immer nicht zugestellt werden.Aus dem Rückgabewert 102 wird nach Ablauf von 2 Tagen der Status 104.",
            109 => "SMS ID abgelaufen oder ungültig (manuelle Status-Abfrage)",
            110 => "Falscher Schnittstellen-Key oder Ihr Account ist gesperrt",
            120 => "Guthaben reicht nicht aus",
            130 => "Falsche Datenübergabe (z.B. Absender fehlt)",
            131 => "Empfänger nicht korrekt",
            132 => "Absender nicht korrekt",
            133 => "Nachrichtentext nicht korrekt",
            140 => "Falscher AppKey oder Ihr Account ist gesperrt",
            150 => "Sie haben versucht an eine internationale Handynummer eines Gateways, das ausschließlich für den Versand nach Deutschland bestimmt ist, zu senden. Bitte internationales Gateway oder Auto-Type-Funktion verwenden.",
            170 => "Parameter time= ist nicht korrekt. Bitte im Format: TT.MM.JJJJ-SS:MM oder Parameter entfernen für sofortigen Versand.",
            171 => "Parameter time= ist zu weit in der Zukunft terminiert (max. 360 Tage)",
            180 => "Account noch nicht komplett freigeschaltet Volumen-Beschränkung noch aktiv Bitte im Kundencenter die Freischaltung beantragen, damit unbeschränkter Nachrichtenversand möglich ist.",
            231 => "Keine smsflatrate.net Gruppe vorhanden oder nicht korrekt",
            404 => "Unbekannter Fehler. Bitte dringend Support (ticket@smsflatrate.net) kontaktieren."			
          );

          return $ErrorCodes[$Code];
        }

        public function SendTestMessage() 
        {
          $HandyNumber  = $this->ReadPropertyString("HandyNumber");
          $ApiKey       = $this->ReadPropertyString("APIKey");
          $Message      = $this->ReadPropertyString("TestMessage");

          if(!empty($HandyNumber) && !preg_match('/^[0-9]+$/', $HandyNumber)) {  
              echo "HandyNumber is wrong!\nCheck there are \"No spaces allowed\"";
          } elseif(!empty($HandyNumber) && preg_match('/^[0-9]+$/', $HandyNumber)) {
              $Output = $this->SendSMS($HandyNumber, $Message);
              echo "Status: ".$Output['StatusCode']."\n"."Status Message: ".$this->ErrorCodes($Output['StatusCode'])."\n"."Price: ".str_replace(".",",",round($Output['Price'],2));
          } elseif(empty($HandyNumber)) {
              echo "Handynumber is empty!";
          }
        }
            
    }