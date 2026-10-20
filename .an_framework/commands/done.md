<!-- PURPOSE: Shared done workflow — records review completion and integrates locally. -->
# done — close a reviewed work item

**Arguments:** supplied by the active runtime adapter; an optional id. Strip a leading `#`, then normalise the
same three forms `/implement` accepts: `001` → `001-000-0000` (epic), `001-001` →
`001-001-0000` (story), `EEE-SSS-TTTT` as given. Any other token — a stray flag, a free-form
string — **stop and ask**; never read a free-form string as an id.

`/done` runs git: it records the `done` transition on the item's branch (via `/commit`), then
performs a **local** `git merge --no-ff --no-edit` into the integration branch. It **never
pushes**. It is the **local-integration** path — for solo/internal repos, or once a review is
approved. A team that integrates through a protected branch / Merge Request does **not** run
`/done`; it pushes the branch and opens the MR instead (see
`.an_framework/docs/git-integration.md`, *Merge / Integration*). Talk to the user in German
(content-language rule in `.an_framework/AGENT-GUIDE.md`).

**Context budget.** Read `.an_framework/docs/git-integration.md`, `.an_framework/commands/commit.md`
and `an_project/docs/git.md` **once** in this run. Search backlog frontmatter in bounded calls;
never open every work-item file (step 1). `an_project/CHANGELOG.md` is appended at the head,
never read whole (step 6).

## What a level means here

The id decides what gets closed, and whether there is anything to merge at all:

| Level | Branch | `/done` does |
|---|---|---|
| **Task on its own branch** | its own | commit `done`, merge the task branch |
| **Task on a story branch** | the story's | **nothing on its own** — redirect to the story |
| **Story** | its own | commit `done` for the story **and every child task**, merge the story branch |
| **Epic** | none | record `done` only — its stories were merged one by one |

A task is on a story branch exactly when its parent story's status is `in-progress` or
`review` — that is the marker `.an_framework/commands/implement/story.md` leaves behind.
Closing such a task alone would merge the whole story's work under one task's name.

## Steps
1. **Pick the item** — search backlog frontmatter; never open every work-item file.
   - **An id was given** → one call, pattern `^id: <EEE-SSS-TTTT>`, path `an_project/work`,
     returning matching filenames — O(1) in the backlog size. The file that carries the id
     gives the level (`epic.md` / `story.md` / task file), as in `/implement` §0. No file → stop.
     - **Task whose parent story is `in-progress` or `review`** → stop and say so: this task
       lives on the story branch and is closed with it. Name the story id and point at
       `/done <story-id>`. Change nothing.
     - Otherwise the item must be `status: review`, **or** a resumable `status: done`: an
       earlier `/done` was interrupted after committing `done` but before the merge, so its
       branch (step 4) is not yet merged into the integration branch (step 5). For a resume,
       skip step 6 and continue at the merge (step 7). Any other status → stop and report it.
   - **No id** → one call, pattern `^status: review`, path `an_project/work`, output mode
     `files_with_matches`, then a second call for `^(id|title|status|depends_on):` restricted to
     those files — four lines per candidate, no bodies. From them collect the closeable ones:
     every item in `status: review`, minus tasks whose parent story is `in-progress` or `review`
     (those are not closeable on their own).
     - exactly one → select it and name it (id + title + level).
     - several → print the list (id + title + level) and ask which one. Never guess.
     - none → stop and say so. (If you interrupted a `/done`, re-run it **with the id** to resume.)

   Keep the main context on the selected item — never load a whole work-item file just to learn
   a status.
