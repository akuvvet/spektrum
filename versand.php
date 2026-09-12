<?php
/**
 * versand.php — Mailversand über einen echten Postausgangsserver (SMTP)
 *
 * Warum überhaupt: Webserver und Mailserver sind hier zwei getrennte Häuser.
 * Die PHP-Funktion mail() erzeugt Post direkt auf dem Webserver mit einem
 * Absender, der beim Mailanbieter gar nicht wohnt. Solche Post wird dort
 * angenommen und stillschweigend weggeworfen — man merkt es nie.
 *
 * Deshalb meldet sich diese Datei beim Postausgangsserver des Anbieters an
 * und übergibt die Mail dort, so wie ein Mailprogramm es täte. Absender und
 * Anbieter passen dann zusammen, und die Mail kommt an.
 *
 * Die Zugangsdaten stehen NICHT hier, sondern in einer Datei eine Ebene über
 * httpdocs. Diese Datei hier enthält keine Geheimnisse und darf im Git liegen.
 *
 * Wird von formular.php und vom Adminbereich eingebunden, nie direkt
 * aufgerufen — dafür die Sperre gleich unten.
 */

declare(strict_types=1);

if (!defined('SPEKTRUM_VERSAND')) {
    http_response_code(403);
    exit;
}

/** Zugangsdatei: eine Ebene über httpdocs, über den Browser nicht erreichbar. */
function zugang_pfad(): string
{
    return dirname(__DIR__) . '/mail-zugang.php';
}

/**
 * Liest die Zugangsdaten. Rückgabe leer, wenn die Datei fehlt oder das
 * Passwort noch der Platzhalter ist — dann greift der alte Weg über mail().
 */
function zugang_lesen(): array
{
    $pfad = zugang_pfad();
    if (!is_readable($pfad)) {
        return [];
    }
    $z = @include $pfad;
    if (!is_array($z)) {
        return [];
    }
    $z += ['host' => '', 'port' => 587, 'sicher' => 'tls', 'benutzer' => '',
           'passwort' => '', 'absender' => '', 'name' => 'Website'];
    foreach (['host', 'benutzer', 'passwort', 'absender'] as $pflicht) {
        if (trim((string)$z[$pflicht]) === '') {
            return [];
        }
    }
    if (str_contains((string)$z['passwort'], 'HIER')) {   // Platzhalter unangetastet
        return [];
    }
    return $z;
}

/** Kopfzeilen dürfen keine Zeilenumbrüche enthalten — sonst Header-Injection. */
function kopf_saeubern(string $wert): string
{
    return trim(str_replace(["\r", "\n", "\0"], ' ', $wert));
}

/** Betreff nach RFC 2047, damit Umlaute nicht zerfallen. */
function betreff_kodieren(string $betreff): string
{
    return '=?UTF-8?B?' . base64_encode(kopf_saeubern($betreff)) . '?=';
}

/**
 * Eine Antwort des Servers lesen. SMTP antwortet mehrzeilig, solange nach
 * der Zahl ein Bindestrich steht: "250-..." geht weiter, "250 ..." ist Schluss.
 */
function smtp_antwort($verbindung): array
{
    $text = '';
    while (($zeile = fgets($verbindung, 515)) !== false) {
        $text .= $zeile;
        if (strlen($zeile) < 4 || $zeile[3] !== '-') {
            break;
        }
    }
    return [(int)substr($text, 0, 3), trim($text)];
}

/** Befehl senden und Antwort prüfen. Wirft eine Ausnahme bei falschem Code. */
function smtp_befehl($verbindung, string $befehl, int $erwartet, string $zeigen = null): void
{
    if ($befehl !== '') {
        fwrite($verbindung, $befehl . "\r\n");
    }
    [$code, $text] = smtp_antwort($verbindung);
    if ($code !== $erwartet) {
        // $zeigen steht dort, wo der Befehl selbst nicht ins Protokoll darf.
        $wobei = $zeigen ?? substr($befehl, 0, 40);
        throw new RuntimeException('Server antwortete auf „' . $wobei . '“ mit: ' . $text);
    }
}

/**
 * Verschickt eine Mail über den Postausgangsserver aus der Zugangsdatei.
 *
 * @return array [erfolg, Meldung im Klartext]
 */
