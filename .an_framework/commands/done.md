<!-- PURPOSE: Slash command /done — records a reviewed work item as done and, where it owns a branch, merges it (local, no push) into the integration branch. -->
# /done — close a reviewed work item

**Args:** `$ARGUMENTS` — an optional id. Strip a leading `#` immediately, then normalise the
same three forms `/implement` accepts: `001` → `001-000-0000` (epic), `001-001` →
`001-001-0000` (story), `EEE-SSS-TTTT` as given. Any other token — a stray flag, a free-form
string — **stop and ask**; never read a free-form string as an id.

`/done` runs git: it records the `done` transition on the item's branch (via `/commit`), then
performs a **local** `git merge --no-ff --no-edit` into the integration branch. It **never
pushes**. It is the **local-integration** path — for solo/internal repos, or once a review is
approved. A team that integrates through a protected branch / Merge Request does **not** run
`/done`; it pushes the branch and opens the MR instead (see
`.an_framework/docs/git-conventions.md`, *Merge / Integration*). Talk to the user in German
(content-language rule).

## What a level means here

The id decides what gets closed, and whether there is anything to merge at all:

| Level | Branch | `/done` does |
|---|---|---|
| **Task on its own branch** | its own | commit `done`, merge the task branch |
| **Task on a story branch** | the story's | **nothing on its own** — redirect to the story |
| **Story** | its own | commit `done` for the story **and every child task**, merge the story branch |
| **Epic** | none | record `done` only — its stories were merged one by one |

A task is on a story branch exactly when its parent story's status is `in-progress` or
`review` — that is the marker `/implement` §B leaves behind. Closing such a task alone would
merge the whole story's work under one task's name.

## Steps
1. **Pick the item** — scan `an_project/work/**` frontmatter.
   - **An id was given** → resolve the file by its `id:` frontmatter and take the level from the
     file (`epic.md` / `story.md` / task file), as in `/implement` §0. No file → stop.
     - **Task whose parent story is `in-progress` or `review`** → stop and say so: this task
       lives on the story branch and is closed with it. Name the story id and point at
       `/done <story-id>`. Change nothing.
     - Otherwise the item must be `status: review`, **or** a resumable `status: done`: an
       earlier `/done` was interrupted after committing `done` but before the merge, so its
       branch (step 4) is not yet merged into the integration branch (step 5). For a resume,
       skip step 6 and continue at the merge (step 7). Any other status → stop and report it.
   - **No id** → collect the closeable candidates: every item in `status: review`, minus tasks
     whose parent story is `in-progress` or `review` (those are not closeable on their own).
     - exactly one → select it and name it (id + title + level).
     - several → print the list (id + title + level) and ask which one. Never guess.
     - none → stop and say so. (If you interrupted a `/done`, re-run it **with the id** to resume.)
2. **Readiness gate** — an item is closeable only if it is truly finished (the PR-readiness rule
   in `.an_framework/docs/git-conventions.md`). Anything missing → stop and report it; do not merge.
   - **Task** — every Acceptance-criteria box checked, and an `an_project/CHANGELOG.md` entry
     records the work.
   - **Story** — every child task is `review` or `done` and passes the task gate above, and the
     story has a `CHANGELOG.md` entry. A child still `todo`, `in-progress` or `blocked` → stop
     and name it; `/implement <story-id>` resumes there.
   - **Epic** — every child story is `done`. A story still `review` → stop and say it must be
     closed first (`/done <story-id>`); the epic is merged story by story, never in one go.
3. **Preflight** (as in `/commit` step 1) — each probe stops the run on a hit; change nothing:
   - `git symbolic-ref --short -q HEAD` exits non-zero → detached HEAD; stop.
   - `git rev-parse -q --verify MERGE_HEAD` / `CHERRY_PICK_HEAD` / `REVERT_HEAD` exits 0 → finish or abort that operation first.
   - a rebase is in progress (`git rev-parse --git-path rebase-merge` then `rebase-apply`; `test -e` the printed path as a **separate** command) → stop.
   - `git status --porcelain=v1 -z -uall` is non-empty → uncommitted work in the tree; stop and send the user to `/commit <id>` first. `/done` starts from a clean worktree.
