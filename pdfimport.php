<?php

/* fuer die Fehlersuche */
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

$config = require __DIR__ . '/config/config.php';

if (empty($config['accounts']) || !is_array($config['accounts'])) {
    die('Keine Konten konfiguriert.');
}

foreach ($config['accounts'] as $account) {
    processAccount($account, $config['epson_sender'], $config['senders']);    
}

function processAccount(array $account, ?string $epsonSender = null, ?array $senders = null): void
{
    $accountName = $account['name'] ?? 'unbekannt';

    $imapHost    = $account['imap']['host'];
    $imapUser    = $account['imap']['user'];
    $imapPass    = $account['imap']['pass'];

    $ncUrl       = rtrim($account['cloud']['url'], '/');
    $ncUser      = $account['cloud']['user'];
    $ncPass      = $account['cloud']['pass'];
    $ncPfad     = $account['cloud']['pfad'];

    $zielOrdner  = $account['target_folder'] ?? 'PDFImport';
    $senders     = $senders ?? [];

    echo "<h3>Starte Konto: {$accountName}</h3>";
    
    // Test, ob Nextcloud beschrieben werden kann
    if (!testNCAccess($ncUrl, $ncUser, $ncPass, $accountName)) {
        echo "Nextcloud-Zugriff für {$accountName} fehlgeschlagen. Konto wird übersprungen.<br><hr>";
        return;
    }
    
    $imapMailbox   = $imapHost;
    $imapReference = preg_replace('/\}.*$/', '}', $imapMailbox);

    $mbox = imap_open($imapMailbox, $imapUser, $imapPass);

    if ($mbox === false) {
        echo "IMAP-Fehler in {$accountName}: " . imap_last_error() . "<br>";
        return;
    }

    if (!ensureImapFolderExists($mbox, $imapReference, $zielOrdner)) {
        imap_close($mbox);
        return;
    }
    
    if (!ensureWebdavPathExists($ncUrl, $ncUser, $ncPass, $ncPfad)) {
        imap_close($mbox);
        return;
    }
            
    echo '<h2>EPSON</h2>';
    echo 'Beginne mit der Suche nach E-Mails von ' . $epsonSender . '<br>';
    // E-Mails von EPSON suchen
    if (!empty($epsonSender)) {
        $emails = imap_search($mbox, 'FROM "' . $epsonSender . '"');
        echo 'Beginne mit der Verarbeitung von EPSON E-Mails<br>';

        if ($emails) {
            $anzahl = 0;
            foreach ($emails as $email_number) {
                $anzahl++;
                if ($anzahl >= 5) {
                    continue;
                }
                $structure = imap_fetchstructure($mbox, $email_number);
                if (isset($structure->parts) && count($structure->parts)) {
                    for ($i = 0; $i < count($structure->parts); $i++) {
                        $part = $structure->parts[$i];

                        // Anhang mit Dateiname prüfen
                        if ($part->ifdparameters) {
                            foreach ($part->dparameters as $object) {
                                $filename = $object->value;
                                echo 'Ich habe eine E-Mail gefunden<br>';

                                // Regex passend für EPSON Dateinamen
                                if (preg_match('/^Epson_(\d{2})(\d{2})(\d{4})(\d{6})\.pdf$/', $filename, $matches)) {
                                    // Anhang extrahieren
                                    $attachment = imap_fetchbody($mbox, $email_number, $i + 1);
                                    if ($part->encoding == 3) {
                                        $decoded = base64_decode($attachment, true);
                                        if ($decoded === false) {
                                            echo "BASE64-Decodierung fehlgeschlagen für $filename<br>";
                                            continue;
                                        }
                                        $attachment = $decoded;
                                    }

                                    // Zielpfad anhand Datum aus Dateiname erstellen
                                    $jahr = $matches[3];
                                    $monat = $matches[2];
                                    
                                    $zielPfad = trim($ncPfad, '/') . "/$jahr/$monat";
                                    if (!ensureWebdavPathExists($ncUrl, $ncUser, $ncPass, $zielPfad)) {
                                        echo "Zielpfad konnte nicht angelegt werden: {$zielPfad}<br>";
                                        continue;
                                    }
                                    $ziel_url = buildWebdavUrl($ncUrl, $zielPfad, $filename);
                                    echo "Die Datei wird unter " . $ziel_url . " abgelegt<br>";
                                    
                                    // WebDAV Upload mit cURL
                                    $ch = curl_init();
                                    curl_setopt($ch, CURLOPT_URL, $ziel_url);
                                    curl_setopt($ch, CURLOPT_USERPWD, "$ncUser:$ncPass");
                                    curl_setopt($ch, CURLOPT_PUT, 1);
                                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                                    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/pdf"]);
                                    $temp = fopen('php://temp', 'r+');
                                    fwrite($temp, $attachment);
                                    rewind($temp);
                                    curl_setopt($ch, CURLOPT_INFILE, $temp);
                                    curl_setopt($ch, CURLOPT_INFILESIZE, strlen($attachment));
                                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                                    $response = curl_exec($ch). ' ' . curl_error($ch);
                                    fclose($temp);
                                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                    curl_close($ch);

                                    if ($httpCode >= 200 && $httpCode < 300) {
                                        echo "Datei erfolgreich hochgeladen!<br>";

                                        // Mail verschieben nach Zielordner
                                        if (@imap_mail_move($mbox, $email_number, $zielOrdner)) {
                                            echo "E-Mail $email_number zum verschieben nach $zielOrdner vorgemerkt<br>";
                                        } else {
                                            echo "Fehler beim Verschieben: " . imap_last_error() . "<br>";
                                        }
                                    } else {
                                        echo "HTTP-Fehler $httpCode: $response<br>";
                                    }
                                } else {
                                    echo 'Dateiname passt wohl nicht: ' . $filename . '<br>';
                                }
                            }
                        }
                    }
                }
            }
        }
        echo 'Suche nach den E-Mails von EPSON abgeschlossen <br>';
    }

    echo '<h2>weitere E-Mails</h2>';
    echo 'Beginne mit der Suche nach E-Mails<br>';    
    // E-Mails der gelisteten Versender abarbeiten
    foreach ($senders as $sender) {
        searchAndProcessSender($mbox, $sender, $ncUser, $ncPass, $ncUrl, $ncPfad, $zielOrdner);
    }

    imap_expunge($mbox);
    echo "vorgemerkte E-Mails wurden verschoben";
    imap_close($mbox);
    echo "<h2>Konto {$accountName} abgeschlossen</h2>";
}

