#!/usr/bin/env python3
"""Erzeugt aus den statischen Seiten ein WordPress-Theme (wp-theme/spektrum-ev + ZIP).

Aufruf:  python tools/build_wp_theme.py
"""
import json
import re
import shutil
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / "wp-theme" / "spektrum-ev"
ZIP = ROOT / "wp-theme" / "spektrum-ev.zip"
ALT, NEU = "spektrum-nachhilfe.de", "spektrum-ev.de"
THEME = "@THEME@"   # wird zur Laufzeit durch die Theme-URL ersetzt

SEITEN = sorted(p for p in ROOT.glob("*.html") if p.stem != "404")
FORMULAR_IDS = {"elternfragebogen": 9, "lehrersuche": 10, "mitglied-werden": 11}   # Formidable-IDs (tools/formidable_formulare.php)
FORMULAR_BEHALTEN = {"kontakt"}   # dieses Formular sendet ueber das Theme (admin-post.php)


def umschreiben(html: str) -> str:
    """Relative Adressen der statischen Seite auf WordPress-Adressen umstellen."""
    html = re.sub(r'(["\'(])assets/', r"\1" + THEME + "/assets/", html)
    html = html.replace('"favicon.ico"', '"' + THEME + '/favicon.ico"')
    html = re.sub(r'href="index\.html(#[^"]*)?"', lambda m: 'href="/' + (m.group(1) or "") + '"', html)
    html = re.sub(r'href="([a-z0-9\-]+)\.html(#[^"]*)?"',
                  lambda m: 'href="/%s/%s"' % (m.group(1), m.group(2) or ""), html)
    html = html.replace('action="formular.php"', 'action="' + THEME + '/formular.php"')
    return html.replace(ALT, NEU)


def formulare_entfernen(html: str) -> str:
    html = re.sub(r'\s*<div class="fehlerbox".*?</div>', "", html, flags=re.S)
    return re.sub(r"<form.*?</form>", "<!-- Formular: hier mit eigenem Formular-Plugin einfuegen -->", html, flags=re.S)


def als_bloecke(html: str) -> str:
    """Jeden Abschnitt oberster Ebene als eigenen HTML-Block, damit der Editor nichts umbaut."""
    teile, tiefe, start = [], 0, 0
    for m in re.finditer(r"<(/?)(section|div)[^>]*>", html):
        tiefe += -1 if m.group(1) else 1
        if tiefe == 0:
            teile.append(html[start:m.end()].strip())
            start = m.end()
    rest = html[start:].strip()
    if rest:
        teile.append(rest)
    return "\n\n".join("<!-- wp:html -->\n%s\n<!-- /wp:html -->" % t for t in teile if t)


