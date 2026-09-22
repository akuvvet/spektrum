<?php
/**
 * index.php — Adminbereich: Projekte anlegen, ändern, löschen, sortieren.
 *
 * Aufruf:  https://spektrum-nachhilfe.de/admin/
 * Zugang:  Passwort, einmalig gesetzt über einrichten.php
 *
 * Nach jedem Speichern wird projekte.html neu geschrieben — die öffentliche
 * Seite bleibt also reines HTML und lädt so schnell wie bisher.
 */

declare(strict_types=1);
require __DIR__ . '/kern.php';

sitzung_starten();
kopfzeilen();

$meldung = '';
$art = 'gut';

// ---------------------------------------------------------------- Einrichtung fehlt
if (!zugang_vorhanden()) {
    header('Location: einrichten.php');
    exit;
}

// ---------------------------------------------------------------- POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tat = (string)($_POST['tat'] ?? '');

    if ($tat === 'anmelden') {
        $rest = gesperrt();
        if ($rest > 0) {
            $meldung = 'Zu viele Fehlversuche. Bitte in ' . ceil($rest / 60) . ' Minuten erneut versuchen.';
            $art = 'schlecht';
        } elseif (!token_pruefen()) {
            $meldung = 'Das Formular war abgelaufen. Bitte noch einmal.';
            $art = 'schlecht';
        } elseif (passwort_pruefen((string)($_POST['passwort'] ?? ''))) {
            versuche_loeschen();
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            $_SESSION['zuletzt'] = time();
            header('Location: index.php');
            exit;
        } else {
            fehlversuch_merken();
            usleep(400000);
            $meldung = 'Passwort falsch.';
            $art = 'schlecht';
        }
    } elseif (!angemeldet()) {
        $meldung = 'Die Sitzung ist abgelaufen. Bitte neu anmelden.';
        $art = 'schlecht';
    } elseif (!token_pruefen()) {
        $meldung = 'Das Formular war abgelaufen. Bitte den Vorgang wiederholen.';
        $art = 'schlecht';
    } else {
        [$meldung, $art] = verarbeiten($tat);
        if ($meldung !== '') {
            $_SESSION['nachricht'] = [$meldung, $art];
        }
        header('Location: index.php');
        exit;
    }
}

if (!empty($_SESSION['nachricht'])) {
    [$meldung, $art] = $_SESSION['nachricht'];
    unset($_SESSION['nachricht']);
}

/**
 * Alle ändernden Vorgänge. Rückgabe: [Meldung, gut|schlecht].
 */
