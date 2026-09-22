<?php
/**
 * aktuelles.php — Adminbereich: „Aktuelles aus dem Spektrum“ auf der Startseite.
 *
 * Aufruf:  https://spektrum-nachhilfe.de/admin/aktuelles.php
 * Anmeldung wie bei den Projekten (gleiche Sitzung, gleiches Passwort).
 *
 * Nach jedem Speichern passiert zweierlei:
 *  1. In index.html wird der Block zwischen den AKTUELLES-Marken neu geschrieben.
 *  2. Eine öffentliche Kopie der sichtbaren Meldungen landet unter
 *     assets/projekte/aktuelles.json. Dieser Ordner ist vom Deploy
 *     ausgenommen. main.js liest die Datei — so zeigt die Startseite den
 *     Stand vom Server auch dann, wenn index.html beim nächsten Deploy
 *     wieder aus dem Repo kommt.
 */

declare(strict_types=1);
require __DIR__ . '/kern.php';

sitzung_starten();
kopfzeilen();

if (!zugang_vorhanden()) { header('Location: einrichten.php'); exit; }
if (!angemeldet()) { header('Location: index.php'); exit; }

const N_TITEL = 90;
const N_DATUM = 60;
const N_TEXT  = 280;

const N_MARKE_ANFANG = '<!-- AKTUELLES:ANFANG - nicht entfernen, der Adminbereich schreibt hier hinein -->';
const N_MARKE_ENDE   = '<!-- AKTUELLES:ENDE -->';

define('STARTSEITE', WEB . '/index.html');
define('N_OEFFENTLICH', BILDER . '/aktuelles.json');

function pfad_aktuelles(): string { return daten_ordner() . '/aktuelles.json'; }

// ---------------------------------------------------------------- Daten
function meldungen_laden(): array
{
    $datei = is_file(pfad_aktuelles()) ? pfad_aktuelles() : __DIR__ . '/start-aktuelles.json';
    $roh = @json_decode((string)@file_get_contents($datei), true);
    $liste = is_array($roh['meldungen'] ?? null) ? $roh['meldungen'] : [];
    $sauber = [];
    foreach ($liste as $m) {
        if (!is_array($m) || empty($m['titel'])) { continue; }
        $sauber[] = [
            'id'       => (string)($m['id'] ?? neue_id()),
            'titel'    => (string)$m['titel'],
            'datum'    => (string)($m['datum'] ?? ''),
            'text'     => (string)($m['text'] ?? ''),
            'link'     => (string)($m['link'] ?? ''),
            'bild'     => (string)($m['bild'] ?? ''),
            'sichtbar' => !empty($m['sichtbar']),
        ];
    }
    return $sauber;
}

