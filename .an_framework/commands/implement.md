<!-- PURPOSE: Slash command /implement — implements a work item end-to-end. The id decides the level: task, story (one story branch), or epic (plan only). -->
# /implement — implement a work item end-to-end

**Args:** `$ARGUMENTS` — the id of the item to implement. If missing or ambiguous, ask.

**No item, no code.** If you were sent here by a bare instruction with no backing work item,
do **not** implement. Stop and run the ticket-gate first — ask, verbatim in German, under
which parent to file it and create it with `/new-task` (see the *Kein Code ohne Ticket* rule
in `.an_framework/AGENT-GUIDE.md`). Only a real id gets past this line.

## 0. Resolve the id and the level

Strip a leading `#`. Accept three forms and normalise to the full address `EEE-SSS-TTTT`:

| Given | Normalised | Level |
|---|---|---|
| `001` | `001-000-0000` | epic |
| `001-001` | `001-001-0000` | story |
| `EEE-SSS-TTTT` | as given | see below |

Then read the level off the normalised id — the three cases are disjoint, so this never guesses:

- `TTTT ≠ 0000` → **task** (§A) — this covers `001-001-0001`, the epic-level task `001-000-0007` and the ad-hoc task `000-000-0004` alike.
- `TTTT = 0000` and `SSS ≠ 000` → **story** (§B), standalone (`000-002-0000`) included.
- `TTTT = 0000`, `SSS = 000`, `EEE ≠ 000` → **epic** (§C).
- `000-000-0000` is not an id → stop and ask.

Anything that is not one of the three forms — a stray flag, a free-form string — **stop and
ask**; never read a free-form string as an id.

**Verify against the file, don't trust the parse.** Find the item by its `id:` frontmatter
across `an_project/work/epic/`, `an_project/work/story/` and `an_project/work/task/`. The file
that carries it decides: `epic.md` → epic, `story.md` → story, `EEE-SSS-TTTT-*.md` → task. No
file resolves for the id → stop; do not improvise a work item (run the ticket-gate above). File
and parse disagree → stop and report both; that is a broken id somewhere, not something to
paper over.

**Then branch to the matching section: §A task · §B story · §C epic.** Read only that one.

---

## §A — Task

A task gets **its own branch**, unless it belongs to a story that is already being
implemented — then the story owns the branch and this task is one commit on it (§B).

