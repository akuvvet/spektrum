# Mailversand der Formulare

## Warum es bisher nicht ankam

Webserver und Mailserver sind zwei getrennte Häuser:

| | |
|---|---|
| Website | Plesk-Server, `/var/www/vhosts/spektrum-nachhilfe.de` |
| Postfächer beider Domains | STRATO, MX `smtpin.rzone.de` |

Die PHP-Funktion `mail()` erzeugt Post **auf dem Webserver** mit dem Absender `noreply@spektrum-nachhilfe.de`. Diese Adresse gibt es bei STRATO nicht, und ein SPF-Eintrag, der den Webserver als Absender autorisiert, existiert für die Domain ebenfalls nicht. STRATO nimmt solche Post an und wirft sie weg. Deshalb meldete der Adminbereich „übergeben" und im Postfach kam nie etwas an.

## Was jetzt passiert

Das Formular meldet sich beim Postausgangsserver von STRATO an und übergibt die Mail dort — so wie ein Mailprogramm es täte. Absender und Anbieter passen dann zusammen.

| Datei | Rolle |
|---|---|
| `versand.php` | spricht SMTP, enthält **keine** Zugangsdaten, gehört ins Git |
| `mail-zugang.php` | Zugangsdaten, liegt **außerhalb** von httpdocs, gehört **nicht** ins Git |
| `formular.php` | Empfänger und Feldlisten wie bisher |

Fehlt `mail-zugang.php`, fällt alles auf den alten `mail()`-Weg zurück. Nichts bricht, es kommt nur wie bisher nichts an.

---

## Einrichten — einmalig, etwa fünf Minuten

### 1. Datei auf dem Server anlegen

Als Benutzer `spektrum`, **eine Ebene über httpdocs**:

```bash
cd /var/www/vhosts/spektrum-nachhilfe.de
nano mail-zugang.php
```

Inhalt:

```php
<?php
return [
    'host'   => 'smtp.strato.de',
    'port'   => 587,
    'sicher' => 'tls',        // 'tls' für Port 587, 'ssl' für Port 465

    'benutzer' => 'info@spektrum-ev.de',
    'passwort' => 'DAS-PASSWORT-DES-POSTFACHS',

    'absender' => 'info@spektrum-ev.de',
    'name'     => 'Website Spektrum',
];
```

Das Passwort ist das des STRATO-Postfachs `info@spektrum-ev.de`. Es steht nur in dieser einen Datei auf dem Server — nirgends sonst, und in keinem Chat.

### 2. Rechte setzen

```bash
chown spektrum:psacln mail-zugang.php
chmod 640 mail-zugang.php
ls -l mail-zugang.php
```

Erwartet: `-rw-r----- 1 spektrum psacln`. Über den Browser ist die Datei nicht erreichbar, weil sie außerhalb von httpdocs liegt.

### 3. Prüfen

Adminbereich → **Prüfen**. Unter *Fassung Adminbereich* muss `2026-09-12 a (SMTP-Versand)` stehen. Im Block *Mailversand der Formulare*:

| Zeile | soll zeigen |
|---|---|
| Versandweg | `Postausgangsserver smtp.strato.de:587 (TLS), Anmeldung als info@spektrum-ev.de` |
| Zugangsdatei | Pfad und Rechte `640` |

Dann **Testmail senden**. Die Mail sollte binnen einer Minute im Postfach liegen.

---

## Wenn es klemmt

| Meldung | Ursache |
|---|---|
| `Keine Verbindung zu smtp.strato.de:587 — Connection refused` | Die Firewall des Servers lässt ausgehende Mailverbindungen nicht durch. In Plesk unter *Tools & Einstellungen → Firewall* Port 587 (oder 465) ausgehend freigeben |
| `Server antwortete auf „Passwort" mit: 535 …` | Passwort oder Benutzername stimmen nicht. Im STRATO-Kundenbereich prüfen — dort ist der Benutzername meist die vollständige Adresse |
| `Server antwortete auf „MAIL FROM…" mit: 550 …` | `absender` passt nicht zum Postfach unter `benutzer`. Beide müssen dieselbe Adresse sein |
| `Die Verschlüsselung ließ sich nicht aufbauen` | `'sicher' => 'ssl'` und `'port' => 465` versuchen |
| Versandweg zeigt weiter `mail()` | Datei am falschen Ort, nicht lesbar für PHP, oder das Passwort ist noch der Platzhalter |

## Protokoll

`/var/www/vhosts/spektrum-nachhilfe.de/formular-protokoll.log` — eine Zeile je Sendung, die letzten fünf stehen im Adminbereich unter *Letzte Sendungen*.

Bewusst **ohne Namen, Adressen und Nachrichtentexte**: nur Datum, Formularart, Versandweg und ob es geklappt hat. Anfragen von Besuchern haben in einer Logdatei nichts verloren.

## Wenn das Passwort einmal getauscht wird

Nur `mail-zugang.php` auf dem Server ändern. Kein Deploy nötig, kein Commit, nichts im Git.