# Anpassungen der Datenschutzerklaerung fuer die WordPress-Seite (alt -> neu).
DATENSCHUTZ = [
    ("Diese Website ist als rein statische Seite aufgebaut. Es werden keine Cookies gesetzt, keine Analyse- oder Trackingdienste eingesetzt und keine Inhalte von Drittanbietern nachgeladen. Schriften, Bilder und Programmcode liegen ausschließlich auf unserem eigenen Server. Ein Einwilligungsbanner ist deshalb nicht erforderlich.",
     "Diese Website läuft auf dem Redaktionssystem WordPress. Beim Besuch der Seite werden keine Cookies gesetzt, keine Analyse- oder Trackingdienste eingesetzt und keine Inhalte von Drittanbietern nachgeladen. Schriften, Bilder und Programmcode liegen ausschließlich auf unserem eigenen Server. Kommentare und Registrierungen sind nicht möglich. Für die Formulare und den E-Mail-Versand setzen wir die Plugins Formidable Forms und WP Mail SMTP ein; beide laufen vollständig auf unserem eigenen Server und übertragen keine Daten an ihre Hersteller. Cookies verwendet WordPress nur für angemeldete Redakteurinnen und Redakteure im Verwaltungsbereich; Besucherinnen und Besucher sind davon nicht betroffen. Ein Einwilligungsbanner ist deshalb nicht erforderlich."),
    ("Die Daten werden nach 7 Tagen gelöscht. Eine Zusammenführung",
     "Die Protokolldateien werden automatisch rotiert und ältere Dateien werden gelöscht, sobald die festgelegte Höchstzahl an Archivdateien erreicht ist. Eine Zusammenführung"),
    ("Diese Website wird gehostet bei der STRATO GmbH, Otto-Ostrowski-Straße 7, 10249 Berlin.",
     "Diese Website wird gehostet bei der IONOS SE, Elgendorfer Straße 57, 56410 Montabaur."),
    ("Die Formulare auf dieser Website übergeben Ihre Angaben an den Postausgangsserver der STRATO GmbH",
     "Angaben, die Sie uns über ein Formular auf dieser Website senden, übergibt das auf unserem Server laufende Plugin WP Mail SMTP an den Postausgangsserver der STRATO GmbH"),
    ("Die Formulare übermitteln Ihre Angaben per E-Mail an unsere Geschäftsstelle. Es erfolgt keine Speicherung auf dem Webserver und keine Auswertung zu anderen Zwecken.",
     "Die Formulare übermitteln Ihre Angaben per E-Mail an unsere Geschäftsstelle. Elternfragebogen, Lehrersuche, Mitgliedsantrag und Anmeldungen zu Veranstaltungen laufen mit dem Plugin Formidable Forms auf unserem eigenen Server. Damit keine Anfrage verloren geht, speichert die Website diese Angaben zusätzlich in ihrer Datenbank. Dabei werden weder IP-Adresse noch Browserkennung mitgespeichert, und es werden keine Cookies gesetzt. Die Einträge werden nach 180 Tagen automatisch gelöscht; wir können sie auf Wunsch auch früher löschen. Das Kontaktformular speichert nichts in der Datenbank, seine Angaben stehen nur in der E-Mail. Eine Auswertung zu anderen Zwecken findet nicht statt.</p>" "\n    <p>Melden Sie sich über ein Formular zu einer Veranstaltung an, verarbeiten wir Ihren Namen, Ihre E-Mail-Adresse, auf Wunsch Ihre Telefonnummer und die Zahl der Personen, um Plätze zu planen und Sie bei Rückfragen zu erreichen. Rechtsgrundlage ist Art. 6 Abs. 1 lit. b DSGVO. Wir löschen die Angaben nach der Veranstaltung, spätestens nach 180 Tagen.</p>" "\n    <p>Gegen automatisierte Zusendungen schützen die Formulare eine einfache Rechenaufgabe (Sicherheitsfrage) und ein verstecktes Feld, das nur Programme ausfüllen. Beides wird auf unserem Server geprüft; Dienste Dritter kommen dabei nicht zum Einsatz."),
    ('<tr><th scope="row">Anfragen ohne Vertragsschluss</th>',
     '<tr><th scope="row">Formulareinträge in der Datenbank</th><td>180 Tage, dann automatische Löschung</td></tr>\n        '
     '<tr><th scope="row">Anmeldungen zu Veranstaltungen</th><td>nach der Veranstaltung, spätestens nach 180 Tagen</td></tr>\n        '
     '<tr><th scope="row">Anfragen ohne Vertragsschluss</th>'),
    ('<tr><th scope="row">Server-Logfiles</th><td>7 Tagen</td></tr>',
     '<tr><th scope="row">Server-Logfiles</th><td>bis zur automatischen Rotation und Löschung durch den Hoster</td></tr>'),
    ("Stand September 2026", "Stand Oktober 2026"),
]


def datenschutz_anpassen(html: str) -> str:
    for alt, neu in DATENSCHUTZ:
        if alt not in html:
            raise SystemExit("Datenschutz: Textstelle nicht gefunden: " + alt[:60])
        html = html.replace(alt, neu)
    return html


def teil(html: str, von: str, bis: str) -> str:
    a = html.index(von) + len(von)
    return html[a:html.index(bis, a)]