function meldungen_speichern(array $meldungen): bool
{
    $inhalt = json_encode(
        ['version' => 1, 'stand' => date('c'), 'meldungen' => array_values($meldungen)],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    return schreiben_sicher(pfad_aktuelles(), (string)$inhalt);
}

// ---------------------------------------------------------------- Ausgabe
// Muss zeichengleich zu aktuelles_daten.py im Baukasten sein.
function n_adresse(string $pfad): string
{
    return $pfad;   // relativ wie überall auf der Website; index.html liegt im Wurzelordner
}

function n_karte(array $m): string
{
    $k = '<article class="karte karte--projekt karte--neu" id="n-' . h($m['id']) . '">';
    if ($m['bild'] !== '') {
        $k .= '<div class="karte__bild"><img src="' . h(n_adresse($m['bild'])) . '" alt="" '
            . 'width="800" height="500" loading="lazy" decoding="async"></div>';
    } else {
        $k .= '<div class="karte__bild karte__bild--leer" aria-hidden="true"></div>';
    }
    $k .= '<div class="karte__koerper">';
    if ($m['datum'] !== '') {
        $k .= '<p class="karte__zeit">' . h($m['datum']) . '</p>';
    }
    $titel = h($m['titel']);
    $k .= $m['link'] !== ''
        ? '<h3><a class="karte__flaeche" href="' . h(n_adresse($m['link'])) . '">' . $titel . '</a></h3>'
        : '<h3>' . $titel . '</h3>';
    $k .= '<p class="karte__text">' . h($m['text']) . '</p>';
    if ($m['link'] !== '') {
        $k .= '<p class="karte__mehr" aria-hidden="true">Weiterlesen</p>';
    }
    return $k . '</div></article>';
}

function aktuelles_block(array $meldungen): string
{
    $sichtbar = array_values(array_filter($meldungen, static fn($m) => !empty($m['sichtbar'])));
    if (!$sichtbar) {
        return '      <p class="hinweis">Zurzeit gibt es keine neuen Meldungen.</p>';
    }
    $zeilen = ['      <div class="aktuelles__band" tabindex="0" aria-label="Meldungen, seitlich blätterbar">'];
    foreach ($sichtbar as $m) { $zeilen[] = '        ' . n_karte($m); }
    $zeilen[] = '      </div>';
    return implode("\n", $zeilen);
}

function n_zwischen_marken(string $html): string
{
    $a = strpos($html, N_MARKE_ANFANG);
    $e = strpos($html, N_MARKE_ENDE);
    if ($a === false || $e === false || $e < $a) { return ''; }
    $a += strlen(N_MARKE_ANFANG);
    // Die Endmarke steht eingerückt — die Einrückung davor gehört nicht zum Block
    return trim(rtrim(substr($html, $a, $e - $a), ' '), "\r\n");
}

/**
 * Schreibt die öffentliche JSON-Kopie und den Block in index.html.
 * @return string Leer = alles gut, sonst die Fehlermeldung
 */
function startseite_bauen(array $meldungen): string
{
    $fehler = [];

    // 1. Öffentliche Kopie — nur sichtbare Meldungen, nur die nötigen Felder
    $oeffentlich = [];
    foreach ($meldungen as $m) {
        if (empty($m['sichtbar'])) { continue; }
        $oeffentlich[] = ['titel' => $m['titel'], 'datum' => $m['datum'], 'text' => $m['text'],
                          'link' => $m['link'], 'bild' => $m['bild']];
    }
    if (!is_dir(BILDER)) { @mkdir(BILDER, 0755, true); }
    $json = (string)json_encode(['stand' => date('c'), 'meldungen' => $oeffentlich],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!schreiben_sicher(N_OEFFENTLICH, $json, 0644)) {
        $fehler[] = 'assets/projekte/aktuelles.json ließ sich nicht schreiben';
    } elseif (!oeffentlich_lesbar(N_OEFFENTLICH)) {
        @chmod(N_OEFFENTLICH, 0644);
    }

    // 2. index.html
    if (!is_readable(STARTSEITE)) {
        $fehler[] = 'index.html wurde nicht gefunden';
    } else {
        $html = (string)file_get_contents(STARTSEITE);
        $a = strpos($html, N_MARKE_ANFANG);
        $e = strpos($html, N_MARKE_ENDE);
        if ($a === false || $e === false || $e < $a) {
            $fehler[] = 'in index.html fehlen die AKTUELLES-Marken — bitte die aktuelle index.html hochladen';
        } else {
            $neu = substr($html, 0, $a + strlen(N_MARKE_ANFANG)) . "\n"
                . aktuelles_block($meldungen) . "\n    "
                . substr($html, $e);
            if ($neu !== $html) {
                @copy(STARTSEITE, daten_ordner() . '/index-sicherung.html');
                if (!schreiben_sicher(STARTSEITE, $neu, 0644)) {
                    $fehler[] = 'index.html ließ sich nicht schreiben (Datei gehört ' . besitzer(STARTSEITE)
                        . ', PHP läuft als ' . php_benutzer() . ')';
                } elseif (!oeffentlich_lesbar(STARTSEITE)) {
                    @chmod(STARTSEITE, 0644);
                }
            }
        }
    }
    return implode('; ', $fehler);
}

/** Bilder, die niemand mehr braucht, entfernen — aber nie eines, das ein Projekt zeigt. */
function bilder_aufraeumen(array $vorher, array $nachher): void
{
    $belegt = [];
    foreach ($nachher as $m) { if ($m['bild'] !== '') { $belegt[$m['bild']] = true; } }
    foreach (projekte_laden() as $p) {
        if ($p['bild'] !== '') { $belegt[$p['bild']] = true; }
        foreach ($p['bilder'] as $b) { $belegt[$b['datei']] = true; }
    }
    // Doppelt hält besser: auch was projekte.html gerade zeigt, bleibt liegen
    $projektseite = is_readable(SEITE) ? (string)file_get_contents(SEITE) : '';
    foreach ($vorher as $m) {
        $pfad = $m['bild'];
        if ($pfad === '' || isset($belegt[$pfad])) { continue; }
        if ($projektseite !== '' && strpos($projektseite, $pfad) !== false) { continue; }
        if (preg_match('~^assets/projekte/[pg]-[0-9a-z\-]+\.jpg$~', $pfad)) {
            @unlink(WEB . '/' . $pfad);
        }
    }
}

// ---------------------------------------------------------------- POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!token_pruefen()) {
        $_SESSION['nachricht'] = ['Das Formular war abgelaufen. Bitte den Vorgang wiederholen.', 'schlecht'];
    } else {
        [$text, $art] = verarbeiten((string)($_POST['tat'] ?? ''));
        if ($text !== '') { $_SESSION['nachricht'] = [$text, $art]; }
    }
    header('Location: aktuelles.php');
    exit;
}

