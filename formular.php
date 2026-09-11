<?php
/**
 * formular.php — gehaertete Formularannahme fuer spektrum-nachhilfe.de
 *
 * Verarbeitet: Kontaktformular, Elternfragebogen, Lehrersuche, Mitgliedsantrag.
 *
 * Bewusste Entscheidungen:
 *  - Empfaenger steht fest im Code, nie im Formular  -> kein offener Mailversender
 *  - Alle Kopfzeilen von \r und \n befreit           -> keine Header-Injection
 *  - Keine Ausgabe von Nutzereingaben ins HTML       -> kein XSS
 *  - Nichts wird gespeichert (ausser IP-Hash 1 Std.) -> nichts auslesbar
 *  - Honigtopf + Zeitfalle + IP-Bremse               -> Bots
 *  - Feld-Allowlist je Formular                      -> keine Fremdfelder in der Mail
 *  - Keine IBAN/BIC/Kontodaten vorgesehen            -> Mail ist unverschluesselte Post
 *
 * VOR DEM UPLOAD PRUEFEN:
 *  - EMPFAENGER: Postfach, das die Anfragen bekommen soll
 *  - ABSENDER:   Adresse auf der eigenen Domain (SPF/DKIM muessen dazu passen)
 *  - SPERRDATEI: Pfad AUSSERHALB von httpdocs, fuer PHP schreibbar
 */

declare(strict_types=1);

const EMPFAENGER      = 'info@spektrum-ev.de';
const ABSENDER        = 'noreply@spektrum-nachhilfe.de';
const SPERRDATEI      = '/var/www/vhosts/spektrum-nachhilfe.de/formular-sperre.json';
// Protokoll des Versands. Bewusst OHNE Inhalte und ohne Absenderadressen —
// es haelt nur fest, ob der Server die Mail angenommen hat. Liegt ausserhalb
// von httpdocs und ist damit ueber den Browser nicht abrufbar.
const PROTOKOLL       = '/var/www/vhosts/spektrum-nachhilfe.de/formular-protokoll.log';
const MIN_SEKUNDEN    = 3;
const MAX_PRO_STUNDE  = 5;

/** Erlaubte Formulare: Kennung => [Betreffzeile, [feld => Beschriftung]] */
const FORMULARE = [
    'kontakt' => ['Kontaktanfrage über die Website', [
        'name'      => 'Name',
        'email'     => 'E-Mail',
        'betreff'   => 'Betreff',
        'nachricht' => 'Nachricht',
    ]],
    'eltern' => ['Elternfragebogen', [
        'elternname'       => 'Name des Elternteils',
        'telefon'          => 'Telefon',
        'email'            => 'E-Mail',
        'kindname'         => 'Name des Kindes',
        'geburtsjahr'      => 'Geburtsjahr des Kindes',
        'schulart'         => 'Schulart',
        'klassenstufe'     => 'Klassenstufe',
        'sprachen'         => 'Sprachen zu Hause',
        'leistung'         => 'Schulische Leistungen',
        'staerken'         => 'Staerken in Faechern',
        'regelmaessigkeit' => 'Regelmaessigkeit des Lernens',
        'konzentration'    => 'Konzentrationsfaehigkeit',
        'sozial'           => 'Soziale Faehigkeiten',
        'gruppenverhalten' => 'Verhalten in Gruppen',
        'interessen'       => 'Interessen',
        'erwartungen'      => 'Erwartungen',
        'verfuegbarkeit'   => 'Erreichbar fuer Erstgespraech',
        'hinweise'         => 'Weitere Hinweise',
    ]],
    'lehrer' => ['Bewerbung als Nachhilfelehrkraft', [
        'name'             => 'Name',
        'email'            => 'E-Mail',
        'telefon'          => 'Telefon',
        'geburtsjahr'      => 'Geburtsjahr',
        'abschluss'        => 'Hoechster Abschluss',
        'studiengang'      => 'Studiengang',
        'universitaet'     => 'Universitaet oder Schule',
        'faecher'          => 'Faecher',
        'stufen'           => 'Klassenstufen',
        'erfahrung'        => 'Nachhilfeerfahrung',
        'erfahrungdetails' => 'Erfahrung im Detail',
        'tage'             => 'Verfuegbare Tage',
        'unterrichtsart'   => 'Unterrichtsart',
        'deutschniveau'    => 'Deutschniveau',
    ]],
    'mitglied' => ['Aufnahmeantrag Mitgliedschaft', [
        'vorname'     => 'Vorname',
        'nachname'    => 'Nachname',
        'geburtsjahr' => 'Geburtsjahr',
        'telefon'     => 'Telefon',
        'email'       => 'E-Mail',
        'strasse'     => 'Strasse und Hausnummer',
        'plz'         => 'PLZ',
        'ort'         => 'Ort',
        'beitragab'   => 'Mitgliedsbeitrag ab',
        'beitrag'     => 'Beitragshoehe',
        'zahlweise'   => 'Zahlungsweg',
        'spende'      => 'Zusaetzliche Spende',
        'rhythmus'    => 'Ausfuehrungsrhythmus',
        'infomail'    => 'Einverstanden mit Infomails',
        'satzung'     => 'Satzung anerkannt',
    ]],
];

