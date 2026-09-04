<!-- PURPOSE: How the framework fits together — the two axes (data/logic, framework/project), the stub indirection, the dependency graph, the contracts that couple the system, and the honest failure modes. Read this before changing the structure. -->

# ki-dev-framework — Architecture & Dependencies

This repo is the **framework core**. Since v2 it is no longer a git submodule: a
project fetches a tagged snapshot of this repo, strips its `.git`, and commits the
result as `.an_framework/`. So every path below that starts with `.an_framework/` is a
path *inside this repo*, now living as ordinary committed files inside the project.

Understand every dependency so coupling stays minimal.

---

## The mental model — two axes

Two orthogonal axes, both true of every file at once: **data vs. logic**, and
**framework zone vs. project zone**.

### Axis 1 — data vs. logic

- **Data files** — passive. They hold content and reference *nothing*.
  (`AGENT-GUIDE.md`, everything in `docs/`, `templates/`, `VERSION`, the project's
  `project-description.md`, its `docs/`, its work items, its `CHANGELOG.md`.)
- **Logic files** — active. They *do* things and therefore reference data files.
  (the ten commands in `commands/` plus the `implement-task` alias, the ten stubs that
  load them, and `init.sh`.)

> **Rule: logic depends on data, never the reverse. Data files have zero outgoing
> dependencies.** You can rewrite any doc or template without breaking anything.

### Axis 2 — framework zone vs. project zone

- **Framework zone** — `.an_framework/**`. **Read-only at project runtime.** Commands,
  templates and baseline docs are read out of it; nothing is ever written into it.
  It is committed as plain files, so a `git clone` reproduces it — but read-only is now
  a **convention**, not a git-enforced boundary (see *failure modes*). Changing a command
  means working in *this* repo, not from inside a project.
- **Project zone** — everything else in the project working tree. **The only place
  anything is written.** Editable *data* lives under the visible `an_project/`
  (`an_project/work/`, `an_project/docs/`, `an_project/CHANGELOG.md`,
  `an_project/project-description.md`); editable *config* stays at the project **root**
  (`CLAUDE.md`, `.claude/`), because Claude Code discovers those only there.

### The one rule, and how it is encoded in text

> **The core is read-only at runtime; `skeleton/` is write-only at setup time.**

Inside `commands/`, the path *prefix* is the zone marker — and since both zones are now
prefixed, the prefix carries the whole distinction:

| Path form in a command | Meaning |
|---|---|
| `.an_framework/templates/task.md`, `.an_framework/docs/guidelines.md` | **read** — framework zone |
| `an_project/work/`, `an_project/CHANGELOG.md`, `an_project/project-description.md` | **write target** — project zone |
| `an_project/docs/git.md`, `an_project/docs/guidelines.md` | **read** — project overlays; read *after* their baseline |
| `CLAUDE.md`, `.claude/`, `CLAUDE-ARCHIVE.md` | **project root** — never move under `an_project/` (discovery) |
| a path handed to `git add` / `git commit` (`/commit` only) | **stage target** — project zone; `.an_framework` is never staged as project work |

The load-bearing point: a command that *writes* into `.an_framework/…` would put project
data where the next framework update overwrites it and where the project does not own it.
The guard in the repo's own `CLAUDE.md` is a grep that must stay silent:

```sh
grep -rnoE '\.an_framework/(work|CHANGELOG|project-description)[^ ]*' commands/
```

`docs` is deliberately absent from that alternation: `.an_framework/docs/` (baseline) and
`an_project/docs/` (overlay) are both legitimate reads.

The reverse mistake is just as bad: a **command** that reads from `skeleton/`. That path
does not exist at project runtime — `skeleton/` is copied out once, at setup. The one
exception is `init.sh` itself, which reads `skeleton/` at setup time; but `init.sh` is
setup-time logic, not a command, and the grep is scoped to `commands/`.

### The two axes as a grid

