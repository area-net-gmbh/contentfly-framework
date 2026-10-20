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
| `AGENTS.md` | Codex entry point for contributors to this framework repo; it routes here without duplicating these rules. |
| `commands/` | Thirteen shared workflows plus `implement-task.md`, a legacy Claude alias. `implement.md` routes to exactly one of `commands/implement/{task,story,epic}.md`. Read-only at project runtime. |
| `agents/` | The two read-only agent roles (`an-scout`, `an-briefer`). Same stub indirection as the commands: the project owns a thin `.claude/agents/<name>.md` carrying the frontmatter (`tools:` is what makes them read-only), the role itself lives here. |
| `hooks/` | The two shell hooks — `session-brief.sh` (SessionStart orientation) and `guard-verbose-bash.sh` (blocks uncapped test runs). Wired up from the project's `.claude/settings.json`; **executed** at project runtime, never written to. Both fail open. |
| `templates/` | Epic/story/task **work-item** blueprints, the compact `adr.md` decision-record template, and `tech-stack/<slug>/{stack,runbook}.md` descriptions. |
| `viewer/` | The stakeholder-doc viewer: `page.template.html` plus `assets/` (CSS, JS, Mermaid, logo). Read by the generator, copied into the project's `html/` on every build — so a design fix reaches every project with the next core swap. |
| `docs/guidelines.md` · `git-conventions.md` · `git-branching.md` · `git-integration.md` | Baseline rules; projects narrow them via overlays. The three git files are split **by reader**, one each: commit format → `/commit`, branch schema → `/implement`, merge/PR → `/done`. One overlay, `an_project/docs/git.md`, narrows all three. |
| `docs/framework-architecture.md` | How this framework fits together. |
| `tutorial/quickstart.md` | Step-by-step onboarding for non-developers. **The only source of the setup commands.** |
| `tutorial/update.md` | Step-by-step upgrade of an existing project to a newer release. **The only source of the update commands** (`init.sh --update`). |
| `tutorial/how-to-use.md` | Developer-facing manual: the model, the rules, why an update works the way it does, troubleshooting. Links to the two above; re-lists neither. |
| `skeleton/` | Partitioned into `root/` (→ project root) and `an_project/` (→ the project's `an_project/`). The init script copies it **once** into a new project; everything here becomes project-owned. |
| `scripts/build-stakeholder-doc.py` | Renders `an_project/stakeholder/*.md` into offline HTML. Stdlib-only, Python 3.9, deterministic output. **Executed** at project runtime, like the hooks — see the rule below. |
| `scripts/check-contracts.sh` | Release check for Claude/Codex adapter parity, a clean-project setup smoke test, a real build of the shipped stakeholder skeleton, and a real build of the release archive. |
| `scripts/build-release-zip.sh` | Builds `dist/an_framework-v<version>.zip` — the tracked tree of one ref under a single `.an_framework/` prefix, so a project can take the core without git. Release-time only, POSIX `sh` (the CI image has no bash). |
| `.gitlab-ci.yml` | The release pipeline: on every `vX.Y.Z` tag it builds that ZIP and attaches it to a GitLab Release. Tag-only, plus a manual UI run for a dry run — ordinary pushes start no pipeline. |
| `VERSION` | Bumped on release and tagged. A project carries its version in the committed `.an_framework/VERSION`. The tag pipeline **refuses** a release whose `VERSION` and tag disagree. |

## The one rule that keeps this working

**The core is read-only at project runtime; the skeleton is write-only at setup time.**

- Files under `commands/`, `agents/`, `templates/`, `docs/`, `tutorial/` are only ever *read*
  by a running project (from `.an_framework/`). Never make a command write into `.an_framework/`.
- `hooks/` is one of two places the core is also **executed** at project runtime — the project's
  `.claude/settings.json` points at `.an_framework/hooks/*.sh`. Read-only still holds: a hook
  reads the project and prints, it never writes into the core, and it never writes project data
  either. Every hook **fails open** (exit 0, no output) on any error; a broken hook must never
  block work.
- `scripts/build-stakeholder-doc.py` is the other one, and it is where the shape differs: it
  **writes**, into `an_project/stakeholder/html/` and nowhere else, and it **fails loud**
  (non-zero exit, message on stderr). Both are deliberate. It writes because rendering is its
  whole job, and the target is the project zone, never the core. It fails loud because it is
  invoked explicitly by the `doc` workflow — a hook that stays silent costs nothing, a build
  that silently does nothing ships a stale document. Together with `init.sh` these are the
  three exceptions to the read-only rule, and all three have the same frame: shipped in the
  core, run outside the model, writing only into the project.
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
| `CLAUDE.md`, `AGENTS.md`, `.claude/`, `.agents/`, `.codex/` | **project root** — runtime discovery adapters; never move under `an_project/` |

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

### And keep the reading load split

The context split is a contract, not a style choice: the git baseline is three files precisely
because no command needs both, and `implement.md` is a router precisely so one run loads one
level. Two greps hold that line — these have an **expected** hit list, they are not
silent-greps:

```sh
grep -rln 'git-conventions.md' commands/   # commit.md · implement/story.md
grep -rln 'git-branching.md'   commands/   # implement.md · implement/{task,story}.md · commit.md
grep -rln 'git-integration.md' commands/   # done.md
```

Each git baseline has **exactly one** reading command; the extra hits are deliberate and must
stay *mentions*, never reads: `implement/story.md` lists the rule files that are already in
context and must **not** be re-read per task, and `commit.md` names the other two only to say it
does not read them. A new *read* of a baseline by a command that had no business with it is the
regression to catch — check what the hit says, not just that it exists. That is what makes the
split pay: a command that reads two of the three loads more than the one combined file ever
cost it.

The same applies to `commands/implement/`: the router names all three level files, and each
level file may name a sibling it hands off to, but **no level file may instruct reading another
one in full**. `story.md` re-enters `task.md` at named steps; that is the one crossing.

After adapter or setup changes, run `scripts/check-contracts.sh`. A workflow release is
incomplete unless its Claude command stub and Codex `an-*` skill both load the same shared body.

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

Neither tutorial may hard-code a version in a command that fetches the core. Both write
`{VERSION}` and tell the reader to substitute the newest `v*` tag (the GitLab tags page, the
Releases page, or `git ls-remote --tags`). The placeholder is a **loud failure on purpose**: a
reader who pastes without reading gets `Could not find remote branch {VERSION}` instead of an
old framework installed silently — which is what a real-looking `v2.0.0` in an example did for
years. A third grep must stay silent:

```sh
grep -nE '(--branch v[0-9]|an_framework-v[0-9])' tutorial/*.md
```

(The version *table* in `update.md` is full of real numbers — that is the point of it. Only
fetch commands are covered.) Bump `VERSION` **and** push the matching tag on release, or the
reader looks for a tag that does not exist.

## Slim vs. full projects

A project is set up **full** (default) or **slim** — a plain HTML page with no Docker, no
tech-stack, no runbook/dev-guide. The choice is made at **setup time**: `init.sh` asks (or
takes `--slim`/`--full`), writes `an_project/.framework-profile` (`slim`|`full`), and in
slim mode **skips** the heavy overlay docs when it copies `skeleton/an_project/` (it keeps
`work/`, `CHANGELOG.md`, `project-description.md` and the `git`/`guidelines` overlays).
`/new-project` reads that marker and, when slim, skips the tech-stack question and the
tech-stack/runbook copy. The stakeholder doc exists in **both** profiles — slim just leaves
three chapters out (`060-prozesse`, `100-stand-und-roadmap`, `120-entscheidungen`), again via
`is_slim_excluded`, and the chapter numbering stays gapless because the generator numbers by
position, not by filename. The gate lives in `init.sh` (the one place allowed to read
`skeleton/`) — never solve slim by having a *command* delete freshly-copied docs, and never
let a command read `skeleton/`.

## Before you change the stakeholder-doc generator

Three properties are load-bearing, and you would not notice losing any of them:

- **Standard library only, Python 3.9.** The people this is for run macOS system Python. A
  third-party import or a `match` statement makes the feature unavailable on exactly those
  machines.
- **Deterministic output.** The date shown comes from `stand:` in the source, never from the
  clock. The moment a build timestamp lands in the HTML, every `doc` run dirties the worktree
  and the generated tree stops being committable without noise.
- **It writes into `an_project/stakeholder/html/` and nowhere else.** The path guard sits in
  `build()` before the `rmtree`. Keep it, and keep the `rmtree` from ever seeing a path it did
  not construct itself.

`scripts/check-contracts.sh` builds the shipped skeleton on every run, so a *broken* renderer is
caught. A silently *worse* one is not — build a filled project and look at it in a browser.

## Before you cut a release

A release hands the core out over **two** routes, and they must stay the same tree: the git
bootstrap in the tutorials, and the ZIP that `.gitlab-ci.yml` builds for everyone who does not
want to use git. `scripts/build-release-zip.sh` is therefore nothing but
`git archive --prefix=.an_framework/` over one ref — no filtering, no `export-ignore`, no
generated files. The moment the archive is *curated*, the two routes install different frameworks
and only the people who downloaded the ZIP will notice.

- **Bump `VERSION` in the commit you tag.** The ZIP's name and its content both come from the
  ref's own `VERSION`, and the tag job aborts on `v$VERSION != $CI_COMMIT_TAG`. That guard is the
  reason the mismatch is now loud instead of shipping a `v3.4.0` tag containing `3.3.0`.
- **`build-release-zip.sh` is not a fourth exception to the read-only rule.** It never runs
  inside a project — it runs in CI, against this repo, like `check-contracts.sh`. The three
  runtime exceptions are still `init.sh`, `hooks/*.sh` and `scripts/build-stakeholder-doc.py`.
- **The Release description stays command-free**, for the same reason `README.md` does: the
  setup and update sequences live once, in `tutorial/quickstart.md` and `tutorial/update.md`.
  A third copy inside a CI file nobody re-reads is the drift this repo is organised against.
- **The job is named `zip` because the name is part of the asset URL**
  (`…/-/jobs/artifacts/<tag>/raw/dist/…?job=zip`). Renaming the job breaks the download link of
  every future release; renaming the archive breaks it for the release being cut.
- **Tag-only by design.** Nothing here is built per commit, so a push starts no pipeline at all.
  Try changes with *Run pipeline* on a branch — the `zip` job runs, the `release` job does not.

Run `scripts/check-contracts.sh` before tagging: it builds the archive and diffs its contents
against `git ls-tree -r HEAD`, so a lost file or a changed prefix fails there rather than in a
project.

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
