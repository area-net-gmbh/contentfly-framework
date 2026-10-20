<!-- PURPOSE: Framework-Baseline für Branches — Schema und die Ebene, auf der ein Branch aufgemacht wird. Gelesen von /implement. Commit-Format: git-conventions.md. Merge und PRs: git-integration.md. -->

# Git Branching — Framework-Baseline

Verbindliche Grundlage dafür, **wo** gearbeitet wird: Branch-Schema und die Ebene, auf der ein
Branch entsteht. Gelesen von `.an_framework/commands/implement/*.md`.

Die beiden Nachbarn, bewusst getrennt, weil sie andere Leser haben:
`.an_framework/docs/git-conventions.md` — das **Commit-Format** (Types, Scopes, Body, `Ref:`),
gelesen von `/commit`. `.an_framework/docs/git-integration.md` — **Merge, PRs und
Review-Reife**, gelesen von `/done`. Keine der drei Dateien wiederholt die anderen.

**Overlay-Regel:** Projekte kopieren diese Datei **nicht**, sondern **verengen** sie in ihrer
eigenen `an_project/docs/git.md` — dieselbe Overlay-Datei verengt alle drei Baselines. Bei
Konflikt gewinnt die Projekt-Datei.

---

## Anbindung an das Work-Modell

Die `<ticket-id>` im Commit ist die **Work-Item-ID** `EEE-SSS-TTTT` aus `an_project/work/`
(3-stellig Epic / 3-stellig Story / 4-stellig Task, `000`/`0000` = "diese Ebene
gibt es hier nicht").

## Branch-Schema

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

### Auf welcher Ebene der Branch aufgemacht wird

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
übergibt `/implement` die Task-ID explizit (siehe `.an_framework/docs/git-conventions.md`, *Commit-Referenz*).

Ein Epic bekommt nie einen Branch. Es wird Story für Story gemergt, nicht am Stück.

---

## Standard-Ablauf

`/implement` legt den Branch an und committet die Arbeit samt `review`-Status; `/done` schreibt
`done` fest und integriert den Branch. Der Ablauf je Ebene steht dort, wo er ausgeführt wird —
`.an_framework/commands/implement/{task,story,epic}.md` und `.an_framework/commands/done.md`,
zusammengefasst in `.an_framework/AGENT-GUIDE.md` (*Lifecycle*). Er wird hier bewusst nicht
noch einmal aufgeschrieben: zwei Fassungen desselben Ablaufs laufen auseinander, und jede
Wiederholung kostet Context in jedem Lauf, der diese Datei liest.

An git fassen nur `/commit` und `/done` (dazu die Branch-Wechsel von `/implement` und `/done`).

<!-- ERGÄNZUNGSBEDARF (projektspezifisch in an_project/docs/git.md festlegen):
     - Abweichende Branch-Namen (Abschnitt "Branch names")
     Die Baseline macht dazu bewusst keine Vorgabe. Alles rund um Merge, PR und
     Branch-Löschung steht in git-integration.md. -->