```
                    │ DATA (passive)                  │ LOGIC (active)
────────────────────┼─────────────────────────────────┼──────────────────────────
 FRAMEWORK ZONE     │ README.md                       │ commands/*.md  (10 + alias)
 .an_framework/**   │ AGENT-GUIDE.md                  │
 READ-ONLY          │ docs/guidelines.md              │ (they read data,
 (by convention)    │ docs/git-conventions.md         │  they write only into
                    │ docs/framework-architecture.md  │  the project zone)
                    │ templates/{epic,story,task}.md  │
                    │ templates/tech-stack/*/stack.md │  init.sh  (setup-time logic:
                    │ tutorial/{quickstart,update,    │  reads skeleton/, writes the
                    │            how-to-use}          │  project zone, once)
                    │ VERSION                         │
────────────────────┼─────────────────────────────────┼──────────────────────────
 PROJECT ZONE       │ an_project/project-description.md│ .claude/commands/*.md  (10
 WRITABLE           │ an_project/docs/architecture.md  │  stubs — the only logic
   data →           │ an_project/docs/technical.md     │  the project owns)
   an_project/      │ an_project/docs/deployment.md    │
   config → root    │ an_project/docs/styleguide.md    │
                    │ an_project/docs/tech-stack.md    │
                    │ an_project/docs/guidelines.md (overlay)
                    │ an_project/docs/git.md        (overlay)
                    │ an_project/work/{epic,story,task}/
                    │ an_project/CHANGELOG.md          │
                    │ CLAUDE.md            (stub, ROOT)│
                    │ .claude/settings.json     (ROOT) │
────────────────────┴─────────────────────────────────┴──────────────────────────
 SETUP-TIME ONLY: skeleton/**  — templates for the whole PROJECT ZONE row, pre-split
   into skeleton/root/ (→ project root) and skeleton/an_project/ (→ an_project/).
   Copied by init.sh, once; never read at runtime by a command.
```

---

## The stub indirection

Two things had to cross the zone boundary from project into framework: the **slash
commands** and the **agent guide**. Claude Code offers exactly one mechanism for
each, and neither one reaches into `.an_framework/` on its own. Hence stubs.

### Commands — why the indirection exists

Slash commands are discovered in `<cwd>/.claude/commands`, **its parent directories**,
and `~/.claude/commands`. A directory at `.an_framework/.claude/commands` is in none of
those — there is no setting that extends the search path to it.

(The parent-directory fallback matters in exactly one place: from a subdirectory the
commands still resolve while the rules do not — see *failure modes*. `.claude/settings.json`,
by contrast, is cwd-only with no fallback.)

So the project keeps ten real command files in its own `.claude/commands/`, each
three short instructions wrapped in frontmatter, and each one does nothing but
delegate. `skeleton/root/.claude/commands/new-task.md`:

```markdown
---
description: Create a new task under a story, an epic, or standalone
argument-hint: "[EEE-SSS] <title>"
---
Read `.an_framework/commands/new-task.md` and follow it exactly.

Arguments: $ARGUMENTS

If `.an_framework/commands/new-task.md` does not exist, **stop immediately**. Report:
"Framework not initialised — `.an_framework/` is missing. Re-run the framework setup,
or restart Claude from the project root."
Create nothing, write nothing, and do not improvise IDs or folder structure.
```

Three parts, all load-bearing:

1. **The delegation line.** The stub is discovered; the real body is read from the
   framework zone. All logic lives in one place, versioned with the framework.
2. **`Arguments: $ARGUMENTS`.** `$ARGUMENTS` is substituted **only in the stub file
   itself** — not in files pulled in afterwards with Read. `commands/new-task.md`
   contains the literal token `$ARGUMENTS`, and it is never expanded there. **Removing
   that line silently breaks every command that takes an argument.**
3. **The abort clause.** If `.an_framework/` is missing, the Read fails. Without an
   explicit instruction the agent would invent an ID scheme and a folder layout. The
   clause turns a silent wrong answer into a loud stop. Its recovery text is
   *re-run the setup* (no `git submodule update` — there is no submodule).

Consequence: **stubs are near-frozen.** They change only when a command is added or
removed, or when its `description` / `argument-hint` changes.

### `CLAUDE.md` — the `@` import path

The project's `CLAUDE.md` pulls in the framework rules with a single line:

```markdown
@.an_framework/AGENT-GUIDE.md
```

This is an import, not a copy: the project never holds a stale duplicate of the rules.
The rest of the file is the project's own — a pointer list, an optional
`@an_project/docs/tech-stack.md` import that `/new-project` adds (a project-zone import,
so it crosses no boundary), and a "Project-specific rules" section that wins over the
imported text.

