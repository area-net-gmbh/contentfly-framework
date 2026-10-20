<!-- PURPOSE: Schritt-für-Schritt-Anleitung, um ein bestehendes Projekt auf eine neue Framework-Version zu heben. EINZIGE Quelle der Update-Befehle — how-to-use.md §8 verweist nur hierher, README.md verlinkt nur. Keine Setup-Befehle fuer NEUE Projekte: die stehen ausschliesslich in quickstart.md. -->

# Framework aktualisieren — Schritt für Schritt

Dein Projekt trägt seine Framework-Version als **mitcommitteten Ordner** `.an_framework/`.
Ein Update heißt: diesen Ordner gegen einen neueren getaggten Stand tauschen, die neuen
Skelett-Dateien nachziehen und beides als **eigenen** Commit abschicken.

Rechne mit **10 Minuten**. Du brauchst keine Vorkenntnisse außer Terminal öffnen und
Befehle einfügen.

> **Falsche Datei?**
> - Du richtest ein **neues** Projekt ein, oder baust das Framework zum **ersten Mal** in
>   ein bestehendes → [quickstart.md](quickstart.md), Teil B bzw. Teil C.
> - Du hast das Projekt gerade nur geklont → gar nichts tun. Die Version kam mit.
>   [quickstart.md](quickstart.md), Teil D.
> - Du willst wissen, **warum** es so läuft → [how-to-use.md](how-to-use.md) §8.

---

## Das Wichtigste in vier Sätzen

1. Der Kern `.an_framework/` wird **komplett ersetzt**, nicht gemerged.
2. Deine Projektdateien (`an_project/`, `CLAUDE.md`, `.claude/`, dein Code) fasst das
   Update **nicht** an.
3. Neue Slash-Commands und neue Doku-Vorlagen kommen **nicht** automatisch mit — dafür
   läuft `init.sh` noch einmal (Schritt 5). Es überschreibt nie etwas Gefülltes.
4. Framework-Update und Projektarbeit gehören **nie** in denselben Commit.

---

## Schritt 0 — Sauber starten

Öffne das Terminal in der **Projektwurzel** (dort, wo `CLAUDE.md` und `an_project/`
liegen) und prüfe, dass nichts Unfertiges herumliegt:

```sh
git status
```

Erwartet: **„nichts zu committen, Arbeitsverzeichnis unverändert"**. Steht dort etwas
anderes, committe oder verwirf deine Arbeit zuerst — sonst mischt sich dein Stand in den
Update-Commit. Bist du mitten in einem Task, ist `/commit` der richtige Weg dafür.

Wechsle außerdem auf deinen Integrationsbranch (meist `master`) oder lege einen eigenen
Branch für das Update an. Auf einem fremden Task-Branch hat ein Framework-Update nichts
zu suchen.

**Merke dir deine aktuelle Version** — du willst nachher sehen, was sich geändert hat:

```sh
cat .an_framework/VERSION
```

## Schritt 1 — Die neue Version heraussuchen

Öffne im Browser die **Tags-Seite** des Framework-Projekts in GitLab und nimm den
**obersten** `v`-Eintrag. Schreib **keine** Versionsnummer aus dieser Anleitung ab — sie
ist morgen veraltet.

Ohne Browser geht es auch im Terminal:

```sh
git ls-remote --tags http://gitlab.in.area-net.de/intern/ki-dev-framework.git
```

Die letzte Zeile mit einem `v…`-Namen ist die neueste Version.

> Ist die gefundene Version **dieselbe** wie in Schritt 0 — fertig, du bist aktuell.
> Hier aufhören.

## Schritt 2 — Ist etwas zu beachten?

Sieh **vor** dem Tausch in die Release-Hinweise der neuen Version (siehe *Was welche
Version verlangt* am Ende dieser Datei). Manche Versionen benennen einen Command um oder
brauchen einen Handgriff mehr. Überspring das nicht — es ist der einzige Schritt, den
`init.sh` dir nicht abnimmt.

## Schritt 3 — Den Kern austauschen

Zwei Wege, **du brauchst nur einen**. Das Ergebnis ist identisch, Datei für Datei.

### Variante 1 — mit Git

