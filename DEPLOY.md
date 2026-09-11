# Automatisch übertragen: push → live

Ab jetzt gilt: Was auf `main` landet, liegt eine Minute später auf dem Server. Kein FTP mehr.

Die Einrichtung dauert einmalig etwa zehn Minuten.

---

## 1. Schlüsselpaar erzeugen (auf deinem Rechner)

PowerShell:

```powershell
ssh-keygen -t ed25519 -C "github-deploy spektrum" -f $env:USERPROFILE\.ssh\spektrum_deploy
```

Bei der Frage nach der Passphrase **zweimal Enter** — ein Deploy-Schlüssel darf keine Passphrase haben, sonst kann GitHub ihn nicht benutzen.

Es entstehen zwei Dateien:

| Datei | Was damit passiert |
|---|---|
| `spektrum_deploy.pub` | **öffentlich** — kommt auf den Server |
| `spektrum_deploy` | **privat** — kommt als GitHub-Secret, sonst nirgendwohin |

Den privaten Schlüssel niemals ins Repo legen, nicht per Mail verschicken, nicht in einen Chat kopieren.

---

## 2. Öffentlichen Schlüssel auf den Server

Inhalt der `.pub`-Datei anzeigen:

```powershell
type $env:USERPROFILE\.ssh\spektrum_deploy.pub
```

Auf dem Server — **als Benutzer `spektrum`, nicht als root**:

```bash
mkdir -p ~/.ssh && chmod 700 ~/.ssh
echo 'ssh-ed25519 AAAA…hier die ganze Zeile…github-deploy spektrum' >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
```

Falls du als root eingeloggt bist, vorher umschalten: `su - spektrum`.

**Warum nicht root:** Ein Schlüssel, mit dem GitHub root-Rechte auf eurem Server bekommt, ist ein zu großes Pfand für einen Website-Upload. Der Benutzer `spektrum` darf genau das, was nötig ist — und Dateien, die er anlegt, haben von vornherein den richtigen Eigentümer. Genau daran hing das 403-Problem.

---

## 3. Fingerabdruck des Servers holen

Damit GitHub sicher ist, mit dem richtigen Server zu sprechen, und nicht mit jemandem, der sich dazwischenschiebt:

```powershell
ssh-keyscan -p 22 spektrum-nachhilfe.de
```

Die Ausgabe komplett kopieren (mehrere Zeilen, `#`-Zeilen dürfen mit).

---

## 4. Secrets in GitHub eintragen

Im Repository: **Settings → Secrets and variables → Actions → New repository secret**. Sechs Stück:

| Name | Wert | Pflicht |
|---|---|---|
| `SSH_HOST` | `spektrum-nachhilfe.de` (oder die IP des Servers) | ja |
| `SSH_USER` | `spektrum` | ja |
| `SSH_PORT` | `22` — nur nötig, wenn euer SSH auf einem anderen Port läuft | nein |
| `ZIEL_PFAD` | `/var/www/vhosts/spektrum-nachhilfe.de/httpdocs` | ja |
| `SSH_KEY` | Inhalt der Datei `spektrum_deploy` — **mit** den Zeilen `-----BEGIN …` und `-----END …` | ja |
| `SSH_KNOWN_HOSTS` | die Ausgabe aus Schritt 3 | ja¹ |

¹ Fehlt dieses Secret, läuft der Deploy trotzdem, übernimmt den Serverschlüssel aber ungeprüft und schreibt eine Warnung ins Protokoll. Setz es lieber.

Secrets sind nach dem Speichern auch für dich nicht mehr lesbar, nur überschreibbar. Das ist so gewollt.

---

## 5. Erster Lauf

**Actions → „Auf den Server übertragen" → Run workflow.**

Der Lauf prüft der Reihe nach: Schlüssel da, Verbindung steht, Zielordner existiert und ist beschreibbar, `rsync` auf dem Server vorhanden. Erst dann überträgt er. In der Zusammenfassung stehen anschließend Eigentümer und Rechte von drei Stichproben — dort muss `spektrum:psacln 644` stehen.

Danach genügt jeder `git push` auf `main`.

---

## Was übertragen wird — und was nicht

`rsync` gleicht `httpdocs` mit dem Repo ab und löscht auf dem Server, was hier nicht mehr existiert. Vier Dinge sind ausgenommen, weil sie dem Server gehören:

