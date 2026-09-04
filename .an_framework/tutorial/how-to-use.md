# How to use — Team-Guide für das ki-dev-framework

Dieses Framework ist unsere gemeinsame Arbeitsgrundlage für Claude-Projekte. Es
sorgt dafür, dass alle Entwickler im **gleichen Format** arbeiten: gleiche
Ordnerstruktur, gleiche Work-Items (Epics/Stories/Tasks), gleiche Slash-Commands,
gleicher Changelog. Das Framework wird als **getaggter, mitcommitteter Stand unter
`.an_framework/`** in dein Projekt geholt — ein einfaches `git clone` bringt es mit. Diese
Datei erklärt dir als Entwickler, wie du damit arbeitest.

> **Neu im Team oder keine Entwicklerin?** Dann ist
> [quickstart.md](quickstart.md) die richtige Datei — dort steht jeder Schritt einzeln
> erklärt, für macOS und Windows, inklusive Installation und Fehlersuche. Dieses
> Dokument hier setzt Git-Grundlagen voraus.
>
> `CLAUDE.md` (in deinem Projekt) und `.an_framework/AGENT-GUIDE.md` sind die
> Anleitung **für den Agenten**. Dieses `how-to-use.md` ist die Anleitung
> **für dich**. Bei Unklarheiten über interne Mechanik ist
> `.an_framework/docs/framework-architecture.md` die technische Referenz.

---

## 1. Was ist das hier?

Ein versionierter Framework-Kern plus ein Projekt-Skelett. Du holst den Kern als
getaggten Stand nach `.an_framework/`, richtest das Projekt mit `init.sh` **einmal** ein,
beschreibst dein Projekt, legst Arbeit in Epics/Stories/Tasks an und lässt Claude die
Tasks abarbeiten. Nichts davon machst du von Hand — es gibt für jeden Schritt einen
Slash-Command.

Das Wichtigste vorweg: **Framework und Projekt sind strikt getrennt.** Der Kern
wird zur Laufzeit nur *gelesen*, dein Projekt-Inhalt liegt ausschließlich im
Projekt-Git.

**Das Framework (`.an_framework/`, read-only):**

| Ort | Was drin ist |
|-----|--------------|
| `.an_framework/AGENT-GUIDE.md` | Die Regeln, die jedes Projekt per `@`-Import in seine `CLAUDE.md` zieht. |
| `.an_framework/commands/` | Die zehn Slash-Commands im Volltext. |
| `.an_framework/templates/` | Die Blueprints (`epic.md`, `story.md`, `task.md`), aus denen Work-Items entstehen. |
| `.an_framework/docs/guidelines.md` | Baseline der Verhaltens- und Code-Regeln. |
| `.an_framework/docs/git-conventions.md` | Baseline der Git-Konventionen. |
| `.an_framework/docs/framework-architecture.md` | Wie das Framework intern zusammenhängt. |
| `.an_framework/skeleton/` | Das Projekt-Skelett — wird **einmal** beim Setup herauskopiert. |
| `.an_framework/VERSION` | Die Version des mitcommitteten Kerns — seit v2 die maßgebliche Versionsangabe (kein Submodule-SHA mehr). |

**Dein Projekt (im Projekt-Git):**

| Ort | Was drin ist |
|-----|--------------|
| `CLAUDE.md` | Projekt-Stub: importiert `.an_framework/AGENT-GUIDE.md` und ergänzt Projekt-Regeln. |
| `an_project/project-description.md` | Wofür das Projekt existiert (Titel, Beschreibung, Ziel). **Zuerst lesen.** |
| `an_project/work/` | Der Backlog: `epic/`, `story/`, `task/`. Hier liegt die eigentliche Arbeit. |
| `an_project/CHANGELOG.md` | Laufende Historie. **Alle** Commands außer `/board`, `/briefing` und `/commit` hängen hier automatisch an. |
| `an_project/docs/guidelines.md` · `an_project/docs/git.md` | **Overlays** — verengen die Framework-Baseline. |
| `an_project/docs/architecture.md` · `technical.md` · `deployment.md` · `styleguide.md` · `runbook.md` · `dev-guide.md` | Die Doku **deines** Projekts (`runbook`/`dev-guide` nur bei Full-Projekten, nicht im Slim-Profil). |
| `.claude/commands/` | Zehn kurze Stubs, die auf `.an_framework/commands/` verweisen. |
| `.claude/settings.json` | Sperrt Edits in `.an_framework/`; erlaubt Lesen/Schreiben in den Projekt-Ordnern (`an_project/`, `src/`, `tests/`) und die Git-Flag-Formen, die `/commit`, `/implement` und `/done` brauchen. |
| `src/` … | Dein Code. |