**`{VERSION}` ist ein Platzhalter** — ersetze ihn durch die Nummer aus Schritt 1, **mit**
führendem `v`, also z. B. `v3.4.0`. In dieser Anleitung steht absichtlich keine echte Nummer:
eine abgeschriebene wäre bald veraltet und du würdest beim Update auf einem alten Stand landen,
ohne es zu merken. Nicht ersetzt, bricht der Befehl ab — genau das soll er.

```sh
rm -rf .an_framework
git clone --depth 1 --branch {VERSION} http://gitlab.in.area-net.de/intern/ki-dev-framework.git .an_framework
rm -rf .an_framework/.git
```

Was die drei Zeilen tun:

- Zeile 1 wirft den alten Kern weg. Das ist ungefährlich: er enthält **nur** Framework-
  Dateien, nie etwas von dir. Alles, was dir gehört, liegt außerhalb.
- Zeile 2 holt den neuen Stand. GitLab fragt nach **Benutzername und Passwort** — deine
  normalen Firmen-Zugangsdaten. Beim Tippen des Passworts bewegt sich nichts, kein
  Sternchen. Das ist Absicht.
- Zeile 3 macht aus dem geklonten Framework gewöhnliche Dateien deines Projekts. Dadurch
  bringt später ein einfaches `git clone` bei allen anderen genau diese Version mit — kein
  Submodule, kein Sonderwissen.

### Variante 2 — ZIP herunterladen (ohne Git)

Möglich für Zielversionen **ab 3.4.0** — an jedem Release hängt ein fertiges ZIP des Kerns.

Lade auf der **Releases**-Seite beim gewünschten Release die Datei `an_framework-v….zip`
herunter (**nicht** *Source code (zip)* — darin heißt der Ordner anders). Dann, in der
Projektwurzel, **`{VERSION}` wieder durch deine Nummer ersetzen**:

```sh
rm -rf .an_framework
unzip -q ~/Downloads/an_framework-{VERSION}.zip -d .
```

Die erste Zeile ist dieselbe wie in Variante 1 und aus demselben Grund ungefährlich. Die
zweite legt den neuen Kern als `.an_framework` an — ein `rm -rf .an_framework/.git` braucht
es hier nicht, im ZIP ist keine Git-Historie.

> **Windows:** In Git Bash gibt es `unzip` nicht. Lösche `.an_framework` im Explorer und
> extrahiere das ZIP per Rechtsklick → **Alle extrahieren…** in die Projektwurzel.

**Prüfung:**

```sh
cat .an_framework/VERSION
```

Hier muss jetzt die neue Nummer stehen. Kommt `No such file or directory`, hat der Clone
nicht geklappt → *Wenn etwas schiefgeht*, Fall 1.

## Schritt 4 — Kurz hinsehen, was neu ist

```sh
git status --short .an_framework
```

Das listet dir die geänderten Kern-Dateien. Interessant sind vor allem drei Orte:

| Ort | Bedeutung |
|---|---|
| `.an_framework/skeleton/root/.claude/commands/` | Kam ein **Slash-Command** dazu? |
| `.an_framework/skeleton/root/.claude/settings.json` | Kamen **Permissions** dazu? |
| `.an_framework/skeleton/root/.codex/rules/anna.rules` | Kamen Codex-**Befehlsfreigaben** dazu? |
| `.an_framework/skeleton/an_project/docs/` | Kam eine **Doku-Vorlage** dazu? |

Alles unter `skeleton/` wandert **nicht** von allein in dein Projekt — dafür ist der
nächste Schritt da.

## Schritt 5 — Die neuen Skelett-Dateien nachziehen

**Das `--update` ist Pflicht.** Ohne die Option lässt das Skript ein bereits eingerichtetes
Projekt bewusst in Ruhe und macht gar nichts.

```sh
bash .an_framework/init.sh --update
```

Es zeigt `Mode : UPDATE` und **ergänzt nur, was fehlt**: neue Command-Stubs, neue
Permissions (deine bestehenden werden mit den neuen vereinigt, nichts fällt weg), fehlende
Doku-Vorlagen. **Gefüllte Dateien von dir überschreibt es nie**, und einen Commit macht es
auch nicht.