/** Pflichtfelder je Formular */
const PFLICHT = [
    'kontakt'  => ['name', 'email', 'nachricht'],
    'eltern'   => ['elternname', 'telefon', 'email'],
    'lehrer'   => ['name', 'email', 'telefon', 'abschluss'],
    'mitglied' => ['vorname', 'nachname', 'telefon', 'email', 'strasse', 'plz', 'ort'],
];

/** Felder, die niemals angenommen werden — auch nicht, wenn sie jemand mitschickt. */
const GESPERRT = ['iban', 'bic', 'kontoinhaber', 'geldinstitut', 'konto', 'kreditkarte'];

/** Seite, zu der nach Abschluss zurueckgeleitet wird. */
const QUELLE = [
    'kontakt'  => 'kontakt.html',
    'eltern'   => 'elternfragebogen.html',
    'lehrer'   => 'lehrersuche.html',
    'mitglied' => 'mitglied-werden.html',
];

function zurueck(int $code, string $ziel): void
{
    http_response_code($code);
    header('Location: /' . $ziel);
    exit;
}

function saeubern(string $wert, int $max): string
{
    $wert = str_replace(["\r", "\n", "\0"], ' ', $wert);
    $wert = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $wert) ?? '';
    return mb_substr(trim($wert), 0, $max);
}

// --- Nur POST -----------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    zurueck(405, 'kontakt.html');
}

// --- Formular bestimmen -------------------------------------------------
$art = (string)($_POST['formular'] ?? '');
if (!isset(FORMULARE[$art])) {
    zurueck(400, 'kontakt.html?fehler=1');
}
[$betreffzeile, $felder] = FORMULARE[$art];
$quelle = QUELLE[$art];

// --- Honigtopf ----------------------------------------------------------
if (trim((string)($_POST['webseite'] ?? '')) !== '') {
    zurueck(200, 'danke.html');            // Bot bekommt Erfolg vorgespielt
}

// --- Zeitfalle (nur wenn der Zeitstempel gesetzt wurde) -----------------
$start = (int)($_POST['ts'] ?? 0);
if ($start > 0 && (time() - $start) < MIN_SEKUNDEN) {
    zurueck(200, 'danke.html');
}

// --- Einwilligung -------------------------------------------------------
if (($_POST['datenschutz'] ?? '') !== 'ja') {
    zurueck(400, $quelle . '?fehler=1');
}

// --- Gesperrte Felder ---------------------------------------------------
foreach (GESPERRT as $verboten) {
    if (isset($_POST[$verboten])) {
        zurueck(400, $quelle . '?fehler=1');
    }
}