function verarbeiten(string $tat): array
{
    $meldungen = meldungen_laden();
    $vorher = $meldungen;
    $id = (string)($_POST['id'] ?? '');
    $stelle = null;
    foreach ($meldungen as $i => $m) {
        if ($m['id'] === $id) { $stelle = $i; break; }
    }

    switch ($tat) {
        case 'abmelden':
            abmelden();
            return ['', 'gut'];

        case 'speichern':
            $titel = saeubern((string)($_POST['titel'] ?? ''), N_TITEL);
            if ($titel === '') { return ['Ohne Titel geht es nicht.', 'schlecht']; }
            $eintrag = [
                'id'       => $stelle !== null ? $meldungen[$stelle]['id'] : neue_id(),
                'titel'    => $titel,
                'datum'    => saeubern((string)($_POST['datum'] ?? ''), N_DATUM),
                'text'     => str_replace("\n", ' ', saeubern((string)($_POST['text'] ?? ''), N_TEXT)),
                'link'     => link_pruefen((string)($_POST['link'] ?? '')),
                'bild'     => $stelle !== null ? $meldungen[$stelle]['bild'] : '',
                'sichtbar' => !empty($_POST['sichtbar']),
            ];
            $linkroh = trim((string)($_POST['link'] ?? ''));
            $hinweis = ($linkroh !== '' && $eintrag['link'] === '')
                ? ' Der Link wurde verworfen: erlaubt sind seite.html, seite.html#anker, https://…, mailto: und tel:.'
                : '';
            if (!empty($_POST['bild_weg'])) { $eintrag['bild'] = ''; }
            if (!empty($_FILES['bild'])) {
                [$neuesBild, $fehler] = bild_annehmen($_FILES['bild']);
                if ($fehler !== '') { return [$fehler, 'schlecht']; }
                if ($neuesBild !== '') { $eintrag['bild'] = $neuesBild; }
            }
            if ($eintrag['sichtbar'] && $eintrag['bild'] === '') {
                $hinweis .= ' Tipp: Mit einem Bild wirkt die Meldung auf der Startseite deutlich besser.';
            }
            if ($stelle !== null) { $meldungen[$stelle] = $eintrag; }
            else { array_unshift($meldungen, $eintrag); }     // Neues steht vorn
            return abschliessen($vorher, $meldungen,
                ($stelle !== null ? 'Meldung geändert.' : 'Meldung angelegt.') . $hinweis);

        case 'loeschen':
            if ($stelle === null) { return ['Meldung nicht gefunden.', 'schlecht']; }
            $name = $meldungen[$stelle]['titel'];
            array_splice($meldungen, $stelle, 1);
            return abschliessen($vorher, $meldungen, '„' . $name . '“ wurde gelöscht.');

        case 'hoch':
        case 'runter':
            if ($stelle === null) { return ['Meldung nicht gefunden.', 'schlecht']; }
            $ziel = $tat === 'hoch' ? $stelle - 1 : $stelle + 1;
            if ($ziel < 0 || $ziel >= count($meldungen)) { return ['', 'gut']; }
            [$meldungen[$stelle], $meldungen[$ziel]] = [$meldungen[$ziel], $meldungen[$stelle]];
            return abschliessen($vorher, $meldungen, 'Reihenfolge geändert.');

        case 'sichtbar':
            if ($stelle === null) { return ['Meldung nicht gefunden.', 'schlecht']; }
            $meldungen[$stelle]['sichtbar'] = !$meldungen[$stelle]['sichtbar'];
            return abschliessen($vorher, $meldungen, $meldungen[$stelle]['sichtbar']
                ? 'Meldung steht jetzt auf der Startseite.'
                : 'Meldung ist jetzt ein Entwurf und steht nicht mehr auf der Startseite.');

        case 'neubau':
            $fehler = startseite_bauen($meldungen);
            return $fehler === '' ? ['Startseite wurde neu geschrieben.', 'gut'] : [$fehler, 'schlecht'];
    }
    return ['Unbekannter Vorgang.', 'schlecht'];
}