function testNCAccess(string $url, string $user, string $pass, string $accountName = 'unbekannt'): bool
{
    $url = rtrim($url, '/') . '/';

    $xml = <<<XML
<?xml version="1.0"?>
<d:propfind xmlns:d="DAV:">
    <d:prop>
        <d:displayname />
    </d:prop>
</d:propfind>
XML;

    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_USERPWD => $user . ':' . $pass,
        CURLOPT_CUSTOMREQUEST => 'PROPFIND',
        CURLOPT_HTTPHEADER => [
            'Depth: 0',
            'Content-Type: application/xml; charset=utf-8',
        ],
        CURLOPT_POSTFIELDS => $xml,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    echo "Prüfe Nextcloud-Zugang für {$accountName}<br>";

    if ($response === false) {
        echo "cURL-Fehler: {$curlError}<br>";
        return false;
    }

    if ($httpCode === 207) {
        echo "Nextcloud-Zugriff erfolgreich (HTTP 207)<br>";
        return true;
    }

    if ($httpCode === 401) {
        echo "Nextcloud-Login fehlgeschlagen (HTTP 401)<br>";
        return false;
    }

    if ($httpCode === 403) {
        echo "Nextcloud-Zugriff verweigert (HTTP 403)<br>";
        return false;
    }

    if ($httpCode === 404) {
        echo "Nextcloud-Pfad nicht gefunden (HTTP 404)<br>";
        return false;
    }

    echo "Unerwartete Nextcloud-Antwort: HTTP {$httpCode}<br>";
    return false;
}

