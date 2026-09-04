# Framework rules (imported from `.an_framework/`)

How work is structured in every project that mounts this framework. These rules
are the baseline; the project's own `CLAUDE.md` and `an_project/docs/` narrow them, and on
conflict the project wins.

## Two zones

**Read only — the framework, committed into the project at `.an_framework/`:**
- `.an_framework/commands/` — the real slash commands (`board`, `briefing`, `commit`,
  `done`, `implement`, `new-epic`, `new-project`, `new-story`, `new-task`, `refine`).
  The files in the project's own `.claude/commands/` are thin stubs that load these
  and stop if the framework is missing. `implement-task.md` is kept beside them as a
  pure alias of `implement`, so projects set up before v2.2 keep working.
- `.an_framework/templates/` — the item blueprints (`epic.md` · `story.md` · `task.md`)
  and `tech-stack/<slug>/stack.md`, the stack descriptions `/new-project` copies in.
- `.an_framework/docs/guidelines.md` — baseline code style.
- `.an_framework/docs/git-conventions.md` — baseline branch/commit/PR rules.

**Write and maintain — the project:**
- `an_project/project-description.md` — what this project is. **Read before starting.**
- `an_project/docs/` — this project's own architecture, technical, deployment, styleguide.
  Full projects also carry `runbook.md` (how to run the project locally after checkout)
  and `dev-guide.md` (backend/frontend structure + how to add e.g. an API route). A **slim**
  project (chosen at setup, e.g. a plain HTML page) has only `work/` + `CHANGELOG.md` +
  `project-description.md` and the two overlays — no tech-stack, runbook or dev-guide, and
  no Docker/environment scaffolding. Commands tolerate a missing doc — they skip it.
- `an_project/docs/guidelines.md` · `an_project/docs/git.md` — overlays (see below).
- `an_project/work/` — the backlog: `epic/`, `story/`, `task/` roots.
- `an_project/CHANGELOG.md` — running history of what changed. Keep it updated.

**Overlay rule:** the project's `an_project/docs/guidelines.md` and `an_project/docs/git.md` *narrow*
the framework baseline. Read the framework file first, then the project file; on
conflict the project file wins. They never replace the baseline, only tighten it.

**Hard rule:** never write, edit, create — or stage or commit — anything under
`.an_framework/`. It is read-only at runtime. To change a command, template, or baseline
doc, work in the framework repo itself — not from inside a project.

## Work model
Work items are **Epic → Story → Task**, but the hierarchy is optional — a task can
stand alone, and a story can exist without an epic.

- **Where they live** — three roots under `an_project/work/`:
  - `an_project/work/epic/` — epics, with their stories and tasks nested inside.
  - `an_project/work/story/` — stories that have no epic (tasks nested inside).
  - `an_project/work/task/` — ad-hoc tasks with no epic or story.
- **IDs** — every item has a full address `EEE-SSS-TTTT` (3-digit epic, 3-digit
  story, 4-digit task). `000`/`0000` = "no level here".
  Examples: epic `001-000-0000` · story under it `001-001-0000` · its task
  `001-001-0001` · standalone story `000-002-0000` · ad-hoc task `000-000-0004`.
- **Numbers are handed out per parent.** Only `EEE` is project-wide. `SSS` counts the
  stories of *one* epic and `TTTT` the tasks of *one* story, each starting at 1 again
  under the next parent — `001-001-0001` and `001-002-0001` are two different tasks.
  The full triple is still unique, because the parent is part of it. Consequences:
  - **An id only ever means something in full.** Never shorten one to its last part,
    never say "task 1" — a wrong-but-existing id in a `depends_on` looks valid.
  - **Never move or renumber an item.** Its id encodes its parent, so a story moved to
    another epic collides with that epic's own numbering — and it orphans the branch
    name and every `Ref:` already written into history. Close it and open a new one.
- Every item starts with YAML frontmatter (`id`, `title`, `status`, `depends_on`).
- Don't create items by hand — use `/new-epic`, `/new-story`, `/new-task`.