function verarbeiten(string $tat): array
{
    $projekte = projekte_laden();
    $id = (string)($_POST['id'] ?? '');
    $stelle = null;
    foreach ($projekte as $i => $p) {
        if ($p['id'] === $id) { $stelle = $i; break; }
    }

    switch ($tat) {
        case 'abmelden':
            abmelden();
            return ['', 'gut'];

        case 'speichern':
            $titel = saeubern((string)($_POST['titel'] ?? ''), MAX_TITEL);
            if ($titel === '') { return ['Ohne Titel geht es nicht.', 'schlecht']; }

            $eintrag = [
                'id'       => $stelle !== null ? $projekte[$stelle]['id'] : neue_id(),
                'titel'    => $titel,
                'zeitraum' => saeubern((string)($_POST['zeitraum'] ?? ''), MAX_ZEITRAUM),
                'text'     => saeubern((string)($_POST['text'] ?? ''), MAX_TEXT),
                'detail'   => saeubern((string)($_POST['detail'] ?? ''), MAX_DETAIL),
                'link'     => link_pruefen((string)($_POST['link'] ?? '')),
                'bild'     => $stelle !== null ? $projekte[$stelle]['bild'] : '',
                'bilder'   => $stelle !== null ? $projekte[$stelle]['bilder'] : [],
                'sichtbar' => !empty($_POST['sichtbar']),
            ];

            $linkroh = trim((string)($_POST['link'] ?? ''));
            $hinweis = ($linkroh !== '' && $eintrag['link'] === '')
                ? ' Der Link wurde verworfen: erlaubt sind seite.html, https://…, mailto: und tel:.'
                : '';

            if (!empty($_POST['bild_weg'])) {
                bild_loeschen($eintrag['bild']);
                $eintrag['bild'] = '';
            }
            if (!empty($_FILES['bild'])) {
                [$neuesBild, $fehler] = bild_annehmen($_FILES['bild']);
                if ($fehler !== '') { return [$fehler, 'schlecht']; }
                if ($neuesBild !== '') {
                    bild_loeschen($eintrag['bild']);
                    $eintrag['bild'] = $neuesBild;
                }
            }

            // --- Galerie: erst vorhandene Bilder pflegen, dann neue annehmen ---
            $behalten = [];
            foreach ($eintrag['bilder'] as $nr => $b) {
                if (!empty($_POST['bild_raus'][$nr])) {
                    bild_loeschen($b['datei']);
                    continue;
                }
                $b['text'] = saeubern((string)($_POST['bildtext'][$nr] ?? ''), 160);
                $behalten[] = $b;
            }
            $eintrag['bilder'] = $behalten;

            $neue = $_FILES['galerie'] ?? null;
            if (is_array($neue) && is_array($neue['name'] ?? null)) {
                foreach (array_keys($neue['name']) as $i) {
                    if (count($eintrag['bilder']) >= MAX_BILDER) {
                        $hinweis .= ' Mehr als ' . MAX_BILDER . ' Bilder je Projekt gehen nicht — '
                            . 'die überzähligen wurden nicht übernommen.';
                        break;
                    }
                    $eine = [
                        'name'     => $neue['name'][$i],
                        'type'     => $neue['type'][$i],
                        'tmp_name' => $neue['tmp_name'][$i],
                        'error'    => $neue['error'][$i],
                        'size'     => $neue['size'][$i],
                    ];
                    [$pfad, $fehler] = bild_annehmen($eine, 'galerie');
                    if ($fehler !== '') { return [$fehler, 'schlecht']; }
                    if ($pfad !== '') { $eintrag['bilder'][] = ['datei' => $pfad, 'text' => '']; }
                }
            }

            if ($stelle !== null) { $projekte[$stelle] = $eintrag; }
            else { $projekte[] = $eintrag; }

            return abschliessen($projekte,
                ($stelle !== null ? 'Projekt geändert.' : 'Projekt angelegt.') . $hinweis);

        case 'loeschen':
            if ($stelle === null) { return ['Projekt nicht gefunden.', 'schlecht']; }
            bild_loeschen($projekte[$stelle]['bild']);
            foreach ($projekte[$stelle]['bilder'] as $b) { bild_loeschen($b['datei']); }
            $name = $projekte[$stelle]['titel'];
            array_splice($projekte, $stelle, 1);
            return abschliessen($projekte, '„' . $name . '“ wurde gelöscht.');

        case 'hoch':
        case 'runter':
            if ($stelle === null) { return ['Projekt nicht gefunden.', 'schlecht']; }
            $ziel = $tat === 'hoch' ? $stelle - 1 : $stelle + 1;
            if ($ziel < 0 || $ziel >= count($projekte)) { return ['', 'gut']; }
            [$projekte[$stelle], $projekte[$ziel]] = [$projekte[$ziel], $projekte[$stelle]];
            return abschliessen($projekte, 'Reihenfolge geändert.');

        case 'sichtbar':
            if ($stelle === null) { return ['Projekt nicht gefunden.', 'schlecht']; }
            $projekte[$stelle]['sichtbar'] = !$projekte[$stelle]['sichtbar'];
            return abschliessen($projekte, $projekte[$stelle]['sichtbar']
                ? 'Projekt ist jetzt auf der Website sichtbar.'
                : 'Projekt ist jetzt ein Entwurf und steht nicht mehr auf der Website.');

        case 'neubau':
            $fehler = seite_bauen($projekte);
            return $fehler === ''
                ? ['projekte.html wurde neu geschrieben.', 'gut']
                : [$fehler, 'schlecht'];

        case 'testmail':
            [$ok, $text] = testmail_senden();
            return [$text, $ok ? 'gut' : 'schlecht'];
    }
    return ['Unbekannter Vorgang.', 'schlecht'];
}