// Funktion zum rekursiven Durchsuchen der MIME-Parts aller Anhänge
function processParts($mbox, $email_number, $parts, $ncUser, $ncPass, $ncUrl, $ncPfad, $zielOrdner, $prefix = '') {
    foreach ($parts as $index => $part) {
        $partNum = $prefix === '' ? ($index + 1) : $prefix . '.' . ($index + 1);

        // Wenn verschachtelte Parts vorhanden, rekursiv verarbeiten
        if (isset($part->parts) && count($part->parts)) {
            processParts($mbox, $email_number, $part->parts, $ncUser, $ncPass, $ncUrl, $ncPfad, $zielOrdner, $partNum);
        } else {
            // Prüfen ob Anhang
                $filename = '';

                if (!empty($part->ifdparameters)) {
                    foreach ($part->dparameters as $object) {
                        if (strtolower($object->attribute) === 'filename') {
                            $filename = $object->value;
                            break;
                        }
                    }
                }

                if (!$filename && !empty($part->ifparameters)) {
                    foreach ($part->parameters as $object) {
                        if (strtolower($object->attribute) === 'name') {
                            $filename = $object->value;
                            break;
                        }
                    }
                }

                if ($filename !== '') {
                    echo "Gefundener Anhang: $filename<br>";

                    if (preg_match('/\.pdf$/i', $filename)) {
                        // Attachment holen
                        $attachment = imap_fetchbody($mbox, $email_number, $partNum);
                        if ($part->encoding == 3) {
                            $decoded = base64_decode($attachment, true);
                            if ($decoded === false) {
                                echo "BASE64-Decodierung fehlgeschlagen für $filename<br>";
                                continue;
                            }
                            $attachment = $decoded;
                        } elseif ($part->encoding == 4) {
                            $attachment = quoted_printable_decode($attachment);
                        }

                        // Zielpfad vorbereiten
                        $jahr = date("Y");
                        $monat = date("m");
                        $zielpfad = rtrim($ncUrl, "/") . $ncPfad . "$jahr/$monat/";
                        $ziel_url = $zielpfad . $filename;

                        echo "Die Datei wird unter " . $ziel_url . " abgelegt<br>";

                        // WebDAV Upload
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $ziel_url);
                        curl_setopt($ch, CURLOPT_USERPWD, "$ncUser:$ncPass");
                        curl_setopt($ch, CURLOPT_PUT, 1);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/pdf"]);

                        $temp = fopen('php://temp', 'r+');
                        fwrite($temp, $attachment);
                        rewind($temp);

                        curl_setopt($ch, CURLOPT_INFILE, $temp);
                        curl_setopt($ch, CURLOPT_INFILESIZE, strlen($attachment));
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                        $response = curl_exec($ch). ' ' . curl_error($ch);
                        fclose($temp);

                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);

                        if ($httpCode >= 200 && $httpCode < 300) {
                            echo "Datei erfolgreich hochgeladen!<br>";

                            // Mail verschieben
                            if (@imap_mail_move($mbox, $email_number, $zielOrdner)) {
                                echo "E-Mail $email_number erfolgreich zum Verschieben nach $zielOrdner vorgemerkt<br>";
                            } else {
                                echo "Fehler beim Verschieben: " . imap_last_error() . "<br>";
                            }
                        } else {
                            echo "HTTP-Fehler $httpCode: $response<br>";
                        }
                    }
                }
            }
        }
}

