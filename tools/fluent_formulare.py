#!/usr/bin/env python3
"""Erzeugt aus den statischen Formularen (elternfragebogen, lehrersuche, mitglied-werden)
Fluent-Forms-Definitionen als JSON: tools/fluent-formulare.json

Aufruf:  python tools/fluent_formulare.py
Eingelesen wird dann auf dem Server mit tools/fluent_einrichten.php.
"""
import html
import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
EMPFAENGER = "info@spektrum-ev.de"
DANKE = "https://spektrum-ev.de/danke/"

FORMULARE = [
    # (Datei, Titel, Betreff der Mail, Text des Sendeknopfs, Rechenfrage?)
    ("elternfragebogen", "Elternfragebogen", "Elternfragebogen über die Website", "Fragebogen absenden", True),
    ("lehrersuche", "Bewerbung als Nachhilfelehrkraft", "Bewerbung als Nachhilfelehrkraft über die Website", "Bewerbung absenden", True),
    ("mitglied-werden", "Aufnahmeantrag Mitgliedschaft", "Aufnahmeantrag Mitgliedschaft über die Website", "Antrag absenden", True),
]


def text(s: str) -> str:
    s = re.sub(r'<span class="(pflicht|freiwillig)">.*?</span>', "", s, flags=re.S)
    s = re.sub(r"<[^>]+>", "", s)
    return html.unescape(re.sub(r"\s+", " ", s)).strip()


def links(s: str) -> str:
    s = re.sub(r'<span class="pflicht">.*?</span>', "", s, flags=re.S)
    s = s.replace('href="datenschutz.html"', 'href="/datenschutz/" target="_blank"')
    s = s.replace('href="assets/Satzung2026.pdf"', 'href="/wp-content/themes/spektrum-ev/assets/Satzung2026.pdf" target="_blank"')
    return re.sub(r"\s+", " ", s).strip()


def regel(pflicht: bool, art: str = "") -> dict:
    r = {"required": {"value": pflicht, "message": "Bitte füllen Sie dieses Feld aus.", "global": False}}
    if art == "email":
        r["email"] = {"value": True, "message": "Bitte geben Sie eine gültige E-Mail-Adresse ein.", "global": False}
    return r


class Bauer:
    def __init__(self):
        self.felder = []
        self.n = 0

    def neu(self, element: str, attribute, einstellungen: dict, vorlage: str, titel: str, extra=None) -> dict:
        self.n += 1
        f = {"index": self.n, "element": element, "attributes": attribute, "settings": einstellungen,
             "editor_options": {"title": titel, "template": vorlage}, "uniqElKey": "el_%d" % (1700000000000 + self.n)}
        if extra:
            f.update(extra)
        self.felder.append(f)
        return f

    def html(self, code: str) -> None:
        self.neu("custom_html", [], {"html_codes": code, "conditional_logics": []}, "customHTML", "Custom HTML")

    def einzeilig(self, name, label, typ, pflicht, maxlaenge=None, autocomplete=None, hilfe="") -> None:
        attr = {"type": "email" if typ == "email" else typ, "name": name, "value": "", "id": "", "class": "", "placeholder": ""}
        if maxlaenge:
            attr["maxlength"] = maxlaenge
        if autocomplete:
            attr["autocomplete"] = autocomplete
        s = {"container_class": "", "label": label, "label_placement": "", "help_message": hilfe,
             "admin_field_label": "", "validation_rules": regel(pflicht, "email" if typ == "email" else ""),
             "conditional_logics": []}
        self.neu("input_email" if typ == "email" else "input_text", attr, s, "inputText", label)

    def mehrzeilig(self, name, label, pflicht, hilfe="") -> None:
        attr = {"name": name, "value": "", "id": "", "class": "", "placeholder": "", "rows": 5, "cols": 2}
        s = {"container_class": "", "label": label, "admin_field_label": "", "label_placement": "",
             "help_message": hilfe, "validation_rules": regel(pflicht), "conditional_logics": []}
        self.neu("textarea", attr, s, "inputTextarea", label)

    def auswahl(self, name, label, optionen, pflicht, hilfe="") -> None:
        s = {"label": label, "admin_field_label": "", "help_message": hilfe, "container_class": "", "label_placement": "",
             "placeholder": "Bitte wählen", "validation_rules": regel(pflicht), "conditional_logics": []}
        self.neu("select", {"name": name, "value": "", "id": "", "class": ""}, s, "select", label,
                 {"options": {o: o for o in optionen}})

    def gruppe(self, name, label, optionen, mehrfach, pflicht, hilfe="") -> None:
        s = {"container_class": "", "label": label, "admin_field_label": "", "label_placement": "", "display_type": "",
             "help_message": hilfe, "validation_rules": regel(pflicht), "conditional_logics": []}
        if mehrfach:
            self.neu("input_checkbox", {"type": "checkbox", "name": name, "value": []}, s, "inputCheckbox", label,
                     {"options": {o: o for o in optionen}})
        else:
            self.neu("input_radio", {"type": "radio", "name": name, "value": ""}, s, "inputRadio", label,
                     {"options": {o: o for o in optionen}})

    def zustimmung(self, name, label_html, pflicht) -> None:
        s = {"tnc_html": label_html, "has_checkbox": True, "admin_field_label": name, "container_class": "",
             "validation_rules": regel(pflicht), "conditional_logics": []}
        self.neu("terms_and_condition", {"type": "checkbox", "name": name, "value": False, "class": ""}, s,
                 "termsCheckbox", "Zustimmung")