2. **Readiness gate** — an item is closeable only if it is truly finished (the PR-readiness rule
   in `.an_framework/docs/git-integration.md`). Anything missing → stop and report it; do not merge.
   Read full work-item bodies only for the selected item and direct children that must pass this
   gate.
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
     with it. **Appending is a head operation:** read `an_project/CHANGELOG.md` with `limit: 30`
     and edit at the `# Changelog` header or the topmost date block. The file grows without
     bound and is never read for logic — it must never enter the context as a whole.
   - **Stakeholder documentation — story and epic only.** A task closes without this; its
     chapter was already carried in `implement/task.md` step 8. For a story or an epic, go over
     `an_project/stakeholder/` once against the actual state: read
     `.an_framework/docs/stakeholder-doc.md`, then carry the chapters this item touched plus
     *Funktionen & Features*, *Stand & Roadmap*, *Risiken & offene Punkte* and the head in
     `steckbrief.md` (status, version, release, `stand:`). Set `stand:` on every chapter you edit
     and leave the others untouched. Rebuild with
     `python3 .an_framework/scripts/build-stakeholder-doc.py --quiet`. Where an answer is not in
     the repo, **ask the user** — one bundled set of questions, never an invented one; what nobody
     can answer stays `status: offen`, which is how the gap stays visible. **Never ask for
     anything marked `entfällt`** — chapter status or steckbrief field. That was declined once and
     stays declined; re-asking it at every story and epic is exactly what the state prevents.
     No secrets. Add the
     edited chapters and `an_project/stakeholder/html/` to the commit paths below. No such folder
     → the project predates framework 3.3.0; skip silently.
   - **Commit exactly this change** by handing off to
     `/commit <id> --paths <the item file> <each child task file you flipped> <the parent file
     if you ticked its box> <any stakeholder chapter you edited plus an_project/stakeholder/html/>
     an_project/CHANGELOG.md`: read `.an_framework/commands/commit.md` and
     follow it. Do **not** compose the message yourself, and **pass the paths** — you just edited
     them, so `/commit` must not have to ask. Never `.an_framework`. Pass the id explicitly too —
     on a story branch the branch name carries the story id, which is what you want here. After
     it, the worktree is clean and `done` is committed.
7. **Merge — local, no push** (skip for an epic; it has no branch):
   - Switch to the integration branch: `git checkout <integration-branch>` (prompts once, same reason as step 6).
   - `git merge --no-ff --no-edit <branch>` — `--no-ff` keeps the item a revertible unit; `--no-edit` takes git's default merge message, so there is no `-m` and no shell-quoting hazard. The merge carries the committed `done` status in. **Never route this merge through `/commit`** — its preflight aborts on `MERGE_HEAD`.
   - A merge conflict stops the run: report it and leave the half-merged state for the human (`git merge --abort` is theirs to run); change nothing further. The most likely conflict is `an_project/CHANGELOG.md` — both branches append to it — which is a routine hand-merge, not a framework error.
8. **Offer branch cleanup** — offer `git branch -d <branch>` (safe: `-d` deletes only a merged branch), default **keep**. Never `-D`. This prompts — it is not pre-approved.
9. **Report** — `git log --oneline -3` on the integration branch, the item's new status (for a story: that its N tasks went `done` with it), and state plainly that **nothing was pushed** — the remote is untouched. If this closed the last open child of a parent, say so and name the parent's `/done`.
10. **Recommend a context reset** — the item is closed and its whole state is on disk: the
    branch is merged, `status: done` is committed, the CHANGELOG records it. Nothing in the
    session history is needed for the next item, so end the report with the two concrete steps:
    start a fresh session (`/clear` in Claude Code, `/new` in Codex), then `implement <next-id>`
    — name that id from step 9 where you know it. Coming back in, the SessionStart hook prints
    branch, open items and the last changelog lines by itself; `board` or `briefing` are only
    needed for more. Say it as a recommendation, never as a demand, and leave it out when the
    user is mid-conversation about something else. **You cannot do this yourself** — no command
    and no hook can clear a session; the reset is the user's keystroke.

Never `git push`, never `git add -A`/`.`/`-u`, never `--amend`/`--no-verify`/`--no-gpg-sign`, and never stage or commit anything under `.an_framework/`.
