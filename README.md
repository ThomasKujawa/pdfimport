# PDFImport

Automatisches E-Mail-Importskript für Nextcloud und IMAP, das PDF-Anhänge aus E-Mails erkennt, in die richtige Ordnerstruktur hochlädt und verarbeitete Nachrichten automatisch in einen Zielordner verschiebt.

## 🎯 Funktionalität

Das Script verarbeitet E-Mails aus einem IMAP-E-Mail-Account und automatisiert folgende Aufgaben:

- **E-Mail-Verarbeitung**: Verbindung mit IMAP-Servern zur Suche nach E-Mails bestimmter Absender
- **PDF-Erkennung**: Automatische Erkennung und Extraktion von PDF-Anhängen aus E-Mails
- **Intelligente Ordnerstruktur**: Speicherung von PDFs in Nextcloud mit automatischer Jahrgangs-/Monatsunterteilung
- **WebDAV-Upload**: Sicherer Upload von Dateien zu Nextcloud via WebDAV-Protokoll
- **Automatische Archivierung**: Verarbeitete E-Mails werden in einen konfigurierbaren Zielordner verschoben
- **Mehrkonten-Support**: Verarbeitung mehrerer E-Mail-Konten in einer Ausführung
- **Fehlerbehandlung**: Umfangreiche Protokollierung und Fehlerausgabe

## ⚙️ Besonderheiten

### EPSON-Scanner Support
Das Script hat spezielle Unterstützung für EPSON-Scanner, die E-Mails mit einem standardisierten Dateinamensformat versenden:
- Format: `Epson_DDMMJJJJHHMMSS.pdf`
- Diese Dateien werden anhand des Dateinamens in die passende Jahrgangs-/Monatsstruktur sortiert

### Multi-Sender Support
Neben EPSON-Scannern können beliebig viele weitere E-Mail-Absender konfiguriert werden, deren PDF-Anhänge ebenfalls verarbeitet werden.

### WebDAV-Integration
- Verbindung zu Nextcloud via WebDAV
- Automatisches Anlegen fehlender Ordnerstrukturen
- Sichere Authentifizierung mit Benutzerkennung

## 📋 Anforderungen

- PHP 7.4+ mit IMAP-Extension
- CURL-Extension
- IMAP-E-Mail-Account mit entsprechenden Zugangsdaten
- Nextcloud-Installation mit WebDAV-Zugriff
- Credentials für Nextcloud-Benutzer

## 🚀 Installation

1. Repository klonen oder Dateien herunterladen:
```bash
git clone https://github.com/ThomasKujawa/pdfimport.git
cd pdfimport
```

2. Konfigurationsdatei erstellen:
```bash
cp config/config.example.php config/config.php
```

3. Konfigurationsdatei bearbeiten und Zugangsdaten eintragen (siehe [Konfiguration](#-konfiguration))

## ⚙️ Konfiguration

Die Konfiguration erfolgt über eine PHP-Datei (`config/config.php`), die folgende Parameter enthält:

```php
<?php
return [
    'epson_sender' => 'noreply@epson.com',  // E-Mail-Adresse des EPSON-Scanners
    'accounts' => [
        [
            'name' => 'Hauptkonto',
            'imap' => [
                'host'  => '{imap.beispiel.de:993/imap/ssl}INBOX',
                'user'  => 'deine-email@beispiel.de',
                'pass'  => 'dein-passwort',
            ],
            'cloud' => [
                'url'   => 'https://nextcloud.beispiel.de/remote.php/webdav/',
                'user'  => 'nextcloud-benutzer',
                'pass'  => 'nextcloud-passwort',
                'pfad'  => '/PDFs/',  // Zielordner in Nextcloud
            ],
            'target_folder' => 'PDFImport',  // IMAP-Ordner für verarbeitete E-Mails
        ],
    ],
    'senders' => [
        'absender1@beispiel.de',
        'absender2@beispiel.de',
        // weitere Absender...
    ],
];
```

### Konfigurationsparameter

| Parameter | Beschreibung |
|-----------|-------------|
| `epson_sender` | E-Mail-Adresse des EPSON-Scanners |
| `accounts` | Array mit E-Mail-Konten |
| `accounts[].name` | Name des Kontos (für Logging) |
| `accounts[].imap.host` | IMAP-Verbindungsstring |
| `accounts[].imap.user` | IMAP-Benutzername |
| `accounts[].imap.pass` | IMAP-Passwort |
| `accounts[].cloud.url` | Nextcloud WebDAV-URL |
| `accounts[].cloud.user` | Nextcloud-Benutzername |
| `accounts[].cloud.pass` | Nextcloud-Passwort |
| `accounts[].cloud.pfad` | Zielordner in Nextcloud |
| `accounts[].target_folder` | IMAP-Ordner für verarbeitete E-Mails |
| `senders` | Array mit weiteren E-Mail-Absendern zur Verarbeitung |

## 🔄 Verwendung

Das Script wird typischerweise über einen Cron-Job oder einen Scheduler ausgeführt:

```bash
php pdfimport.php
```

Beispiel für tägliche Ausführung (Cron):
```bash
0 */4 * * * /usr/bin/php /path/to/pdfimport.php
```

Das Script erzeugt HTML-Output mit Informationen zum Verarbeitungsverlauf, einschließlich:
- Verbindungstests
- Verarbeitete E-Mails
- Hochgeladene Dateien
- Fehler und Warnungen

## 📁 Ordnerstruktur

Nach Verarbeitung werden PDFs wie folgt in Nextcloud organisiert:
```
/PDFs/
  └── 2024/
      ├── 01/
      │   ├── Epson_12012024123456.pdf
      │   └── ...
      ├── 02/
      └── ...
```

## ⚠️ Wichtige Hinweise

- **Sicherheit**: Die `config/config.php` enthält sensitive Zugangsdaten und sollte **niemals** in Versionskontrolle committed werden (siehe `.gitignore`)
- **SSL-Zertifikate**: Das Script deaktiviert aktuell SSL-Verifikation für cURL-Anfragen. Dies sollte in Produktionsumgebungen überprüft werden
- **Fehlerbehandlung**: Bei Fehlern gibt das Script HTML-Output aus, was bei Ausführung über Cron zu E-Mail-Benachrichtigungen führt
- **Performance**: Die Verarbeitung ist auf max. 5 E-Mails pro Sender pro Ausführung begrenzt (verhinderungssystem zur Lastbegrenzung)

## 🔧 Technische Details

- **IMAP-Protokoll**: Für E-Mail-Verwaltung
- **WebDAV**: Für sichere Dateiübertragung zu Nextcloud
- **MIME-Part-Verarbeitung**: Rekursive Verarbeitung verschachtelter E-Mail-Strukturen
- **BASE64-Decodierung**: Automatische Dekodierung von E-Mail-Anhängen
- **UTF7-Encoding**: Korrekte Behandlung von IMAP-Ordnernamen

## 📝 Lizenz

Nicht angegeben. Bitte entsprechende Lizenz hinzufügen.

## 👤 Autor

[ThomasKujawa](https://github.com/ThomasKujawa)

## 🤝 Beitragen

Verbesserungsvorschläge und Bug-Reports sind willkommen!