function smtp_senden(array $z, string $an, string $betreff, string $text, string $antwortAn = ''): array
{
    $schema = ((string)$z['sicher'] === 'ssl') ? 'ssl://' : '';
    $ziel = $schema . $z['host'] . ':' . (int)$z['port'];

    $rahmen = stream_context_create(['ssl' => [
        'verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true,
    ]]);

    $verbindung = @stream_socket_client($ziel, $fehlerNr, $fehlerText, 20,
        STREAM_CLIENT_CONNECT, $rahmen);
    if (!$verbindung) {
        return [false, 'Keine Verbindung zu ' . $z['host'] . ':' . (int)$z['port']
            . ' — ' . ($fehlerText !== '' ? $fehlerText : 'Zeitüberschreitung')
            . '. Meist blockiert die Firewall des Servers ausgehende Mailverbindungen.'];
    }
    stream_set_timeout($verbindung, 20);

    $rechner = (string)($_SERVER['SERVER_NAME'] ?? 'localhost');
    if ($rechner === '' || !preg_match('/^[A-Za-z0-9.\-]+$/', $rechner)) {
        $rechner = 'localhost';
    }

    try {
        smtp_befehl($verbindung, '', 220, 'Begrüßung');
        smtp_befehl($verbindung, 'EHLO ' . $rechner, 250);

        if ((string)$z['sicher'] === 'tls') {
            smtp_befehl($verbindung, 'STARTTLS', 220);
            $arten = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $arten |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (!@stream_socket_enable_crypto($verbindung, true, $arten)) {
                throw new RuntimeException('Die Verschlüsselung ließ sich nicht aufbauen.');
            }
            smtp_befehl($verbindung, 'EHLO ' . $rechner, 250);
        }

        // Anmeldung. Die beiden folgenden Befehle tragen Benutzer und Passwort —
        // ihr Wortlaut darf niemals in eine Fehlermeldung geraten.
        smtp_befehl($verbindung, 'AUTH LOGIN', 334);
        smtp_befehl($verbindung, base64_encode((string)$z['benutzer']), 334, 'Benutzername');
        smtp_befehl($verbindung, base64_encode((string)$z['passwort']), 235, 'Passwort');

        smtp_befehl($verbindung, 'MAIL FROM:<' . $z['absender'] . '>', 250);
        smtp_befehl($verbindung, 'RCPT TO:<' . $an . '>', 250);
        smtp_befehl($verbindung, 'DATA', 354);

        $kopf = [
            'Date: ' . date('r'),
            'From: ' . mb_encode_mimeheader(kopf_saeubern((string)$z['name']), 'UTF-8')
                . ' <' . $z['absender'] . '>',
            'To: <' . $an . '>',
            'Subject: ' . betreff_kodieren($betreff),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $rechner . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
        ];
        if ($antwortAn !== '' && filter_var($antwortAn, FILTER_VALIDATE_EMAIL)) {
            $kopf[] = 'Reply-To: <' . $antwortAn . '>';
        }

        // Zeilen, die mit einem Punkt beginnen, müssen verdoppelt werden —
        // ein einzelner Punkt beendet sonst mitten im Text die Nachricht.
        $koerper = preg_replace('/^\./m', '..', str_replace("\r\n", "\n", $text));
        $koerper = str_replace("\n", "\r\n", (string)$koerper);

        fwrite($verbindung, implode("\r\n", $kopf) . "\r\n\r\n" . $koerper . "\r\n.\r\n");
        smtp_befehl($verbindung, '', 250, 'Nachricht');

        @fwrite($verbindung, "QUIT\r\n");
        fclose($verbindung);
        return [true, 'Über ' . $z['host'] . ' als ' . $z['benutzer'] . ' verschickt.'];

    } catch (Throwable $e) {
        @fclose($verbindung);
        return [false, $e->getMessage()];
    }
}

/**
 * Der eine Weg nach draußen: nimmt SMTP, wenn Zugangsdaten hinterlegt sind,
 * sonst den alten mail()-Weg.
 *
 * @return array [erfolg, Weg, Meldung]
 */
function versenden(string $an, string $absender, string $betreff,
                   string $text, string $antwortAn = ''): array
{
    $z = zugang_lesen();
    if ($z !== []) {
        [$ok, $meldung] = smtp_senden($z, $an, $betreff, $text, $antwortAn);
        return [$ok, 'SMTP', $meldung];
    }

    $kopf = [
        'From: Website Spektrum <' . $absender . '>',
        'Content-Type: text/plain; charset=UTF-8',
        'MIME-Version: 1.0',
    ];
    if ($antwortAn !== '' && filter_var($antwortAn, FILTER_VALIDATE_EMAIL)) {
        $kopf[] = 'Reply-To: ' . $antwortAn;
    }

    $vorher = error_get_last();
    $ok = @mail($an, betreff_kodieren($betreff), $text,
                implode("\r\n", $kopf), '-f' . $absender);
    $nachher = error_get_last();

    if ($ok) {
        return [true, 'mail()', 'Vom Server angenommen. Ob sie ankommt, ist damit '
            . 'noch nicht gesagt — ohne hinterlegtes Postfach werfen viele Anbieter '
            . 'solche Post weg.'];
    }
    $grund = ($nachher !== null && $nachher !== $vorher) ? ' ' . $nachher['message'] : '';
    return [false, 'mail()', 'Der Server hat die Mail nicht angenommen.' . $grund];
}