/** Speichern und die öffentliche Seite neu schreiben. */
function abschliessen(array $projekte, string $erfolg): array
{
    if (!projekte_speichern($projekte)) {
        return ['Die Datei projekte.json ließ sich nicht schreiben. Bitte die Schreibrechte '
            . 'des Ordners ' . h(daten_ordner()) . ' prüfen.', 'schlecht'];
    }
    $fehler = seite_bauen($projekte);
    return $fehler === ''
        ? [$erfolg . ' Die Website ist aktualisiert.', 'gut']
        : ['Gespeichert — aber die Website wurde nicht neu geschrieben: ' . $fehler, 'schlecht'];
}

// ---------------------------------------------------------------- Ansicht
$ansicht = (string)($_GET['ansicht'] ?? 'liste');
$projekte = angemeldet() ? projekte_laden() : [];
$aktuell = null;
if (in_array($ansicht, ['bearbeiten', 'loeschen'], true)) {
    foreach ($projekte as $p) {
        if ($p['id'] === (string)($_GET['id'] ?? '')) { $aktuell = $p; break; }
    }
    if ($aktuell === null) { $ansicht = 'liste'; }
}

?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Projekte verwalten – Spektrum</title>
<link rel="stylesheet" href="admin.css">
<link rel="icon" href="../favicon.ico" sizes="any">
</head>
<body>

<?php if (!angemeldet()): ?>

  <main class="anmeldung">
    <h1>Projekte verwalten</h1>
    <p class="unter">Spektrum Bildungszentrum Solingen e.V.</p>
    <?php if ($meldung !== ''): ?>
      <p class="meldung meldung--<?= h($art) ?>"><?= h($meldung) ?></p>
    <?php endif; ?>
    <form method="post" autocomplete="off">
      <input type="hidden" name="tat" value="anmelden">
      <input type="hidden" name="token" value="<?= h(token()) ?>">
      <label for="passwort">Passwort</label>
      <input type="password" id="passwort" name="passwort" required autofocus autocomplete="current-password">
      <button type="submit" class="knopf">Anmelden</button>
    </form>
    <p class="fusszeile"><a href="../index.html">Zurück zur Website</a></p>
  </main>