4. **Find the branch** — **skip this step for an epic**; it has none, and there is nothing to
   merge (jump to step 6, then report). Otherwise: the name follows `<type>/<id>-<slug>`, but
   `<type>` and `<slug>` are stored nowhere, so resolve it **by id**, never by rebuilding the
   name. List the branches with `git for-each-ref --format='%(refname:short)' refs/heads` and
   pick the one whose name **contains** the item's id `<EEE-SSS-TTTT>` — for a story that is its
   own `EEE-SSS-0000`, which no task branch can match.
   - exactly one match → that is the branch.
   - zero → stop and report: the work was never branched (`/implement` opens the branch), so there is nothing to merge.
   - several → print them and ask which one.
5. **Determine the integration branch** — read the *Integration branch* section of `an_project/docs/git.md`; if empty, use `master` (else `main`, else `develop` — whichever the repo actually has). A missing overlay is valid: the baseline `master` stands.
6. **Record `done`, then commit it** — **skip this entire step when resuming** an interrupted run (the item is already committed as `done` on its branch; go straight to step 7). Otherwise:
   - **Be on the right branch.** For a task or story: if HEAD is not already `<branch>`, switch
     with `git checkout <branch>`. This plain checkout is **not** pre-approved — it prompts once;
     a blanket allow would also admit the file-discarding `git checkout -- <path>`. Confirm it.
     (If HEAD is already the branch — the usual case right after `/implement` — skip the switch.)
     If the switch is declined, stop and report that the item stays `review`, un-touched —
     nothing was changed. For an **epic** there is no branch: stay where you are, which is the
     integration branch its stories were merged into.
   - **Set the statuses.** For a task: its own `status: done`. For a **story**: the story **and
     every child task** to `status: done` — they were committed on the story branch and reach
     the integration branch with this merge, so they become `done` together. For an epic: its
     own `status: done`.
   - **Roll the status up.** Tick the item's box in its parent's checklist: a task in the parent
     story's `## Tasks` list (`- [x] <id> — <title>`), a story in the parent epic's `## Stories`
     list. No parent, or no matching line → skip it silently; the list is a convenience, not the
     contract. The frontmatter `status:` is the contract.
   - **Changelog.** Append `- <id> → done (merged into <integration-branch>)`; for an epic,
     which merges nothing, `- <id> → done`. A story adds one line naming the tasks it closed
     with it.
   - **Commit exactly this change** by handing off to `/commit <id>`: read
     `.an_framework/commands/commit.md` and follow it. Do **not** compose the message yourself;
     `/commit` stages the work-item files + `CHANGELOG.md` by named path, never `.an_framework`.
     Pass the id explicitly — on a story branch the branch name carries the story id, which is
     what you want here. After it, the worktree is clean and `done` is committed.
7. **Merge — local, no push** (skip for an epic; it has no branch):
   - Switch to the integration branch: `git checkout <integration-branch>` (prompts once, same reason as step 6).
   - `git merge --no-ff --no-edit <branch>` — `--no-ff` keeps the item a revertible unit; `--no-edit` takes git's default merge message, so there is no `-m` and no shell-quoting hazard. The merge carries the committed `done` status in. **Never route this merge through `/commit`** — its preflight aborts on `MERGE_HEAD`.
   - A merge conflict stops the run: report it and leave the half-merged state for the human (`git merge --abort` is theirs to run); change nothing further. The most likely conflict is `an_project/CHANGELOG.md` — both branches append to it — which is a routine hand-merge, not a framework error.
8. **Offer branch cleanup** — offer `git branch -d <branch>` (safe: `-d` deletes only a merged branch), default **keep**. Never `-D`. This prompts — it is not pre-approved.
9. **Report** — `git log --oneline -3` on the integration branch, the item's new status (for a story: that its N tasks went `done` with it), and state plainly that **nothing was pushed** — the remote is untouched. If this closed the last open child of a parent, say so and name the parent's `/done`.

Never `git push`, never `git add -A`/`.`/`-u`, never `--amend`/`--no-verify`/`--no-gpg-sign`, and never stage or commit anything under `.an_framework/`.
