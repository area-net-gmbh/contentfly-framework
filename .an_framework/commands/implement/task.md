<!-- PURPOSE: /implement, task level — one task, its own branch unless the parent story already owns one. Reached from commands/implement.md §1; the context budget and the rules for every level there stay in force. -->
# implement — task level

A task gets **its own branch**, unless it belongs to a story that is already being
implemented — then the story owns the branch and this task is one commit on it
(`.an_framework/commands/implement/story.md`).

1. **Read** — the task's Context, Acceptance criteria and Verification.
2. **Check dependencies** — if any `depends_on` item isn't `done`, stop and report it as blocked.
   Resolve each one with a bounded search for `^id: <that-id>` and `^status:`, without opening
   every backlog file.
3. **Check the parent story** — if the task sits under a story (`SSS ≠ 000`) whose own status is
   `in-progress` or `review`, that story owns an open story branch. Do **not** open a task
   branch: switch to the story branch (find it by the story's id, as in `/done`), say so, and
   continue at step 5 — everything else in this section applies unchanged, the commit just
   lands on the story branch. Any other parent status (`todo`, `done`, `blocked`, or no story
   at all) → the task branches on its own, step 4.
4. **Branch** — `<type>/<EEE-SSS-TTTT>-<kurz-slug>` (schema in
   `.an_framework/docs/git-branching.md`; `<type>` is the task's intended commit type, default
   `feat`). Resolve the state **in this order — first match wins**:
   - HEAD is already this task's branch → continue on it.
   - the worktree is dirty, or HEAD is a branch belonging to a *different* work item (its name
     carries a different `EEE-SSS-TTTT`) → **stop** and say so; don't mix two items or fold in
     unrelated changes.
   - the branch already exists (`git rev-parse -q --verify refs/heads/<branch>` succeeds) →
     **resume** it: `git checkout <branch>` (this plain checkout is not pre-approved — it
     prompts once; a blanket allow would also admit the file-discarding `git checkout -- <path>`).
   - otherwise create it **off the integration branch** (the *Integration branch* in
     `an_project/docs/git.md`, else `master`) with the base named explicitly:
     `git checkout -b <branch> <integration-branch>` (so it never branches off whatever HEAD
     happens to be).
5. **Start** — set `status: in-progress`; append `- <id> → in-progress` to
   `an_project/CHANGELOG.md` (append rule: step 8).
6. **Implement** — first the delegated code scan from the router's *Context budget* (one
   `an-scout` call); keep only its report in active context and open the places it names with
   `offset`/`limit`. Then build to the acceptance criteria,
   following `.an_framework/docs/guidelines.md` and the project's `an_project/docs/guidelines.md`
   (project wins on conflict) — read each once per session. Make surgical changes only.
7. **Verify** — run the task's Verification steps and confirm each acceptance criterion; check
   them off. **Locally** — the Docker environment in a full project, per the testing rule in
   `.an_framework/AGENT-GUIDE.md`; a server environment only on an explicit instruction naming
   it. Use the quiet/summary flag of the test runner: on success keep the summary line,
   on failure the failing part. A full green test log never enters the context.
8. **Review status** — set `status: review`; append
   `- <id> → review: <what changed, how verified>` to `an_project/CHANGELOG.md`. Do this
   **before** the commit so it lands in the same commit.
   **Appending is a head operation:** read `an_project/CHANGELOG.md` with `limit: 30` and edit
   at the `# Changelog` header or the topmost date block. The file grows without bound and is
   never read for logic — it must never enter the context as a whole.
   If this implementation made a binding architecture decision, also create the required ADR
   and add only its linked index row to `an_project/docs/architecture.md`, following the ADR
   rule in `.an_framework/AGENT-GUIDE.md`. Include both files in the commit paths below.
   **Stakeholder documentation** — did this task touch one of these six? An external service or
   interface · a module or plugin with user-visible effect · deployment, environments or
   prerequisites · a feature users can see · a decision or ADR · the processing of personal data.
   **None of them → change nothing;** that is the normal case, not an oversight. One of them →
   read `.an_framework/docs/stakeholder-doc.md` (once per session), edit **only** the affected
   chapter under `an_project/stakeholder/`, set its `stand:` to today, rebuild with
   `python3 .an_framework/scripts/build-stakeholder-doc.py --quiet`, and add that chapter plus
   `an_project/stakeholder/html/` to the commit paths. Never a secret in there — the document is
   written to be handed to the client. No such folder → the project predates framework 3.3.0;
   skip silently.
9. **Commit** — hand off to `/commit <id> --paths <every file you changed> <the task file>
   an_project/CHANGELOG.md`: read `.an_framework/commands/commit.md` and follow it exactly (once
   per session — on a story branch it is already in context). **Pass the paths, don't let it
   ask.** You edited these files in step 6 and 8, so you know them; `/commit`'s candidate
   question exists for a human typing `/commit`, not for this handoff. The whole task lands in
   one commit and the worktree ends clean. Don't reinvent staging or message logic. Passing the
   id matters on a story branch
   — there the branch name carries the *story* id, and `/commit` reconciles the two only when it
   is given the task id. Nothing to commit yet → say so.

Then summarize: the task is `review` and committed. On its own branch, `/done <id>` merges and
closes it; on a story branch, it is closed with the story (`/done <story-id>`) — say which of the
two applies. Stop and ask if the task is underspecified — don't guess at acceptance criteria.
Consider `/refine` on the task first.
