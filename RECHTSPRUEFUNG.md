# Was extern geprüft werden sollte

Diese Punkte sind auf der Website vorbereitet, aber keine Rechtsberatung. Vor dem Freischalten sollte jemand mit Zulassung darüber schauen — je nach Punkt eine Anwältin für IT-/Vertragsrecht, die Steuerberatung oder der Landesjugendring.

---

## 1. Fernunterrichtsschutzgesetz (FernUSG) — der schwerwiegendste Punkt

**Worum es geht.** Wird entgeltlicher Unterricht überwiegend räumlich getrennt erteilt und der Lernerfolg überwacht, kann ein Fernlehrgang im Sinne des FernUSG vorliegen. Solche Lehrgänge brauchen eine Zulassung der Zentralstelle für Fernunterricht (ZFU).

**Warum das hier heikel ist.** Ein Vertrag über einen zulassungspflichtigen, aber nicht zugelassenen Fernlehrgang ist nach § 7 FernUSG **nichtig**. Gezahlte Entgelte können zurückgefordert werden. Die Rechtsprechung hat den Anwendungsbereich in den letzten Jahren eher ausgeweitet, auch auf Angebote, die sich selbst nicht als Fernunterricht verstehen.

**Was zu klären ist.** Ist die neue Seite `online-nachhilfe.html` so ausgestaltet, dass sie darunter fällt? Argumente dagegen: synchroner Live-Unterricht mit gleichzeitiger Anwesenheit beider Seiten gilt in der Regel nicht als Fernunterricht. Argumente dafür: Hausaufgaben zwischen den Stunden und deren Kontrolle könnten als Lernerfolgsüberwachung gewertet werden.

**Empfehlung.** Vor dem Bewerben des Online-Angebots anwaltlich prüfen lassen oder eine kostenlose Voranfrage bei der ZFU stellen. Bis dahin lässt sich die Seite mit `noindex` versehen oder aus dem Menü nehmen.

---

## 2. Vertragsbedingungen und Widerrufsbelehrung

`vertragsbedingungen.html` enthält die gesetzliche Muster-Widerrufsbelehrung und eine Struktur für Laufzeit, Kündigung und Stundenausfall. Die Platzhalter darin sind unternehmerische Entscheidungen, keine Rechtsfragen — die Prüfung betrifft:

- Passt die Musterbelehrung auf die tatsächliche Vertragsgestaltung, insbesondere wenn Mitgliedsbeiträge und Unterrichtsentgelt zusammenfallen?
- Wie ist mit über das Bildungspaket geförderten Stunden umzugehen, bei denen der Verein mit der Stadt abrechnet und nicht mit der Familie?
- Sind die gewählten Laufzeiten und Kündigungsfristen mit § 309 Nr. 9 BGB vereinbar? Laufzeiten über zwei Jahre und stillschweigende Verlängerungen um mehr als ein Jahr sind unwirksam.
- Beginnt der Unterricht regelmäßig innerhalb der Widerrufsfrist? Dann braucht es zusätzlich das ausdrückliche Verlangen der Kundin nach vorzeitigem Beginn — sonst schuldet sie im Widerrufsfall nichts, auch wenn schon unterrichtet wurde.

---

## 3. Verwendung der Partnerlogos

Im Seitenfuß laufen neun Zeichen, darunter das **Ministerium für Schule und Bildung NRW**, das **Europäische Solidaritätskorps**, die **DKJS**, **KOMM-AN NRW** und der **Verband engagierte Zivilgesellschaft NRW**.

Diese Zeichen sind fast durchweg genehmigungspflichtig, und die Genehmigung ist häufig **auf die Projektlaufzeit befristet**. Das Wappen des Landes Nordrhein-Westfalen unterliegt zusätzlich eigenen Vorschriften. Nach Projektende weiterverwendete Fördermittelzeichen sind ein verbreiteter und leicht angreifbarer Fehler — sie erwecken den Eindruck einer laufenden Förderung.

**Zu klären, je Logo:** Liegt eine schriftliche Erlaubnis vor? Bis wann gilt sie? Erlaubt sie die Verwendung im allgemeinen Seitenfuß oder nur auf der Projektseite? Was nicht belegbar ist, sollte raus. Die Liste steht an einer Stelle in `inhalt.py` und ist in einer Minute gekürzt.

---

## 4. Gemeinnützigkeit und Spendenwerbung

