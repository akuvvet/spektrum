<?php
/**
 * kern.php — gemeinsame Grundlage des Adminbereichs (Projekte)
 *
 * Wird von index.php und einrichten.php eingebunden, nie direkt aufgerufen.
 *
 * Grundsaetze:
 *  - Passwort nur als Hash, gespeichert AUSSERHALB von httpdocs
 *  - Sitzung mit HttpOnly, SameSite=Strict, Erneuerung nach der Anmeldung
 *  - Jede aendernde Aktion nur per POST und nur mit gueltigem Formular-Token
 *  - Bilder werden neu berechnet (GD) — dabei fallen EXIF und alles
 *    Eingebettete weg; gespeichert wird immer als JPEG mit eigenem Namen
 *  - Texte werden als reiner Text behandelt und beim Ausgeben maskiert
 *  - Die oeffentliche Seite bleibt statisches HTML: beim Speichern wird der
 *    Block zwischen den beiden Marken in projekte.html neu geschrieben
 */

declare(strict_types=1);

if (PHP_SAPI === 'cli') { exit(1); }

// ---------------------------------------------------------------- Pfade
define('WEB', dirname(__DIR__));                 // .../httpdocs
define('BILDER', WEB . '/assets/projekte');      // oeffentlich erreichbar
define('SEITE', WEB . '/projekte.html');

// Fassung des Adminbereichs — steht unter „Prüfen“, damit man ohne Raten sieht,
// welche Dateien wirklich auf dem Server liegen.
const FASSUNG = '2026-09-11 a';

const MARKE_ANFANG = '<!-- PROJEKTE:ANFANG - nicht entfernen, der Adminbereich schreibt hier hinein -->';
const MARKE_ENDE   = '<!-- PROJEKTE:ENDE -->';

const MAX_TITEL    = 90;
const MAX_ZEITRAUM = 60;
const MAX_TEXT     = 600;
const MAX_DETAIL   = 4000;
const MAX_BILDER   = 8;      // Galeriebilder je Projekt
const MAX_LINK     = 300;
const MAX_BILD_MB  = 8;
const BILD_BREITE  = 800;   // Kartenbild, fester Ausschnitt
const BILD_HOEHE   = 500;
const GALERIE_KANTE = 1400; // Galeriebild, laengste Kante, Seitenverhaeltnis bleibt

const MAX_VERSUCHE = 5;      // Anmeldeversuche
const SPERRE_SEK   = 900;    // danach 15 Minuten Ruhe
const SITZUNG_SEK  = 7200;   // nach 2 Stunden ohne Klick abgemeldet

/**
 * Datenordner: bevorzugt eine Ebene ueber httpdocs, damit niemand die Dateien
 * ueber den Browser abrufen kann. Geht das nicht (manche Hoster erlauben es
 * nicht), wird httpdocs/daten benutzt und dort ein Riegel hinterlegt.
 */
function daten_ordner(): string
{
    static $ordner = null;
    if ($ordner !== null) { return $ordner; }

    $aussen = dirname(WEB) . '/daten';
    if (is_dir($aussen) ? is_writable($aussen) : @mkdir($aussen, 0750, true)) {
        return $ordner = $aussen;
    }
    $innen = WEB . '/daten';
    if (!is_dir($innen)) { @mkdir($innen, 0750, true); }
    $riegel = $innen . '/.htaccess';
    if (!is_file($riegel)) {
        @file_put_contents($riegel,
            "Require all denied\n"
            . "<IfModule !mod_authz_core.c>\n  Order allow,deny\n  Deny from all\n</IfModule>\n");
    }
    return $ordner = $innen;
}

function pfad_daten(): string   { return daten_ordner() . '/projekte.json'; }
function pfad_zugang(): string  { return daten_ordner() . '/zugang.json'; }
function pfad_sperre(): string  { return daten_ordner() . '/anmeldung.json'; }
function pfad_sicherung(): string { return daten_ordner() . '/projekte-sicherung.html'; }

// ---------------------------------------------------------------- Sitzung
function sitzung_starten(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) { return; }
    $sicher = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('spektrum_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Strict',
        'secure'   => $sicher,
    ]);
    session_start();
}

