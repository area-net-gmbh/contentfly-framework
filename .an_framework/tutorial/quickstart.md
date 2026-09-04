<!-- PURPOSE: Schritt-für-Schritt-Einstieg für Team-Mitglieder ohne Entwickler-Hintergrund. Einzige Quelle der Setup-Befehle — how-to-use.md verweist hierher und erklärt nur die Hintergründe. -->

# Quickstart — Schritt für Schritt zum eingerichteten Projekt

Diese Anleitung ist für **alle im Team**, auch ohne Programmier- oder Git-Kenntnisse.
Du musst nichts verstehen, was hier nicht erklärt wird. Beim ersten Mal dauert es
etwa 45 Minuten, danach fünf.

> **Du kannst hier nichts kaputtmachen, solange du nur Befehle ausführst, die in
> dieser Anleitung stehen.** Wenn etwas anders aussieht als beschrieben: nicht
> weiterprobieren, sondern fragen. Fragen kostet nichts.

Die ausführliche Fassung für Entwickler:innen ist [how-to-use.md](how-to-use.md).
Diese hier ist die Kurzfassung mit mehr Erklärung — beides beschreibt dasselbe Framework.

---

## Bevor du anfängst — sechs Wörter

| Wort | Was es bedeutet |
|---|---|
| **Terminal** | Ein Fenster, in dem du dem Rechner Befehle tippst statt zu klicken. Auf dem Mac heißt das Programm „Terminal", auf Windows „Git Bash" bzw. „PowerShell". |
| **Befehl** | Eine Zeile Text, die du in dieses Fenster einfügst und mit **Enter** abschickst. |
| **Ordner / Pfad** | Genau das, was du im Finder bzw. Explorer siehst. Ein Pfad ist die vollständige Adresse eines Ordners, z. B. `/Users/anna/Projekte/kunde-x`. |
| **Projektwurzel** | Der oberste Ordner deines Projekts — der, in dem `CLAUDE.md` und `.an_framework` direkt nebeneinander liegen. Fast alles passiert von hier aus. |
| **Git** | Ein Programm, das jede Version deiner Dateien mitschreibt, damit nichts verloren geht und mehrere Leute gleichzeitig arbeiten können. |
| **Claude Code** | Das Programm, mit dem du im Terminal mit Claude arbeitest. Es darf Dateien in deinem Projekt lesen und schreiben. |

**Kopieren und Einfügen ist ausdrücklich erwünscht — tipp nichts ab.** Jeder Befehl
in dieser Anleitung ist eine Zeile, die du einfügst und mit Enter abschickst.

