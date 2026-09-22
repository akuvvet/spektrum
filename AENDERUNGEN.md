# Änderungsübersicht Relaunch

Stand: 15. September 2026. Alle Seiten werden aus den Quellen im Ordner `build/` erzeugt; die Spalte „Quelle“ nennt die Datei, in der eine Änderung künftig vorzunehmen ist.

---

## A — Kritische Fehler

| Datei | Was geändert | Warum | Quelle |
|---|---|---|---|
| `impressum.html` | Interne Redaktionsnotiz ersatzlos entfernt | Stand live auf der Seite und war für Besucher sichtbar | `recht.py` |
| `impressum.html` | Vorstand als sichtbarer Platzhalter statt „H. Turgut“ | § 5 DDG verlangt den ausgeschriebenen Namen; die Abkürzung war abmahnfähig | `recht.py` |
| `impressum.html` | Eintragungsdatum, § 18 MStV, Gemeinnützigkeit, Bildnachweis ergänzt | Vollständigkeit der Anbieterkennzeichnung | `recht.py` |
| alle Seiten | Vereinsname überall auf die Registerfassung vereinheitlicht | Es kursierten vier Varianten, u. a. im Titel der Datenschutzseite | `inhalt.py` (`NAME`) |
| `bildung-und-teilhabe.html` | „Arbeitslosengeld II / Sozialgeld“ → **Grundsicherungsgeld (SGB II)**, mit Hinweis auf die Umbenennung zum 1. Juli 2026 | Rechtslage seit dem 13. SGB-II-Änderungsgesetz | `seiten.py` |
| `bildung-und-teilhabe.html` | Neuer Abschnitt „Die Versetzung muss nicht gefährdet sein“; Einleitung ersetzt | Der alte Satz spiegelte die vor 2019 geltende Rechtslage | `seiten.py` |
| `unterstuetzen.html` | Kontoinhaber „Spektrum e.V.“ → voller Registername; Abschnitte zu Absetzbarkeit und vereinfachtem Nachweis bis 300 € ergänzt | Kontoinhaber passte nicht zum Verein; Nachweis der Gemeinnützigkeit fehlte ganz | `seiten.py` |
| `projekte.html` | Jedes Projekt bekommt Zeitangabe und Status | „Extra-Zeit zum Lernen“ und „Hilfe für die Ukraine“ wirkten wie laufende Angebote | `projekte_daten.py` |
| mehrere | „täglich 15.30–17 Uhr“ → „Montag bis Freitag, 15:30 bis 17:00 Uhr“ + Ferienfeld | „täglich“ war missverständlich | `inhalt.py` (`HAUSAUFGABEN_ZEIT`) |

## B — Rechtliches

| Datei | Was geändert | Warum | Quelle |
|---|---|---|---|
| `ueber-uns.html` | Neuer Abschnitt **Kinderschutz**: erweitertes Führungszeugnis (§ 72a SGB VIII), Schutzkonzept, Ansprechperson, Hilfetelefone | Fehlte vollständig | `seiten.py` |
| `lehrersuche.html` | Pflichtfeld zur Bereitschaft, ein erweitertes Führungszeugnis vorzulegen | Bewerbende sollen das vorher wissen | `formulare.py` |
| `vertragsbedingungen.html` | **Neue Seite**: Vertragsschluss, Umfang, Laufzeit, Kündigung, Stundenausfall, vollständige Widerrufsbelehrung mit Musterformular | § 312g BGB — Verträge kommen fernmündlich zustande | `recht.py` |
| `elternfragebogen.html` | Geburtsjahr des Kindes entfernt; Sprachabfrage ersetzt durch die direkte Frage nach Förderbedarf in Deutsch als Zweitsprache; alle Einschätzungen freiwillig mit „Keine Angabe“; Zweck je Block erklärt; Pflichtfeld Sorgeberechtigung | Datenminimierung nach Art. 5 DSGVO; die Sprachabfrage war ein Näherungswert für die Herkunft | `formulare.py` |
| `lehrersuche.html` | Abfrage „Geburtsjahr“ entfernt; „Deutschniveau“ bleibt, jetzt tätigkeitsbezogen begründet | AGG-Risiko Altersdiskriminierung | `formulare.py` |
| `datenschutz.html` | Neue Abschnitte: E-Mail-Versand, Angaben zum Kind, Bewerbungen, soziale Netzwerke (Meta), Aufbewahrungstabelle; STRATO-Anschrift korrekt gesetzt | Social Media, Löschfristen und Mailanbieter fehlten; die Anschrift hatte fehlerhafte Zeilenumbrüche statt Kommas | `recht.py` |
| `formular.php` | Feld-Allowlist an die neuen Formulare angepasst | Sonst fielen neue Felder aus der Mail und entfernte blieben zulässig | `formular.php` |

