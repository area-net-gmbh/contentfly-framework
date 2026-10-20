# ANNA framework rules (loaded from `.an_framework/`)

How work is structured in every project that mounts this framework. These rules are the
baseline; the project's runtime guide (`CLAUDE.md` or `AGENTS.md`) and `an_project/docs/`
narrow them, and on conflict the project wins. That overlay rule holds for every pair below:
read the framework file first, then the project file.

## Two zones

**Read only — the framework, committed into the project at `.an_framework/`:**
- `.an_framework/commands/` — the shared workflows (`board`, `briefing`, `commit`, `doc`,
  `done`, `implement`, `migrate-adrs`, `new-epic`, `new-project`, `new-story`, `new-task`,
  `refine`).
  Runtime adapters under `.claude/commands/` and `.agents/skills/` load these files and stop
  if the framework is missing. `implement.md` is a router that reads exactly one of
  `commands/implement/{task,story,epic}.md`; `implement-task.md` is a pure alias of it, so
  projects set up before v2.2 keep working.
- `.an_framework/templates/` — the item blueprints (`epic.md` · `story.md` · `task.md`)
  and `tech-stack/<slug>/stack.md`, the stack descriptions the `new-project` workflow copies.
- `.an_framework/docs/guidelines.md` — baseline code style.
- `.an_framework/docs/git-conventions.md` — baseline commit format (read by `commit`).
- `.an_framework/docs/git-branching.md` — baseline branch rules (read by `implement`).
- `.an_framework/docs/git-integration.md` — baseline merge/PR rules (read by `done`).
- `.an_framework/docs/stakeholder-doc.md` — baseline for the stakeholder documentation: who
  reads it, what goes in which chapter, when it is carried along. Read by `doc`, `new-project`
  and `done`; by `implement` only when one of its six triggers fires.
- `.an_framework/scripts/build-stakeholder-doc.py` — builds `an_project/stakeholder/` into
  offline HTML. The one script a project *runs* from the core besides the hooks; it writes only
  into `an_project/stakeholder/html/`.

**Never read at runtime:** `.an_framework/tutorial/`, `.an_framework/docs/framework-architecture.md`,
`.an_framework/README.md` — written for humans, needed by no command. Exclude `.an_framework/`
from code searches too; it is a copy of the framework, not project code.

**Write and maintain — the project:**
- `an_project/project-description.md` — what this project is. **Read before starting.**
- `an_project/docs/` — this project's own architecture, technical, deployment, styleguide;
  full projects also `runbook.md` (run it locally) and `dev-guide.md` (code structure). A
  **slim** project (chosen at setup, e.g. a plain HTML page) has only `work/`, `CHANGELOG.md`,
  `project-description.md` and the two overlays — no tech-stack, runbook, dev-guide or Docker.
  Commands tolerate a missing doc — they skip it.
- `an_project/docs/guidelines.md` · `an_project/docs/git.md` — the project's overlays. The one
  `git.md` narrows **all three** git baselines above.
- `an_project/work/` — the backlog: `epic/`, `story/`, `task/` roots.
- `an_project/CHANGELOG.md` — running history of what changed. Append-only, newest on top;
  it is never read for logic.
- `an_project/stakeholder/` — the project documentation **for outsiders** (client, designers,
  management): Markdown chapters plus a `steckbrief.md` head, built into
  `an_project/stakeholder/html/` for reading in a browser without a server. The `html/`
  subtree is pure output — never edit a file in it; the next build overwrites it. Rules:
  `.an_framework/docs/stakeholder-doc.md`. Open it with the `doc` workflow.

**Hard rule:** never write, edit, create — or stage or commit — anything under
`.an_framework/`. It is read-only at runtime. To change a command, template, or baseline
doc, work in the framework repo itself — not from inside a project.

## Work model

Workflow invocation depends on the active runtime. Claude Code uses `/board`, `/implement`,
and the other slash commands. Codex uses `$an-board`, `$an-implement`, and the corresponding
`an-*` skills. In this guide, an unprefixed name such as `implement` means the same shared
workflow in either runtime.

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
- Don't create items by hand — use the `new-epic`, `new-story`, or `new-task` workflow.

## Lifecycle
The lifecycle presupposes the work item already exists (see *Kein Code ohne Ticket* below).
Two commands drive it end to end:

1. Pick an unblocked item with `status: todo` (all its `depends_on` are done).
2. **`implement <id>`** — implements it, verifies, **auto-commits via `commit`**, leaves
   `status: review` plus a `CHANGELOG.md` entry. **The id decides the level** (short forms `EEE`
   and `EEE-SSS` included): a **task** is one branch and one commit, a **story** is *one* story
   branch with one commit per task, an **epic** only plans and hands its stories down one at a
   time, asking before each — no epic ever runs unattended. Branch rules:
   `.an_framework/docs/git-branching.md`.
3. **`done [<id>]`** — closes it: `status: done`, and where the item owns a branch, a **local**
   `git merge --no-ff` into the integration branch (never a push). A story closes its child
   tasks with it; an epic only records `done`, once its stories are merged. Details, both
   integration paths included: `.an_framework/docs/git-integration.md`.

Status values: `todo → in-progress → review → done` (plus `blocked`).

**Every run is resumable.** After each committed task the whole state lives in git and in the
frontmatter — nothing is held in the session. `implement <story-id>` picks up at the first task
that is not yet `review` or `done`. So a long session does not have to be carried: **at an item
boundary — after `done` closed a task or a story, or between two stories of an epic — start a
fresh session and re-run the workflow.** Prefer that over compaction: compaction leaves a lossy
summary of the conversation behind, a restart leaves the files, which are the actual state.
Inside a story run do not reset — there the loaded rule files are what makes every task after
the first a cheap one.