def einlesen(datei: str, rechnen: bool) -> list:
    quelle = (ROOT / (datei + ".html")).read_text(encoding="utf-8")
    form = re.search(r"<form.*?</form>", quelle, re.S).group(0)
    form = re.sub(r'<div class="honigtopf">.*?</div>', "", form, flags=re.S)
    b = Bauer()
    muster = re.compile(
        r'(?P<h>\<h[23][^>]*>.*?</h[23]>)'
        r'|(?P<hint><p class="hinweis">.*?</p>)'
        r'|(?P<feld><p class="feld">.*?</p>)'
        r'|(?P<set><fieldset class="feldgruppe">.*?</fieldset>)'
        r'|(?P<wahl><div class="wahl[^"]*">.*?</div>)', re.S)
    rechenfrage_gesetzt = False
    for m in muster.finditer(form):
        if m.group("h"):
            b.html("<h3>%s</h3>" % html.escape(text(m.group("h"))))
        elif m.group("hint"):
            if "Satzung herunterladen" in m.group("hint") or "Zeugnisse und Lebenslauf" in m.group("hint") \
                    or "Bitte keine Angaben" in m.group("hint") or "Alle, die bei uns" in m.group("hint") \
                    or "Wozu:" in m.group("hint"):
                b.html(links(m.group("hint")))
            else:
                b.html(links(m.group("hint")))
        elif m.group("feld"):
            f = m.group("feld")
            label = text(re.search(r"<label[^>]*>(.*?)</label>", f, re.S).group(1))
            pflicht = " required" in f
            hilfe_m = re.search(r'<span class="tipp">(.*?)</span>', f, re.S)
            hilfe = text(hilfe_m.group(1)) if hilfe_m else ""
            name = re.search(r'name="([^"]+)"', f).group(1)
            ml = re.search(r'maxlength="(\d+)"', f)
            ac = re.search(r'autocomplete="([^"]+)"', f)
            if "<textarea" in f:
                b.mehrzeilig(name, label, pflicht, hilfe)
            elif "<select" in f:
                opts = [html.unescape(o) for o in re.findall(r"<option(?: value=\"[^\"]*\")?>(.*?)</option>", f, re.S)]
                opts = [o for o in opts if o and not o.startswith("Bitte") and o != "Keine Angabe"]
                b.auswahl(name, label, opts, pflicht, hilfe)
            else:
                typ = re.search(r'type="([^"]+)"', f).group(1)
                b.einzeilig(name, label, typ, pflicht, int(ml.group(1)) if ml else None, ac.group(1) if ac else None, hilfe)
        elif m.group("set"):
            s = m.group("set")
            legende = text(re.search(r"<legend>(.*?)</legend>", s, re.S).group(1))
            hint = re.search(r'<p class="hinweis">(.*?)</p>', s, re.S)
            hilfe = text(hint.group(1)) if hint else ""
            eingaben = re.findall(r"<input([^>]*)>", s)
            name = re.search(r'name="([^"]+)"', eingaben[0]).group(1)
            mehrfach = name.endswith("[]")
            werte = [html.unescape(re.search(r'value="([^"]*)"', e).group(1)) for e in eingaben]
            pflicht = any(" required" in e for e in eingaben)
            b.gruppe(name.rstrip("[]"), legende, werte, mehrfach, pflicht, hilfe)
        elif m.group("wahl"):
            w = m.group("wahl")
            name = re.search(r'name="([^"]+)"', w).group(1)
            if name == "datenschutz" and rechnen and not rechenfrage_gesetzt:
                b.html("@RECHNEN@")
                rechenfrage_gesetzt = True
            b.zustimmung(name, links(re.search(r"<label[^>]*>(.*?)</label>", w, re.S).group(1)), " required" in w)
    if rechnen and not rechenfrage_gesetzt:
        raise SystemExit("keine Stelle fuer die Rechenfrage in " + datei)
    return b.felder