The failure mode is what makes the next paragraph necessary: **a failed `@` import is
skipped silently.** No error, no log. A session with a missing `.an_framework/` looks
exactly like a healthy one, except the agent has never seen the work model. So
`skeleton/root/CLAUDE.md` follows the import with a **load check** — an instruction to
verify that a "Work model" section with the `EEE-SSS-TTTT` scheme actually arrived, and
to stop and say so if it did not. That check is the only detection mechanism available.

---

## Part-by-part (role · reads · read-by · coupling)

### Framework core — `.an_framework/**`, read-only at runtime

#### `AGENT-GUIDE.md` — the public surface
- **Role:** the rules every consuming project imports: the two zones, the overlay
  rule, the work model, the task lifecycle.
- **Read by:** every project's `CLAUDE.md`, via `@.an_framework/AGENT-GUIDE.md`.
- **Reads:** nothing. It *names* paths but executes nothing.
- **Coupling:** the highest-blast-radius file in the repo — every word ships to every
  project. Treat edits as releases.

#### `CLAUDE.md` — guide for working *on* the framework
- **Role:** orients an agent editing this repo. States the read-only rule, the zone
  markers, and the grep guards.
- **Not part of the shipped surface** — a project imports `AGENT-GUIDE.md`, never this
  file. In a project it sits at `.an_framework/CLAUDE.md`; its first line states outright
  that this repo is the framework core, so an agent that does see it cannot mistake it
  for the project's guide. Keep that first line.

#### `commands/{board,briefing,commit,done,implement,new-epic,new-project,new-story,new-task,refine}.md`
- **Role:** the ten real commands, in full. An eleventh file, `implement-task.md`, is a pure
  alias that reads `implement.md` and holds no logic — projects set up before v2.2 own a
  `.claude/commands/implement-task.md` stub, and `init.sh` never deletes or overwrites a stub
  it already finds. The skeleton ships only `implement.md`, so new projects get ten stubs.
- **Read by:** the matching stub in the project's `.claude/commands/`.
- **Reads / writes** (the only place the zone rule bites):

  | Command | Reads (framework zone) | Writes (project zone) |
  |---|---|---|
  | `new-project` | `CLAUDE-ARCHIVE.md` when retrofitted (project zone, read as data); the `an_project/.framework-profile` marker; `templates/tech-stack/<slug>/{stack,runbook}.md` (full profile) | `an_project/project-description.md`, `an_project/docs/{tech-stack,runbook}.md`, the `@`-import line in `CLAUDE.md`, `an_project/CHANGELOG.md`, and after confirmation `an_project/docs/**` |
  | `new-epic` | `.an_framework/templates/epic.md` | `an_project/work/epic/…`, `an_project/CHANGELOG.md` |
  | `new-story` | `.an_framework/templates/story.md` | `an_project/work/…`, `an_project/CHANGELOG.md` |
  | `new-task` | `.an_framework/templates/task.md` | `an_project/work/**`, `an_project/CHANGELOG.md` |
  | `refine` | `.an_framework/docs/guidelines.md` (narrowed by `an_project/docs/guidelines.md`) | the target file, `an_project/CHANGELOG.md` |
  | `implement` | `.an_framework/docs/{guidelines,git-conventions}.md` (narrowed by `an_project/docs/{guidelines,git}.md`); `.an_framework/commands/{commit,new-task,new-story,refine}.md` for its handoffs | a git branch (`git checkout -b`) — the *task's* at task level, the *story's* at story level, **none** at epic level; the item's `status`; the code; `an_project/CHANGELOG.md`; auto-commits via `/commit` |
  | `done` | `.an_framework/{docs/git-conventions.md, commands/commit.md}`, `an_project/docs/git.md` (integration branch), `an_project/work/**` frontmatter | the item's `status` (a story also flips its **child tasks**), the parent's checklist box, + `an_project/CHANGELOG.md`, committed on the item's branch **via `/commit`**; then git history — a **local** `--no-ff` merge into the integration branch, never a push. An epic has no branch: status only, no merge |
  | `board` | — | nothing (read-only; no changelog entry) |
  | `briefing` | `an_project/project-description.md`, `an_project/docs/**`, `an_project/CHANGELOG.md`, `an_project/work/` (all project zone, read as data) | nothing (read-only; no changelog entry) |
  | `commit` | `.an_framework/docs/git-conventions.md` (narrowed by `an_project/docs/git.md`), `an_project/work/` frontmatter for the id | nothing in the working tree — the git index and history only |

  **Three** commands touch git now. `/commit` is the only one that composes a message and
  stages named paths; its rules are prohibitions rather than steps: named paths only,
  `-F -` instead of `-m`, porcelain instead of prose, and a hard stop if `.an_framework`
  is **staged** — the committed core is ordinary files now and must never be recorded as
  project work. `/done` runs a **local** `git merge --no-ff --no-edit` into the integration
  branch — never a push, and never routed through `/commit` (that aborts on `MERGE_HEAD`).
  `/implement` runs only `git checkout -b` to open the item's branch. See *failure modes*.