Alle weiteren Begriffe stehen im [Glossar](#glossar) ganz unten.

---

## Welchen Weg brauchst du?

| Deine Situation | Dein Weg |
|---|---|
| **Erstes Mal auf diesem Rechner** — Git und Claude Code sind noch nicht eingerichtet | **Teil A** — Initiale Einrichtung. **Nur einmal pro Rechner**, unabhängig vom Projekt. Danach nie wieder. |
| Neues Projekt, es gibt noch nichts | **Teil B** — Projekt aufsetzen |
| Das Projekt gibt es schon, das Framework fehlt | **Teil C** — Framework nachrüsten |
| Das Projekt liegt schon in GitLab, mit Framework | **Teil D** — Projekt übernehmen |
| Alles eingerichtet, ich will arbeiten | **Teil E** — Arbeitsalltag |
| Etwas klappt nicht | **Teil F** — Fehlersuche |

**Teil A gehört nicht zum Projekt, sondern zu deinem Rechner.** Hast du ihn schon
einmal durchlaufen — egal für welches Projekt —, überspring ihn und geh direkt zu B
oder C.

**So prüfst du das in fünf Sekunden:** Terminal öffnen (Teil A1), `claude --version`
eingeben. Kommt eine Versionsnummer, ist Teil A erledigt. Kommt eine Fehlermeldung,
fang bei Teil A an.

---

## Teil A — Initiale Einrichtung (einmal pro Rechner)

Diese Schritte gehören zu deinem **Rechner**, nicht zu einem Projekt. Du machst sie
genau **einmal** — für jedes weitere Projekt entfallen sie komplett.

### A0. Claude-Konto prüfen

Du brauchst ein Claude-Konto mit **Pro, Max, Team oder Enterprise**.
**Das kostenlose Konto funktioniert mit Claude Code nicht** — du würdest alles
installieren und erst beim Anmelden scheitern. Im Zweifel vorher im Team fragen.

### A1. Terminal öffnen

**macOS** — Cmd + Leertaste, `Terminal` tippen, Enter.

**Windows** — du brauchst hier **zwei** verschiedene Fenster, und das ist der einzige
Punkt, an dem sich die beiden Systeme wirklich unterscheiden:

| Wofür | Welches Fenster |
|---|---|
| Die Einrichtungs-Befehle in Teil B / C | **Git Bash** (kommt mit Git mit, siehe A2) |
| Claude Code starten | **PowerShell** |

Grund in einem Halbsatz: Git Bash versteht die Einrichtungs-Befehle, PowerShell startet
Claude zuverlässig. Merk dir einfach: **Einrichten in Git Bash, Arbeiten in PowerShell.**

PowerShell öffnest du über das Startmenü. Woran du erkennst, dass du wirklich in
PowerShell bist: Die Zeile beginnt mit `PS`, also `PS C:\Users\Name>`. Steht dort kein
`PS`, bist du in der alten Eingabeaufforderung — die geht hier nicht.

### A2. Git installieren

**macOS** — tippe:

```sh
git --version
```

Ist Git schon da, erscheint eine Versionsnummer wie `git version 2.39.5`. Fehlt es,
öffnet macOS von selbst ein Fenster „Befehlszeilen-Entwicklertools installieren" —
auf **Installieren** klicken, warten, danach den Befehl wiederholen.

**Windows** — lade Git von <https://git-scm.com/downloads/win> und klicke im
Installationsprogramm überall auf **Weiter** (die Voreinstellungen passen).
Danach: Rechtsklick auf einen beliebigen Ordner → **Git Bash Here** → im Fenster
`git --version` eingeben. Auf Windows 11 versteckt sich der Eintrag eventuell hinter
**Weitere Optionen anzeigen**.

### A3. Git sagen, wer du bist

Ohne diesen Schritt bricht später dein erster Zwischenstand mit einer roten Textwand
ab, in der `fatal:` steht. Deshalb jetzt, nicht später.

> ⚠️ **Diese zwei Zeilen sind die einzigen der ganzen Anleitung, die du nicht
> unverändert einfügen darfst.** Setz deinen echten Namen und deine echte
> Firmen-E-Mail ein, **bevor** du Enter drückst. Die Einstellung gilt für **alle**
> Projekte auf diesem Rechner — dein Name steht später an jedem Zwischenstand.

```sh
git config --global user.name "Anna Beispiel"
git config --global user.email "anna.beispiel@area-net.de"
```

Beide Befehle geben **keine Ausgabe** aus — das ist richtig so, „keine Meldung" heißt
bei Git fast immer „hat geklappt".

**Prüfung:**

```sh
git config --global user.name
```

Hier muss **dein eigener** Name stehen. Steht dort „Anna Beispiel", hast du das Ersetzen
vergessen — dann den Befehl oben mit deinem Namen wiederholen. Das ist gefahrlos, die
Einstellung wird einfach überschrieben.

### A4. Zeilenenden einstellen — nur Windows

```sh
git config --global core.autocrlf true
```

Verhindert, dass jede Datei, die du anfasst, als komplett geändert gilt.

### A5. Claude Code installieren

**macOS:**

```sh
curl -fsSL https://claude.ai/install.sh | bash
```

**Windows (in PowerShell, nicht in Git Bash):**

```powershell
irm https://claude.ai/install.ps1 | iex
```

Am Ende steht `Claude Code successfully installed!`.

**Jetzt das Terminal-Fenster schließen und ein neues öffnen.** Diesen Schritt
überspringen fast alle — und bekommen dann `command not found: claude`, obwohl gerade
„successfully installed" dastand.

**Prüfung:** `claude --version` gibt eine Versionsnummer aus.

Kommt trotz neuem Fenster `command not found`, steht die Lösung am Ende der
Installationsausgabe unter „Setup notes"; auf dem Mac hilft meistens:

```sh
echo 'export PATH="$HOME/.local/bin:$PATH"' >> ~/.zshrc
```

Danach wieder ein neues Fenster öffnen.

### A6. Einmal anmelden

```sh
claude
```

Es öffnet sich ein Browser-Fenster zum Anmelden. Passiert nichts, drücke **`c`** —
damit wird die Anmelde-Adresse kopiert und du fügst sie selbst in den Browser ein.
Zeigt der Browser stattdessen einen Code, füge ihn im Terminal bei
`Paste code here if prompted` ein.

Wenn `Login successful` erscheint: Enter drücken, dann `/exit` tippen. Fertig — die
Anmeldung gilt ab jetzt für diesen Rechner.

### ✅ Checkliste Teil A

- [ ] `git --version` gibt eine Nummer aus
- [ ] `git config --global user.name` gibt deinen Namen aus
- [ ] `claude --version` gibt eine Nummer aus
- [ ] Du warst einmal angemeldet (`Login successful`)

### ➡️ Weiter mit **Teil B** (neuer Ordner), **Teil C** (Framework nachrüsten) oder **Teil D** (Projekt aus GitLab)

---

## Teil B — Ein neues Projekt aufsetzen (leerer Ordner)

> **Setzt Teil A voraus** (einmal pro Rechner). Unsicher? `claude --version` — kommt
> eine Nummer, bist du hier richtig.
>
> Dieser Teil gilt **nur für einen neuen, leeren Ordner**. Existiert der Ordner schon
> mit Dateien darin, ist **Teil C** dein Weg. Liegt das Projekt samt Framework schon in
> GitLab, ist es **Teil D**.

### B1. Projektordner anlegen und Terminal darin öffnen

Lege den Ordner ganz normal im Finder bzw. Explorer an, z. B.
`Dokumente/Projekte/kunde-x`. Dann öffne das Terminal **in genau diesem Ordner**:

- **macOS:** Rechtsklick auf den Ordner → **Dienste** → **Neues Terminal beim Ordner**.
  Alternative: Terminal öffnen, `cd ` tippen (mit Leerzeichen!) und den Ordner aus dem
  Finder ins Terminal-Fenster ziehen — der Pfad wird eingefügt. Enter.
- **Windows:** Rechtsklick auf den Ordner → **Git Bash Here**
  (ggf. erst **Weitere Optionen anzeigen**).

**Prüfung — das ist der wichtigste Handgriff des ganzen Teils:**

```sh
pwd
```

Dort muss dein Projektpfad stehen, z. B. `/Users/anna/Dokumente/Projekte/kunde-x`.
Steht dort nur `/Users/anna`, bist du im falschen Ordner — dann würdest du dein
gesamtes Benutzerverzeichnis in ein Projekt verwandeln. Nicht weitermachen, bis `pwd`
stimmt.

### B2. Git im Ordner starten

```sh
git init
git checkout -b master
```

Ausgabe sinngemäß: `Leeres Git-Repository in … initialisiert` und
`Zu neuem Branch 'master' gewechselt`.

Die zweite Zeile stellt sicher, dass deine Hauptspur `master` heißt — so wie unser
GitLab es erwartet. Je nach Git-Version hieße sie sonst `main`.

### B3. Das Framework holen

Öffne im Browser die **Tags-Seite** des Framework-Projekts in GitLab und merke dir den
**obersten** `v`-Eintrag — das ist die neueste Version, z. B. `v2.0.0`. Schreib **keine**
Versionsnummer aus dieser Anleitung ab; nimm die aktuelle aus GitLab.

Dann das Framework als Ordner `.an_framework` hereinholen — **setze deine Version statt
`v2.0.0` ein**:

```sh
git clone --depth 1 --branch v2.0.0 http://gitlab.in.area-net.de/intern/ki-dev-framework.git .an_framework
rm -rf .an_framework/.git
```

GitLab fragt nach **Benutzername und Passwort** — deine normalen Firmen-Zugangsdaten.
Beim Tippen des Passworts bewegt sich nichts, kein Sternchen. Das ist Absicht.

Die zweite Zeile (`rm -rf .an_framework/.git`) macht aus dem geklonten Framework
gewöhnliche Dateien deines Projekts. Genau das ist gewollt: so lädt später ein einfaches
`git clone` die richtige Framework-Version automatisch mit — kein Submodule, kein
Sonderwissen.

**Prüfung:**

```sh
cat .an_framework/VERSION
ls .an_framework/skeleton
```

Die erste Zeile gibt eine Versionsnummer aus, die zweite listet `root` und `an_project`
auf. Kommt `No such file or directory`, hat der Clone nicht geklappt — Version prüfen
(oberster `v`-Tag aus GitLab) und B3 wiederholen, sonst im Team fragen.

### B4. Das Projekt einrichten — ein Befehl

Jetzt legt das Init-Skript die komplette Struktur an: den sichtbaren Ordner
`an_project/` (mit `work/`, `docs/`, Changelog, Projektbeschreibung), die versteckten
`.claude/`-Befehle, `CLAUDE.md` und die `.gitignore`. Setze deinen Projektnamen ein:

```sh
bash .an_framework/init.sh --name="Kunde X"
```

Es zeigt Modus **NEW** und fragt einmal **Proceed? [y/N]** — tippe `y` und Enter.

Das Skript **überschreibt nichts** und macht **keinen** Commit. Es legt nur Dateien an,
schreibt einen Bericht nach `an_project/SETUP-REPORT.md` und druckt am Ende den fertigen
Setup-Commit, den du gleich selbst abschickst (B6).

**Prüfung:**

```sh
ls -A
```

Es müssen mindestens diese Namen auftauchen — `.an_framework`, `.claude`, `.gitignore`,
`CLAUDE.md`, `an_project`. Das `-A` zeigt auch die Namen mit Punkt am Anfang.

```sh
ls -A .claude/commands
```

Hier müssen **zehn** Dateien stehen (`board.md`, `briefing.md`, `commit.md`, `done.md`,
`implement.md`, `new-epic.md`, `new-project.md`, `new-story.md`, `new-task.md`,
`refine.md`). Sind es weniger als zehn, ist die in **B3** gewählte Version zu alt — hier
stoppen und im Team fragen.

### B5. Kurz prüfen

`cat .gitignore` zeigt am Anfang `# --- ki-dev-framework` und Zeilen mit `.env` und
`*.key` — die Sperrliste für Zugangsdaten steht. Und die erste Zeile von `CLAUDE.md`
trägt jetzt deinen Projektnamen (`# Kunde X — Agent Guide`). Passt alles? Weiter zu B6.

### B6. Den ersten Zwischenstand speichern

Das Init-Skript hat dir am Ende genau diese zwei Befehle ausgegeben — füge sie ein
(deine Version steht in der Commit-Zeile):

```sh
git add -- .an_framework an_project .claude CLAUDE.md .gitignore
git commit -m "chore(setup): Projektgeruest aus ki-dev-framework 2.1.0"
```

Ausgabe: eine Zeile wie `[master (Root-Commit) a1b2c3d] chore(setup): …`.
`(Root-Commit)` steht nur beim allerersten Zwischenstand und ist richtig so.

> **Warum von Hand und nicht `/commit`?** Der Ordner `.an_framework` ist der
> read-only-Kern. `/commit` weigert sich absichtlich, ihn als Projektarbeit mitzunehmen.
> Genau dieser erste Commit **muss** ihn aber enthalten — er bringt den Kern überhaupt
> erst ins Projekt. Deshalb hier einmal von Hand mit **benannten Pfaden** (nie `git add -A`);
> ab Teil E wählt `/commit` die Dateien für dich. Aus demselben Grund darf dieser eine
> Commit direkt auf `master` landen — das Projektgerüst ist noch niemandes Arbeit.

Bricht der Befehl mit `*** Bitte geben Sie an, wer Sie sind.` ab, fehlt Schritt **A3** —
nachholen und wiederholen. Es ist nichts kaputt.

### B7. Projekt nach GitLab hochladen

Lass dir in GitLab ein leeres Projekt anlegen (oder leg es selbst an) und kopiere
dessen Adresse. Dann:

```sh
git remote add origin <die-gitlab-adresse-deines-projekts>
git push -u origin master
```

### ✅ Fertig — weiter mit Teil E

---

## Teil C — Das Framework in ein bestehendes Projekt einbauen

> **Setzt Teil A voraus** (einmal pro Rechner). Unsicher? `claude --version` — kommt
> eine Nummer, bist du hier richtig.
>
> Dieser Teil ist für Projekte, **die es schon gibt**: Der Ordner enthält bereits
> Dateien, Git schreibt sie schon mit, und das Framework soll nachträglich dazukommen.
> Ist der Ordner leer, ist **Teil B** dein Weg. Liegt das Projekt samt Framework schon
> in GitLab, ist es **Teil D**.
>
> **Gut zu wissen:** Das Init-Skript (C5) ist **non-destruktiv** — es überschreibt keine
> vorhandene Datei, legt eine schon vorhandene `CLAUDE.md` als `CLAUDE-ARCHIVE.md` beiseite
> und **merget** eine vorhandene `.claude/`-Konfiguration, statt sie zu ersetzen.
>
> **Windows:** alle Befehle dieses Teils gehören in **Git Bash**, nicht in PowerShell.

### C1. Terminal in der Projektwurzel öffnen

Wie in B1 — Rechtsklick auf den Projektordner → **Neues Terminal beim Ordner** (macOS)
bzw. **Git Bash Here** (Windows).

```sh
pwd
```

Dort muss dein Projektpfad stehen. Steht dort nur `/Users/deinname`, bist du im falschen
Ordner — **nicht weitermachen**.

### C2. Das Sicherheitsnetz spannen

Alles, was Git kennt und gespeichert hat, kannst du zurückholen. Alles andere nicht.
Deshalb zuerst:

```sh
git status --porcelain
```

**Es darf keine einzige Zeile erscheinen.** Kommt etwas, hast du ungespeicherte Arbeit —
die zuerst committen (oder jemanden fragen), dann hierher zurück. Kommt
`fatal: Kein Git-Repository`, ist dieser Ordner kein Projekt im Sinne dieser Anleitung.

Dann eine eigene Nebenspur, damit das Setup nicht auf der Hauptspur landet:

```sh
git checkout -b chore/framework-setup
```

> **Warum anders als in B6?** In einem neuen Projekt darf der Setup-Commit direkt auf
> `master` — dort ist noch niemandes Arbeit. Hier arbeiten Kolleginnen mit; das Setup
> geht über einen Merge Request wie jede andere Änderung.

### C3. Eine Stolperfalle ausschließen

```sh
git check-ignore -v .claude an_project CLAUDE.md
```

**Erwartete Ausgabe: keine.** Kommt etwas (z. B. `.gitignore:2:.claude/  .claude`),
sperrt dein Projekt genau die Ordner aus, die jetzt dazukommen — bei dir würde alles
funktionieren, bei allen anderen wäre das Framework unsichtbar. Die betreffende Zeile
aus der `.gitignore` entfernen, dann weiter.

(Einen Namenskonflikt mit einem eigenen `work/` gibt es nicht mehr: der Backlog liegt
jetzt unter `an_project/work/`, nicht im Projekt-Wurzelverzeichnis.)

### C4. Framework holen

Führe **B3** genau so aus, wie es dort steht (neueste Version aus GitLab, dann
`git clone --depth 1 --branch <version> … .an_framework` und `rm -rf .an_framework/.git`),
und komm hierher zurück.

**Prüfung:** `cat .an_framework/VERSION` gibt eine Nummer aus.

### C5. Einrichten — das Skript erledigt Erkennen, Bewahren und Mergen

```sh
bash .an_framework/init.sh --name="Mein Projekt"
```

Es zeigt Modus **RETROFIT** und fragt einmal **Proceed? [y/N]** — `y`, Enter. Das Skript:

- legt `an_project/` mit `work/`, `docs/`, Changelog und Projektbeschreibung an,
- **bewahrt** eine vorhandene `CLAUDE.md` als `CLAUDE-ARCHIVE.md` (daraus entsteht in C8
  die Projektbeschreibung — **nicht löschen**),
- **merget** eine vorhandene `.claude/settings.json` (deine Einträge bleiben, die
  Schutzregeln des Frameworks kommen dazu) und lässt einen gleichnamigen eigenen Command
  in Ruhe (er wird nach `an_project/_pre-framework/` gesichert und bleibt aktiv),
- hängt die Zugangsdaten-Sperrliste an deine vorhandene `.gitignore` an,
- schreibt einen Bericht nach `an_project/SETUP-REPORT.md`.

### C6. Den Bericht lesen

```sh
cat an_project/SETUP-REPORT.md
```

Hier stehen die **einzigen Stellen, die eine menschliche Entscheidung brauchen** —
Datei-Konflikte, ein nötiger settings-Merge von Hand (falls kein `python3` da war), und
was unter `an_project/_pre-framework/` gesichert wurde. Arbeite die Liste ab; im Zweifel
im Team fragen. Alles andere hat das Skript sicher erledigt.

### C7. Setup speichern und hochladen

Das Skript hat dir am Ende die benannten Befehle ausgegeben — die schicken das Setup als
eigenen Zwischenstand auf deiner Nebenspur ab:

```sh
git add -- .an_framework an_project .claude CLAUDE.md .gitignore CLAUDE-ARCHIVE.md
git commit -m "chore(setup): ki-dev-framework einbauen"
git push -u origin HEAD
```

(Gibt es keine `CLAUDE-ARCHIVE.md`, lass sie im `git add` einfach weg.) Danach in GitLab
einen **Merge Request** anlegen — der Link steht meist direkt in der Ausgabe. Erst wenn
der zusammengeführt ist, haben die Kolleginnen das Framework.

> **Benannte Pfade, kein `git add -A`:** In einem gewachsenen Projekt liegt oft Zeug
> herum, das nie in Git gehört. Taucht in `git status --short` etwas auf, das nach
> Zugangsdaten aussieht (`.env`, `*.key`, `*.pem`, `credentials…`): **stoppen und fragen.**

### C8. Erste Sitzung — Beschreibung aus dem Archiv übernehmen

Terminal in der Projektwurzel (Windows: jetzt **PowerShell**), dann `claude`, dann
zuerst `/context` prüfen wie in **E1**.

```
/new-project
```

Weil es `CLAUDE-ARCHIVE.md` gibt, stellt Claude die drei Fragen **mit einem Vorschlag
und der Fundstelle** statt blind: „In `CLAUDE-ARCHIVE.md`, Zeile 3–4, steht … — als
Beschreibung übernehmen?" Lies jeden Vorschlag und korrigiere ihn, wenn er nicht stimmt.
**Bestätige nichts, was du nicht selbst gelesen hast** — eine erfundene
Projektbeschreibung ist später nicht mehr von einer echten zu unterscheiden.

Danach schlägt Claude vor, wohin der Rest der alten Datei gehört (Build-Befehle,
Code-Regeln, Deployment) — als Tabelle, die erst nach deiner Bestätigung geschrieben wird.

### ✅ Fertig — weiter mit Teil E

---

## Teil D — Ein bestehendes Projekt übernehmen

> **Setzt Teil A voraus** (einmal pro Rechner). Unsicher? `claude --version` — kommt
> eine Nummer, bist du hier richtig.

### D1. Projektadresse besorgen

Die Adresse bekommst du aus GitLab (Schaltfläche **Code** → **Clone with HTTPS**) oder
von einer Kollegin. Nimm die Adresse, die mit `http` beginnt — nicht die mit `git@`.

### D2. Klonen — ein einfaches `git clone` genügt

```sh
git clone <die-projekt-adresse>
```

Seit v2 liegt das Framework als gewöhnliche Dateien mit im Projekt und kommt beim Klonen
automatisch mit. **Kein `--recurse-submodules`, kein `git submodule update` mehr** — die
frühere Falle „Ordner leer, sieht aber sauber aus" gibt es nicht mehr.

### D3. Prüfen, ob das Framework da ist

```sh
cd <projektordner>
ls .an_framework
```

Dort müssen Dateien stehen (u. a. `AGENT-GUIDE.md`). Fehlt der Ordner oder ist er leer,
ist beim Klonen etwas schiefgegangen — im Team fragen, nicht selbst herumprobieren.

### ✅ Fertig — weiter mit Teil E

---

## Teil E — Der Arbeitsalltag

### E1. Claude im richtigen Ordner starten

Öffne das Terminal in der **Projektwurzel** (wie in B1; auf Windows jetzt **PowerShell**,
nicht Git Bash) und starte:

```sh
claude
```

Über der Eingabezeile zeigt Claude Code den Ordner an, in dem es läuft — das ist
deine erste Kontrolle.

**Die zweite Kontrolle ist die wichtigere.** Tippe als Allererstes:

```
/context
```

In der Tabelle **Memory Files** müssen **zwei** Zeilen stehen: `CLAUDE.md` **und**
`.an_framework/AGENT-GUIDE.md`. Fehlt die zweite, kennt Claude die Framework-Regeln nicht
— es arbeitet dann trotzdem freundlich weiter, erfindet aber Nummern und legt Dateien
falsch ab. **Das ist der teuerste Fehler überhaupt, weil er wie Erfolg aussieht.**
In dem Fall: `/exit`, in die Projektwurzel wechseln, neu starten.

> Achtung, Falle: Die Slash-Commands funktionieren auch aus einem Unterordner heraus.
> Dass du `/board` tippen kannst, ist also **kein** Beweis, dass du richtig stehst.
> Nur `/context` ist der Beweis.

### E2. Der Vertrauensdialog beim ersten Start

Beim allerersten Start in einem Projekt fragt Claude einmal, ob du diesem Ordner
vertraust, und listet darunter gut ein Dutzend Git- und Datei-Befehle auf.

Wähle **„Yes, I trust this folder"**. Die Liste sind genau die Befehle, die `/commit`,
`/implement` und `/done` brauchen, plus Lese-/Schreibrechte für die Projekt-Ordner.
Wählst du „No", fragt dich Claude danach bei **jedem einzelnen** Git-Aufruf erneut — das
wirkt, als wäre etwas kaputt, ist es aber nicht.

Bei allen späteren Rückfragen (z. B. vor dem Ändern einer Datei außerhalb der Projekt-Ordner)
genügt **Enter** für Ja. `/board` und die `/new-…`-Befehle fragen normalerweise gar nicht;
Git nutzen `/commit`, `/implement` und `/done` — die dafür nötigen Befehle stehen schon
in der Vertrauensliste.

### E3. Einmal pro Projekt: `/new-project`

```
/new-project
```

Claude stellt dir vier Fragen (Titel, Beschreibung, Ziel, Tech-Stack) und schreibt die
Antworten in `an_project/project-description.md`. Beim Tech-Stack wählst du aus einer
Liste (Shopware Projekt · Shopware Plugin · Wordpress · Individual); die passende
Stack-Beschreibung wird automatisch als `an_project/docs/tech-stack.md` mitgeladen.
Einfach im Chat antworten.

### E4. Arbeit anlegen

Arbeit ist dreistufig: **Epic** (großes Ziel) → **Story** (Ausschnitt davon) →
**Task** (konkrete Aufgabe). Gebaut wird immer auf Task-Ebene — du kannst Claude aber
auch eine ganze Story oder ein Epic geben, dann arbeitet es deren Tasks der Reihe nach
ab (siehe E6). Epic und Story sind optional — ein Task darf auch allein stehen.

```
/new-epic  "Kunden-Login"
/new-story 001 "Passwort zuruecksetzen"
/new-task  001-001 "E-Mail-Adresse pruefen"
```

Die Nummern entstehen dabei automatisch: Das Epic bekommt `001`, die Story darunter
`001-001`, der Task `001-001-0001`. **Du vergibst nie selbst eine Nummer** — du gibst
nur an, worunter etwas gehört.

### E5. Überblick behalten

```
/board
```

Zeigt alles auf einen Blick: was offen ist, was blockiert ist und was als Nächstes
dran wäre. Ändert nichts — du kannst es jederzeit bedenkenlos tippen.

### E6. Arbeiten lassen

```
/implement 001-001-0001
```

Claude legt zuerst eine **Nebenspur** (Branch) für den Task an, setzt die Aufgabe um,
prüft sie, **committet automatisch** (du siehst den Text vorher und bestätigst) und setzt
den Status auf `review`. **`review` heißt: Claude ist fertig, jetzt schaust du drauf.**

Statusfolge: `todo` (offen) → `in-progress` (in Arbeit) → `review` (wartet auf dich) →
`done` (abgenommen).

**Du kannst auch eine ganze Story oder ein Epic angeben** — Claude erkennt an der Nummer,
was gemeint ist:

```
/implement 001-001        ← die ganze Story: alle ihre Aufgaben nacheinander
/implement 001            ← das Epic: Claude plant nur und fragt vor jeder Story nach
```

Bei einer Story gibt es **eine** gemeinsame Nebenspur für die ganze Story, und pro Aufgabe
einen Zwischenstand darin. Hat die Story noch gar keine Aufgaben, schlägt Claude dir welche
vor, legt die bestätigten an — und fragt **danach noch einmal**, bevor gebaut wird. Ein
Epic setzt Claude nie in einem Rutsch um; nach jeder Story hältst du an und entscheidest.

> **`/done` gibt es auch.** Es schließt ein reviewtes Item ab, indem es die Nebenspur
> **lokal** in die Hauptspur zusammenführt (merge) und `done` setzt — ganz ohne etwas ins
> Netz zu schicken. Bei einer Story werden ihre Aufgaben gleich mit auf `done` gesetzt.
> Das passt für Solo- oder interne Projekte. In einem Team mit GitLab gehst du stattdessen
> den Weg über Push + Merge Request (E7/E8).

### E7. Zwischenstand speichern

```
/commit
```

`/implement` committet die Arbeit bereits selbst. `/commit` brauchst du für Änderungen
**außerhalb** eines Tasks: Claude bündelt sie, schlägt dir einen Text vor und **wartet auf
deine Bestätigung**, bevor irgendetwas gespeichert wird. Lies den Vorschlag, bestätige ihn
oder sag, was anders soll.

Arbeitest du gerade auf der Hauptspur `master`, bietet Claude dir an, vorher eine
Nebenspur (einen „Branch") anzulegen — **nimm das Angebot an**. Auf der Hauptspur wird
nie direkt gearbeitet.

### E8. Abschicken — das macht kein Befehl für dich

**`/commit` speichert nur auf deinem Rechner.** Damit deine Arbeit bei den
Kolleginnen ankommt, fehlen zwei Schritte. Beende Claude mit `/exit` und tippe im
Terminal:

```sh
git push -u origin HEAD
```

**`HEAD` bleibt genau so stehen — das ist kein Platzhalter.** Git setzt den Namen
deiner aktuellen Nebenspur selbst ein.

**Prüfung:** In der Ausgabe steht eine Zeile mit `->` und dem Namen deiner Nebenspur,
meist dazu ein anklickbarer GitLab-Link zum Anlegen des Merge Requests.
Danach in GitLab einen **Merge Request** anlegen — das ist die Bitte, deine Nebenspur
in die Hauptspur zu übernehmen. Dort schaut jemand drüber, bevor es zusammengeführt wird.

Ohne diese zwei Schritte liegt deine Arbeit nur auf deinem Rechner.

### E9. Die Notausgänge

| Situation | Was du tust |
|---|---|
| Claude soll sofort aufhören | **Esc** |
| Sitzung beenden | `/exit` |
| Letzte Sitzung fortsetzen | `claude -c` |
| Welche Befehle gibt es? | `/` tippen — die Liste erscheint |

Im Terminal kannst du **nicht klicken**. Bewege dich mit den Pfeiltasten.

### E10. Was du nie tun solltest

> Führe **keinen** Git-Befehl aus, den du aus dem Internet oder von einer KI hast, wenn
> er `--force`, `--hard`, `reset`, `clean` oder `rm -rf` enthält. Diese Befehle löschen
> Arbeit, die Git **nicht** zurückholen kann.

Wenn etwas komisch aussieht: `git status` schadet nie und zeigt dir den Stand. Alles
darüber hinaus: Screenshot machen und fragen.

Die einzige Ausnahme, die du gefahrlos benutzen darfst, ist
`git checkout -- .an_framework` (siehe F9): Hat jemand versehentlich im read-only-Kern
etwas geändert, setzt das den Ordner `.an_framework` auf den committeten Stand zurück —
deine eigene Arbeit außerhalb davon rührt es nicht an.

**Und die beste Gewohnheit:** committe früh und oft. Ein Commit kostet nichts, und
alles, was committet ist, bekommt man zurück.

---

## Teil F — Wenn etwas nicht geht

Jeder Eintrag: **Auf dem Bildschirm steht** → **Das bedeutet** → **Das tust du**.

> Deine Meldung kann etwas anders formuliert sein als hier — Git spricht auf manchen
> Rechnern Deutsch, auf anderen Englisch. Achte auf den **Dateinamen** in der Meldung,
> nicht auf den genauen Satz.

### F1. „Framework not initialised"

Diese Meldung hat **zwei** verschiedene Ursachen. Prüfe beide, in dieser Reihenfolge:

1. `pwd` — steht dort nicht die Projektwurzel? Das ist die häufigste Ursache: Claude
   wurde in einem Unterordner gestartet. Mit `/exit` beenden, in die Projektwurzel
   wechseln (wo `CLAUDE.md` und `.an_framework` liegen) und neu starten.
2. `ls .an_framework` — kommt nichts? Dann fehlt der Kern. War das ein frisch geklontes
   Projekt, ist beim Klonen etwas schiefgegangen (Teil D); sonst wurde das Setup
   (Teil B/C) nie fertig gemacht. Im Team fragen.

### F2. `Could not find remote branch <version>` / `Remote branch not found`

**Bedeutet:** Die Version, die du in B3 eingetippt hast, gibt es nicht.
**Tu das:** Auf der **Tags-Seite** des Framework-Projekts in GitLab den obersten
`v`-Eintrag nehmen und den `git clone`-Befehl aus **B3** damit wiederholen.

### F3. `*** Bitte geben Sie an, wer Sie sind.`

**Bedeutet:** Git weiß nicht, wer du bist. Es ist nichts kaputt.
**Tu das:** Schritt **A3** nachholen, dann den `git commit` wiederholen.

### F4. `fatal: Kein Git-Repository (oder irgendeines der Elternverzeichnisse)`

**Bedeutet:** Du bist im falschen Ordner.
**Tu das:** `pwd` prüfen und in die Projektwurzel wechseln.

### F5. `fatal: destination path '.an_framework' already exists`

**Bedeutet:** Du hast das Framework in B3 schon geholt.
**Tu das:** Überspringen und mit **B4** weitermachen.

### F6. `command not found: claude` / `claude is not recognized`

**Bedeutet:** Das Terminal kennt den Befehl noch nicht.
**Tu das:** Terminal-Fenster schließen, neues öffnen, nochmal versuchen. Hilft das
nicht: siehe **A5**.

### F7. Claude kennt das Nummernschema nicht / legt Dateien falsch ab

**Bedeutet:** Die Framework-Regeln sind nicht geladen — fast immer der falsche
Startordner.
**Tu das:** `/context` prüfen (siehe **E1**). Fehlt dort `.an_framework/AGENT-GUIDE.md`:
`/exit`, in die Projektwurzel wechseln, neu starten.

### F8. Claude fragt bei jedem Git-Befehl um Erlaubnis

**Bedeutet:** Im Vertrauensdialog wurde „No" gewählt.
**Tu das:** `/exit` und neu starten — der Dialog erscheint wieder. Diesmal
„Yes, I trust this folder" wählen.

### F9. `.an_framework` taucht in `git status` auf

**Auf dem Bildschirm steht** eine Zeile mit `.an_framework/…` unter den geänderten Dateien.
**Bedeutet:** Jemand hat im read-only-Kern etwas verändert. Das gehört nicht in einen
normalen Zwischenstand.
**Tu das:**

```sh
git checkout -- .an_framework
```

Das setzt den Kern auf den committeten Stand zurück. Wolltest du das Framework
**absichtlich** aktualisieren, ist das ein eigener Schritt ([how-to-use.md §8](how-to-use.md)) —
nie mit normaler Projektarbeit vermischen. Im Zweifel fragen.

### F10. `/commit` bricht ab und redet von `.an_framework`

**Bedeutet:** Es liegt eine Änderung im read-only-Kern zum Speichern bereit — die
Schutzfunktion hat angeschlagen (der Fall aus F9).
**Tu das:** Siehe F9. Kein Fehler, sondern gewollt.

### F11. `/commit` bricht ab und nennt eine Datei mit Zugangsdaten

**Bedeutet:** Es sollte gerade ein Passwort oder Schlüssel mitgespeichert werden, und
`/commit` hat das verhindert.
**Tu das:** **Nicht** selbst weiterprobieren. Jemanden fragen. Das ist die wichtigste
Schutzfunktion des ganzen Systems.

### F12. Windows: `Raw mode is not supported`

**Bedeutet:** Du hast Claude in Git Bash gestartet.
**Tu das:** Claude in **PowerShell** starten (siehe **A1**).

### Wenn hier nichts passt

```sh
claude doctor
```

Dann einen Screenshot des **ganzen** Fensters machen und im Team fragen.
**Nicht selbst weiterprobieren** — Fragen kostet nichts, ein falscher Befehl kann
Arbeit vernichten.

---

## Glossar

| Begriff | Bedeutung |
|---|---|
| **Backlog** | Alles noch nicht Erledigte — bei uns der Ordner `an_project/work/`. |
| **Branch / Nebenspur** | Eine Spur, auf der du arbeitest, ohne den Stand zu stören, den alle anderen benutzen. |
| **`CLAUDE-ARCHIVE.md`** | Die eingefrorene Kopie der `CLAUDE.md`, die ein Projekt vor dem Framework-Einbau hatte. Wird nicht geladen und gilt nicht — sie ist nur die Quelle für die Projektbeschreibung. Nicht löschen. |
| **CHANGELOG** | Ein laufendes Protokoll. Alle Befehle außer `/board`, `/briefing` und `/commit` schreiben dort automatisch mit. Du trägst nichts von Hand ein. |
| **Charter** | Die Datei `an_project/project-description.md`: wofür das Projekt existiert und wann es fertig ist. |
| **Commit / Zwischenstand** | Ein gespeicherter Stand mit einer kurzen Notiz, was du geändert hast. Bleibt zunächst auf deinem Rechner. |
| **Epic** | Ein großes Ziel über mehrere Wochen, z. B. „Kunden-Login". |
| **`.an_framework/`** | Der mitcommittete Ordner mit den Framework-Regeln. **Nur lesen, nie ändern.** |
| **`an_project/`** | Der sichtbare Ordner mit deinen Projektdateien: Backlog, Doku, Changelog, Projektbeschreibung. |
| **Framework** | Unsere gemeinsame Arbeitsgrundlage: feste Ordner, feste Befehle, feste Regeln — damit jedes Projekt gleich aussieht. |
| **`.gitignore`** | Eine Liste von Dateien, die Git bewusst **nicht** mitschreibt — vor allem Passwörter und Zugangsdaten. |
| **GitLab** | Unsere Seite im Firmennetz, auf der alle Projekte liegen. |
| **Hauptspur / Integrationsbranch** | Die Spur mit dem fertigen, geprüften Stand (bei uns `master`). Hier arbeitest du nie direkt. |
| **ID `EEE-SSS-TTTT`** | Die Nummer eines Work-Items: Epic-Story-Task, z. B. `001-002-0003`. Du vergibst sie nie selbst. |
| **klonen** | Ein Projekt von GitLab einmalig auf deinen Rechner herunterladen. |
| **Merge Request (MR)** | Die Bitte in GitLab, deine Nebenspur in die Hauptspur zu übernehmen. |
| **Version festlegen** | Welche Framework-Version dein Projekt benutzt, steht fest durch den Tag, den du in B3 geklont hast — sie liegt als `.an_framework/VERSION` mit im Projekt. |
| **Push** | Deine Zwischenstände zu GitLab hochladen, damit andere sie sehen. |
| **Repository / Repo** | Ein Ordner, dessen Änderungen Git mitschreibt — umgangssprachlich „das Projekt". |
| **Session / Sitzung** | Ein laufendes Gespräch mit Claude in einem Terminal-Fenster. Beenden mit `/exit`. |
| **Skelett** | Die Startausstattung, die beim Einrichten genau einmal aus dem Framework kopiert wird und danach dir gehört. |
| **Slash-Command** | Ein Kurzbefehl an Claude, der mit `/` beginnt, z. B. `/board`. Tippe `/`, um alle zu sehen. |
| **Status** | Wo ein Task steht: `todo` → `in-progress` → `review` → `done` (plus `blocked` = wartet auf etwas anderes). |
| **Story** | Ein abgeschlossener Ausschnitt eines Epics, z. B. „Passwort zurücksetzen". |
| **Setup-Skript (`init.sh`)** | Das Skript, das beim Einrichten die Projektstruktur anlegt — du rufst es in Teil B/C einmal auf. |
| **Tag / Version** | Ein Name für einen bestimmten Stand, z. B. `v1.1.0`. |
| **Task** | Eine konkrete, umsetzbare Aufgabe, z. B. „E-Mail-Adresse prüfen". Nur Tasks setzt Claude um. |
| **Work-Item** | Sammelbegriff für Epic, Story und Task. |

---

## Wo es weitergeht

- [how-to-use.md](how-to-use.md) — die ausführliche Fassung: warum das Framework so
  gebaut ist, was die Overlay-Docs `an_project/docs/git.md` und
  `an_project/docs/guidelines.md` sind, und wie das Framework aktualisiert wird.
- [update.md](update.md) — **das Framework auf eine neuere Version heben.** Schritt für
  Schritt wie diese Anleitung hier, ~10 Minuten. Das macht **eine** Person im Team, einmal
  pro Version. Für alle anderen heißt ein Update nur: `git pull` — der neue
  Framework-Stand kommt als gewöhnliche Dateien mit.
