<!-- PURPOSE: Framework-Baseline für Git — Branching, Commit-Format, PR-Regeln. Projekte verengen das in an_project/docs/git.md. -->

# Git Conventions — Framework-Baseline

Verbindliche Grundlage für Branches, Commits und Pull Requests.
Referenziert aus `.an_framework/commands/commit.md` und
`.an_framework/commands/implement.md`.

**Overlay-Regel:** Projekte kopieren diese Datei **nicht**, sondern **verengen** sie in
ihrer eigenen `an_project/docs/git.md` — vor allem die Scopes, die hier bewusst offen bleiben,
daneben Branch-Namen und bewusste Abweichungen.
Bei Konflikt gewinnt die Projekt-Datei.

---

## Grundregeln

- Ein Commit = eine fachliche Intention
- Commits beschreiben **warum**, nicht nur was
- Commits sind revertierbar – kein Scope-Creep
- `master` ist jederzeit deploybar – **kein direkter Push aufs Remote**. Integriert wird
  je nach Projekt über `/done` (lokaler Merge) **oder** über Push + PR (siehe
  *Merge / Integration*)
- Commits entstehen über `/commit`, nicht von Hand — der Command liest diese Datei
  und die Verengung in `an_project/docs/git.md`

## Format

```
<type>(<scope>): <kurze Aussage im Präsens>

[Ref: #<ticket-id>]
[Ticket: <url>]

[optionaler Body]
```

Die `Ref:`-Zeile steht nur, wenn ein Work-Item existiert. Gibt es keines — Setup-Commit,
Framework-Bump, Repo-Chore — entfällt sie ersatzlos: nie `#000-000-0000`, nie `#none`,
nie eine erfundene ID.

Die URL eines externen Tickets steht **nie** in der `Ref:`-Zeile, sondern auf einer
eigenen `Ticket:`-Zeile darunter. Sie ergänzt die Work-Item-ID, sie ersetzt sie nie.
So bleibt `Ref: #EEE-SSS-TTTT` maschinell auffindbar.

## Types

| Type | Bedeutung |
|------|-----------|
| feat | Neues Feature |
| fix | Bugfix |
| improve | Funktionale Verbesserung |
| refactor | Umstrukturierung ohne Funktionsänderung |
| perf | Performance-Optimierung |
| docs | Dokumentation |
| test | Tests |
| chore | Build / Tooling / CI |
| security | Sicherheitsrelevant |
| revert | Rücknahme eines Commits |
| wip | Work in Progress – nicht merge-fähig |

## Scopes

Fachliche Module, nicht Dateinamen. Ein Commit darf mehrere technische Bereiche
(Backend / DB / Frontend) betreffen, solange sie einer Intention dienen.
→ Projektspezifisch ergänzen in `an_project/docs/git.md`: `authentication`, `data-model`,
`infrastructure`, `ci`, `docs` …

## Body (optional)

Nutzen wenn Logik, Risiko oder Auswirkungen erklärungsbedürftig sind:

```
WHY:     Begründung für die Änderung
WHAT:    Was wurde umgesetzt
AFFECTS: backend / db / frontend / …
```

## Sprache der Commit-Message

Betreff und Body werden per Default auf **Deutsch** geschrieben. Projekte, die
englisch committen, halten das in ihrer `an_project/docs/git.md` unter *Commit language* fest;
bei Konflikt gewinnt die Projekt-Datei. `/commit --de` bzw. `--en` überschreibt das
für einen einzelnen Lauf.

Immer englisch bleiben: die Types, der Scope und die Schlüssel `Ref:` · `Ticket:` ·
`WHY:` · `WHAT:` · `AFFECTS:`.

---

## Anbindung an das Work-Modell