- **Coupling:** commands do not depend on each other, with one sequencing cluster:
  `/implement` auto-invokes `/commit`, and `/done` closes what `/implement` opened
  (branch → review → merge). At story level `/implement` also hands off to `/new-task` and
  `/refine` when the story has no tasks yet — it never hand-rolls ids, files or the parent's
  checklist. `new-project` is the only `new-*` that reads templates
  (the tech-stack `stack.md` + `runbook.md`), and the only one whose template read writes
  outside `work/`. They depend on the template paths, the `an_project/work/` layout, the ID
  convention, and the frontmatter schema.

#### `templates/` — two kinds
- **Work-item blueprints** `{epic,story,task}.md` — read by `new-epic/story/task`
  (one line each). Their **frontmatter schema** (`id`, `title`, `status`, `depends_on`)
  is the one hard contract.
- **Tech-stack descriptions** `tech-stack/<slug>/stack.md` — free prose, **no
  frontmatter**, read by `new-project` and copied to `an_project/docs/tech-stack.md`.
  A future `docker-compose.template.yml` may sit beside each `stack.md`; not built yet.

#### `docs/guidelines.md` · `docs/git-conventions.md` — baselines
- **Read by:** `AGENT-GUIDE.md` (by reference), `refine`, `implement`, and
  `commit` (git-conventions only). **Narrowed by:** the project's
  `an_project/docs/guidelines.md` / `an_project/docs/git.md`.
- **Coupling:** git-conventions is no longer a passive leaf — `/commit` reads it to make
  decisions, so a section renamed here changes command behaviour there.

#### `docs/framework-architecture.md` (this file) · `README.md` · `tutorial/*`
- Pure data / routing / manuals. `README.md` carries **no setup commands** by design;
  the setup sequence lives once in `quickstart.md`, built around the bootstrap and
  `init.sh`. `README.md` is rendered from the *default branch* in GitLab.

#### `VERSION`
- **Role:** the release number, tagged. Since there is no submodule gitlink, a project's
  committed `.an_framework/VERSION` **is** the record of which version it carries — it is
  load-bearing now, not merely documentation. Regenerate it from the tag on release.

#### `init.sh` — setup-time logic in the core
- **Role:** lays the project down from the core after the bootstrap. FILES ONLY — never
  runs git; prints the setup commit for the human. Non-destructive (copy-if-absent),
  preserves an existing `CLAUDE.md` → `CLAUDE-ARCHIVE.md`, deep-merges `.claude/`
  (settings union + the deny-path rewrite), and writes `an_project/SETUP-REPORT.md`.
- **Reads:** `skeleton/root/`, `skeleton/an_project/`, `skeleton/gitignore.snippet`.
  **Writes:** the project zone (root config + `an_project/`). This is the single slot in
  the model where core logic reads `skeleton/` and writes the project — at setup only.

#### `skeleton/**` — write-only, at setup time
- **Role:** the initial contents of a new project's zone, pre-partitioned into
  `root/` (→ project root: `CLAUDE.md`, `.claude/`) and `an_project/` (→ `an_project/`:
  docs, work, project-description, CHANGELOG). `gitignore.snippet` is an `init.sh` input,
  not a copy target.
- **Coupling:** to the project zone's shape, not to any runtime behaviour. If a skeleton
  file drifts from what the commands expect, nothing errors — projects created *after*
  the drift are simply wrong.

### Project zone — the files `skeleton/` seeds