// E-Mails verarbeiten
function searchAndProcessSender($mbox, $sender, $ncUser, $ncPass, $ncUrl, $ncPfad, $zielOrdner) {
    
    $emails = imap_search($mbox, 'FROM "' . $sender . '"');

        echo 'Beginne mit der Suche nach den E-Mails von ' . $sender . '<br>' . "\n";

    if ($emails) {
        foreach ($emails as $email_number) {
            $structure = imap_fetchstructure($mbox, $email_number);
            if (isset($structure->parts) && count($structure->parts)) {
                processParts($mbox, $email_number, $structure->parts, $ncUser, $ncPass, $ncUrl, $ncPfad, $zielOrdner);
            }
        }
    }

    echo 'Suche nach den E-Mails von ' . $sender . ' abgeschlossen <br>' . "\n";
}

// Funktion um Vorhandensein von Imap-Folder zu prüfen
function ensureImapFolderExists($mbox, string $imapReference, string $folderName): bool
{
    $mailboxes = imap_getmailboxes($mbox, $imapReference, '*');
    if ($mailboxes === false) {
        echo "IMAP-Ordnerliste konnte nicht gelesen werden: " . imap_last_error() . "<br>";
        return false;
    }

    $fullMailboxName = $imapReference . $folderName;

    foreach ($mailboxes as $mailbox) {
        if (isset($mailbox->name) && imap_utf7_decode($mailbox->name) === $fullMailboxName) {
            return true;
        }
    }

    if (@imap_createmailbox($mbox, imap_utf7_encode($fullMailboxName))) {
        echo "IMAP-Ordner wurde angelegt: {$folderName}<br>";
        return true;
    }

    echo "IMAP-Ordner konnte nicht angelegt werden: {$folderName} - " . imap_last_error() . "<br>";
    return false;
}

function webdavFolderExists(string $url, string $user, string $pass): bool
{
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_USERPWD => $user . ':' . $pass,
        CURLOPT_CUSTOMREQUEST => 'PROPFIND',
        CURLOPT_HTTPHEADER => [
            'Depth: 0',
            'Content-Type: application/xml; charset=utf-8',
        ],
        CURLOPT_POSTFIELDS => <<<XML
<?xml version="1.0"?>
<d:propfind xmlns:d="DAV:">
    <d:prop>
        <d:resourcetype />
    </d:prop>
</d:propfind>
XML,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);

    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode === 207;
}

function createWebdavFolder(string $url, string $user, string $pass): bool
{
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_USERPWD => $user . ':' . $pass,
        CURLOPT_CUSTOMREQUEST => 'MKCOL',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        echo "cURL-Fehler beim Anlegen des Ordners: {$curlError}<br>";
        return false;
    }

    if ($httpCode === 201 || $httpCode === 405) {
        return true;
    }

    echo "Ordner konnte nicht angelegt werden. HTTP {$httpCode}<br>";
    return false;
}

function ensureWebdavPathExists(string $baseUrl, string $user, string $pass, string $relativePath): bool
{
    $baseUrl = rtrim($baseUrl, '/');
    $relativePath = trim($relativePath, '/');

    if ($relativePath === '') {
        return true;
    }

    $parts = explode('/', $relativePath);
    $currentPath = '';

    foreach ($parts as $part) {
        $currentPath .= '/' . $part;
        $currentUrl = $baseUrl . str_replace(' ', '%20', $currentPath);

        if (!webdavFolderExists($currentUrl, $user, $pass)) {
            if (!createWebdavFolder($currentUrl, $user, $pass)) {
                echo "Pfad konnte nicht erstellt werden: {$currentPath}<br>";
                return false;
            }
            echo "Ordner angelegt: {$currentPath}<br>";
        }
    }

    return true;
}

function buildWebdavUrl(string $baseUrl, string $relativePath, string $filename = ''): string
{
    $parts = array_map('rawurlencode', explode('/', trim($relativePath, '/')));
    $url = rtrim($baseUrl, '/') . '/' . implode('/', $parts);
    if ($filename !== '') {
        $url .= '/' . rawurlencode($filename);
    }
    return $url;
}