## Lifecycle
The lifecycle presupposes the work item already exists (see *Kein Code ohne Ticket* below).
Two commands drive it end to end:

1. Pick an unblocked item with `status: todo` (all its `depends_on` are done).
2. **`/implement <id>`** — implements it, following the guidelines and git rules (framework
   baseline, narrowed by `an_project/docs/guidelines.md` and `an_project/docs/git.md`),
   verifying, **auto-committing via `/commit`**, and leaving `status: review` with an
   `an_project/CHANGELOG.md` entry. **The id decides the level** — it takes the short forms
   `EEE` and `EEE-SSS` too:
   - **task** (`TTTT ≠ 0000`) — opens the task's own branch `<type>/<id>-<slug>`, unless its
     parent story is already in progress; then the task is one commit on the story's branch.
   - **story** (`SSS ≠ 000`, `TTTT = 0000`) — opens **one story branch**
     `<type>/<EEE-SSS-0000>-<slug>` and works its tasks on it in `depends_on` order, **one
     commit per task** (`Ref: #<task-id>`). Never a branch per task. A story with no tasks
     yet is first cut into tasks via `/new-task` + `/refine`, confirmed, and only then built.
   - **epic** (`SSS = 000`, `TTTT = 0000`) — **plans only**: no branch, no code, no commit of
     its own. It orders the stories and asks before **each** one, then hands it to the story
     flow. There is no way to run a whole epic unattended, by design.
3. **`/done [<id>]`** — closes it. A task or story on its own branch is merged **locally**
   into the integration branch (`git merge --no-ff`, no push) and set `done`; a story sets its
   **child tasks** `done` with it, since they were committed on its branch. An epic has no
   branch — it only records `done`, once its stories are merged. `/done` also ticks the item's
   box in its parent's `## Tasks` / `## Stories` list. With exactly one closeable item in
   `review` it picks that one; otherwise it asks which.

Status values: `todo → in-progress → review → done` (plus `blocked`).

## Rules
- **Kein Code ohne Ticket.** Never implement, edit, or change code from a bare
  instruction. If a request has no backing task (and therefore no story/epic), **stop
  before touching anything** and ask first — verbatim:
  > „Dafür gibt es noch keinen Task. Ich lege einen an — unter der aktuellen Story, unter
  > einem Epic, oder standalone (ohne Epic/Story)?"

  Then create it with `/new-task` and implement only afterwards. The answer maps onto
  `/new-task`'s parent: aktuelle Story → `/new-task <EEE-SSS>`; Epic → `/new-task <EEE>`;
  standalone → `/new-task` (no parent). If the current story is ambiguous, name the
  candidate and confirm before creating. Never implement first and file the ticket after.
- **Content language.** Everything the framework *produces* — work-item titles and
  descriptions, `an_project/CHANGELOG.md` entries, project docs, commit bodies, and what
  you say to the user — is written in **German**. Established technical/loan terms stay
  English (deployment, developer, branch, commit, review, merge, feature, bugfix,
  changelog, …). The frontmatter keys (`id`, `title`, `status`, `depends_on`) and the
  status values (`todo`/`in-progress`/`review`/`done`/`blocked`) stay English — the
  commands parse them. Commit-message specifics: `.an_framework/docs/git-conventions.md`.
- Never hand-roll ids or folder structure — always the `/new-*` commands.
- Never compose a commit message yourself — always `/commit`. Two exceptions, both
  machine-made and never hand-authored: `/done`'s local merge commit (it uses
  `git merge --no-edit`, git's default message), and a framework install or update — it
  touches `.an_framework/`, which `/commit` refuses to stage as project work, so it goes
  in its own `chore(framework): …` commit that the init script prepares.
- Never write into `.an_framework/` — it is read-only at runtime. Never stage its files
  as part of project work; a framework install or update is its own `chore(framework)` commit.
- After any notable change (creating/refining items, implementing tasks, status
  changes, editing docs), append a dated entry to `an_project/CHANGELOG.md`.