1. **Read** — the task's Context, Acceptance criteria and Verification.
2. **Check dependencies** — if any `depends_on` item isn't `done`, stop and report it as blocked.
3. **Check the parent story** — if the task sits under a story (`SSS ≠ 000`) whose own status is
   `in-progress` or `review`, that story owns an open story branch. Do **not** open a task
   branch: switch to the story branch (find it by the story's id, as in `/done`), say so, and
   continue at step 5 — everything else in this section applies unchanged, the commit just
   lands on the story branch. Any other parent status (`todo`, `done`, `blocked`, or no story
   at all) → the task branches on its own, step 4.
4. **Branch** — `<type>/<EEE-SSS-TTTT>-<kurz-slug>` (schema in `.an_framework/docs/git-conventions.md`;
   `<type>` is the task's intended commit type, default `feat`). Resolve the state **in this
   order — first match wins**:
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
5. **Start** — set `status: in-progress`; append to `an_project/CHANGELOG.md`: `- <id> → in-progress`.
6. **Implement** — build to the acceptance criteria. Follow `.an_framework/docs/guidelines.md`
   (behavioral rules) and `.an_framework/docs/git-conventions.md` (branch/commit/PR conventions).
   The project may narrow these in `an_project/docs/guidelines.md` and `an_project/docs/git.md` —
   read those too; **on conflict the project files win**. Make surgical changes only. Produced
   content (comments, docs, messages) is German per the content-language rule.
7. **Verify** — run the task's Verification steps and confirm each acceptance criterion; check them off.
8. **Review status** — set `status: review`; append `- <id> → review: <what changed, how verified>`
   to `an_project/CHANGELOG.md`. Do this **before** the commit so it lands in the same commit.
9. **Commit** — hand off to `/commit <id>` with the **task** id passed explicitly: read
   `.an_framework/commands/commit.md` and follow it exactly. It stages the code **and** the task
   file (now `review`) **and** `CHANGELOG.md` by named path, so the whole task lands in one commit
   and the worktree ends clean. Don't reinvent staging or message logic. Passing the id matters on
   a story branch — there the branch name carries the *story* id, and `/commit` reconciles the two
   only when it is given the task id. Nothing to commit yet → say so.

Then summarize: the task is `review` and committed. On its own branch, `/done <id>` merges and
closes it; on a story branch, it is closed with the story (`/done <story-id>`) — say which of the
two applies. Stop and ask if the task is underspecified — don't guess at acceptance criteria.
Consider `/refine` on the task first.

---

## §B — Story

A story is implemented on **one story branch**. Its tasks become commits on that branch, one
commit per task, and the whole story merges as a single unit. Never open a branch per task here.

1. **Read** — the story's Goal and its `## Tasks` list; read every child task file
   (`an_project/work/**` with the same `EEE-SSS` prefix and `TTTT ≠ 0000`).
2. **Check dependencies** — if any of the story's `depends_on` items isn't `done`, stop and
   report it as blocked.
3. **No tasks yet → cut them first, then confirm.** A story without child tasks is not
   implementable — but don't send the user away either. Do this, in order:
   - Propose a task breakdown: 2–6 tasks, each with a title and one line of intent, in the order
     you would implement them. Name any `depends_on` between them.
   - **Ask for confirmation** of the breakdown. The user may add, drop, reorder or reword. Do not
     create anything before this yes.
   - Create each confirmed task by handing off to `/new-task <EEE-SSS> "<title>"`: read
     `.an_framework/commands/new-task.md` and follow it. Never hand-roll ids, files or the
     parent's `## Tasks` list — `/new-task` owns all three.
   - Fill each new task out by handing off to `/refine <path>`: read
     `.an_framework/commands/refine.md` and follow it. That is what turns a bare title into
     Context, Acceptance criteria and Verification — and it reflects back and confirms per file,
     which is deliberate.
   - **Ask a second time**, now showing the finished plan: the task ids, titles and the order.
     Only on that yes continue at step 4. If the user stops here, the tasks stay — nothing is
     lost, the story is simply `todo` with a real backlog now.
4. **Order the tasks** — by `depends_on` first, then by id. A task whose `depends_on` points
   *outside* this story at something not `done` → stop and report the story as blocked before
   opening any branch. Skip tasks already `done` (a resumed story) and say which.
5. **Branch** — one branch for the story: `<type>/<EEE-SSS-0000>-<kurz-slug>`, `<type>` the
   story's dominant commit type (default `feat`). Same first-match-wins resolution as §A step 4,
   with the story's id in place of the task's — HEAD already this branch → continue; dirty
   worktree or a branch carrying a different `EEE-SSS-TTTT` → stop; branch exists → resume via
   `git checkout <branch>` (prompts once); otherwise
   `git checkout -b <branch> <integration-branch>`.
6. **Start** — set the story's `status: in-progress`; append `- <story-id> → in-progress` to
   `an_project/CHANGELOG.md`.
7. **Work the tasks, one at a time, in order.** For each task run §A steps 5–9 — set
   `in-progress`, implement, verify, set `review`, and commit via `/commit <task-id>`. Do **not**
   re-run §A steps 3 and 4: the branch already exists and is the story's. Each task therefore
   ends as its own commit on the story branch, carrying `Ref: #<task-id>`.
   - After each task, report it in one line and continue with the next.
   - A task that fails verification, turns out blocked, or is underspecified **stops the run**.
     Never skip it and carry on — the remaining tasks may depend on it. Report which tasks are
     committed, which one stopped and why, and leave the branch as it is. Re-running
     `/implement <story-id>` resumes at the first task that is not yet `review` or `done`.
8. **Story to review** — once every task is `review` (or already `done`), set the story's
   `status: review`; append `- <story-id> → review: <tasks umgesetzt, wie verifiziert>` to
   `an_project/CHANGELOG.md`; commit exactly this via `/commit <story-id>`.

Then summarize: the story is `review`, N tasks committed on one branch. `/done <story-id>`
merges the branch into the integration branch and sets story **and** its tasks to `done`.

---

## §C — Epic

An epic is **never implemented directly**: it opens no branch, writes no code and produces no
commit of its own. It plans, and hands each story to §B one at a time, with a confirmation
between them. That is deliberate — an unattended epic would otherwise produce dozens of commits
before anyone looks.

1. **Read** — the epic's Goal and its `## Stories` list; read every child story
   (`an_project/work/epic/<EEE>-*/` with `SSS ≠ 000`, `TTTT = 0000`) and each story's task count.
2. **No stories yet** → propose a story breakdown (2–5 stories, title + one line each), ask for
   confirmation, then create them via `/new-story <EEE> "<title>"` and fill each out via
   `/refine <path>` — same handoff rule as §B step 3, `/new-story` owns ids and the epic's
   `## Stories` list. Then stop and report the plan: the stories exist, their tasks do not yet.
   Point at `/implement <story-id>`, which cuts the tasks (§B step 3). Do not chain straight
   into implementation from a freshly cut epic.
3. **Plan** — order the stories by `depends_on`, then by id. Print the plan: each story's id,
   title, status and task count, in execution order, with anything blocked called out. State
   plainly that each story will be a separate branch and a separate merge.
4. **Confirm the first story only.** Ask whether to start with the first not-yet-`done` story.
   Never ask once for the whole epic — the confirmation is per story, and that is the point of
   this section.
5. **Run one story** — set the epic's `status: in-progress` (if it isn't already) and append
   `- <epic-id> → in-progress` to `an_project/CHANGELOG.md` before the first story. Then hand
   the story to §B, in full, exactly as if it had been called directly.
6. **Checkpoint** — when the story reaches `review`, stop. Report what landed, that the story
   branch is **not** merged yet, and name the next story. Ask whether to continue. Only on an
   explicit yes go back to step 5 with the next one. A story that stops mid-run (§B step 7)
   stops the epic too.
7. **Epic to review** — when every child story is `review` or `done`, set the epic's
   `status: review` and append `- <epic-id> → review` to `an_project/CHANGELOG.md`. The epic has
   no branch, so there is nothing to commit here on its own: fold this edit into the next
   `/commit`, or leave it for `/done <epic-id>`, which commits it on the integration branch.

The epic is closed with `/done <epic-id>` — after its stories are merged. It has no branch and
nothing to merge; `/done` only records the status.

---

## Rules for every level

- **Never widen the item.** Work that turns up on the way and isn't covered by the item's
  acceptance criteria gets its own task (`/new-task`) — the branch is not inflated. This is the
  branch rule from `git-conventions.md`: a branch belongs to exactly one work item, and for a
  story that item is the story.
- **Never compose a commit message** — always `/commit`, always with the id of the item the
  commit is about (the *task* id inside a story).
- **Never touch `.an_framework/`** — read-only at runtime.
- Everything produced is German per the content-language rule; frontmatter keys and status
  values stay English.