Ob dein Projekt **slim** oder **full** ist, liest es aus `an_project/.framework-profile` —
du wirst nicht erneut gefragt, und ein Slim-Projekt bekommt keine schweren Doku-Dateien
untergeschoben.

Es schreibt einen Bericht nach `an_project/SETUP-REPORT.md`. **Lies ihn** — dort steht
namentlich, was dazugekommen ist und ob irgendwo ein Konflikt gemeldet wurde:

```sh
cat an_project/SETUP-REPORT.md
```

Meldet der Bericht einen **Command-Konflikt** (deine eigene Fassung eines Stubs weicht von
der des Frameworks ab), bleibt **deine** aktiv und die des Frameworks liegt als Kopie
daneben. Entscheide von Hand, welche du behalten willst.

## Schritt 6 — Prüfen, dass alles da ist

```sh
cat .an_framework/VERSION
ls -A .claude/commands
ls -A .agents/skills
```

Die Versionsnummer ist die neue. In `.claude/commands` und `.agents/skills` liegt für jeden
gemeinsamen Workflow ein Adapter. Fehlt einer, ist Schritt 5 nicht gelaufen.

Starte danach die verwendete Runtime aus der Projektwurzel neu. Claude Code zeigt die neuen
Slash-Commands unter `/`; Codex zeigt die neuen `an-*` Skills unter `$`.

Steht im `SETUP-REPORT.md` **ADR migration needed**, merke dir den Hinweis, führe die Migration
aber erst **nach dem separaten Framework-Commit in Schritt 7** aus. Der Ablauf steht dort.

## Schritt 7 — Als eigenen Commit abschicken

Das Update ist ein **eigener** `chore(framework)`-Commit. Niemals zusammen mit
Projektarbeit — deshalb weigert sich `/commit` auch, ein gestagtes `.an_framework`
als Projektarbeit aufzunehmen.

`init.sh` hat dir den fertigen Befehl am Ende von Schritt 5 bereits ausgedruckt — nimm den.
Er lautet:

```sh
git add -- .an_framework .claude .codex .agents AGENTS.md an_project
git commit -m "chore(framework): Framework auf 3.2.0"
```

Die Runtime-Adapter gehören mit hinein, `an_project` wegen des Setup-Berichts und eventueller
neuer Doku-Vorlagen. Beachte: **`git add` mit
ausdrücklichen Pfaden**, nie `git add -A` oder `git add .` — sonst rutscht unfertige
Projektarbeit mit in den Framework-Commit.

> Nur hier ist ein `git commit -m` in Ordnung: Der Text ist einzeilig, ohne Anführungs-
> oder Sonderzeichen, und stammt nicht aus einem Modell. Für Projektarbeit bleibt es bei
> `/commit`.

### Schritt 7a — Bestehende Inline-ADRs migrieren

Nur wenn der Setup-Report **ADR migration needed** meldet: Starte die Runtime neu und führe
`/migrate-adrs` in Claude Code oder `$an-migrate-adrs` in Codex aus. Der Workflow behält
vorhandene ADR-Nummern, führt Tabellenzeile und Detailabschnitt zusammen, fragt fehlende
Begründungen gesammelt nach und lässt in `architecture.md` nur den verlinkten Index stehen.
Committe diese Projekt-Dokumentation anschließend mit dem normalen `commit`-Workflow. Sie bleibt
damit sauber vom vorangegangenen Framework-Update-Commit getrennt.

Zum Schluss hochladen, damit dein Team dieselbe Version bekommt:

```sh
git push
```

Ab jetzt holt sich jede Kollegin die neue Framework-Version mit einem gewöhnlichen
`git pull` — ohne Zusatzbefehl.

---

## Wenn etwas schiefgeht

**1 · `Could not find remote branch v… / Remote branch not found`**
Steht im Befehl wörtlich `{VERSION}`, ist nur der Platzhalter stehengeblieben — Nummer aus
Schritt 1 einsetzen und erneut ausführen. Sonst existiert der Tag nicht: fast immer ein
Tippfehler oder eine ausgedachte Nummer. Zurück zu Schritt 1 und die Version wirklich aus
GitLab holen. Steht `.an_framework` jetzt leer oder
gar nicht da: einfach Schritt 3 mit der richtigen Version wiederholen.