def definition(datei, titel, betreff, knopf, rechnen) -> dict:
    felder = einlesen(datei, rechnen)
    # Pflichtfelder duerfen nur Felder sein, die es gibt: E-Mail-Feld fuer Antwort-Adresse
    hat_email = any(isinstance(f["attributes"], dict) and f["attributes"].get("name") == "email" for f in felder)
    return {
        "title": titel,
        "form": {
            "fields": felder,
            "submitButton": {
                "uniqElKey": "el_1524065200616", "element": "button",
                "attributes": {"type": "submit", "class": "knopf"},
                "settings": {"align": "left", "button_style": "", "container_class": "", "help_message": "",
                             "background_color": "", "button_size": "md", "color": "",
                             "button_ui": {"type": "default", "text": knopf, "img_url": ""}},
                "editor_options": {"title": "Submit Button"},
            },
        },
        "formSettings": {
            "confirmation": {"redirectTo": "customUrl", "messageToShow": "Vielen Dank! Wir melden uns bei Ihnen.",
                             "customPage": None, "samePageFormBehavior": "hide_form", "customUrl": DANKE},
            "restrictions": {
                "limitNumberOfEntries": {"enabled": False, "numberOfEntries": None, "period": "total", "limitReachedMsg": ""},
                "scheduleForm": {"enabled": False, "start": None, "end": None, "pendingMsg": "", "expiredMsg": ""},
                "requireLogin": {"enabled": False, "requireLoginMsg": ""},
                "denyEmptySubmission": {"enabled": False, "message": ""},
            },
            "layout": {"labelPlacement": "top", "helpMessagePlacement": "with_label",
                       "errorMessagePlacement": "inline", "asteriskPlacement": "asterisk-right"},
            "delete_after_x_days": "yes",
            "auto_delete_days": 180,
        },
        "notifications": {
            "name": "Mail an die Geschäftsstelle",
            "sendTo": {"type": "email", "email": EMPFAENGER, "field": "email", "routing": [{"email": None, "field": None, "operator": "=", "value": None}]},
            "fromName": "Website Spektrum", "fromEmail": EMPFAENGER,
            "replyTo": "{inputs.email}" if hat_email else "", "bcc": "",
            "subject": betreff,
            "message": "<p>{all_data}</p><p>Gesendet über das Formular auf spektrum-ev.de</p>",
            "conditionals": {"status": False, "type": "all", "conditions": [{"field": None, "operator": "=", "value": None}]},
            "enabled": True, "email_template": "",
        },
    }


if __name__ == "__main__":
    ergebnis = {datei: definition(datei, *rest) for datei, *rest in FORMULARE}
    ziel = ROOT / "tools" / "fluent-formulare.json"
    ziel.write_text(json.dumps(ergebnis, ensure_ascii=False, indent=1), encoding="utf-8")
    for datei, d in ergebnis.items():
        arten = {}
        for f in d["form"]["fields"]:
            arten[f["element"]] = arten.get(f["element"], 0) + 1
        print(datei, len(d["form"]["fields"]), arten)