Auf `unterstuetzen.html` und `transparenz.html` stehen Platzhalter für Finanzamt, Steuernummer und Freistellungsbescheid.

**Bis diese Angaben stehen, sollte die Spendenseite keine Aussagen zur steuerlichen Absetzbarkeit machen.** Wer mit Absetzbarkeit wirbt, muss den Status belegen können. Prüfen sollte das die Steuerberatung:

- Ist der Freistellungsbescheid noch gültig? Er gilt in der Regel für drei Veranlagungszeiträume.
- Deckt die Satzung die auf der Website beschriebenen Tätigkeiten ab? Besonders Jugendarbeit, Dialogarbeit und Beratung müssen als Zwecke erfasst sein.
- Ist der Unterricht gegen Entgelt ein Zweckbetrieb oder ein wirtschaftlicher Geschäftsbetrieb? Davon hängen Steuerpflicht und Umsatzsteuer ab.
- Stimmt der beim Kreditinstitut hinterlegte Kontoinhaber mit dem Registernamen überein?

---

## 5. Kinderschutz nach § 72a SGB VIII

Auf `ueber-uns.html` gibt es jetzt einen eigenen Abschnitt, im Bewerbungsformular ein Pflichtfeld.

**Zu klären mit dem Jugendamt oder dem Landesjugendring:**

- Besteht eine Vereinbarung nach § 72a Abs. 2 oder 4 SGB VIII mit dem örtlichen Träger der Jugendhilfe? Für Vereine, die öffentliche Mittel für Jugendarbeit erhalten, ist das regelmäßig Voraussetzung.
- In welchem Abstand sind die Führungszeugnisse erneut einzusehen?
- Gilt die Pflicht auch für Nachhilfekräfte, die nur Einzelunterricht geben? Die Einschätzung hängt von Art, Dauer und Intensität des Kontakts ab.
- Gibt es ein schriftliches Schutzkonzept? Falls nein, ist das der wichtigste offene Punkt dieser Liste — vor der Website.

---

## 6. Datenschutz beim Elternfragebogen

Der Fragebogen wurde deutlich entschärft: Das Geburtsjahr des Kindes ist entfallen, die Abfrage der zu Hause gesprochenen Sprachen wurde durch die direkte Frage nach Förderbedarf in Deutsch als Zweitsprache ersetzt, alle Einschätzungen zum Lernverhalten sind freiwillig und jeweils mit Zweckangabe versehen, und es gibt ein Pflichtfeld zur Sorgeberechtigung.

**Trotzdem prüfen lassen:**

- Reicht die Zweckbindung für die verbliebenen Fragen zu Konzentration und Verhalten in Gruppen? Es handelt sich um Daten eines Kindes.
- Ist die Einwilligung der Sorgeberechtigten in dieser Form tragfähig, oder braucht es ab einem bestimmten Alter die Einwilligung des Kindes selbst?
- Sind die in der Datenschutzerklärung genannten Löschfristen realistisch und werden sie tatsächlich eingehalten? Eine dokumentierte Frist, an die sich niemand hält, ist schlechter als keine.
- Existiert ein Verzeichnis von Verarbeitungstätigkeiten nach Art. 30 DSGVO? Für die Website selbst ist es überschaubar, für Unterricht und Mitgliederverwaltung nicht.

---

## 7. Kleinere Punkte

| Punkt | Wer | Warum |
|---|---|---|
| Vollständige Vorstandsnamen im Impressum | Vorstand | § 5 DDG verlangt den ausgeschriebenen Namen; ein abgekürzter Vorname ist abmahnfähig |
| Verantwortliche Person nach § 18 Abs. 2 MStV | Vorstand | Nötig, sobald journalistisch-redaktionelle Inhalte erscheinen — bei Projektberichten schnell erreicht |
| Aufsicht und Haftung außerhalb der Unterrichtszeit | Versicherung | Wer haftet, wenn ein Kind vor dem Abholen das Haus verlässt? Vereinshaftpflicht prüfen |
| Auftragsverarbeitungsvertrag mit STRATO | Geschäftsstelle | In der Datenschutzerklärung zugesichert — bitte im Kundenbereich abschließen und ablegen |
| Erfahrungsberichte | Geschäftsstelle | Google-Bewertungen dürfen nicht ohne Erlaubnis übernommen werden; eigene Zitate mit schriftlicher Einwilligung einholen |
