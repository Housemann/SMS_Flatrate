# SMS Flatrate
Mit dem SMS Flattrate Modul, lassen sich SMS an einen oder mehrere Empfänger über den Provider Sms-Flatrate versenden. Die Rückgabe der SMSid vom Provider, ermöglicht das Abfragen des Status der SMS. So kann man sehen ob diese angekommen ist. Der Status sowie der Preis und das noch verfügbare Guthaben, werden in einer TextBox für die zuletzt versendeten SMS angezeigt. 

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [Funktionen](#6-funktionen)

### 1. Funktionsumfang

* Ermöglicht das Versenden von SMS an mehrere Emfänger.
* Auslesen des Status der versedneten SMS und speichern in TextBox.
* Variable zum anzeigen ob Guthaben unterschritten.
* Anzeige des Guthabens.

### 2. Voraussetzungen

- IP-Symcon ab Version 5.5
- Account und API Schlüssel bei SMS-Flatrate (`https://www.smsflatrate.net/`)

### 3. Software-Installation

* Über das Module Control folgende URL hinzufügen:
    `https://github.com/Housemann/SMS_Flatrate`

### 4. Einrichten der Instanzen in IP-Symcon

- Unter "Instanz hinzufügen" kann das 'SMS Flatrate'-Modul mithilfe des Schnellfilters gefunden werden.
    - Auf der Seite `https://www.smsflatrate.net/` registrieren. 
    - Im Kundenlogin dann auf Schnittstelle gehen und unter "Ihr Schnittstellen-Key:", den Key ins Modul kopieren.
    - Zum Testen eine Handynummer und eine Testnachricht hinterlegen und auf "Test-Nachricht versenden" klicken.
    - Aktualisierungszeit für Guthaben und Rückgabe eintragen.
    - Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

### 5. Statusvariablen und Profile

Die Statusvariablen werden bei bedarf über die Checkboxen im Modul automatisch angelegt.

Name             | VariableTyp | Beschreibung
---------------- | ----------- | ---------------------
Guthaben         | (float)     | Anzeige des aktuellen Guthabens
Guthaben Warnung | (integer)   | Guthaben (Vorhanden, Gering, Kein Guthaben)
Rückgabewerte    | (string)    | TextBox mit werten der zuletzt versendeten SMS

### 6. Funktionen

Mit dieser Funktion kann man eine SMS an einen odere Merhere Empfänger senden. Mehrere Empfänger sind durch Semikolon ";" zu trennen (01516500xxxxx;0172658xxxxx;...).
```php
SMSF_SendSMS($Instance, string $HandyNumbers, string $Message);
```

Mit dieser Funktion kann man sich das aktuelle Guthaben holen .
```php
SMSF_GetCredits($Instance);
```

Mit dieser Funktion kann man den Status der letzten SMS holen.
```php
SMSF_GetStatusRequest($Instance);
```