**2 · `fatal: destination path '.an_framework' already exists`**
Das `rm -rf .an_framework` aus Zeile 1 hat gefehlt oder ist fehlgeschlagen. Führe es
einzeln aus, dann den Clone erneut.

**3 · „Framework not initialised" beim Aufruf eines Commands**
Claude findet `.an_framework/` nicht. Zwei Ursachen: der Clone ist schiefgegangen
(Schritt 3 prüfen), oder du hast Claude aus einem **Unterordner** gestartet. Beende mit
`/exit` und starte neu in der Projektwurzel.

**4 · Ein Command fehlt in der `/`-Liste**
Schritt 5 wurde übersprungen oder ohne `--update` gelaufen. Meldet das Skript *„This
project already looks initialised … Nothing to do"*, hast du die Option vergessen:
`bash .an_framework/init.sh --update` nachholen und Claude neu starten.

**5 · Du willst zurück auf die alte Version**
Ein Rollback ist genau dasselbe Verfahren mit der **alten** Nummer: Schritt 3 mit dem
alten Tag, dann Schritt 7. Neue Skelett-Dateien, die `init.sh` bereits angelegt hat,
bleiben liegen — sie stören nicht, du kannst sie von Hand löschen.

**6 · Etwas an deinem Projekt wurde überschrieben**
Sollte nicht passieren — weder der Kern-Tausch noch `init.sh` fassen gefüllte
Projektdateien an. Falls doch: Du hast noch nichts committet, also holt
`git checkout -- <datei>` den letzten committeten Stand zurück.

---

## Was welche Version verlangt

Zusätzliche Handgriffe über die Schritte oben hinaus. Steht deine Zielversion nicht in der
Tabelle, sind keine nötig.