## C — Neue Seiten und Abschnitte

| Datei | Was | Quelle |
|---|---|---|
| `online-nachhilfe.html` | **Neu.** Für wen es taugt, wo Präsenz besser ist, Technik, Ablauf, BuT | `seiten.py` |
| `beratung.html` | **Neu.** Lernbegleitung, Orientierung im Schulsystem, Integrationsberatung | `seiten.py` |
| `faq.html` | **Neu.** 14 Fragen als aufklappbare Liste, ohne JavaScript bedienbar | `seiten.py` (`FRAGEN`) |
| `transparenz.html` | **Neu.** Registerdaten, Satzung, Gemeinnützigkeit, Mittelherkunft und -verwendung, Jahresbericht | `seiten.py` |
| `anfahrt.html` | **Neu.** Öffnungszeiten, ÖPNV, Parken, Barrierefreiheit, datenschutzfreundlicher Kartenverweis statt Einbettung | `seiten.py` |
| `404.html` | **Neu.** Echte Fehlerseite statt heimlicher Weiterleitung auf die Startseite | `bauen.py` |
| `index.html` | Abschnitte „Kostenloses Erstgespräch“ und „Was Eltern sagen“ | `seiten.py` |
| `nachhilfe.html` | „Ablauf und Rahmen“ als Tabelle, vierstufige Anmeldung, Einzelunterricht vs. Kleingruppe, Dauer | `seiten.py` |
| `ueber-uns.html` | Abschnitt „Vorstand und Team“ mit Struktur für Namen und Qualifikationen | `seiten.py` |

**Integrationskurse und Vorbereitungsklassen** wurden auf Ihre Angabe hin **ersatzlos gestrichen** — sie standen bisher auf „Über uns“, ohne dass es das Angebot noch gibt.

**Türkische Fassung** wurde auf Ihre Angabe hin nicht vorbereitet.

## D — Technik und Konsistenz

| Was | Warum |
|---|---|
| Abschlussblock „Erstgespräch vereinbaren“ auf jeder Inhaltsseite | Unterseiten endeten ohne Handlungsaufforderung. Ausgenommen sind Danke-Seite, Impressum und Datenschutz — dort wäre ein Werbeblock unpassend |
| Angebotskasten der Startseite auf acht Kacheln | Abiturvorbereitung und Grundschulförderung fehlten |
| Elternfragebogen: Klassenstufe als Klappliste 2. bis 13. Klasse, „Kindergarten“ entfernt | Feste Jahresgruppen veralten jedes Jahr; die Altersspanne wurde auf Ihre Angabe angepasst |
| Navigation neu geordnet | Neun Unterpunkte unter „Nachhilfe“, neue Bereiche einsortiert |

**Zur Prüfbemerkung D1 (Vorlagen-Drift):** `projekte.html` ist beim automatischen Deploy bewusst ausgenommen, weil der Adminbereich diese Datei selbst schreibt. Dadurch blieb sie auf einem älteren Stand stehen — daher fehlten dort die Social-Icons und der Partnerblock. Vorgehen steht unten unter „Nach dem Hochladen“.

**Zur Prüfbemerkung D2 (doppelte Logos):** Die zweite Logoreihe ist die technische Voraussetzung dafür, dass die Laufleiste ohne Sprung umläuft. Sie trägt bereits `aria-hidden="true"` und durchgehend leere `alt`-Attribute — Screenreader lesen sie also **nicht** doppelt vor. Ich habe das gegengeprüft: neun Bilder mit Alternativtext in der ersten Spur, neun ohne in der zweiten. Hier war nichts zu reparieren.

## E — SEO

| Was | Ergebnis |
|---|---|
| Fachseiten ausgebaut | Deutsch 722, Mathematik 660, Englisch 604, Grundschulförderung 662, Prüfungsvorbereitung 623, Abiturvorbereitung 613, Hausaufgabenbetreuung 523, Online-Nachhilfe 523 Wörter — jeweils mit Bezug zum NRW-Kernlehrplan, typischen Bruchstellen je Klassenstufe und internen Verweisen |
| Titel und Beschreibungen | Alle 32 Seiten überarbeitet, Ortsbezug Solingen, keine falschen Vereinsnamen mehr |
| Strukturierte Daten | `EducationalOrganization` mit Anschrift, Telefon, `areaServed` Solingen und Bergisches Land, `sameAs` Instagram und Facebook; zusätzlich `FAQPage` mit 14 Fragen auf der Fragenseite, direkt aus derselben Datenquelle wie die sichtbare Seite |
| `sitemap.xml` | 27 Adressen, gestaffelte Prioritäten; Impressum, Datenschutz, Danke, 404 und die ausgeblendeten Sprachseiten bleiben draußen |
| `robots.txt` | Danke-Seite und 404 zusätzlich gesperrt |
| Bilder | Alle mit `alt`, `width` und `height` geprüft — kein Layout-Shift |
| Barrierefreiheit | Überschriftenhierarchie auf allen 32 Seiten ohne Sprünge, genau eine `h1` je Seite; Kontraste zwischen 5,7:1 und 18:1 (Mindestwert 4,5:1); Fokusrahmen 3 px sichtbar; Menü per Tastatur bedienbar mit korrektem `aria-expanded`; kein Querlauf bei 1280 und 390 px |