#### `CLAUDE.md` ← `skeleton/root/CLAUDE.md`  (project ROOT)
- `@.an_framework/AGENT-GUIDE.md`, the load check, a pointer list, the optional
  `@an_project/docs/tech-stack.md` import, and project rules that win over the import.

#### `.claude/commands/*.md` ← `skeleton/root/.claude/commands/*.md`  (project ROOT)
- The ten stubs. Filename-for-filename with `commands/`. The tightest coupling in the
  system, and deliberately trivial: one name, one line.

#### `.claude/settings.json` ← `skeleton/root/.claude/settings.json`  (project ROOT)
- `deny: ["Edit(/.an_framework/**)"]` — the **primary** automated guard (git no longer
  backstops it, see *failure modes*). `allow` broadens along two axes but stays deliberately
  scoped:
  - **Project write-zones** — `Read`/`Edit`/`Write` on `an_project/**`, `src/**`, `tests/**`,
    so routine work stops prompting. Not a blanket `**`: `CLAUDE.md`, `.claude/` and `.git`
    stay prompt-gated, and the `.an_framework/` deny still wins over any allow (deny precedes
    allow). A project may widen its own copy for stack-specific folders.
  - **Git — flag forms, not verbs.** The pre-approved forms are the read probes plus
    `git add --`, `git reset -q --`, `git commit -F -`, `git checkout -b`,
    `git merge --no-ff --no-edit`, and the read-only `git for-each-ref` (`/done` lists
    branches to find the task branch by id). Verbs and unbounded flag forms are excluded on
    purpose — `Bash(git checkout:*)` would also admit `git checkout -- <file>`,
    `Bash(git merge:*)` arbitrary merges, and even `Bash(git branch -d:*)` would admit a
    *trailing* `-D` force-delete (`git branch -d x -D y`), so it is deliberately left out.
    Consequence: the **branch switch** `git checkout <branch>` (`/done` steps 6–7,
    `/implement` resume) and the optional `git branch -d` cleanup are **not**
    pre-approved — they prompt once, which is the safe default — and `git push`,
    `git reset --hard`, `git commit -a/--amend` likewise fall through to a prompt. The
    flag-form allow cannot by itself stop `git add -- .an_framework`; that is `/commit`'s
    staged-core preflight plus convention.
- `init.sh` merges this file on retrofit (union) and **rewrites** a stale `Edit(/.framework/**)`
  to `.an_framework` — the one entry the additive merge is allowed to replace. On an *update*
  the already-initialised guard makes `init.sh` exit early, so a broadened default reaches
  new and retrofit projects, not ones already initialised.

#### `an_project/…` ← `skeleton/an_project/…`
- `project-description.md` (charter, read at session start), `docs/*` (project docs +
  overlays + the copied `tech-stack.md`), `work/{epic,story,task}/` (the backlog, via
  the frontmatter schema and ID convention), `CHANGELOG.md` (append-only, many writers,
  **never read to make a decision**).

#### `.gitignore` ← `skeleton/gitignore.snippet`
- The snippet is pasted into the project's `.gitignore`. It documents why
  `.an_framework/` must **not** be ignored (it is committed content, so a plain clone
  reproduces it) and ends with `!.an_framework/**` so the broad credential globs cannot
  silently drop a framework file on `git add`.

---

## The dependency graph

Arrow = "depends on / references". The double line is the zone boundary: reads cross it
left-to-right, **writes never do**.