| Version | Zusätzlich zu tun |
|---|---|
| **v3.4.0** | Drei Neuerungen, **keine zusätzlichen Handgriffe** über Schritt 5 hinaus. **(1) Auslieferung:** an jedem Tag hängt jetzt ein fertiges ZIP des Kerns (`an_framework-v….zip`) — ANNA lässt sich damit ohne Git und ohne Passwort im Terminal einrichten und aktualisieren, siehe **Schritt 3, Variante 2**. Inhaltlich ist es Datei für Datei derselbe Kern. **(2) Optionale Angaben:** `/new-project` fragt Ziel, Auftraggeber und Termine jetzt als *optional* — bei einem laufenden Projekt gibt es die oft nicht. Abgewählte Angaben werden als `entfällt` festgehalten und **nie wieder nachgefragt**; leer gelassene bleiben „offen" und kommen wie bisher zurück. Das gilt auch für ganze Kapitel der Stakeholder-Doku (`status: entfällt`). Bestehende Projekte profitieren sofort, ohne eine Datei zu ändern: den Zustand wertet der Kern aus. **(3) Testumgebung:** in einem Full-Projekt wird ab jetzt grundsätzlich **lokal in Docker** getestet — Features, neue Module und Plugins, Versions-Upgrades. Staging oder Live fasst ANNA nur an, wenn du es in der jeweiligen Nachricht ausdrücklich sagst und die Umgebung benennst. |
| **v3.3.0** | Neu ist die **Stakeholder-Doku**: `an_project/stakeholder/` plus der Befehl `/doc` beziehungsweise `$an-doc`, der daraus eine HTML-Dokumentation für Auftraggeber, Designer und Geschäftsführung baut und im Browser öffnet — ohne Server, ohne Internet. Schritt 5 ist hier **Pflicht**: er kopiert das Kapitel-Grundgerüst und die neuen Befehls-Stubs ins Projekt und ergänzt in beiden Laufzeiten die Freigabe für das Build-Script. Danach einmal `/doc` tippen; die Kapitel sind zunächst leer und füllen sich, während gearbeitet wird (`/implement` zieht betroffene Kapitel am Task-Ende nach, `/done` macht bei Story und Epic einen vollständigen Durchlauf). Voraussetzung ist ein vorhandenes `python3` — auf macOS ist es dabei. Das erzeugte `an_project/stakeholder/html/` wird mitcommittet, damit die Doku auch ohne ANNA aus einem Checkout geöffnet werden kann; es wird bei jedem Bauen neu erzeugt und darf nie von Hand bearbeitet werden. |
| **v3.2.5** | Reines Kontext-Update — **keine zusätzlichen Handgriffe**, der Kern-Austausch nach Schritt 5 genügt. ANNA empfiehlt jetzt am Ende von `/done` beziehungsweise `$an-done` einen Kontext-Reset: Item geschlossen, Zustand vollständig auf Platte, also neue Session (`/clear` in Claude Code, `/new` in Codex) und weiter mit `implement <next-id>`. Innerhalb einer Story wird bewusst **nicht** zurückgesetzt — dort sind die geladenen Regeldateien genau das, was jeden Task nach dem ersten billig macht. Der Reset bleibt dein Tastendruck: weder ein Command noch ein Hook kann eine Session leeren. |
| **v3.2.4** | ANNA erlaubt Claude und Codex jetzt standardmäßig Schreibzugriffe im gesamten Projekt ohne Rückfrage; der installierte Kern `.an_framework/**` bleibt gesperrt. Alle Git-Aufrufe sowie übliche Such- und Lesebefehle sind in beiden Laufzeiten vorab freigegeben. Codex verwendet dafür `workspace-write` und projektlokale Regeln unter `.codex/rules/anna.rules`. Nach dem Framework-Austausch `bash .an_framework/init.sh --update` ausführen und das Projekt in der jeweiligen Laufzeit als vertrauenswürdig bestätigen. Achtung: Die Git-Freigabe umfasst bewusst auch destruktive und entfernte Operationen wie `reset --hard` und `push`. |
| **v3.2.3** | Codex kann vor dem ersten Prompt keinen `SessionStart`-Hook ausführen. ANNA entfernt deshalb den irreführenden Codex-Startup-Hook und konfiguriert stattdessen eine sofort sichtbare Statusline aus Projekt, Branch, Modell und Codex-Version. Neu sind `/anna` in Claude und `$an-anna` in Codex: Banner, ANNA-Version und alle Runtime-Befehle auf einen Blick. Der Claude-Startup-Banner nennt jetzt ebenfalls die ANNA-Version. `init.sh --update` entfernt nur ANNAS bekannten Codex-Startup-Handler und ergänzt die Statusline, sofern das Projekt noch keine eigene `[tui]`-Konfiguration besitzt. |
| **v3.2.2** | Codex zeigt erfolgreichen `SessionStart`-Plain-Text nur dem Modell als Kontext. ANNA liefert den Welcome-Screen deshalb nun auch dort als `systemMessage`-JSON aus; `init.sh --update` migriert den bisherigen Codex-Hookbefehl automatisch. Anschließend in Codex `/hooks` öffnen und die geänderte Definition nach Pfadprüfung erneut als vertrauenswürdig bestätigen, dann Codex vollständig beenden und neu starten. Ohne dieses Trust-Votum überspringt Codex den projektlokalen Hook. |
| **v3.2.1** | Claude Code zeigt erfolgreichen `SessionStart`-Plain-Text nur dem Modell als Kontext, nicht als sichtbaren Starttext. ANNA liefert den Welcome-Screen deshalb für Claude als `systemMessage`-JSON aus; `init.sh --update` migriert den bisherigen Claude-Hookbefehl automatisch. Außerdem entfernt das Update veraltete `Write(path)`-Permission-Regeln aus `.claude/settings.json`; aktuelle Claude-Versionen verwenden dafür ausschließlich `Edit(path)`. Danach Claude vollständig beenden und neu starten. |
| **v3.2** | Claude Code und Codex sind gleichwertige Runtime-Adapter über demselben Kern. Schritt 5 installiert `AGENTS.md`, elf `.agents/skills/an-*` Workflows, `.codex/agents/` und `.codex/hooks.json`; die vorhandenen Claude-Dateien bleiben bestehen. Danach kann jede Arbeit ohne Datenmigration in der jeweils anderen Runtime fortgesetzt werden. Bei bestehenden gleichnamigen Codex-Dateien überschreibt `init.sh` nichts und nennt den manuellen Merge im Setup-Report. Außerdem werden verbindliche Architekturentscheidungen ab jetzt als kompakte ADRs unter `an_project/docs/adr/` dokumentiert; `architecture.md` enthält nur Links. `init.sh --update` erkennt bestehende Inline-ADRs und fordert danach `/migrate-adrs` beziehungsweise `$an-migrate-adrs` an. |
| **v3.1** | Zwei neue Teile, beide über Schritt 5. **Agent-Rollen:** `.claude/agents/an-scout.md` und `an-briefer.md` — Claude lagert Code-Recherche und den `/briefing`-Scan jetzt an feste, technisch lesend beschränkte Rollen aus. **Hooks:** ein SessionStart-Hook druckt beim Start (auch nach `/clear`) Branch, offene Items und die letzten CHANGELOG-Zeilen; ein zweiter blockt Testläufe ohne Ausgabebegrenzung und nennt die Wiederholung mit `| tail -n 30`. Beide Skripte liegen im Kern und scheitern nach außen offen. **Achtung:** hat deine `.claude/settings.json` bereits einen eigenen `hooks`-Key, rührt `init.sh` ihn nicht an — der Setup-Report sagt dir dann, welchen Block du von Hand übernehmen musst. Außerdem übergibt `/implement` beim Commit jetzt die Pfade, statt pro Task nachzufragen: der automatische Commit ist damit wirklich automatisch. |
| **v3.0** | Reines Context-Update — **keine Migration, keine Umbenennung von Projektdaten.** Im Kern ist `/implement` jetzt ein Router, der pro Lauf nur noch die passende Ebenen-Datei lädt, und die Git-Baseline ist nach Leser in drei Dateien geteilt: `git-conventions.md` (Commit-Format, liest `/commit`), `git-branching.md` (Branch-Schema, liest `/implement`) und `git-integration.md` (Merge und PRs, liest `/done`). Alle drei werden weiterhin von der **einen** Datei `an_project/docs/git.md` verengt — dort ist nichts zu tun. Schritt 5 lohnt sich trotzdem: er zieht drei neue `deny`-Permissions nach, die verhindern, dass Claude die Tutorials und die Architektur-Doku im Kern liest (~110 KB, die kein Command braucht). |
| **v2.3** | Story- und Task-Nummern werden jetzt **pro Elternteil** vergeben statt projektweit durchgezählt. **Keine zusätzlichen Handgriffe, nichts wird umnummeriert** — deine vorhandenen IDs, Ordnernamen, Branch-Namen und `Ref:`-Zeilen bleiben unverändert gültig. Was du merken wirst: `/new-story` und `/new-task` vergeben ab jetzt **kleinere** Nummern als früher, und in der bisherigen Nummerierung bleiben Lücken stehen. Beides ist richtig so. Was du dir merken musst: Eine ID gilt nur noch **vollständig**. `001-001-0001` und `001-002-0001` sind zwei verschiedene Tasks — ein Vertipper in `depends_on` sieht dadurch gültig aus. Und verschieb kein Item mehr zwischen Epics: In der ID steckt jetzt sein Elternteil. Details in [how-to-use.md, „IDs verstehen"](how-to-use.md). |
| **v2.2** | Aus `/implement-task` wurde **`/implement`** — es nimmt jetzt Task-, Story- und Epic-IDs. Schritt 5 ist hier **Pflicht**, sonst fehlt der neue Stub und `/implement` existiert in deinem Projekt nicht. Dein alter Stub `.claude/commands/implement-task.md` bleibt liegen und funktioniert weiter (im Kern steht ein Alias). Du kannst ihn löschen, sobald sich alle an den neuen Namen gewöhnt haben. Neu ist außerdem `init.sh --update` selbst: vor v2.2 hat ein zweiter Lauf gar nichts getan. Kommst du von v2.1, benutze deshalb das `init.sh` aus dem **neuen** Kern — genau das tust du, wenn du Schritt 3 vor Schritt 5 machst. |
| **v2.1** | Neuer Command `/done`. Schritt 5 nötig, damit sein Stub ankommt. Projekte, die über Push + Merge Request integrieren, brauchen ihn nicht. |
| **v2.0** | Kein Update, sondern ein Umbau: das Framework war vorher ein **Git-Submodule** und ist jetzt ein mitcommitteter Ordner. Diese Anleitung passt dafür **nicht** — sprich das im Team ab, bevor du etwas tust. |