## Rules
- **Kein Code ohne Ticket.** Never implement, edit, or change code from a bare
  instruction. If a request has no backing task (and therefore no story/epic), **stop
  before touching anything** and ask first — verbatim:
  > „Dafür gibt es noch keinen Task. Ich lege einen an — unter der aktuellen Story, unter
  > einem Epic, oder standalone (ohne Epic/Story)?"

  Then create it with `new-task` and implement only afterwards. The answer maps onto
  its parent argument: aktuelle Story → `new-task <EEE-SSS>`; Epic → `new-task <EEE>`;
  standalone → `new-task` (no parent). If the current story is ambiguous, name the
  candidate and confirm before creating. Never implement first and file the ticket after.
- **Getestet wird lokal.** In a **full** project that is the Docker environment from
  `an_project/docs/runbook.md`: features, new modules and plugins, dependency and core version
  upgrades, migrations — brought up and verified there. A **slim** project has no Docker; its
  local preview is the equivalent. **Staging, live or a customer installation are touched only
  when the user asks for it in that message and names the environment** — „teste das mal" without
  a named target means local, always. Cannot something be verified locally? Say so and ask.
  Deciding on your own to try it on a server is the one shortcut that reaches real users.
- **Content language.** Everything the framework *produces* — work-item titles and
  descriptions, `an_project/CHANGELOG.md` entries, project docs, commit bodies, and what
  you say to the user — is written in **German**. Established technical/loan terms stay
  English (deployment, developer, branch, commit, review, merge, feature, bugfix,
  changelog, …). The frontmatter keys (`id`, `title`, `status`, `depends_on`) and the
  status values (`todo`/`in-progress`/`review`/`done`/`blocked`) stay English — the
  commands parse them. Commit-message specifics: `.an_framework/docs/git-conventions.md`.
- Never hand-roll ids or folder structure — always the `new-*` workflows.
- Never compose a commit message yourself — always `commit`. Two exceptions, both machine-made:
  `done`'s local merge commit (`git merge --no-edit`, git's own message), and a framework
  install or update, which `commit` refuses to stage and which the init script prepares as its
  own `chore(framework): …` commit.
- After any notable change (creating/refining items, implementing tasks, status
  changes, editing docs), append a dated entry to `an_project/CHANGELOG.md`.
- **Capture architecture decisions as ADRs during every conversation.** When an architectural
  or cross-cutting technical decision becomes binding in the conversation, create one compact
  file under `an_project/docs/adr/` before finishing the current work. Recording the agreed
  decision is documentation bookkeeping and does not need a separate work item; implementation
  of that decision still follows the ticket gate. Do not create an ADR for
  brainstorming, a tentative option, a local implementation detail, or a decision already
  covered by an existing ADR. Use the next monotonic four-digit number and a short slug:
  `NNNN-<slug>.md`, where `NNNN` is the highest existing number plus one; never count files or
  renumber old records. Start from `.an_framework/templates/adr.md` and
  fill every section with concrete project facts: **Status, Datum, Kontext, Entscheidung,
  Begründung, erwogene Alternativen, Konsequenzen**. The explicit rationale is mandatory: a
  decision sentence alone is not an ADR. Keep the whole record small, normally well below one
  page. In `an_project/docs/architecture.md`, add only a linked index row with ADR number, short
  decision title, and status; never copy the ADR body or rationale there. If that file is absent
  in a slim project, create a minimal `# <project> — Architecture` file containing only the
  `## Architecture decisions` index because the first ADR now gives it a purpose.
  If a later decision
  replaces one, keep both files, set the old status to `superseded by ADR NNNN`, and link them.
  ADR content follows the framework's German content-language rule.

## Context discipline

Context is the scarcest resource in a long session. These are not preferences — they are how
the commands are written to work:

- **Search the backlog index; do not open every work item.** For one id, search the pattern
  `^id: <EEE-SSS-TTTT>` over `an_project/work` and return matching filenames — independent of size.
  For an overview, return only matches for `^(id|title|status|depends_on):` — four lines per item
  instead of one whole file each. Narrow to open work with
  `^status: (todo|in-progress|review|blocked)`. Read a full work-item body only for the item
  being worked on and its direct blockers.
- **`an_project/CHANGELOG.md` is appended at the head, never read whole.** Read at most its first
  30 lines and edit at the `# Changelog` header or the topmost date block.
- **Read each rule file once per session.** In a story run, `commit.md`, the git baselines and
  the guidelines are in context from the first task — do not read them again for the next one.
- **Delegate read-heavy scanning to an agent role.** `an-scout` for code reconnaissance before
  an edit, `an-briefer` for the doc/backlog scan behind `briefing`. Both are read-only by their
  runtime sandbox/tool configuration and return a bounded fact sheet. Their contracts live in
  `.an_framework/agents/`, so no workflow repeats them. Then read only what they name, in bounded
  ranges. Decisions, edits, git and the final user-facing output always stay with the main agent.
- **Keep tool output out of the log.** Run tests with their quiet/summary flag or pipe them
  through `tail`; carry over the summary line on success, the failing part on failure. A hook
  blocks the best-known offenders and names the capped form to retry with.
- **A fresh session orients itself.** A SessionStart hook prints branch, open items and the last
  changelog lines automatically after a fresh or compacted session. Run `board` or `briefing`
  only when you need more than those few lines.
- **Put the session's knowledge on disk before it ends.** A reset only costs nothing because the
  state is in the files — so anything agreed in the conversation and written nowhere is what a
  reset actually loses. Before recommending one, check the three places that hold it: the work
  item (open points, changed acceptance criteria), `an_project/CHANGELOG.md` (what happened), and
  an ADR (a binding architecture decision). One line in the right file beats any amount of
  carried history.