Die `<ticket-id>` im Commit ist die **Work-Item-ID** `EEE-SSS-TTTT` aus `an_project/work/`
(3-stellig Epic / 3-stellig Story / 4-stellig Task, `000`/`0000` = "diese Ebene
gibt es hier nicht").

### Branch-Schema

```
<type>/<EEE-SSS-TTTT>-<kurz-slug>
```

- `<type>` — derselbe Type wie im Commit (`feat`, `fix`, `refactor`, …)
- `<EEE-SSS-TTTT>` — die vollständige ID des Work-Items, an dem gearbeitet wird
- `<kurz-slug>` — 2–4 Wörter, kebab-case, kleingeschrieben

Beispiele:

```
feat/001-001-0001-login-formular
fix/000-000-0004-csv-export-encoding
refactor/000-002-0000-session-handling
```

Ein Branch gehört zu **genau einem** Work-Item. Ergibt sich unterwegs weitere
Arbeit, wird dafür ein eigenes Item angelegt (`/new-task`) — nicht der Branch
aufgeblasen.

#### Auf welcher Ebene der Branch aufgemacht wird

Das entscheidet die Ebene, auf der `/implement` gestartet wurde:

| Start | Branch | Commits darauf |
|---|---|---|
| `/implement <task-id>` | Task-Branch `…/<EEE-SSS-TTTT>-…` | einer, für diesen Task |
| `/implement <story-id>` | **Story-Branch** `…/<EEE-SSS-0000>-…` | einer **pro Task** der Story |
| `/implement <epic-id>` | keiner — das Epic plant nur | — |

Der **Story-Branch ist der Regelfall**, sobald eine Story umgesetzt wird: die Story ist
dann das eine Work-Item des Branches, ihre Tasks werden nicht einzeln abgezweigt. Das
hält die Story als **eine** Merge- und Revert-Einheit zusammen, ohne die Commit-Granularität
zu verlieren — jeder Task bleibt ein eigener Commit mit `Ref: #<task-id>`, denn ein Commit
ist eine fachliche Intention, und die liegt auf Task-Ebene.

Einen **eigenen Task-Branch** gibt es nur für Tasks, die für sich stehen: ad-hoc
(`000-000-TTTT`), direkt unter einem Epic (`EEE-000-TTTT`), oder ein einzelner Task einer
Story, deren Story gerade **nicht** in Arbeit ist.

Daraus folgt für `/commit`: auf einem Story-Branch trägt der Branch-Name die **Story**-ID,
der Commit aber die **Task**-ID. Das ist kein Widerspruch, sondern der Normalfall — deshalb
übergibt `/implement` die Task-ID explizit (siehe *Commit-Referenz*).

Ein Epic bekommt nie einen Branch. Es wird Story für Story gemergt, nicht am Stück.

### Commit-Referenz

Jeder Commit auf dem Branch trägt die ID des Work-Items — mit externem Ticket auf
einer eigenen Zeile darunter:

```
feat(authentication): Login-Formular mit Passwort-Reset-Link

Ref: #001-001-0001
Ticket: https://tracker.example/browse/ABC-123
```

`/commit` leitet die ID in dieser Reihenfolge ab, erster Treffer gewinnt:

1. das explizit übergebene Argument,
2. der Branch-Name nach dem Schema oben,
3. das einzige Work-Item mit `status: in-progress` auf Task-Ebene,
4. keines davon → die `Ref:`-Zeile entfällt, nach ausdrücklicher Bestätigung.

Widersprechen sich Argument und Branch, wird gefragt statt geraten. **Eine Ausnahme:** der
Story-Branch. Ist die Branch-ID eine Story (`…-0000`) und das Argument ein Task **derselben**
Story (gleiches `EEE-SSS`), gewinnt das Argument — das ist der Normalfall aus
`/implement <story-id>`, kein Widerspruch. Alles andere stoppt weiterhin.

Ein fehlgeschlagener Commit — Pre-Commit-Hook, Signatur — wird gemeldet, nicht
umgangen: `--no-verify` und `--no-gpg-sign` sind verboten.

### Standard-Ablauf

Ein einzelner Task:

```
/implement <task-id>   →  Task-Branch anlegen · status: in-progress · umsetzen · verifizieren
                          · status: review · /commit (auto) — alles in EINEM Commit
/done [<task-id>]      →  status: done (auf dem Branch, via /commit) · Branch lokal
                          in den Integrationsbranch mergen
```

Eine ganze Story — **ein** Branch, ein Commit je Task:

```
/implement <story-id>  →  Story-Branch anlegen · Story auf in-progress
                          · je Task: in-progress → umsetzen → verifizieren → review
                            → /commit <task-id>   (ein Commit pro Task, alle auf demselben Branch)
                          · am Ende Story auf review
/done <story-id>       →  Story UND alle ihre Tasks auf done (ein Commit, via /commit)
                          · Story-Branch lokal in den Integrationsbranch mergen
```

Ein Epic plant nur und fragt vor **jeder** Story nach; es legt keinen Branch an und
committet keinen Code. `/done <epic-id>` schreibt am Ende nur den Status fest — gemergt
wurde Story für Story.

`/implement` legt den Branch an und committet die Arbeit samt `review`-Status, `/done`
schreibt `done` fest und integriert den Branch. git fassen damit `/commit` und `/done` an
(dazu die Branch-Wechsel von `/implement` und `/done`).

### Merge / Integration (`/done`)

Es gibt **zwei** Integrationswege; das Projekt wählt einen:

- **Solo / intern — lokal über `/done`:** Steht ein Task auf `status: review`, schreibt
  `/done` den `done`-Status auf dem Task-Branch fest (über `/commit`) und führt den Branch
  dann lokal zusammen — `git merge --no-ff --no-edit` in den Integrationsbranch, **kein
  Push**. Passt für Repos ohne geschützten Branch / ohne PR-Pflicht.
- **Team — Push + PR:** Bei geschütztem Integrationsbranch **kein** `/done` — stattdessen
  den Task-Branch pushen und einen Pull/Merge Request stellen; gemergt wird über
  Review/CI. So bleibt „kein direkter Push aufs Remote" gewahrt, und der PR ersetzt das
  `/done`.

Für den `/done`-Weg gilt:

- `--no-ff` hält den Task als **eine revertierbare Einheit** (ein Merge-Commit, den man am
  Stück zurücknehmen kann). `--no-edit` nimmt die Standard-Merge-Message von git — kein
  `-m`, also kein Quoting-Risiko. Der `done`-Status ist **vor** dem Merge committet, der
  Merge trägt ihn hinein.
- Der Merge läuft **nicht** über `/commit` — dessen Preflight bricht bei `MERGE_HEAD` ab.
- Der Branch-Wechsel (`git checkout <branch>`) ist bewusst **nicht** vorab erlaubt (er
  würde auch das dateiverwerfende `git checkout -- <pfad>` zulassen) und fragt einmal nach.
- Ein Merge-Konflikt stoppt den Lauf; der Mensch löst ihn (`git merge --abort` ist seine
  Entscheidung), der Status bleibt unverändert.

---

## Pull Requests

- `master` ist jederzeit deploybar — **kein direkter Push aufs Remote**. Zwei
  Integrationswege (siehe *Merge / Integration*): lokal über `/done` (solo/intern)
  **oder** Push + PR von einem Branch nach dem Schema oben (Team). Im Team-Weg ersetzt der
  PR das `/done`; beides zusammen wäre doppelte Integration.
- Ein PR = ein Work-Item. PR-Titel folgt dem Commit-Format, der PR-Body enthält
  `Ref: #<EEE-SSS-TTTT>`.
- Ein PR ist erst review-fähig, wenn das Work-Item auf `status: review` steht,
  die Acceptance Criteria erfüllt und verifiziert sind und der Eintrag in
  `an_project/CHANGELOG.md` gesetzt ist.
- `wip`-Commits sind nicht merge-fähig — vor dem Merge aufräumen.

<!-- ERGÄNZUNGSBEDARF (projektspezifisch in an_project/docs/git.md festlegen):
     - Name des Integrationsbranch (master / main / develop) — die Baseline sagt `master`.
       Wird von /commit gelesen: Abschnitt "Integration branch"
     - Sprache der Commit-Message (de / en) — Baseline ist `de`.
       Wird von /commit gelesen: Abschnitt "Commit language"
     - Basis-URL des externen Trackers, falls es einen gibt (Abschnitt "Ticket URL
       base"). Reine Doku fuer Menschen — /commit bekommt die vollstaendige URL als
       Argument und liest den Abschnitt nicht.
     - Merge-Strategie (squash / merge commit / rebase)
     - Anzahl erforderlicher Reviewer / Approvals
     - Pflicht-Checks (CI, Lint, Tests) vor dem Merge
     - Branch-Löschung nach Merge
     Die Baseline macht dazu bewusst keine Vorgabe. -->