| Ausgenommen | Grund |
|---|---|
| `daten/` | Projektdaten und Passwort-Hash. Liegt ohnehin außerhalb von httpdocs |
| `assets/projekte/` | Bilder, die du im Adminbereich hochlädst. Ein Deploy darf sie nie löschen |
| `projekte.html` | schreibt der Adminbereich selbst — siehe unten |
| `.well-known/` | dort legt Let's Encrypt seine Prüfdateien ab. Löschen = Zertifikat läuft aus |

Ebenfalls nicht übertragen: `.md`-Dateien, Archive, `.github/`, `.gitignore`. Die gehören ins Repo, nicht auf den Webserver.

**Die Rechte setzt der Deploy gleich mit** (`--chmod=D755,F644`): Ordner 755, Dateien 644. Damit kann das 403 Forbidden auf `projekte.html` nicht wiederkommen.

---

## Der Sonderfall `projekte.html`

Diese Datei hat zwei Autoren: uns (Aufbau, Marken, Gestaltung) und den Adminbereich auf dem Server (die Projektkacheln und Fenster dazwischen). Würde jeder Push sie überschreiben, wären deine im Adminbereich angelegten Projekte danach jedes Mal weg, bis du auf *Seite neu schreiben* klickst.

Deshalb ist sie normalerweise ausgenommen. Wenn sich der **Aufbau** ändert — wie zuletzt, als die Detailfenster dazukamen:

1. **Actions → „Auf den Server übertragen" → Run workflow**
2. Haken bei **„projekte.html mit überschreiben"**
3. Nach dem Lauf im Adminbereich einmal auf **Seite neu schreiben**

Die Zusammenfassung des Laufs erinnert dich daran.

---

## Wenn etwas klemmt

| Meldung im Protokoll | Ursache |
|---|---|
| `Permission denied (publickey)` | Der öffentliche Schlüssel steht nicht (oder falsch) in `~/.ssh/authorized_keys` des Benutzers `spektrum`, oder `SSH_KEY` wurde unvollständig eingefügt (BEGIN/END-Zeilen fehlen) |
| `Host key verification failed` | `SSH_KNOWN_HOSTS` passt nicht zum Server — Schritt 3 wiederholen |
| `Zielordner fehlt` | `ZIEL_PFAD` stimmt nicht |
| `Kein Schreibrecht auf …` | httpdocs gehört nicht dem Benutzer `spektrum` → `plesk repair fs -y spektrum-nachhilfe.de` |
| `Auf dem Server fehlt rsync` | `dnf install rsync` bzw. `apt install rsync` als root |

---

## Zwei Dinge zum Aufräumen

`6.tar.gz` liegt noch im Repo (1 MB). Raus damit:

```powershell
git rm --cached 6.tar.gz
del 6.tar.gz
git commit -m "Archiv entfernt, .gitignore ergaenzt"
```

Die neue `.gitignore` sorgt dafür, dass so etwas nicht wieder hineinrutscht — und hält vor allem `daten/` draußen, den Ordner mit dem Passwort-Hash.

---

## Zur Sicherheit dieses Aufbaus

Der Deploy-Schlüssel kann sich als `spektrum` anmelden und dort alles tun, was dieser Benutzer darf. Wer Zugriff auf euer GitHub-Konto bekommt, bekommt damit auch Zugriff auf den Webspace. Zwei Maßnahmen, die das eingrenzen:

**Zwei-Faktor-Anmeldung für euer GitHub-Konto.** Der wirksamste einzelne Schritt.

**Den Schlüssel einschränken.** In `~/.ssh/authorized_keys` vor die Schlüsselzeile setzen:

```
from="140.82.112.0/20,143.55.64.0/20,192.30.252.0/22,185.199.108.0/22" ssh-ed25519 AAAA…
```

Damit funktioniert der Schlüssel nur von GitHub-Adressen aus. Die Liste ändert sich gelegentlich; die aktuellen Bereiche stehen unter `https://api.github.com/meta` im Feld `actions`. Wenn ein Deploy plötzlich mit *Permission denied* scheitert, ist meist diese Liste veraltet.

Falls der Schlüssel doch einmal abhandenkommt: Zeile aus `~/.ssh/authorized_keys` löschen, neues Paar erzeugen, `SSH_KEY` in GitHub überschreiben. Mehr ist nicht nötig.