```
  PROJECT ZONE (writable)                   ║  FRAMEWORK ZONE (.an_framework/, read-only)
                                            ║
  CLAUDE.md ───────@ import─────────────────╫──▶ AGENT-GUIDE.md
    │  (+ load check: verify it arrived)    ║        │ (names paths only — no execution)
    ├─▶ an_project/project-description.md    ║        ▼
    ├─▶ an_project/docs/* · tech-stack.md    ║   [ work model · ids · lifecycle · rules ]
    ├─▶ an_project/docs/guidelines.md ─narrows╫─▶ docs/guidelines.md      (baseline)
    ├─▶ an_project/docs/git.md ───────narrows─╫─▶ docs/git-conventions.md (baseline)
    ├─▶ an_project/work/{epic,story,task}/   ║
    └─▶ an_project/CHANGELOG.md              ║
                                            ║
  .claude/commands/<name>.md  (8 stubs,ROOT)║   commands/<name>.md   (8 real bodies)
    │  "Read .an_framework/commands/<name>" ║        │
    │  "Arguments: $ARGUMENTS"  ────────────╫────────┘  (substituted in the STUB only)
    │        ┌──── new-epic/story/task ─────╫────────▶ templates/{epic,story,task}.md
    │        ├──── new-project ─────────────╫────────▶ templates/tech-stack/<slug>/stack.md
    │        ├──── refine / implement ──────╫────────▶ docs/guidelines.md (+git-conventions)
    │        └──── commit ──────────────────╫────────▶ docs/git-conventions.md
    ▼                                       ║
  writes: an_project/**  ·  src/**          ║
  /commit ──▶ the git index and history (refuses staged .an_framework)
                                            ║
  ──────────────────────────────────────────╫─────────────────────────────────────────
  SETUP TIME ONLY:  init.sh (in the core) ──reads──▶ skeleton/{root,an_project}/
                                          ──writes─▶ the project zone (once)
```

---

## The narrow waists

Strip away the soft pointers and the append-only log, and **two contracts remain.**

### 1. The data contract — the work-item schema

```
id · title · status · depends_on  (the frontmatter schema)
EEE-SSS-TTTT                      (full-address ids; 000/0000 = "no level here")
an_project/work/{epic,story,task}/   (three roots)
status: todo → in-progress → review → done   (plus blocked)
```

Defined by the three files in `templates/`, written by `/new-*`, read by `/board`,
`/implement` and `/done`. It survived both the v1 rebuild and the v2 distribution
change untouched — the point of a narrow waist.

### 2. The wiring contract

```
stub → command      .claude/commands/<name>.md  reads  .an_framework/commands/<name>.md
                    and restates "Arguments: $ARGUMENTS" in its own body
path prefix         inside commands/: ".an_framework/…" = READ, "an_project/…" = WRITE
git paths           /commit hands paths to git; ".an_framework" is never staged as work
archive file        init.sh creates CLAUDE-ARCHIVE.md on retrofit; /new-project reads it
                    when present — its ABSENCE must stay valid, or fresh setup breaks
```

Same filename on both sides, two path prefixes that mean read and write. No manifest,
no registry, no build step. The old wiring had a fifth line — the submodule gitlink as
"the one thing a project can commit about the framework." That line is gone: the whole
core is now committed content, and the thing a project must *not* do is record a change
to it as project work (the `/commit` preflight).

### Why this stays decoupled

1. **Data has zero outgoing dependencies** — rewrite any doc or template freely.
2. **Commands don't depend on each other** — delete `/board`, nothing else breaks.
3. **Two tiny shared contracts** — 3 fields + an ID rule + 3 folders; 1 filename
   convention + 2 path prefixes.
4. **The zone boundary is one-directional** — reads cross it, writes never do.
5. **`CHANGELOG.md` has many writers but is never read for logic** — history without
   coupling.

---

## Failure modes — what actually protects what

The framework's guarantees are **conventions in text**, not enforcement. Being honest
about which is which is part of the architecture — and v2 shifts the honesty, because the
git-enforced boundary the submodule gave for free is gone.

### The empty-core failure class is gone

`git clone` without `--recurse-submodules` used to leave `.framework/` empty, silently
skipping the `@` import and firing every stub's abort clause. Since `.an_framework/` is
committed content, a plain `git clone` reproduces it — that entire class disappears, and
with it the `--recurse-submodules` requirement and the `git submodule update --init`
recovery. The stub abort clause and the load-check therefore now point at *re-run the
setup / restart at the project root*, the two causes that remain.

### Started from a subdirectory — still real

If Claude Code is started from a subdirectory of the project instead of its root:

- the relative `@` import resolves **outside the working directory** → treated as an
  *external import*, performed only after a prompt, and a single "No" disables it
  **permanently**;
- `.claude/settings.json` is **not loaded** (cwd-only, no parent fallback) — the deny
  rule and the `allow` list are both absent;
- **slash commands are still found** (cwd *and parents*), so the stubs resolve, each
  stub's relative read of `.an_framework/commands/<name>.md` misses, and it fires its
  abort clause. Visible symptom (commands present) and actual fault (rules absent) are
  decoupled.

