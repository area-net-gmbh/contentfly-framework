# ki-dev-framework — working ON the framework

**This repo is the framework core, not a project built with it.** There is no
backlog here and no `/new-*` commands — those only exist inside a consuming
project, where this repo's contents are committed as `.an_framework/`.

If you were looking for the agent guide that projects use, it is
[AGENT-GUIDE.md](AGENT-GUIDE.md) — that file is imported by each project's own
`CLAUDE.md`, so treat every word in it as shipped to every project.

## Layout

| Path | Role |
|---|---|
| `README.md` | The GitLab landing page. Its only job is to route a human to the right tutorial — it must never carry setup commands. |
| `AGENT-GUIDE.md` | What every consuming project imports. The framework's public surface. |
| `commands/` | The ten slash commands, in full, plus `implement-task.md` — a pure alias of `implement.md` for projects set up before v2.2 (the skeleton ships only the `implement` stub). Read-only at project runtime. |
| `templates/` | Two kinds: the epic/story/task **work-item** blueprints (their frontmatter schema is the one hard contract) and `tech-stack/<slug>/{stack,runbook}.md` **stack descriptions + runbooks** — free prose, no frontmatter, read by `/new-project` (runbook: full projects only). |
| `docs/guidelines.md` · `git-conventions.md` | Baseline rules; projects narrow them via overlays. |
| `docs/framework-architecture.md` | How this framework fits together. |
| `tutorial/quickstart.md` | Step-by-step onboarding for non-developers. **The only source of the setup commands.** |
| `tutorial/update.md` | Step-by-step upgrade of an existing project to a newer release. **The only source of the update commands** (`init.sh --update`). |
| `tutorial/how-to-use.md` | Developer-facing manual: the model, the rules, why an update works the way it does, troubleshooting. Links to the two above; re-lists neither. |
| `skeleton/` | Partitioned into `root/` (→ project root) and `an_project/` (→ the project's `an_project/`). The init script copies it **once** into a new project; everything here becomes project-owned. |
| `VERSION` | Bumped on release and tagged. A project carries its version in the committed `.an_framework/VERSION`. |

## The one rule that keeps this working

**The core is read-only at project runtime; the skeleton is write-only at setup time.**

- Files under `commands/`, `templates/`, `docs/`, `tutorial/` are only ever *read*
  by a running project (from `.an_framework/`). Never make a command write into `.an_framework/`.
- Files under `skeleton/` are copied out once by the init script and then belong to the
  project. Never make a *command* read from `skeleton/` — at project runtime that path
  does not exist. (The init script, shipped in the core, does read `skeleton/` — but only
  at setup time, never as command logic. That is the one exception to the skeleton rule.)

Concretely, inside `commands/`:

| Path form | Meaning |
|---|---|
| `.an_framework/…` (templates, docs, AGENT-GUIDE) | **read** — the immutable core |
| `an_project/work/`, `an_project/CHANGELOG.md`, `an_project/project-description.md` | **write target** — project zone |
| `an_project/docs/git.md`, `an_project/docs/guidelines.md` | **read** — project overlays (after their baseline) |
| `CLAUDE.md`, `.claude/`, `CLAUDE-ARCHIVE.md` | **project root** — never move these under `an_project/` (Claude Code discovers commands, settings and `@`-imports only from the project root) |

The two prefixes are the zone markers now — `.an_framework/` reads, `an_project/` writes.
Mixing them up is the failure mode to guard against.

## Before you change a command

Run both — they must stay silent:

```sh
grep -rnoE '\.an_framework/(work|CHANGELOG|project-description)[^ ]*' commands/
grep -rn 'skeleton/' commands/
```

1. A hit means a command writes project data into the core — where the next framework
   update would overwrite it and where the project does not own it.
2. A hit means a command reads from `skeleton/` — a path that does not exist at project
   runtime. (The init script may read it; the init script is not a command.)

`docs` is deliberately **not** in the first alternation: `.an_framework/docs/` (baseline)
and `an_project/docs/` (overlay) are both legitimate reads. Re-run these against the
actual tree after any rename — a pattern that can no longer match is silently green.

## Before you touch the tutorials

Each command sequence has exactly **one** owner, and every other file only links to it:

| Sequence | Sole owner |
|---|---|
| **Setup** of a new/retrofitted project (`git clone --depth 1 --branch <tag> … .an_framework`, strip its `.git`, `init.sh`) | `tutorial/quickstart.md` (Teil B · C · D) |
| **Update** of an existing project to a newer release (swap the core, `init.sh --update`, `chore(framework)` commit) | `tutorial/update.md` |

`README.md` only routes people to both and must stay **command-free**.
`tutorial/how-to-use.md` explains *why* — its §8 covers the update rationale and links to
`update.md`, but must not re-list either sequence. Two greps must stay silent:

```sh
grep -nE 'clone --depth 1|init\.sh' README.md
grep -nE 'clone --depth 1|git commit -m' tutorial/how-to-use.md
```

(`init.sh` is deliberately absent from the second: §8 must be able to *name* the
`--update` flag while the runnable block lives in `update.md`.)

The quickstart must never hard-code a version tag: it tells the reader to pick the
newest `v*` tag (the GitLab tags page, or `git ls-remote --tags`). Bump `VERSION` **and**
push the matching tag on release, or the reader clones a tag that does not exist.

## Slim vs. full projects

A project is set up **full** (default) or **slim** — a plain HTML page with no Docker, no
tech-stack, no runbook/dev-guide. The choice is made at **setup time**: `init.sh` asks (or
takes `--slim`/`--full`), writes `an_project/.framework-profile` (`slim`|`full`), and in
slim mode **skips** the heavy overlay docs when it copies `skeleton/an_project/` (it keeps
`work/`, `CHANGELOG.md`, `project-description.md` and the `git`/`guidelines` overlays).
`/new-project` reads that marker and, when slim, skips the tech-stack question and the
tech-stack/runbook copy. The gate lives in `init.sh` (the one place allowed to read
`skeleton/`) — never solve slim by having a *command* delete freshly-copied docs, and never
let a command read `skeleton/`.

## Before you let a command run git

`/commit` and `/done` run git, and `/implement` opens the work item's branch
(`git checkout -b`) — these are the only places a mistake reaches outside the working tree.
There is no grep for this — a command file quotes the forbidden forms in order to forbid
them, so a text search cannot tell an instruction from a prohibition. Read the diff instead
and check by hand:

- **Named paths only.** `git add -A`, `git add .`, `git add -u` and `git commit -a` at a
  project root also sweep in the committed `.an_framework/` tree — and any stray edit to
  it — as project work. `/commit` refuses staged `.an_framework` for exactly this reason;
  a framework install or update is its own `chore(framework)` commit that the init script
  prepares, never folded into project work.
- **Never `-m`.** A message is piped in with `git commit -F -` and a quoted heredoc.
  Double quotes execute backticks and expand `$`; single quotes break on the first
  apostrophe. Quoting is the whole reason — a multi-line `-m` *does* keep the blank
  line before `Ref:`, so don't cite that as the justification.
- **Never parse git's prose.** Only machine-stable output — `--porcelain`, `--name-only`,
  `--name-status`, `--oneline` — or exit codes; stderr may be quoted verbatim, never
  branched on. Git's human-readable output is localised — on a German macOS it is
  German even with `LANG=""`.
- **A pathspec commit takes the worktree, not the index.** `git commit -- <paths>`
  records what is on disk at those paths. Any multi-commit split must re-check
  `git diff -- <paths>` immediately before each commit.
- **`/done` records `done`, then merges locally — never pushes.** On the task branch it flips
  `status: done` and commits that **via `/commit`** (named paths, refuses `.an_framework`), then
  checks out the integration branch and runs `git merge --no-ff --no-edit <task-branch>` — local
  only. The merge commit is git-generated (`--no-edit`), never routed through `/commit` (which
  aborts on `MERGE_HEAD`). The two branch switches use plain `git checkout <branch>`, deliberately
  **not** pre-approved (would admit `git checkout -- <path>`), so they prompt. The remote is a
  human step; a merge conflict stops `/done` for the human.
- **Permission rules are prefix matches.** `Bash(git reset:*)` would also allow
  `git reset --hard`. `skeleton/root/.claude/settings.json` therefore allows *flag forms*
  (`git add --`, `git reset -q --`, `git commit -F -`, `git checkout -b`,
  `git merge --no-ff --no-edit`) plus the read-only `git for-each-ref`, not verbs. Even
  `Bash(git branch -d:*)` is left out — a prefix match would admit a *trailing* force-delete
  (`git branch -d x -D y`) — so the `git branch -d` cleanup and the plain `git checkout <branch>`
  switch both prompt, as do `git checkout -- <file>`, arbitrary `git merge`, `git branch -D`,
  `git push`, `git reset --hard` and `git commit -a/--amend`. The flag-form allow cannot by itself
  stop `git add -- .an_framework` — that guard is `/commit`'s staged-core preflight plus convention,
  not the permission layer.
- **Never `push`, `--amend`, `--no-verify`, `--no-gpg-sign`.**