> **Merksatz:** Alles mit `.an_framework/`-Präfix wird gelesen (der read-only-Kern).
> Alles mit `an_project/`-Präfix — Backlog, Changelog, Projektbeschreibung, Doku —
> gehört dem Projekt und wird beschrieben; `CLAUDE.md` und `.claude/` liegen dafür im
> Projekt-Root. Einzige Ausnahme: `/commit` übergibt Pfade an Git — geschrieben wird
> dann nicht die Datei, sondern die Historie. Den Kern `.an_framework` staged `/commit`
> dabei nie als Projektarbeit.

---

## 2. Das Arbeitsmodell: Epic → Story → Task

Arbeit ist in drei Ebenen gegliedert. Die Hierarchie ist **optional** — ein Task
kann allein stehen, eine Story auch ohne Epic existieren.

- **Epic** — ein großes Ziel (z. B. „Authentifizierung").
- **Story** — ein Ausschnitt eines Epics (z. B. „Login-Formular").
- **Task** — eine umsetzbare Einheit (z. B. „E-Mail-Feld validieren").

**Wo alles liegt** — drei Roots unter `an_project/work/` (im Projekt, nicht im Framework):
- `an_project/work/epic/` — Epics, mit ihren Stories und Tasks darin verschachtelt.
- `an_project/work/story/` — Stories ohne Epic (Tasks darin verschachtelt).
- `an_project/work/task/` — Ad-hoc-Tasks ohne Epic oder Story.

### IDs verstehen

Jedes Item hat eine volle Adresse **`EEE-SSS-TTTT`**:
- `EEE` = 3-stellige Epic-Nummer
- `SSS` = 3-stellige Story-Nummer
- `TTTT` = 4-stellige Task-Nummer
- `000` / `0000` heißt „diese Ebene gibt es hier nicht".

Die Nummern werden **pro Elternteil** vergeben, nicht projektweit. Nur `EEE` läuft
über das ganze Projekt durch. `SSS` zählt die Stories *eines* Epics, `TTTT` die Tasks
*einer* Story — unter dem nächsten Elternteil fängt der Zähler wieder bei 1 an.
Eindeutig bleibt die ID trotzdem, weil der Elternteil in ihr steckt.

| Beispiel-ID | Bedeutung |
|-------------|-----------|
| `001-000-0000` | Epic 1 |
| `001-001-0000` | Story 1 **von Epic 1** |
| `001-001-0001` | Task 1 **von Story 001-001** |
| `001-002-0001` | Task 1 **von Story 001-002** — ein anderer Task als der darüber |
| `000-002-0000` | Story 2 der **eigenständigen** Stories (kein Epic) |
| `000-000-0004` | Task 4 der **ad-hoc** Tasks (kein Epic, keine Story) |

Daraus folgen zwei Regeln:

- **Eine ID gilt nur vollständig.** Nie auf den letzten Teil verkürzen, nie „Task 1"
  sagen. `001-001-0001` und `001-002-0001` unterscheiden sich in einem Zeichen und
  **existieren beide** — ein Vertipper in `depends_on` sieht gültig aus.
- **Ein Item wird nie verschoben oder umnummeriert.** In der ID steckt der Elternteil.
  Eine Story in ein anderes Epic zu ziehen kollidiert dort mit der laufenden Nummer und
  hängt Branch-Name und jede `Ref:`-Zeile in der Historie ins Leere. Stattdessen: Item
  schließen, neues anlegen.

Jedes Item startet mit YAML-Frontmatter: `id`, `title`, `status`, `depends_on`. Das ist der
einzige harte Kontrakt im System.

> Du vergibst IDs **nie selbst**. Die `/new-*`-Commands berechnen die nächste
> freie Nummer automatisch.

---

## 3. Der typische Ablauf

Wenn du ein neues Projekt auf Basis des Frameworks startest (Setup siehe
Abschnitt 7):

```
1. /new-project        →  Projekt-Charter im Interview ausfüllen
2. /refine <datei>     →  (optional) Formulierung glätten
3. /new-epic  "…"      →  großes Ziel anlegen
4. /new-story "…"      →  Ausschnitt anlegen (optional unter einem Epic)
5. /new-task  "…"      →  konkrete Aufgabe anlegen
6. /board              →  Überblick: was ist offen, was blockiert, was als Nächstes
7. /implement <id>     →  Claude setzt um und verifiziert — Task, Story oder Epic,
                          je nachdem, welche ID du gibst
8. /commit             →  das Ergebnis nach den Git-Konventionen committen
```

Danach wiederholst du 3–8 nach Bedarf. `/board` ist dein Cockpit.

### Warum unter `.claude/commands/` nur kurze Stubs liegen

Claude Code sucht Slash-Commands im **aktuellen Verzeichnis und dessen
Elternverzeichnissen** sowie in `~/.claude/commands`. Ein Ordner
`.an_framework/.claude/commands` liegt in keinem dieser Suchpfade und wird schlicht nicht
gefunden. Deshalb liegt in deinem Projekt pro Command nur ein kurzer **Stub**, der im
Kern sinngemäß sagt:

```
Read `.an_framework/commands/new-epic.md` and follow it exactly.

Arguments: $ARGUMENTS
```

Zwei Details, die dahinterstecken:

- `$ARGUMENTS` wird **nur in der Stub-Datei selbst** ersetzt, nicht in Dateien,
  die der Agent danach per Read nachlädt. Darum reicht der Stub die Argumente
  explizit durch.
- Fehlt `.an_framework/commands/<name>.md`, bricht der Stub bewusst ab und meldet
  „Framework not initialised" — statt die Struktur zu improvisieren.

Die Stubs sind Projekt-Dateien und werden mit eingecheckt. Die eigentliche Logik
ändert sich nur im Framework-Repo.

---

## 4. Die Slash-Commands im Überblick

| Command | Zweck | Schreibt |
|---------|-------|----------|
| `/new-project` | Charter im Interview ausfüllen | `an_project/project-description.md` |
| `/new-epic "Titel"` | Neues Epic anlegen | `an_project/work/epic/…` |
| `/new-story ["EEE"] "Titel"` | Neue Story, optional unter Epic `EEE` | `an_project/work/epic/…` oder `an_project/work/story/…` |
| `/new-task ["EEE-SSS"] "Titel"` | Neuer Task unter Story/Epic oder ad-hoc | passender `an_project/work/`-Ort |
| `/implement <id>` | Work-Item end-to-end umsetzen — **die ID entscheidet die Ebene**: Task, Story oder Epic. Branch anlegen, umsetzen, verifizieren, per `/commit` committen, auf `review` setzen | Branch, Status, Code, CHANGELOG |
| `/done [<id>]` | Reviewtes Item lokal in den Integrationsbranch mergen (`--no-ff`, kein Push) und auf `done` setzen; eine Story nimmt ihre Tasks mit | Status, CHANGELOG, Git-Historie (lokaler Merge) |
| `/refine <datei>` | Einen ausgefüllten Artefakt klarer/strukturierter machen | die Zieldatei |
| `/commit [<id>] [<url>] [--de\|--en]` | Änderungen nach fachlicher Intention clustern und nach `git-conventions.md` committen | die Git-Historie (keine Projektdatei) |
| `/board` | Status-Überblick des ganzen Backlogs (read-only) | — |
| `/briefing [todos\|<abschnitt>]` | Kompaktes Onboarding-Briefing aus Doku + Backlog, damit ein:e Neu-Dazugestoßene:r nach dem Auschecken schnell produktiv ist (read-only) | — |

**Argumente-Hinweise:**
- `/new-story 001 "Login-Formular"` → hängt die Story unter Epic `001`. Ohne die
  Nummer wird sie eigenständig.
- `/new-task 001-001 "E-Mail validieren"` → Task unter Story `001-001`.
  `/new-task 001 "…"` → direkt unter Epic `001`. Ohne Präfix → ad-hoc.
- `/commit` — alle Argumente optional und in beliebiger Reihenfolge:
  `001-001-0001` setzt die Work-Item-ID (sonst kommt sie aus dem Branch-Namen oder
  aus dem Task mit `status: in-progress`), eine `https://…`-URL wird als eigene
  `Ticket:`-Zeile ergänzt, `--en` / `--de` schaltet die Sprache für diesen einen
  Lauf um. Alles andere → der Command fragt nach, statt zu raten.
- `/briefing` → ohne Argument fragt der Command zuerst „komplettes Briefing" oder
  „nur Todos". `todos` gibt nur *Zuletzt gemacht* + *Als Nächstes* aus; ein
  Abschnittsname (z. B. `deployment`) erzeugt das volle Briefing mit diesem Abschnitt
  vertieft. Ideal direkt nach dem Auschecken für alle, die neu ins Projekt kommen.

**Alle** Commands außer dreien tragen sich automatisch in `an_project/CHANGELOG.md` ein — auch
`/new-project` und `/refine`, die gar kein Work-Item anfassen. Die drei Ausnahmen:
`/board` und `/briefing` sind read-only, und `/commit` schreibt in die Git-Historie statt in
Projektdateien — der Command, der die Änderung erzeugt hat, hat sie bereits geloggt. **Kein Command schreibt jemals nach `.an_framework/`** — alle
Schreibziele sind projekt-eigene Pfade, und `/commit` staged den Kern `.an_framework`
unter keinen Umständen als Projektarbeit mit.

### Ein Task von Anfang bis Ende

Der Lebenszyklus: `todo → in-progress → review → done` (plus `blocked`).

1. Nimm einen freien Task mit `status: todo`, dessen `depends_on` alle `done` sind
   (`/board` zeigt dir unter **Next up** genau diese).
2. Starte mit `/implement <id>`. Der Command legt den Branch `<type>/<id>-<slug>` an,
   setzt `in-progress`, baut zu den Akzeptanzkriterien, verifiziert, **committet automatisch
   über `/commit`** (du siehst die Message vorher und bestätigst) und setzt am Ende `review`.
3. Du reviewst das Ergebnis.
4. `/done [<id>]` schließt ab: es mergt den Branch **lokal** in den Integrationsbranch
   (`git merge --no-ff`, kein Push) und setzt `done`. Ist genau ein Item in `review`, nimmt
   `/done` es automatisch; sonst fragt es, welches.

### `/implement` auf Story- und Epic-Ebene

`/implement` nimmt die ID **jedes** Work-Items — und liest die Ebene aus der ID selbst.
Kurzformen gehen auch: `/implement 001` ist das Epic, `/implement 001-001` die Story.

| Du gibst | Was passiert | Branch |
|---|---|---|
| `/implement 001-001-0001` | genau dieser Task | eigener Task-Branch |
| `/implement 001-001` | die **ganze Story**: alle ihre Tasks nacheinander, in `depends_on`-Reihenfolge, **ein Commit pro Task** | **ein** Story-Branch |
| `/implement 001` | das Epic **plant nur** — Reihenfolge der Stories, dann Rückfrage vor **jeder** einzelnen | keiner |

**Warum ein Story-Branch und nicht einer je Task?** Weil die Story die fachliche Einheit
ist, die man am Stück abnimmt, mergt und im Zweifel zurücknimmt. Die Commits bleiben
trotzdem task-fein — jeder Task ist ein eigener Commit mit `Ref: #<task-id>`. Du bekommst
also grobe Merges und feine Historie statt beides gleich grob.

**Story ohne Tasks?** Kein Abbruch — `/implement` schlägt dir einen Task-Schnitt vor, legt
die bestätigten Tasks über `/new-task` an, füllt sie über `/refine` aus und **fragt dann
noch einmal**, bevor irgendetwas gebaut wird. Du kannst nach dem Schnitt aussteigen; die
Tasks bleiben, die Story hat dann einfach ein echtes Backlog.

**Warum fragt das Epic vor jeder Story?** Ein Epic am Stück wären schnell dutzende Commits
über Stunden, die niemand dazwischen ansieht. Der Checkpoint nach jeder Story ist Absicht.

`/done` folgt derselben Ebene: `/done <story-id>` setzt die Story **und alle ihre Tasks**
auf `done` und mergt den Story-Branch einmal. Ein einzelner Task, der auf einem Story-Branch
liegt, lässt sich nicht getrennt abschließen — `/done` sagt dir das und verweist auf die
Story. Ein Epic hat keinen Branch: `/done <epic-id>` schreibt nur den Status fest, wenn alle
Stories gemergt sind.

---

## 5. Die `an_project/docs/` — Baseline im Framework, Verengung im Projekt

Die Regeln stehen an **zwei** Stellen, und das ist Absicht.

**Baseline (Framework, read-only) — gilt für alle Projekte:**

| Datei | Inhalt |
|-------|--------|
| `.an_framework/docs/guidelines.md` | Verhaltens- und Code-Regeln (Think Before Coding, Simplicity First, chirurgische Änderungen). Der Kern. |
| `.an_framework/docs/git-conventions.md` | Branch-, Commit- und PR-Konventionen. |
| `.an_framework/docs/framework-architecture.md` | Wie das Framework selbst gebaut ist — Referenz, keine Projektregel. |

**Overlays (Projekt) — verengen die Baseline:**

| Datei | Inhalt |
|-------|--------|
| `an_project/docs/guidelines.md` | Was *dieses* Projekt zusätzlich oder anders macht. |
| `an_project/docs/git.md` | Vor allem die Commit-**Scopes** dieses Projekts, dazu Branch-Namen, Integrationsbranch, Commit-Sprache, Ticket-URL-Basis und bewusste Abweichungen. Die Abschnitte *Scopes*, *Integration branch* und *Commit language* liest `/commit` direkt aus; die Ticket-URL-Basis ist reine Doku. |

**Projekt-eigene Doku — gehört komplett dir:**

| Datei | Inhalt |
|-------|--------|
| `an_project/docs/architecture.md` | Architektur **deines Produkts**: Komponenten, Grenzen, Entscheidungen. |
| `an_project/docs/technical.md` | Tech-Stack und technische Konventionen. |
| `an_project/docs/runbook.md` | Wie man das Projekt nach dem Checkout **lokal** zum Laufen bringt (Docker hoch, Backend/Frontend installieren, DB migrieren). Nur bei Full-Projekten. |
| `an_project/docs/dev-guide.md` | Backend-/Frontend-Struktur (Symfony immer in Bundles, Angular `src/modules`+`src/shared`) und wie man z. B. eine API-Route anlegt. Nur bei Full-Projekten. |
| `an_project/docs/deployment.md` | Umgebungen, CI/CD, Release-Prozess (das **lokale** Setup steht im `runbook.md`). |
| `an_project/docs/styleguide.md` | UI/Brand/Design-Tokens (bei Nicht-UI-Projekten `n/a`). |

### Die Overlay-Regel

- **Ergänzen, nicht kopieren.** Schreib in `an_project/docs/guidelines.md` und `an_project/docs/git.md`
  nur, was von der Baseline abweicht oder dazukommt. Die Baseline dorthin zu
  kopieren erzeugt einen Fork, der beim nächsten Framework-Update auseinanderläuft.
- **Bei Konflikt gewinnt das Projekt.** Sagt die Baseline A und dein Overlay B,
  gilt B.
- **Wo nichts steht, gilt die Baseline.** Ein leeres Overlay ist ein gültiger
  Zustand.
- Das gilt genauso für Projekt-Regeln direkt in `CLAUDE.md`: Sie schlagen
  `.an_framework/AGENT-GUIDE.md`. Halte sie kurz — lieber die Overlay-Docs verengen.

---

## 6. Die goldenen Regeln

- **`.an_framework/` nie bearbeiten** — Änderungen am Framework gehören ins
  Framework-Repo. Im Projekt ist der Ordner read-only; was du dort änderst, ist
  beim nächsten Update weg oder blockiert es.
- **Struktur & IDs nie von Hand basteln** — immer die `/new-*`-Commands nutzen.
- **Commit-Messages nie selbst formulieren** — immer `/commit`. Nur so haben alle
  Commits dasselbe Format und dieselbe Bindung an das Work-Item. Zwei maschinell erzeugte
  Ausnahmen: der Merge-Commit von `/done` (nimmt mit `--no-edit` git's Standard-Message)
  und der Setup-Commit beim Aufsetzen eines Projekts — er bringt den Kern `.an_framework`
  erstmals ins Projekt, den `/commit` als Projektarbeit bewusst nie mitnimmt; seine Befehle
  druckt `init.sh`, siehe [quickstart.md](quickstart.md) Teil B6.
- **`an_project/docs/git.md` und `an_project/docs/guidelines.md`** (plus die Framework-Baseline
  dahinter) sind für Branches, Commits und Code-Stil verbindlich.
- **Nach jeder nennenswerten Änderung** landet ein datierter Eintrag im
  `an_project/CHANGELOG.md` (die Commands erledigen das; bei manuellen Änderungen selbst).
- **Ein Task ist erst `done`, wenn** alle Akzeptanzkriterien erfüllt und die
  Verification durchlaufen ist.
- **Unklar? Stopp.** Lieber `/refine` auf das Item oder eine Rückfrage, als bei
  unterspezifizierten Kriterien zu raten.

---

## 7. Projekt aufsetzen

> **Die Setup-Befehle stehen vollständig und Schritt für Schritt in
> [quickstart.md](quickstart.md)** — Teil B (neuer Ordner), Teil C (Framework in ein
> bestehendes Projekt einbauen) bzw. Teil D (fertiges
> Projekt klonen). Das ist die **einzige** gepflegte Quelle dafür; hier stehen sie
> bewusst nicht noch einmal, sonst driften die beiden Dateien auseinander.
> Die Quickstart ist für Nicht-Entwickler geschrieben, taugt aber als Copy-Paste-Liste
> für alle.

> **Slim oder Full?** Beim Einrichten fragt `init.sh`, ob es ein **Slim**-Projekt wird
> (z. B. eine einfache HTML-Seite): dann nur der Work-Backlog (Epic/Story/Task) plus die
> `git`/`guidelines`-Overlays — **ohne** Docker, Tech-Stack, Runbook und Dev-Guide.
> **Full** ist der Standard und bringt die ganze Doku-Ausstattung mit. Nicht-interaktiv
> steuern das `--slim` / `--full`. Die Wahl steht danach in `an_project/.framework-profile`.

Der Ablauf in einem Absatz: Projekt-Repo anlegen, den getaggten Framework-Stand nach
`.an_framework/` klonen und dessen `.git` entfernen, `.an_framework/init.sh` **einmal**
laufen lassen, den ausgegebenen Setup-Commit abschicken. Danach Claude Code **im
Projekt-Wurzelverzeichnis** öffnen (nicht in einem Unterordner, siehe Abschnitt 9).

Was `init.sh` dir abnimmt — und was das für die Entwickler-Fälle bedeutet:

**Nicht-leeres Repo (Retrofit).** Das Skript ist **non-destruktiv**: es kopiert eine
Skelett-Datei nur, wenn das Ziel fehlt. Eine vorhandene `CLAUDE.md` legt es als
`CLAUDE-ARCHIVE.md` beiseite (daraus zieht `/new-project` die Projektbeschreibung), eine
vorhandene `.claude/`-Konfiguration wird **gemerget**, nicht ersetzt. Das frühere
manuelle „kopieren-dann-mit-`git checkout --`-zurückholen" entfällt komplett. Was das
Skript nicht automatisch entscheiden kann, listet es in `an_project/SETUP-REPORT.md`.

**settings.json.** Beim Retrofit unioniert `init.sh` deine `allow`-Einträge mit denen des
Frameworks und **ersetzt** dabei einen veralteten `Edit(/.framework/**)`-deny-Pfad durch
`.an_framework` — die eine bewusste Ausnahme von „nie überschreiben". Fehlt `python3`,
verschiebt es die Zusammenführung an Claude und schreibt die Anweisung in den Report.

**Gleichnamige Commands.** Hatte dein Projekt ein eigenes `/board` o. Ä., bleibt deine
Fassung aktiv und wird nach `an_project/_pre-framework/` gesichert — der Konflikt steht
laut im Report, du entscheidest von Hand.

Was du als Entwicklerin danach prüfen musst: ein vorhandenes `an_project/docs/guidelines.md`
bzw. `an_project/docs/git.md` überlebt als **Overlay** — und ein Overlay schlägt die
Baseline. Ein gewachsenes eigenes Regelwerk an dieser Stelle schaltet Framework-Regeln
ab, ohne dass es jemandem auffällt.

**Overlay-Docs füllen.** (Den Platzhalter `<project name>` in `CLAUDE.md` setzt schon
`init.sh --name` bzw. quickstart Teil B.) `an_project/docs/guidelines.md` ·
`an_project/docs/git.md` mit den Projekt-Besonderheiten füllen. In `an_project/docs/git.md`
lohnen sich vor allem die Abschnitte, die `/commit` direkt ausliest: *Scopes*,
*Integration branch* und *Commit language*. Die *Ticket URL base* ist reine Doku — die
vollständige URL gibst du `/commit` als Argument mit. Leer lassen ist erlaubt — dann gilt
die Baseline.

---

## 8. Framework aktualisieren

> **Die Befehle stehen in [update.md](update.md)** — Schritt für Schritt, mit Prüfungen,
> Fehlerfällen und einer Tabelle, welche Version welchen Zusatzhandgriff braucht. Dieser
> Abschnitt erklärt nur, **warum** es so läuft. Eine zweite Kopie der Befehle würde
> garantiert auseinanderdriften.

Der Kern liegt als committete Dateien im Projekt. Ein Update besteht aus drei Bewegungen:
`.an_framework/` gegen einen neueren getaggten Stand tauschen, mit
`init.sh --update` die neuen Skelett-Dateien nachziehen, und beides als **eigenen**
`chore(framework)`-Commit abschicken — nie mit Projektarbeit vermischt.

Was dabei passiert:

- Der Diff ist jetzt der **volle Inhalt** des Kerns, nicht mehr eine SHA-Zeile — dafür
  reproduziert bei allen anderen ein einfaches `git clone`/`git pull` exakt diesen Stand,
  ohne `--recurse-submodules` und ohne `git submodule update`.
- Framework-Bump und Projektarbeit gehören **nie** in denselben Commit. `/commit`
  verweigert deshalb ein gestagtes `.an_framework` als Projektarbeit.
- Zurückrollen geht genauso: den alten Tag klonen und als `chore(framework)`-Commit
  abschicken.

**Der Kern-Tausch allein reicht nicht.** Er ersetzt nur `.an_framework/`. **Neue oder
geänderte Skelett-Dateien wandern dabei NICHT ins Projekt** — das Skelett wird einmal beim
Setup verteilt und gehört danach dem Projekt. Ein neuer Slash-Command besteht aber aus
**zwei** Teilen: der Logik im Kern *und* einem Stub unter `.claude/commands/`. Ohne den
Stub findet Claude Code ihn nicht (siehe Abschnitt 3).

Deshalb `init.sh --update`. Der Lauf ist rein additiv: er legt fehlende Stubs an,
unioniert neue Permissions in deine `.claude/settings.json`, ergänzt fehlende
Doku-Vorlagen — und fasst nichts an, was du gefüllt hast. Das `--update` ist ausdrücklich
nötig, weil ein eingerichtetes Projekt sonst in Ruhe gelassen wird: wer ein v2-Projekt
frisch klont, hat bereits alles und soll `init.sh` gerade **nicht** laufen lassen. Das
Profil (slim/full) liest der Lauf aus `an_project/.framework-profile`, fragt also nicht
erneut — ein Slim-Projekt bleibt slim.

---

## 9. Wenn etwas nicht geht

### `.an_framework/` fehlt oder ist leer

**Symptom:** Slash-Commands melden „Framework not initialised", oder Claude kennt
das Work-Modell (`EEE-SSS-TTTT`) nicht. `ls .an_framework` zeigt nichts.

**Ursache:** Da der Kern committet ist, bringt ein normaler `git clone` ihn eigentlich
mit. Fehlt er trotzdem, wurde das Setup (Teil B/C) nie abgeschlossen oder der Clone ist
schiefgegangen. (Die alte Submodule-Falle „ohne `--recurse-submodules` geklont" gibt es
nicht mehr.)

**Heilung:** Das Setup nachholen (quickstart Teil B/C) bzw. das Projekt sauber neu
klonen. Danach muss `.an_framework/AGENT-GUIDE.md` existieren; laufende Claude-Session
neu starten, damit der Import greift.

### Claude Code wurde aus einem Unterverzeichnis gestartet

**Symptom:** Claude arbeitet ohne die Regeln — falsche IDs, falsche Ordner,
ignorierte Guidelines. **Es gibt keine Fehlermeldung.**

**Die Falle dabei:** Die Slash-Commands sind trotzdem da. Sie werden auch aus
Elternverzeichnissen gefunden, `/board` funktioniert also weiter — nur die *Regeln*
fehlen. Sichtbares Symptom und tatsächlicher Fehler sind entkoppelt; „die Commands
sind ja da" ist **kein** Beweis, dass die Session richtig steht.

**Ursache:** Ein relativer `@`-Import, der aus dem Arbeitsverzeichnis herausführt, gilt
als *externer Import* und wird nicht automatisch ausgeführt — Claude fragt einmal um
Erlaubnis oder überspringt ihn. `.claude/settings.json` wird ohnehin nur im aktuellen
Verzeichnis gesucht, ohne Rückfall auf Elternverzeichnisse; die Deny-Regel und die
`allow`-Liste für `/commit` gelten dann nicht.

> Wurde diese Rückfrage einmal mit „Nein" beantwortet, bleibt der Import **dauerhaft**
> deaktiviert und die Frage kommt nicht wieder — dann fehlen die Regeln auch später,
> wenn alles andere stimmt.

**Woran du es erkennst:** `/context` eingeben. In der Tabelle **Memory Files** müssen
`CLAUDE.md` **und** `.an_framework/AGENT-GUIDE.md` stehen. Das ist der harte, sichtbare
Test. Ersatzweise direkt nachfragen — „Siehst du den Abschnitt *Work model* mit dem
`EEE-SSS-TTTT`-Schema?"; genau dafür steht der Load-Check-Absatz in der Projekt-`CLAUDE.md`.

**Heilung:** Session beenden, in die Projekt-Wurzel wechseln (dort, wo `CLAUDE.md`
und `.an_framework/` liegen) und neu starten.

### Ein Command will nach `.an_framework/` schreiben

Das darf nicht passieren. `.claude/settings.json` setzt dafür
`"deny": ["Edit(/.an_framework/**)"]`. Das ist eine **Leitplanke, keine
Sicherheitsgrenze** — über Bash lässt es sich umgehen. Wenn du so etwas siehst,
ist es ein Bug im Framework-Repo und gehört dort gefixt, nicht lokal umgangen.

### `.an_framework/` taucht in einem Commit auf

**Symptom:** Im Diff eines ganz normalen Commits stehen Änderungen unter
`.an_framework/…` — der read-only-Kern würde als Projektarbeit mitcommittet.

**Ursache:** Da der Kern committete Dateien sind, staged `git add -A` (oder `git add .`)
im Projekt-Wurzelverzeichnis auch jede versehentliche Änderung im Kern. Deshalb staged
`/commit` ausschließlich **benannte Pfade** und bricht ab, sobald etwas unter
`.an_framework` **gestaged** ist.

**Heilung:** `git checkout -- .an_framework` setzt den Kern auf den committeten Stand
zurück. War die Änderung ein gewollter Framework-Bump, gehört sie in einen eigenen
`chore(framework): …`-Commit (siehe §8) — Framework-Bump und Projektarbeit nie im selben
Commit. Der read-only-Schutz ist jetzt **Konvention** (deny-Regel + `/commit`-Preflight),
nicht mehr die frühere git-erzwungene Submodule-Grenze.

### Zwei Commit-Commands in der Liste

Claude Code sucht Slash-Commands auch in `~/.claude/commands`. Hast du dort ein
eigenes Commit-Command liegen, taucht es neben `/commit` auf. Es kennt weder das
`EEE-SSS-TTTT`-Schema noch das Branch-Schema noch die Overlay-Regel — in einem
Framework-Projekt also immer `/commit` nehmen.

---

Viel Erfolg — und halte dich an das Format, dann bleibt für alle alles lesbar.