// --- Eingaben einsammeln (nur Felder aus der Allowlist) -----------------
$werte = [];
foreach ($felder as $feld => $beschriftung) {
    if (!isset($_POST[$feld])) {
        continue;
    }
    $roh = $_POST[$feld];
    $lang = ($feld === 'nachricht' || $feld === 'hinweise');

    if (is_array($roh)) {
        $teile = [];
        foreach (array_slice($roh, 0, 20) as $eintrag) {
            if (is_string($eintrag)) {
                $sauber = saeubern($eintrag, 80);
                if ($sauber !== '') {
                    $teile[] = $sauber;
                }
            }
        }
        $wert = implode(', ', $teile);
    } elseif (is_string($roh)) {
        if ($lang) {
            $wert = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $roh) ?? '';
            $wert = mb_substr(trim($wert), 0, 3000);
        } else {
            $wert = saeubern($roh, 200);
        }
    } else {
        continue;
    }

    if ($wert !== '') {
        $werte[$feld] = $wert;
    }
}

// --- Pflichtfelder pruefen ---------------------------------------------
foreach (PFLICHT[$art] as $feld) {
    if (($werte[$feld] ?? '') === '') {
        zurueck(400, $quelle . '?fehler=1');
    }
}
$absenderMail = (string)($werte['email'] ?? '');
if (!filter_var($absenderMail, FILTER_VALIDATE_EMAIL)) {
    zurueck(400, $quelle . '?fehler=1');
}

// --- Bremse je IP -------------------------------------------------------
$schluessel = hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? ''));
$jetzt = time();
$liste = [];
if (is_readable(SPERRDATEI)) {
    $liste = json_decode((string)file_get_contents(SPERRDATEI), true) ?: [];
}
$liste = array_values(array_filter(
    $liste,
    static fn(array $e): bool => (int)($e['t'] ?? 0) > $jetzt - 3600
));
$anzahl = count(array_filter($liste, static fn(array $e): bool => ($e['k'] ?? '') === $schluessel));
if ($anzahl >= MAX_PRO_STUNDE) {
    zurueck(429, $quelle . '?fehler=2');
}
$liste[] = ['k' => $schluessel, 't' => $jetzt];
@file_put_contents(SPERRDATEI, json_encode($liste), LOCK_EX);

// --- Mail zusammenbauen -------------------------------------------------
$koerper = $betreffzeile . "\n"
    . str_repeat('=', mb_strlen($betreffzeile)) . "\n\n"
    . 'Eingegangen: ' . date('d.m.Y H:i') . "\n\n";

foreach ($felder as $feld => $beschriftung) {
    if (!isset($werte[$feld])) {
        continue;
    }
    $koerper .= str_pad($beschriftung . ':', 32) . $werte[$feld] . "\n";
}
$koerper .= "\n" . str_repeat('-', 60) . "\n"
    . "Gesendet über das Formular auf spektrum-nachhilfe.de\n";

$kopfzeilen = [
    'From: Website Spektrum <' . ABSENDER . '>',
    'Reply-To: ' . $absenderMail,
    'Content-Type: text/plain; charset=UTF-8',
    'MIME-Version: 1.0',
];

$vorher = error_get_last();
$ok = @mail(
    EMPFAENGER,
    '=?UTF-8?B?' . base64_encode($betreffzeile) . '?=',
    $koerper,
    implode("\r\n", $kopfzeilen),
    '-f' . ABSENDER
);
$nachher = error_get_last();

// Eine Zeile je Sendung. Ohne Namen, ohne Adressen, ohne Inhalte — nur die
// Frage, ob der Server die Mail angenommen hat. Sonst raet man im Dunkeln,
// wenn eine Anfrage nicht ankommt.
$notiz = date('d.m.Y H:i') . ' ' . $art . ' ' . ($ok ? 'ok' : 'FEHLER');
if (!$ok && $nachher !== null && $nachher !== $vorher) {
    $notiz .= ' (' . mb_substr(str_replace(["\r", "\n"], ' ', $nachher['message']), 0, 200) . ')';
}
@file_put_contents(PROTOKOLL, $notiz . "\n", FILE_APPEND | LOCK_EX);
// Das Protokoll darf nicht endlos wachsen: ueber 200 Zeilen wird vorne gekuerzt.
$zeilen = @file(PROTOKOLL, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (is_array($zeilen) && count($zeilen) > 200) {
    @file_put_contents(PROTOKOLL, implode("\n", array_slice($zeilen, -200)) . "\n", LOCK_EX);
}

zurueck(200, $ok ? 'danke.html' : $quelle . '?fehler=3');