## `.htaccess`

**Auf `spektrum-nachhilfe.de`** (liegt im Paket):

- HTTPS erzwungen
- `www` wird auf die Adresse ohne `www` umgeleitet — vorher waren dieselben Inhalte unter zwei Adressen erreichbar
- Alte Adressen ohne `.html` werden auf die richtige Datei umgeleitet, sofern sie existiert
- Sicherheits-Header: Content-Security-Policy, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, Cross-Origin-Opener-Policy
- HSTS ist vorbereitet, aber **auskommentiert** — bitte erst einschalten, wenn HTTPS über Wochen zuverlässig läuft. Eine Rücknahme dauert Monate
- Fehlerseiten zeigen jetzt `404.html` statt der Startseite

**Auf den Altdomains** (`htaccess-altdomains.txt`, gehört **nicht** ins Paket): 301-Weiterleitungen für 24 bekannte Adressmuster von `spektrum-ev.de` und `spektrum-sg.de`, danach alles Übrige auf die Startseite. Let’s-Encrypt-Prüfpfade bleiben durchgelassen, damit die Zertifikate der alten Domains nicht auslaufen.

---

## Nach dem Hochladen — drei Schritte

1. **`projekte.html` einmalig mitübertragen.** In GitHub unter *Actions → „Auf den Server übertragen“ → Run workflow*, Haken bei „projekte.html mit überschreiben“. Sonst bleibt die Projektseite auf dem alten Stand ohne Social-Icons und Partnerleiste.
2. **Danach im Adminbereich auf „Seite neu schreiben“ klicken.** Sonst stehen dort wieder die acht Startprojekte statt Ihrer eigenen Einträge.
3. **`htaccess-altdomains.txt`** auf die Webspaces von `spektrum-ev.de` und `spektrum-sg.de` legen, dort umbenannt in `.htaccess`.

## Vor dem Freischalten

Die Website enthält **62 sichtbare Platzhalter** in 14 Dateien. Sie sind gelb hinterlegt und beginnen mit `[[TODO:`. Die vollständige Liste steht in `TODO-Liste.md`; sie wird bei jedem Neubau automatisch aus dem fertigen Paket erzeugt und kann deshalb nicht veralten.

Zwei Seiten sollten **nicht** online gehen, bevor ihre Platzhalter gefüllt sind:

- `unterstuetzen.html` — wer mit steuerlicher Absetzbarkeit wirbt, muss den Freistellungsbescheid belegen können
- `impressum.html` — der unvollständige Vorstandsname ist der am leichtesten angreifbare Punkt der ganzen Seite

---

## 22.09.2026 — Trello „In Arbeit“

| Änderung | Seite | Quelle |
|---|---|---|
| Videoprogramm benannt: Zoom, auf Wunsch der Eltern WhatsApp-Videoanruf. Platzhalter entfernt | online-nachhilfe.html | `build/seiten.py` |
| Neuer Abschnitt 11 „Online-Unterricht über Zoom oder WhatsApp“: Anbieter, verarbeitete Daten, Rechtsgrundlage, USA-Übermittlung (Data Privacy Framework), keine Aufzeichnung. Folgende Abschnitte ab 12 neu nummeriert, Abschnitt 12 verweist auf 11 | datenschutz.html | `build/recht.py` |
| Neuer Bereich „Aktuelles aus dem Spektrum“ direkt unter dem Bildbanner: seitlich blätterbare Karten mit Bild, Datum, Kurztext, „Weiterlesen“ | index.html | `build/aktuelles_daten.py`, `build/gestaltung.py` |
| Adminbereich: neue Seite `admin/aktuelles.php` zum Anlegen, Ändern, Löschen, Sortieren und Verstecken von Meldungen samt Bild-Upload | admin/ | `build/admin/aktuelles.php` |
| Startbestand: drei Meldungen aus den vorhandenen Projekten (DSEE, Förderung NRW, Aschure). Weltflüchtlingstag, Quilling-Workshop und Jugendprojekte liegen als Entwurf bereit, bis Text und Bild da sind | index.html | `build/aktuelles_daten.py` |