function abschliessen(array $vorher, array $meldungen, string $erfolg): array
{
    if (!meldungen_speichern($meldungen)) {
        return ['Die Datei aktuelles.json ließ sich nicht schreiben. Bitte die Schreibrechte des Ordners '
            . daten_ordner() . ' prüfen.', 'schlecht'];
    }
    bilder_aufraeumen($vorher, $meldungen);
    $fehler = startseite_bauen($meldungen);
    return $fehler === ''
        ? [$erfolg . ' Die Startseite ist aktualisiert.', 'gut']
        : ['Gespeichert — aber: ' . $fehler . '.', 'schlecht'];
}

// ---------------------------------------------------------------- Ansicht
$meldung = '';
$art = 'gut';
if (!empty($_SESSION['nachricht'])) {
    [$meldung, $art] = $_SESSION['nachricht'];
    unset($_SESSION['nachricht']);
}

$meldungen = meldungen_laden();
$ansicht = (string)($_GET['ansicht'] ?? 'liste');
$aktuell = null;
if (in_array($ansicht, ['bearbeiten', 'loeschen'], true)) {
    foreach ($meldungen as $m) {
        if ($m['id'] === (string)($_GET['id'] ?? '')) { $aktuell = $m; break; }
    }
    if ($aktuell === null) { $ansicht = 'liste'; }
}
$ohneDatei = !is_file(pfad_aktuelles());
$startHtml = is_readable(STARTSEITE) ? (string)file_get_contents(STARTSEITE) : '';
$aufStand = $startHtml !== '' && n_zwischen_marken($startHtml) === aktuelles_block($meldungen);