def main() -> None:
    if OUT.parent.exists():
        shutil.rmtree(OUT.parent)
    (OUT / "content").mkdir(parents=True)
    shutil.copytree(ROOT / "assets", OUT / "assets")
    shutil.copy(ROOT / "favicon.ico", OUT / "favicon.ico")

    quelle = (ROOT / "index.html").read_text(encoding="utf-8")
    (OUT / "content" / "_kopf.html").write_text(
        umschreiben(teil(quelle, "<body>\n", '<main id="inhalt">')).replace(' aria-current="page"', ""),
        encoding="utf-8")
    (OUT / "content" / "_fuss.html").write_text(
        umschreiben(teil(quelle, "</main>\n", '<script src="@THEME@')
                    if False else teil(quelle, "</main>\n", '<script src="assets/main.js"')),
        encoding="utf-8")

    meta = {}
    for datei in SEITEN + [ROOT / "404.html"]:
        h = datei.read_text(encoding="utf-8")
        slug = "start" if datei.stem == "index" else datei.stem
        inhalt = umschreiben(teil(h, '<main id="inhalt">', "</main>"))
        if slug in FORMULAR_BEHALTEN:
            inhalt = inhalt.replace('action="' + THEME + '/formular.php" method="post">',
                                    'action="/wp-admin/admin-post.php" method="post">'
                                    '<input type="hidden" name="action" value="spektrum_kontakt">')
            assert "admin-post.php" in inhalt
            inhalt = inhalt.replace('<div class="wahl wahl--pflicht">', '@RECHNEN@<div class="wahl wahl--pflicht">', 1)
            assert "@RECHNEN@" in inhalt
        else:
            inhalt = formulare_entfernen(inhalt)
            if slug in FORMULAR_IDS:
                inhalt = inhalt.replace("<!-- Formular: hier mit eigenem Formular-Plugin einfuegen -->", '[formidable id=%d]' % FORMULAR_IDS[slug])
                assert "[formidable" in inhalt
        inhalt = inhalt.strip()
        if slug == "datenschutz":
            inhalt = datenschutz_anpassen(inhalt)
        (OUT / "content" / (slug + ".html")).write_text(
            inhalt if slug == "404" else als_bloecke(inhalt), encoding="utf-8")
        titel = re.search(r"<title>(.*?)</title>", h, re.S).group(1).strip()
        beschr = re.search(r'<meta name="description" content="(.*?)"', h)
        h1 = re.search(r"<h1[^>]*>(.*?)</h1>", inhalt, re.S)
        ld = re.search(r'<script type="application/ld\+json">(.*?)</script>', h, re.S)
        meta[slug] = {
            "titel": titel.replace(ALT, NEU),
            "ueberschrift": re.sub(r"<[^>]+>", "", h1.group(1)).strip() if h1 else titel,
            "beschreibung": beschr.group(1).replace(ALT, NEU) if beschr else "",
            "noindex": 'content="noindex' in h,
            "jsonld": ld.group(1).strip().replace(ALT, NEU) if ld else "",
        }
    # Zusatzseiten, die es auf der statischen Seite nicht gibt (tools/seiten/*.html + .json)
    waben = re.search(r"<svg.*?</svg>", (OUT / "content" / "kontakt.html").read_text(encoding="utf-8"), re.S).group(0)
    for seite in sorted((ROOT / "tools" / "seiten").glob("*.html")):
        e = json.loads(seite.with_suffix(".json").read_text(encoding="utf-8"))
        inhalt = umschreiben(seite.read_text(encoding="utf-8").replace("@WABEN@", waben)).strip()
        (OUT / "content" / seite.name).write_text(als_bloecke(inhalt), encoding="utf-8")
        meta[seite.stem] = {"titel": e["titel"], "ueberschrift": e["ueberschrift"], "beschreibung": e["beschreibung"],
                            "noindex": False, "jsonld": json.dumps(e["jsonld"], ensure_ascii=False) if e.get("jsonld") else "", "parent": e.get("parent", "")}
    (OUT / "content" / "_meta.json").write_text(
        json.dumps(meta, ensure_ascii=False, indent=1), encoding="utf-8")

    vorlagen = ROOT / "tools" / "wp-vorlagen"
    for p in vorlagen.iterdir():
        shutil.copy(p, OUT / p.name)

    with zipfile.ZipFile(ZIP, "w", zipfile.ZIP_DEFLATED) as z:
        for p in sorted(OUT.rglob("*")):
            if p.is_file():
                z.write(p, "spektrum-ev/" + p.relative_to(OUT).as_posix())
    print(len(meta), "Seiten ->", ZIP)


main()