<?php else: ?>

  <header class="kopf">
    <div class="kopf__innen">
      <strong>Projekte verwalten</strong>
      <nav>
        <a href="aktuelles.php">Aktuelles</a>
        <a href="../projekte.html" target="_blank" rel="noopener">Seite ansehen</a>
        <a href="index.php?ansicht=pruefen">Prüfen</a>
        <form method="post" class="inline">
          <input type="hidden" name="token" value="<?= h(token()) ?>">
          <button type="submit" name="tat" value="neubau" class="knopf knopf--leise" title="Schreibt projekte.html aus den gespeicherten Daten neu">Seite neu schreiben</button>
          <button type="submit" name="tat" value="abmelden" class="knopf knopf--leise">Abmelden</button>
        </form>
      </nav>
    </div>
  </header>

  <main class="huelle">

  <?php if ($meldung !== ''): ?>
    <p class="meldung meldung--<?= h($art) ?>"><?= h($meldung) ?></p>
  <?php endif; ?>

  <?php if (is_file(__DIR__ . '/einrichten.php')): ?>
    <p class="meldung meldung--warnung">
      Die Datei <code>admin/einrichten.php</code> liegt noch auf dem Server.
      Sie wird nicht mehr gebraucht — bitte per FTP löschen.
    </p>
  <?php endif; ?>

  <?php if ($ansicht === 'pruefen'): ?>

    <h1>Prüfen</h1>
    <p>Diese Übersicht beantwortet die Frage, warum ein Eintrag nicht auf der Website steht.</p>
    <table class="pruef">
      <tbody>
      <?php foreach (pruefung($projekte) as [$was, $zustand, $text]): ?>
        <tr class="pruef--<?= h($zustand) ?>">
          <th scope="row"><?= h($was) ?></th>
          <td><span class="ampel" aria-hidden="true"></span><?= h($text) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <div class="aktion">
      <form method="post" class="inline">
        <input type="hidden" name="token" value="<?= h(token()) ?>">
        <button type="submit" name="tat" value="neubau" class="knopf">Seite neu schreiben</button>
      </form>
      <a class="knopf knopf--leise" href="index.php">Zur Liste</a>
    </div>

    <h2>Mailversand der Formulare</h2>
    <p>Kontaktformular, Elternfragebogen, Lehrersuche und Mitgliedsantrag gehen alle
       an dieselbe Adresse. Hier steht, was davon der Server wirklich kann.</p>
    <table class="pruef">
      <tbody>
      <?php foreach (mail_pruefung() as [$was, $zustand, $text]): ?>
        <tr class="pruef--<?= h($zustand) ?>">
          <th scope="row"><?= h($was) ?></th>
          <td><span class="ampel" aria-hidden="true"></span><?= h($text) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <div class="aktion">
      <form method="post" class="inline">
        <input type="hidden" name="token" value="<?= h(token()) ?>">
        <button type="submit" name="tat" value="testmail" class="knopf">Testmail senden</button>
      </form>
    </div>
    <p class="tipp">Die Testmail geht an dieselbe Adresse wie die Formulare.
       Kommt sie an, kommen auch die Anfragen an. Kommt sie nicht an, obwohl der
       Server sie angenommen hat, liegt es am Mailversand des Servers — nicht an
       der Website.</p>

  <?php elseif ($ansicht === 'neu' || $ansicht === 'bearbeiten'): ?>

    <h1><?= $aktuell ? 'Projekt bearbeiten' : 'Neues Projekt' ?></h1>
    <form method="post" enctype="multipart/form-data" class="blatt">
      <input type="hidden" name="tat" value="speichern">
      <input type="hidden" name="token" value="<?= h(token()) ?>">
      <?php if ($aktuell): ?>
        <input type="hidden" name="id" value="<?= h($aktuell['id']) ?>">
      <?php endif; ?>

      <p class="feld">
        <label for="titel">Titel <span class="pflicht">*</span></label>
        <input type="text" id="titel" name="titel" maxlength="<?= MAX_TITEL ?>" required
               value="<?= h($aktuell['titel'] ?? '') ?>">
        <span class="tipp">So steht es als Überschrift auf der Karte.</span>
      </p>

      <p class="feld">
        <label for="zeitraum">Zeitraum</label>
        <input type="text" id="zeitraum" name="zeitraum" maxlength="<?= MAX_ZEITRAUM ?>"
               value="<?= h($aktuell['zeitraum'] ?? '') ?>"
               placeholder="z. B. Osterferien 2026">
        <span class="tipp">Kleine Zeile über dem Titel. Leer lassen, wenn das Projekt dauerhaft läuft.</span>
      </p>

      <p class="feld">
        <label for="text">Beschreibung</label>
        <textarea id="text" name="text" rows="5" maxlength="<?= MAX_TEXT ?>"><?= h($aktuell['text'] ?? '') ?></textarea>
        <span class="tipp">Zwei bis drei Sätze reichen. Reiner Text — HTML wird nicht übernommen.</span>
      </p>

      <p class="feld">
        <label for="link">Link</label>
        <input type="text" id="link" name="link" maxlength="<?= MAX_LINK ?>"
               value="<?= h($aktuell['link'] ?? '') ?>"
               placeholder="kontakt.html oder https://…">
        <span class="tipp">Macht den Titel anklickbar. Erlaubt: eine Seite der Website
          (<code>kontakt.html</code>), <code>https://…</code>, <code>mailto:</code> oder <code>tel:</code>.</span>
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
        <span class="tipp">JPEG, PNG oder WebP, bis <?= str_replace('.', ',', (string)max_upload_mb()) ?> MB
          (Grenze dieses Servers). Wird automatisch auf
          <?= BILD_BREITE ?> × <?= BILD_HOEHE ?> px zugeschnitten (mittiger Ausschnitt) und verkleinert.
          Ein neues Bild ersetzt das alte.</span>
      </div>

      <hr class="trenner">
      <p class="abschnitt">Detailfenster <span>— erscheint, wenn jemand auf die Kachel klickt</span></p>

      <p class="feld">
        <label for="detail">Ausführliche Beschreibung</label>
        <textarea id="detail" name="detail" rows="10" maxlength="<?= MAX_DETAIL ?>"><?= h($aktuell['detail'] ?? '') ?></textarea>
        <span class="tipp">Bis <?= MAX_DETAIL ?> Zeichen. <strong>Eine Leerzeile beginnt einen neuen Absatz.</strong>
          Bleibt das Feld leer, steht im Fenster der Kartentext. Ohne ausführlichen Text, Bilder und Link
          ist die Kachel nicht anklickbar — dann gibt es nichts zu zeigen.</span>
      </p>

      <div class="feld">
        <label>Bilder im Detailfenster</label>
        <?php if (!empty($aktuell['bilder'])): ?>
          <ul class="galerieliste">
            <?php foreach ($aktuell['bilder'] as $nr => $b): ?>
              <li>
                <img src="../<?= h($b['datei']) ?>" alt="" width="120" height="80">
                <span class="galerieliste__felder">
                  <label for="bildtext<?= (int)$nr ?>" class="klein-label">Bildunterschrift</label>
                  <input type="text" id="bildtext<?= (int)$nr ?>" name="bildtext[<?= (int)$nr ?>]"
                         maxlength="160" value="<?= h($b['text']) ?>" placeholder="optional">
                  <label class="kasten">
                    <input type="checkbox" name="bild_raus[<?= (int)$nr ?>]" value="1"> Dieses Bild entfernen
                  </label>
                </span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <input type="file" id="galerie" name="galerie[]" multiple
               accept="image/jpeg,image/png,image/webp">
        <span class="tipp">Mehrere Dateien auf einmal auswählbar, bis <?= MAX_BILDER ?> Bilder je Projekt.
          Diese Bilder werden <strong>nicht</strong> zugeschnitten, nur verkleinert (längste Kante
          <?= GALERIE_KANTE ?> px) — Hoch- und Querformat bleiben also, wie sie sind.
          Im Fenster kann man sie zum Vergrößern anklicken.</span>
      </div>

      <hr class="trenner">

      <p class="feld">
        <label class="kasten">
          <input type="checkbox" name="sichtbar" value="1" <?= (!$aktuell || $aktuell['sichtbar']) ? 'checked' : '' ?>>
          Auf der Website veröffentlichen
        </label>
        <span class="tipp">Ohne Haken bleibt das Projekt ein Entwurf und erscheint nirgends.</span>
      </p>

      <p class="aktion">
        <button type="submit" class="knopf">Speichern</button>
        <a class="knopf knopf--leise" href="index.php">Abbrechen</a>
      </p>
    </form>

  <?php elseif ($ansicht === 'loeschen'): ?>

    <h1>Projekt löschen</h1>
    <p>Soll „<strong><?= h($aktuell['titel']) ?></strong>“ wirklich gelöscht werden?
       <?= $aktuell['bild'] !== '' ? 'Das hochgeladene Bild wird mitgelöscht.' : '' ?>
       Das lässt sich nicht rückgängig machen.</p>
    <form method="post">
      <input type="hidden" name="tat" value="loeschen">
      <input type="hidden" name="token" value="<?= h(token()) ?>">
      <input type="hidden" name="id" value="<?= h($aktuell['id']) ?>">
      <p class="aktion">
        <button type="submit" class="knopf knopf--gefahr">Endgültig löschen</button>
        <a class="knopf knopf--leise" href="index.php">Abbrechen</a>
      </p>
    </form>

  <?php else: ?>

    <div class="titelzeile">
      <h1>Projekte <span class="zahl"><?= count($projekte) ?></span></h1>
      <a class="knopf" href="index.php?ansicht=neu">Neues Projekt</a>
    </div>

    <?php if (!$projekte): ?>
      <p class="leer">Noch kein Projekt angelegt. Mit „Neues Projekt“ geht es los.</p>
    <?php else: ?>
      <ul class="liste">
        <?php foreach ($projekte as $i => $p): ?>
          <li class="zeile<?= $p['sichtbar'] ? '' : ' zeile--entwurf' ?>">
            <span class="zeile__bild">
              <?php if ($p['bild'] !== ''): ?>
                <img src="../<?= h($p['bild']) ?>" alt="" width="96" height="60">
              <?php else: ?>
                <span class="ohnebild" aria-hidden="true"></span>
              <?php endif; ?>
            </span>

            <span class="zeile__text">
              <?php if ($p['zeitraum'] !== ''): ?>
                <span class="zeile__zeit"><?= h($p['zeitraum']) ?></span>
              <?php endif; ?>
              <span class="zeile__titel">
                <strong><?= h($p['titel']) ?></strong>
                <?php if (!$p['sichtbar']): ?><span class="marke">Entwurf</span><?php endif; ?>
                <?php if (hat_fenster($p)): ?>
                  <span class="marke marke--ruhig"><?= count($p['bilder']) ?: '' ?><?= $p['bilder'] ? ' Bilder · ' : '' ?>Details</span>
                <?php endif; ?>
              </span>
              <span class="zeile__kurz"><?= h(mb_strimwidth($p['text'], 0, 110, '…')) ?></span>
            </span>

            <form method="post" class="zeile__knoepfe">
              <input type="hidden" name="token" value="<?= h(token()) ?>">
              <input type="hidden" name="id" value="<?= h($p['id']) ?>">
              <button type="submit" name="tat" value="hoch" class="klein" title="Nach oben"
                      <?= $i === 0 ? 'disabled' : '' ?>>&uarr;</button>
              <button type="submit" name="tat" value="runter" class="klein" title="Nach unten"
                      <?= $i === count($projekte) - 1 ? 'disabled' : '' ?>>&darr;</button>
              <button type="submit" name="tat" value="sichtbar" class="klein"
                      title="<?= $p['sichtbar'] ? 'Auf Entwurf setzen' : 'Veröffentlichen' ?>">
                <?= $p['sichtbar'] ? 'Verstecken' : 'Zeigen' ?>
              </button>
              <a class="klein" href="index.php?ansicht=bearbeiten&amp;id=<?= h(rawurlencode($p['id'])) ?>">Bearbeiten</a>
              <a class="klein klein--gefahr" href="index.php?ansicht=loeschen&amp;id=<?= h(rawurlencode($p['id'])) ?>">Löschen</a>
            </form>
          </li>
        <?php endforeach; ?>
      </ul>
      <p class="hinweis">Die Reihenfolge hier ist die Reihenfolge auf der Website.
         Jede Änderung wird sofort veröffentlicht.</p>
    <?php endif; ?>

  <?php endif; ?>

  </main>

<?php endif; ?>

</body>
</html>