function kopfzeilen(): void
{
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; "
        . "base-uri 'none'; form-action 'self'; frame-ancestors 'none'; object-src 'none'");
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store');
}

function angemeldet(): bool
{
    if (empty($_SESSION['admin'])) { return false; }
    if (time() - (int)($_SESSION['zuletzt'] ?? 0) > SITZUNG_SEK) {
        abmelden();
        return false;
    }
    $_SESSION['zuletzt'] = time();
    return true;
}

function abmelden(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function token(): string
{
    if (empty($_SESSION['token'])) {
        $_SESSION['token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['token'];
}

function token_pruefen(): bool
{
    return !empty($_SESSION['token'])
        && is_string($_POST['token'] ?? null)
        && hash_equals($_SESSION['token'], $_POST['token']);
}

// ---------------------------------------------------------------- Anmeldung
function kennung(): string
{
    return substr(hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '?') . '|spektrum'), 0, 16);
}

/** @return int Verbleibende Sperrsekunden, 0 = frei */
function gesperrt(): int
{
    $liste = @json_decode((string)@file_get_contents(pfad_sperre()), true) ?: [];
    $jetzt = time();
    $meine = array_values(array_filter(
        $liste[kennung()] ?? [],
        static fn($t) => is_int($t) && $t > $jetzt - SPERRE_SEK
    ));
    if (count($meine) < MAX_VERSUCHE) { return 0; }
    return max(1, min($meine) + SPERRE_SEK - $jetzt);
}

function fehlversuch_merken(): void
{
    $datei = pfad_sperre();
    $liste = @json_decode((string)@file_get_contents($datei), true) ?: [];
    $jetzt = time();
    foreach ($liste as $k => $zeiten) {                 // Altes wegwerfen
        $zeiten = array_values(array_filter($zeiten, static fn($t) => $t > $jetzt - SPERRE_SEK));
        if ($zeiten) { $liste[$k] = $zeiten; } else { unset($liste[$k]); }
    }
    $liste[kennung()][] = $jetzt;
    @file_put_contents($datei, json_encode($liste), LOCK_EX);
}

function versuche_loeschen(): void
{
    $datei = pfad_sperre();
    $liste = @json_decode((string)@file_get_contents($datei), true) ?: [];
    unset($liste[kennung()]);
    @file_put_contents($datei, json_encode($liste), LOCK_EX);
}

function zugang_vorhanden(): bool
{
    return is_file(pfad_zugang());
}

function passwort_pruefen(string $eingabe): bool
{
    $zugang = @json_decode((string)@file_get_contents(pfad_zugang()), true);
    if (!is_array($zugang) || empty($zugang['hash'])) { return false; }
    return password_verify($eingabe, (string)$zugang['hash']);
}

// ---------------------------------------------------------------- Daten
function projekte_laden(): array
{
    $roh = @json_decode((string)@file_get_contents(pfad_daten()), true);
    $liste = is_array($roh['projekte'] ?? null) ? $roh['projekte'] : [];
    $sauber = [];
    foreach ($liste as $p) {
        if (!is_array($p) || empty($p['titel'])) { continue; }
        $bilder = [];
        foreach (($p['bilder'] ?? []) as $b) {
            if (is_array($b) && !empty($b['datei'])) {
                $bilder[] = ['datei' => (string)$b['datei'], 'text' => (string)($b['text'] ?? '')];
            }
        }
        $sauber[] = [
            'id'       => (string)($p['id'] ?? neue_id()),
            'titel'    => (string)$p['titel'],
            'zeitraum' => (string)($p['zeitraum'] ?? ''),
            'text'     => (string)($p['text'] ?? ''),
            'detail'   => (string)($p['detail'] ?? ''),
            'link'     => (string)($p['link'] ?? ''),
            'bild'     => (string)($p['bild'] ?? ''),
            'bilder'   => array_slice($bilder, 0, MAX_BILDER),
            'sichtbar' => !empty($p['sichtbar']),
        ];
    }
    return $sauber;
}

function projekte_speichern(array $projekte): bool
{
    $inhalt = json_encode(
        ['version' => 1, 'stand' => date('c'), 'projekte' => array_values($projekte)],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    return schreiben_sicher(pfad_daten(), (string)$inhalt);
}

/**
 * Erst in eine Nebendatei schreiben, dann umbenennen — nie eine halbe Datei.
 * Das Umbenennen braucht nur Schreibrecht am ORDNER, nicht an der Zieldatei.
 *
 * $rechte: 0640 fuer Daten (niemand ausser PHP soll sie lesen),
 *          0644 fuer alles, was der Webserver ausliefern muss — sonst 403.
 */
function schreiben_sicher(string $ziel, string $inhalt, int $rechte = 0640): bool
{
    $temp = $ziel . '.neu';
    if (@file_put_contents($temp, $inhalt, LOCK_EX) === false) { return false; }
    @chmod($temp, $rechte);
    if (@rename($temp, $ziel)) { return true; }
    @unlink($temp);
    return false;
}

function neue_id(): string
{
    return date('ymd') . '-' . bin2hex(random_bytes(3));
}

function saeubern(string $wert, int $max): string
{
    $wert = str_replace(["\r\n", "\r"], "\n", $wert);
    $wert = preg_replace('/[^\P{C}\n]/u', '', $wert) ?? '';   // Steuerzeichen raus
    $wert = trim(preg_replace('/\n{3,}/', "\n\n", $wert) ?? '');
    return mb_substr($wert, 0, $max);
}

/** Erlaubt sind interne Seiten (name.html), Anker, mailto, tel und https. */
function link_pruefen(string $link): string
{
    $link = trim($link);
    if ($link === '') { return ''; }
    if (mb_strlen($link) > MAX_LINK) { return ''; }
    if (preg_match('~^[a-z0-9\-]+\.html(#[a-z0-9\-]+)?$~i', $link)) { return $link; }
    if (preg_match('~^https://[^\s"<>]+$~i', $link)) { return $link; }
    if (preg_match('~^mailto:[^\s"<>@]+@[^\s"<>@]+$~i', $link)) { return $link; }
    if (preg_match('~^tel:\+?[0-9 ]{5,20}$~', $link)) { return $link; }
    return '';
}

function h(string $wert): string
{
    return htmlspecialchars($wert, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ---------------------------------------------------------------- Bilder
/**
 * Nimmt eine hochgeladene Datei an, rechnet sie auf 800x500 herunter
 * (mittiger Ausschnitt) und legt sie als JPEG unter assets/projekte/ ab.
 *
 * @return array{0:string,1:string} [Pfad relativ zur Website, Fehlermeldung]
 */
function bild_annehmen(array $datei, string $art = 'karte'): array
{
    if (($datei['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) { return ['', '']; }
    if ($datei['error'] !== UPLOAD_ERR_OK) {
        return ['', 'Das Bild wurde nicht vollständig übertragen (Fehler ' . (int)$datei['error']
            . '). Bei sehr großen Dateien begrenzt der Server den Upload.'];
    }
    if (!is_uploaded_file($datei['tmp_name'])) { return ['', 'Ungültiger Upload.']; }
    if ($datei['size'] > MAX_BILD_MB * 1024 * 1024) {
        return ['', 'Das Bild ist größer als ' . MAX_BILD_MB . ' MB.'];
    }
    if (!extension_loaded('gd')) {
        return ['', 'Auf dem Server fehlt die Bildbibliothek GD. Ohne sie kann ich Bilder '
            . 'nicht sicher umrechnen. Bitte GD in Plesk aktivieren.'];
    }

    $masse = @getimagesize($datei['tmp_name']);
    if (!$masse) { return ['', 'Die Datei ist kein Bild.']; }
    [$breite, $hoehe, $typ] = $masse;
    if ($breite < 200 || $hoehe < 150) { return ['', 'Das Bild ist zu klein (mindestens 200 × 150 px).']; }
    if ($breite * $hoehe > 60000000) { return ['', 'Das Bild hat zu viele Bildpunkte.']; }

    $quelle = match ($typ) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($datei['tmp_name']),
        IMAGETYPE_PNG  => @imagecreatefrompng($datei['tmp_name']),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($datei['tmp_name']) : false,
        IMAGETYPE_GIF  => @imagecreatefromgif($datei['tmp_name']),
        default        => false,
    };
    if (!$quelle) { return ['', 'Dieses Bildformat kann ich nicht verarbeiten. Bitte JPEG, PNG oder WebP.']; }

    if ($art === 'galerie') {
        // Galeriebild: nichts abschneiden, nur verkleinern — sonst fehlen
        // auf Hochformaten die Koepfe und auf Panoramen die Raender.
        $faktor = min(1.0, GALERIE_KANTE / max($breite, $hoehe));
        $zb = max(1, (int)round($breite * $faktor));
        $zh = max(1, (int)round($hoehe * $faktor));
        $ziel = imagecreatetruecolor($zb, $zh);
        imagefill($ziel, 0, 0, imagecolorallocate($ziel, 255, 255, 255));
        imagecopyresampled($ziel, $quelle, 0, 0, 0, 0, $zb, $zh, $breite, $hoehe);
    } else {
        // Kartenbild: mittiger Ausschnitt im Seitenverhaeltnis der Kachel,
        // damit alle Kacheln gleich aussehen
        $ziel_v = BILD_BREITE / BILD_HOEHE;
        $quell_v = $breite / $hoehe;
        if ($quell_v > $ziel_v) {
            $sw = (int)round($hoehe * $ziel_v); $sh = $hoehe;
        } else {
            $sw = $breite; $sh = (int)round($breite / $ziel_v);
        }
        $sx = (int)round(($breite - $sw) / 2);
        $sy = (int)round(($hoehe - $sh) / 2);

        $ziel = imagecreatetruecolor(BILD_BREITE, BILD_HOEHE);
        imagefill($ziel, 0, 0, imagecolorallocate($ziel, 255, 255, 255));
        imagecopyresampled($ziel, $quelle, 0, 0, $sx, $sy, BILD_BREITE, BILD_HOEHE, $sw, $sh);
    }
    imagedestroy($quelle);

    if (!is_dir(BILDER) && !@mkdir(BILDER, 0755, true)) {
        imagedestroy($ziel);
        return ['', 'Der Ordner assets/projekte/ lässt sich nicht anlegen. Bitte per FTP anlegen '
            . 'und beschreibbar machen.'];
    }
    // mkdir wird von der umask beschnitten — 0750 hiesse: Webserver kommt nicht
    // in den Ordner hinein, jedes Bild waere 403. Deshalb ausdruecklich setzen.
    if (!oeffentlich_lesbar(BILDER)) { @chmod(BILDER, 0755); }

    $vorsatz = $art === 'galerie' ? 'g-' : 'p-';
    $name = $vorsatz . date('ymd') . '-' . bin2hex(random_bytes(4)) . '.jpg';
    $ok = imagejpeg($ziel, BILDER . '/' . $name, 82);
    imagedestroy($ziel);
    if (!$ok) { return ['', 'Das Bild konnte nicht gespeichert werden — Schreibrechte prüfen.']; }
    @chmod(BILDER . '/' . $name, 0644);

    if (!oeffentlich_lesbar(BILDER . '/' . $name)) {
        return ['', 'Das Bild wurde gespeichert, ist aber für den Webserver nicht lesbar '
            . '(Rechte ' . substr(sprintf('%o', (int)fileperms(BILDER . '/' . $name)), -3)
            . '). Bitte: chmod 644 httpdocs/assets/projekte/*.jpg'];
    }

    return ['assets/projekte/' . $name, ''];
}

/** Was der Server tatsaechlich durchlaesst — PHP-Grenzen koennen kleiner sein. */
function max_upload_mb(): float
{
    $inMb = static function (string $wert): float {
        $wert = trim($wert);
        if ($wert === '' || $wert === '0') { return 1e9; }
        $zahl = (float)$wert;
        return match (strtolower(substr($wert, -1))) {
            'g' => $zahl * 1024,
            'm' => $zahl,
            'k' => $zahl / 1024,
            default => $zahl / 1048576,
        };
    };
    return round(min(
        (float)MAX_BILD_MB,
        $inMb((string)ini_get('upload_max_filesize')),
        $inMb((string)ini_get('post_max_size'))
    ), 1);
}

function bild_loeschen(string $pfad): void
{
    if (!preg_match('~^assets/projekte/[pg]-[0-9a-z\-]+\.jpg$~', $pfad)) { return; }
    @unlink(WEB . '/' . $pfad);
}

// ---------------------------------------------------------------- Ausgabe
/**
 * Erzeugt den Kartenblock. Muss zeichengleich zu projekte_daten.karte()
 * im Baukasten sein, damit ein spaeterer Neubau nichts verschiebt.
 */
function absaetze(string $text): string
{
    $text = str_replace("\r\n", "\n", $text);
    if (trim($text) === '') { return ''; }
    $raus = '';
    foreach (preg_split('/\n{2,}/', $text) as $stueck) {
        $stueck = trim($stueck);
        if ($stueck !== '') {
            $raus .= '<p>' . str_replace("\n", '<br>', h($stueck)) . '</p>';
        }
    }
    return $raus;
}

/** Ein Fenster lohnt nur, wenn es dort mehr gibt als auf der Kachel. */
function hat_fenster(array $p): bool
{
    return trim((string)($p['detail'] ?? '')) !== ''
        || !empty($p['bilder'])
        || ($p['link'] ?? '') !== '';
}

function karte_html(array $p): string
{
    $k = '<article class="karte karte--projekt" id="k-' . h($p['id']) . '">';
    if ($p['bild'] !== '') {
        $k .= '<div class="karte__bild"><img src="' . h($p['bild']) . '" alt="" '
            . 'width="800" height="500" loading="lazy" decoding="async"></div>';
    } else {
        $k .= '<div class="karte__bild karte__bild--leer" aria-hidden="true"></div>';
    }
    $k .= '<div class="karte__koerper">';
    if ($p['zeitraum'] !== '') {
        $k .= '<p class="karte__zeit">' . h($p['zeitraum']) . '</p>';
    }
    $titel = h($p['titel']);
    $k .= hat_fenster($p)
        ? '<h3><a class="karte__flaeche" href="#p-' . h($p['id']) . '">' . $titel . '</a></h3>'
        : '<h3>' . $titel . '</h3>';
    $k .= '<p class="karte__text">' . h($p['text']) . '</p>';
    if (hat_fenster($p)) {
        $k .= '<p class="karte__mehr" aria-hidden="true">Details ansehen</p>';
    }
    return $k . '</div></article>';
}

function fenster_html(array $p): string
{
    if (!hat_fenster($p)) { return ''; }
    $kennung = h($p['id']);
    $f = '<div class="fenster" id="p-' . $kennung . '" role="dialog" aria-modal="true" '
        . 'aria-labelledby="t-' . $kennung . '">'
        . '<a class="fenster__grund" href="#projekte" tabindex="-1" aria-hidden="true"></a>'
        . '<div class="fenster__blatt">'
        . '<a class="fenster__zu" href="#projekte" aria-label="Fenster schließen">'
        . '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
        . '<path d="M6 6 L18 18 M18 6 L6 18"/></svg></a>';

    if ($p['zeitraum'] !== '') {
        $f .= '<p class="fenster__zeit">' . h($p['zeitraum']) . '</p>';
    }
    $f .= '<h2 id="t-' . $kennung . '">' . h($p['titel']) . '</h2>';

    $lang = absaetze((string)($p['detail'] ?? ''));
    if ($lang === '') { $lang = '<p>' . h($p['text']) . '</p>'; }
    $f .= '<div class="fenster__text">' . $lang . '</div>';

    if (!empty($p['bilder'])) {
        $f .= '<div class="galerie">';
        foreach ($p['bilder'] as $b) {
            $unter = h((string)($b['text'] ?? ''));
            $f .= '<figure class="galerie__stueck">'
                . '<a class="galerie__gross" href="' . h($b['datei']) . '">'
                . '<img src="' . h($b['datei']) . '" alt="' . $unter . '" '
                . 'loading="lazy" decoding="async">'
                . '</a>'
                . ($unter !== '' ? '<figcaption>' . $unter . '</figcaption>' : '')
                . '</figure>';
        }
        $f .= '</div>';
    }

    if ($p['link'] !== '') {
        $f .= '<p class="fenster__aktion"><a class="knopf" href="' . h($p['link']) . '">'
            . 'Mehr erfahren</a></p>';
    }
    return $f . '</div></div>';
}

/**
 * Der ganze Block zwischen den Marken: erst die Kacheln im Raster,
 * dann die Fenster. Muss zeichengleich zu projekte_daten.py sein.
 */
function karten_html(array $projekte): string
{
    $sichtbar = array_values(array_filter($projekte, static fn($p) => !empty($p['sichtbar'])));
    if (!$sichtbar) {
        return '      <p class="hinweis">Zurzeit sind keine Projekte veröffentlicht.</p>';
    }
    $zeilen = ['      <div class="raster raster--projekte">'];
    foreach ($sichtbar as $p) { $zeilen[] = '        ' . karte_html($p); }
    $zeilen[] = '      </div>';
    foreach ($sichtbar as $p) {
        if (hat_fenster($p)) { $zeilen[] = '      ' . fenster_html($p); }
    }
    return implode("\n", $zeilen);
}

/**
 * Selbstpruefung — beantwortet die Frage "warum ist mein Eintrag nicht online".
 *
 * @return array Liste aus [Bezeichnung, Zustand (gut|schlecht|hinweis), Text]
 */
function pruefung(array $projekte): array
{
    $z = [];

    $z[] = ['Datenordner', is_writable(daten_ordner()) ? 'gut' : 'schlecht',
        daten_ordner() . (is_writable(daten_ordner()) ? ' — beschreibbar' : ' — NICHT beschreibbar')];

    $z[] = ['Projektdatei', is_file(pfad_daten()) ? 'gut' : 'schlecht',
        is_file(pfad_daten())
            ? 'projekte.json, ' . number_format((float)filesize(pfad_daten()) / 1024, 1, ',', '.')
              . ' KB, zuletzt gespeichert ' . date('d.m.Y H:i', (int)filemtime(pfad_daten()))
            : 'projekte.json fehlt — noch nie gespeichert'];

    $sichtbar = 0;
    foreach ($projekte as $p) { if (!empty($p['sichtbar'])) { $sichtbar++; } }
    $entwurf = count($projekte) - $sichtbar;
    $z[] = ['Fassung Adminbereich', 'hinweis',
        FASSUNG . ' — kern.php vom ' . date('d.m.Y H:i', (int)filemtime(__DIR__ . '/kern.php'))];

    $z[] = ['Einträge', $entwurf > 0 ? 'hinweis' : 'gut',
        count($projekte) . ' gespeichert, davon ' . $sichtbar . ' veröffentlicht'
        . ($entwurf > 0 ? ' und ' . $entwurf . ' als Entwurf (Entwürfe stehen absichtlich nicht auf der Website)' : '')];

    if (!is_file(SEITE)) {
        $z[] = ['projekte.html', 'schlecht', 'Datei nicht gefunden unter ' . SEITE];
        return $z;
    }
    $html = (string)file_get_contents(SEITE);
    $marken = strpos($html, MARKE_ANFANG) !== false && strpos($html, MARKE_ENDE) !== false;
    $z[] = ['Marken in projekte.html', $marken ? 'gut' : 'schlecht',
        $marken ? 'gefunden — die Stelle zum Schreiben ist da'
            : 'FEHLEN. Diese Datei stammt aus einer alten Fassung. projekte.html aus dem '
              . 'aktuellen Paket hochladen, dann hier auf „Seite neu schreiben" klicken.'];

    $ordnerFrei = is_writable(dirname(SEITE));
    if (is_writable(SEITE)) {
        $z[] = ['Schreibrecht projekte.html', 'gut',
            'beschreibbar (Rechte ' . substr(sprintf('%o', fileperms(SEITE)), -3)
            . ', Eigentümer ' . besitzer(SEITE) . ')'];
    } elseif ($ordnerFrei) {
        $z[] = ['Schreibrecht projekte.html', 'hinweis',
            'Die Datei selbst ist schreibgeschützt (Rechte '
            . substr(sprintf('%o', fileperms(SEITE)), -3) . ', Eigentümer ' . besitzer(SEITE)
            . '), aber der Ordner httpdocs ist beschreibbar — der Umweg über eine neue Datei '
            . 'funktioniert, Speichern geht also trotzdem. Sauberer wäre: chmod 644 und '
            . 'Eigentümer ' . besitzer(dirname(SEITE)) . '.'];
    } else {
        $z[] = ['Schreibrecht projekte.html', 'schlecht',
            'Weder die Datei noch der Ordner httpdocs sind für PHP beschreibbar. '
            . 'Datei: Rechte ' . substr(sprintf('%o', fileperms(SEITE)), -3)
            . ', Eigentümer ' . besitzer(SEITE)
            . ' · Ordner: Eigentümer ' . besitzer(dirname(SEITE))
            . ' · PHP läuft als ' . php_benutzer()];
    }

    $z[] = ['Stand projekte.html', 'hinweis',
        'zuletzt geschrieben ' . date('d.m.Y H:i', (int)filemtime(SEITE))
        . ' — ' . substr_count($html, 'class="karte karte--projekt"') . ' Karten darin'];

    $gleich = $marken && (karten_html($projekte) === zwischen_marken($html));
    $z[] = ['Seite auf dem neuesten Stand', $gleich ? 'gut' : 'schlecht',
        $gleich ? 'Website zeigt genau das, was gespeichert ist'
            : 'Website weicht von den gespeicherten Daten ab — auf „Seite neu schreiben" klicken'];

    $z[] = ['Lesbar für den Webserver', oeffentlich_lesbar(SEITE) ? 'gut' : 'schlecht',
        oeffentlich_lesbar(SEITE)
            ? 'ja — Rechte ' . substr(sprintf('%o', fileperms(SEITE)), -3)
            : 'NEIN. Rechte ' . substr(sprintf('%o', fileperms(SEITE)), -3)
              . ' — der Webserver läuft unter einem anderen Benutzer und darf die Datei '
              . 'nicht lesen. Besucher bekommen 403 Forbidden. Abhilfe: chmod 644 projekte.html'];

    $z[] = ['Bildordner', is_dir(BILDER) && is_writable(BILDER) ? 'gut' : 'hinweis',
        is_dir(BILDER)
            ? BILDER . (is_writable(BILDER) ? ' — beschreibbar' : ' — nicht beschreibbar, Bild-Upload schlägt fehl')
            : 'assets/projekte/ fehlt, wird beim ersten Bild-Upload angelegt'];

    $z[] = ['Bildbibliothek GD', extension_loaded('gd') ? 'gut' : 'schlecht',
        extension_loaded('gd') ? 'vorhanden' : 'fehlt — Bild-Upload nicht möglich, alles andere geht'];

    $z[] = ['PHP', 'hinweis', PHP_VERSION
        . ', Upload bis ' . ini_get('upload_max_filesize')
        . ', POST bis ' . ini_get('post_max_size')];

    $z[] = ['Einrichtungsdatei', is_file(__DIR__ . '/einrichten.php') ? 'schlecht' : 'gut',
        is_file(__DIR__ . '/einrichten.php')
            ? 'admin/einrichten.php liegt noch auf dem Server — bitte löschen'
            : 'gelöscht, richtig so'];

    return $z;
}

/**
 * Darf "jeder andere" die Datei lesen? Genau dieses Bit braucht der Webserver,
 * weil er unter einem anderen Benutzer laeuft als PHP. Fehlt es: 403 Forbidden.
 */
function oeffentlich_lesbar(string $pfad): bool
{
    clearstatcache(true, $pfad);
    $rechte = @fileperms($pfad);
    return $rechte !== false && ($rechte & 0004) === 0004;
}

/** Eigentuemer:Gruppe einer Datei, soweit das System es verraet. */
function besitzer(string $pfad): string
{
    $u = function_exists('posix_getpwuid') ? @posix_getpwuid((int)@fileowner($pfad)) : null;
    $g = function_exists('posix_getgrgid') ? @posix_getgrgid((int)@filegroup($pfad)) : null;
    return ($u['name'] ?? (string)@fileowner($pfad)) . ':' . ($g['name'] ?? (string)@filegroup($pfad));
}

/** Unter welchem Benutzer PHP hier laeuft. */
function php_benutzer(): string
{
    if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
        $u = @posix_getpwuid(posix_geteuid());
        if (!empty($u['name'])) { return $u['name']; }
    }
    return (string)(get_current_user() ?: '?');
}

/** Der Inhalt zwischen den beiden Marken, ohne die umgebenden Zeilenumbrueche. */
function zwischen_marken(string $html): string
{
    $a = strpos($html, MARKE_ANFANG);
    $e = strpos($html, MARKE_ENDE);
    if ($a === false || $e === false || $e < $a) { return ''; }
    $a += strlen(MARKE_ANFANG);
    return trim(substr($html, $a, $e - $a), "\r\n");
}

/**
 * Schreibt den Kartenblock in die oeffentliche projekte.html.
 * Der Rest der Datei bleibt unangetastet.
 *
 * @return string Leer = alles gut, sonst die Fehlermeldung
 */
function seite_bauen(array $projekte): string
{
    if (!is_readable(SEITE)) { return 'projekte.html wurde nicht gefunden.'; }
    $html = (string)file_get_contents(SEITE);

    $a = strpos($html, MARKE_ANFANG);
    $e = strpos($html, MARKE_ENDE);
    if ($a === false || $e === false || $e < $a) {
        return 'In projekte.html fehlen die Marken PROJEKTE:ANFANG / PROJEKTE:ENDE. '
            . 'Ohne sie weiß ich nicht, welche Stelle ich ersetzen darf.';
    }
    // Kein frueher Abbruch bei fehlendem Schreibrecht auf der Datei:
    // schreiben_sicher() legt eine Nebendatei an und benennt sie um. Dafuer
    // genuegt Schreibrecht am ORDNER — und das ist auf Plesk der Normalfall,
    // selbst wenn die Datei einem anderen Benutzer gehoert.
    if (!is_writable(SEITE) && !is_writable(dirname(SEITE))) {
        return 'Weder projekte.html noch der Ordner httpdocs sind für PHP beschreibbar. '
            . 'Datei gehört ' . besitzer(SEITE) . ', Ordner gehört ' . besitzer(dirname(SEITE))
            . ', PHP läuft als ' . php_benutzer() . '. Siehe „Prüfen“.';
    }

    @copy(SEITE, pfad_sicherung());        // eine Fassung zurueck, falls etwas schiefgeht

    $neu = substr($html, 0, $a + strlen(MARKE_ANFANG)) . "\n"
        . karten_html($projekte) . "\n"
        . substr($html, $e);

    // 0644: projekte.html wird vom Webserver ausgeliefert und muss lesbar bleiben
    if (schreiben_sicher(SEITE, $neu, 0644)) {
        // Guertel und Hosenträger: manche Server setzen eine umask, die das
        // Leserecht fuer "andere" wieder wegnimmt — dann liefert der Webserver
        // 403 Forbidden aus. Also nachfassen und das Ergebnis pruefen.
        if (!oeffentlich_lesbar(SEITE)) {
            @chmod(SEITE, 0644);
            clearstatcache(true, SEITE);
        }
        if (!oeffentlich_lesbar(SEITE)) {
            return 'Gespeichert, aber projekte.html steht auf Rechten '
                . substr(sprintf('%o', fileperms(SEITE)), -3)
                . ' — der Webserver darf sie damit nicht lesen und zeigt 403 Forbidden. '
                . 'Bitte einmal: chmod 644 httpdocs/projekte.html';
        }
        return '';
    }

    return 'projekte.html ließ sich nicht schreiben. Datei gehört ' . besitzer(SEITE)
        . ', Ordner gehört ' . besitzer(dirname(SEITE)) . ', PHP läuft als ' . php_benutzer()
        . '. Abhilfe: chmod 644 projekte.html und Eigentümer wie beim Ordner setzen.';
}