?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Aktuelles verwalten – Spektrum</title>
<link rel="stylesheet" href="admin.css">
<link rel="icon" href="../favicon.ico" sizes="any">
</head>
<body>

  <header class="kopf">
    <div class="kopf__innen">
      <strong>Aktuelles verwalten</strong>
      <nav>
        <a href="index.php">Projekte</a>
        <a href="../index.html#aktuelles" target="_blank" rel="noopener">Startseite ansehen</a>
        <form method="post" class="inline">
          <input type="hidden" name="token" value="<?= h(token()) ?>">
          <button type="submit" name="tat" value="neubau" class="knopf knopf--leise" title="Schreibt den Block auf der Startseite aus den gespeicherten Daten neu">Startseite neu schreiben</button>
          <button type="submit" name="tat" value="abmelden" class="knopf knopf--leise">Abmelden</button>
        </form>
      </nav>
    </div>
  </header>

  <main class="huelle">

  <?php if ($meldung !== ''): ?>
    <p class="meldung meldung--<?= h($art) ?>"><?= h($meldung) ?></p>
  <?php endif; ?>

  <?php if ($ansicht === 'neu' || $ansicht === 'bearbeiten'): ?>

    <h1><?= $aktuell ? 'Meldung bearbeiten' : 'Neue Meldung' ?></h1>
    <form method="post" enctype="multipart/form-data" class="blatt">
      <input type="hidden" name="tat" value="speichern">
      <input type="hidden" name="token" value="<?= h(token()) ?>">
      <?php if ($aktuell): ?>
        <input type="hidden" name="id" value="<?= h($aktuell['id']) ?>">
      <?php endif; ?>

      <p class="feld">
        <label for="titel">Titel <span class="pflicht">*</span></label>
        <input type="text" id="titel" name="titel" maxlength="<?= N_TITEL ?>" required
               value="<?= h($aktuell['titel'] ?? '') ?>">
      </p>

      <p class="feld">
        <label for="datum">Datum oder Zeitraum</label>
        <input type="text" id="datum" name="datum" maxlength="<?= N_DATUM ?>"
               value="<?= h($aktuell['datum'] ?? '') ?>" placeholder="z. B. 20. Juni 2026">
        <span class="tipp">Kleine Zeile über dem Titel.</span>
      </p>

      <p class="feld">
        <label for="text">Kurztext</label>
        <textarea id="text" name="text" rows="4" maxlength="<?= N_TEXT ?>"><?= h($aktuell['text'] ?? '') ?></textarea>
        <span class="tipp">Zwei, drei Sätze, bis <?= N_TEXT ?> Zeichen. Reiner Text. Auf der Karte stehen
          höchstens vier Zeilen — was länger ist, gehört auf die verlinkte Seite.</span>
      </p>

      <p class="feld">
        <label for="link">Link „Weiterlesen“</label>
        <input type="text" id="link" name="link" maxlength="<?= MAX_LINK ?>"
               value="<?= h($aktuell['link'] ?? '') ?>" placeholder="projekte.html oder https://…">
        <span class="tipp">Macht die ganze Karte anklickbar. Erlaubt: eine Seite der Website
          (<code>projekte.html</code>), ein Projektfenster (<code>projekte.html#p-…</code> — die Kennung
          steht in der Adresszeile, wenn das Projekt geöffnet ist), <code>https://…</code>,
          <code>mailto:</code> oder <code>tel:</code>.</span>
      </p>

      <div class="feld">
        <label for="bild">Bild</label>
        <?php if (!empty($aktuell['bild'])): ?>
          <span class="vorschau">
            <img src="../<?= h($aktuell['bild']) ?>" alt="" width="240" height="150">
          </span>
          <label class="kasten"><input type="checkbox" name="bild_weg" value="1"> Bild entfernen</label>
        <?php endif; ?>
        <input type="file" id="bild" name="bild" accept="image/jpeg,image/png,image/webp">
        <span class="tipp">JPEG, PNG oder WebP, bis <?= str_replace('.', ',', (string)max_upload_mb()) ?> MB.
          Wird auf <?= BILD_BREITE ?> × <?= BILD_HOEHE ?> px zugeschnitten (mittiger Ausschnitt) —
          wichtige Motive also nicht an den Rand setzen. Ein neues Bild ersetzt das alte.</span>
      </div>

      <p class="feld">
        <label class="kasten">
          <input type="checkbox" name="sichtbar" value="1" <?= (!$aktuell || $aktuell['sichtbar']) ? 'checked' : '' ?>>
          Auf der Startseite zeigen
        </label>
        <span class="tipp">Ohne Haken bleibt die Meldung ein Entwurf.</span>
      </p>

      <p class="aktion">
        <button type="submit" class="knopf">Speichern</button>
        <a class="knopf knopf--leise" href="aktuelles.php">Abbrechen</a>
      </p>
    </form>

  <?php elseif ($ansicht === 'loeschen'): ?>

    <h1>Meldung löschen</h1>
    <p>Soll „<strong><?= h($aktuell['titel']) ?></strong>“ wirklich gelöscht werden?
       Ein nur hier verwendetes Bild wird mitgelöscht; Bilder, die ein Projekt zeigt, bleiben.
       Das lässt sich nicht rückgängig machen.</p>
    <form method="post">
      <input type="hidden" name="tat" value="loeschen">
      <input type="hidden" name="token" value="<?= h(token()) ?>">
      <input type="hidden" name="id" value="<?= h($aktuell['id']) ?>">
      <p class="aktion">
        <button type="submit" class="knopf knopf--gefahr">Endgültig löschen</button>
        <a class="knopf knopf--leise" href="aktuelles.php">Abbrechen</a>
      </p>
    </form>

  <?php else: ?>

    <div class="titelzeile">
      <h1>Aktuelles <span class="zahl"><?= count($meldungen) ?></span></h1>
      <a class="knopf" href="aktuelles.php?ansicht=neu">Neue Meldung</a>
    </div>

    <?php if ($ohneDatei): ?>
      <p class="meldung meldung--warnung">Hier steht noch der Startbestand. Mit dem ersten Speichern
         wird er übernommen — die drei Entwürfe unten warten auf Text und Bild.</p>
    <?php elseif (!$aufStand): ?>
      <p class="meldung meldung--warnung">Die index.html auf dem Server zeigt nicht den gespeicherten
         Stand — meist nach einem Deploy. Besucher sehen trotzdem das Richtige, weil die Startseite die
         Meldungen nachlädt. Für Suchmaschinen einmal auf „Startseite neu schreiben“ klicken.</p>
    <?php endif; ?>

    <?php if (!$meldungen): ?>
      <p class="leer">Noch keine Meldung. Mit „Neue Meldung“ geht es los.</p>
    <?php else: ?>
      <ul class="liste">
        <?php foreach ($meldungen as $i => $m): ?>
          <li class="zeile<?= $m['sichtbar'] ? '' : ' zeile--entwurf' ?>">
            <span class="zeile__bild">
              <?php if ($m['bild'] !== ''): ?>
                <img src="../<?= h($m['bild']) ?>" alt="" width="96" height="60">
              <?php else: ?>
                <span class="ohnebild" aria-hidden="true"></span>
              <?php endif; ?>
            </span>
            <span class="zeile__text">
              <?php if ($m['datum'] !== ''): ?>
                <span class="zeile__zeit"><?= h($m['datum']) ?></span>
              <?php endif; ?>
              <span class="zeile__titel">
                <strong><?= h($m['titel']) ?></strong>
                <?php if (!$m['sichtbar']): ?><span class="marke">Entwurf</span><?php endif; ?>
              </span>
              <span class="zeile__kurz"><?= h(mb_strimwidth($m['text'] !== '' ? $m['text'] : '(noch kein Text)', 0, 110, '…')) ?></span>
            </span>
            <form method="post" class="zeile__knoepfe">
              <input type="hidden" name="token" value="<?= h(token()) ?>">
              <input type="hidden" name="id" value="<?= h($m['id']) ?>">
              <button type="submit" name="tat" value="hoch" class="klein" title="Nach vorn"
                      <?= $i === 0 ? 'disabled' : '' ?>>&uarr;</button>
              <button type="submit" name="tat" value="runter" class="klein" title="Nach hinten"
                      <?= $i === count($meldungen) - 1 ? 'disabled' : '' ?>>&darr;</button>
              <button type="submit" name="tat" value="sichtbar" class="klein"
                      title="<?= $m['sichtbar'] ? 'Auf Entwurf setzen' : 'Auf der Startseite zeigen' ?>">
                <?= $m['sichtbar'] ? 'Verstecken' : 'Zeigen' ?>
              </button>
              <a class="klein" href="aktuelles.php?ansicht=bearbeiten&amp;id=<?= h(rawurlencode($m['id'])) ?>">Bearbeiten</a>
              <a class="klein klein--gefahr" href="aktuelles.php?ansicht=loeschen&amp;id=<?= h(rawurlencode($m['id'])) ?>">Löschen</a>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="hinweis">Die Reihenfolge hier ist die Reihenfolge auf der Startseite — die erste Meldung
         steht ganz links. Neue Meldungen kommen automatisch nach vorn.
         Jede Änderung wird sofort veröffentlicht.</p>
    <?php endif; ?>

  <?php endif; ?>

  </main>

</body>
</html>