`/context` is the positive check: its *Memory Files* table lists
`.an_framework/AGENT-GUIDE.md` when the import actually arrived. Every path in this
framework must stay CWD-relative, and sessions must start at the project root.

### `permissions.deny` — now the primary guard, and still weak

`skeleton/root/.claude/settings.json` denies `Edit(/.an_framework/**)`.

- `Edit(...)` in a deny rule works for the Edit tool; `Write(...)` is inert — do not
  rely on it.
- It is **not a security boundary**: a Bash command (`sed -i`, `cat >`, `rm`) bypasses
  it, and it is not loaded at all in a subdirectory session.

Under v1 this was "a loud hint, not the real boundary" — git was the real boundary,
because a submodule made a stray edit uncommittable and `git submodule update` discarded
it. **That backstop is gone.** `.an_framework/` is ordinary tracked files; a stray edit
is a normal, committable change, and nothing auto-reverts it — recovery is
`git checkout -- .an_framework/<path>` or re-extracting the release. So the deny rule
plus the `/commit` preflight plus convention are *all* the protection there is. This is a
real reduction, accepted deliberately in exchange for a framework that a plain `git clone`
reproduces with no submodule literacy.

### A command that runs git — the hole, restated for committed files

Under v1 the danger was a **moved gitlink SHA**: `git add -A` at the root staged a
one-line pointer that silently re-pinned the framework. That specific hole is gone. The
new one is its inverse: because `.an_framework/` is ordinary files, `git add -A` (or a
stray hand-edit) can stage **framework content** as if it were project work, and git will
happily commit it into a silent per-project fork of the "immutable" core.

So `/commit` stages named paths only, never `-A`/`.`/`-u`, and preflights with

```sh
git diff --cached --name-only -- ':(top).an_framework'
```

Two details are load-bearing. It probes **staged**, not merely dirty: an in-progress
framework install/update leaves `.an_framework` dirty, and stopping every routine commit
on that would cry wolf (the v1 probe used `--ignore-submodules=dirty` for the same
reason). And `:(top)` is required because a bare pathspec is cwd-relative. On a hit,
`/commit` discriminates the setup commit from an update with
`git rev-parse -q --verify HEAD:.an_framework` (fails before the core is first committed,
succeeds after) and hands over the right dedicated commit — it never makes the framework
commit itself.

The permission `allow` list helps only partly: `Bash(git add --:*)` already prefix-matches
`git add -- .an_framework/…`, and a reordered pathspec dodges any deny — so the permission
layer cannot gate framework staging. What actually makes the mistake loud is the preflight.

The two other git-touching commands do **not** reopen this hole. `/implement`'s own git
is only `git checkout -b`/`checkout` (it stages nothing; its commit is delegated to
`/commit`). `/done` stages only *through* `/commit` — for the `done`-status flip, under the
same named-path, refuses-`.an_framework` contract — and its own step is a branch
`git merge --no-ff` (never a push, never routed through `/commit`, which aborts on
`MERGE_HEAD`). So neither can record `.an_framework` as project work. `/done`'s preflight
mirrors `/commit`'s (aborts on detached HEAD, an in-progress merge/rebase, or a dirty tree),
so it never runs on top of half-finished state.

### Skeleton drift — now with a propagation path

`skeleton/` is copied once. If a command starts expecting something the skeleton does not
seed — a new `work/` root, a new frontmatter key, a ninth command — existing projects
break *silently* and new projects are born wrong. What changed in v2.2: `init.sh --update`
is a real (if manual) propagation mechanism, and it is additive by construction, so it can
add new skeleton files without clobbering project-owned ones. (Before v2.2 the
already-initialised guard made a re-run a no-op, so the propagation mechanism the docs
promised did not actually exist. `--update` is the opt-in that gets past that guard; the
guard itself stays, because a fresh clone of a v2 project must not re-run init.)
**Any change to a command's expectations about the project zone must still be paired with
the matching skeleton change and a row in the *Was welche Version verlangt* table in
`tutorial/update.md`** about what existing projects have to do — the update run helps, but
nothing detects a drift automatically.

The v2.2 rename is the worked example: `implement-task.md` → `implement.md` needed a new
stub in the skeleton (propagated only by `--update`), a *retained* alias in `commands/`
(because `init.sh` never deletes a project-owned stub), and the table row that tells the
reader the update run is mandatory this time.
