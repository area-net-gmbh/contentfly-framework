<!-- PURPOSE: /implement, story level — one story branch, one commit per task. Reached from commands/implement.md §1; the context budget and the rules for every level there stay in force. -->
# implement — story level

A story is implemented on **one story branch**. Its tasks become commits on that branch, one
commit per task, and the whole story merges as a single unit. Never open a branch per task here.

1. **Read** — the story's Goal and its `## Tasks` list. Get child tasks with one bounded search
   for `^(id|title|status|depends_on):` under `an_project/work`; keep rows whose id shares this
   story's `EEE-SSS` and has `TTTT ≠ 0000`. Read
   a full child task body only when `task.md` actually works it, one at a time.
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
     which is deliberate. Read both command files **once**, not per task.
   - **Ask a second time**, now showing the finished plan: the task ids, titles and the order.
     Only on that yes continue at step 4. If the user stops here, the tasks stay — nothing is
     lost, the story is simply `todo` with a real backlog now.
4. **Order the tasks** — by `depends_on` first, then by id. A task whose `depends_on` points
   *outside* this story at something not `done` → stop and report the story as blocked before
   opening any branch. Skip tasks already `done` (a resumed story) and say which.
5. **Branch** — one branch for the story: `<type>/<EEE-SSS-0000>-<kurz-slug>`, `<type>` the
   story's dominant commit type (default `feat`). Same first-match-wins resolution as
   `implement/task.md` step 4, with the story's id in place of the task's — HEAD already this
   branch → continue; dirty worktree or a branch carrying a different `EEE-SSS-TTTT` → stop;
   branch exists → resume via `git checkout <branch>` (prompts once); otherwise
   `git checkout -b <branch> <integration-branch>`.
6. **Start** — set the story's `status: in-progress`; append `- <story-id> → in-progress` to
   `an_project/CHANGELOG.md` (head-append rule: `implement/task.md` step 8).
7. **Work the tasks, one at a time, in order.** For each task run `implement/task.md` steps 5–9
   — set `in-progress`, implement, verify, set `review`, and commit via
   `/commit <task-id> --paths …` (step 9 there names the paths). Do
   **not** re-run its steps 3 and 4: the branch already exists and is the story's. Each task
   therefore ends as its own commit on the story branch, carrying `Ref: #<task-id>`.
   - **The rules are already in context.** `commit.md`, `git-conventions.md`,
     `git-branching.md`, `guidelines.md` and the project overlays were read for the first task —
     do not read any of them again for the next one. The same goes for `implement/task.md`
     itself, and for `stakeholder-doc.md` once a task has pulled it in.
   - **Do not reset the context between the tasks of one story.** The cheap task here is the
     second one, and it is cheap precisely because the rule files above and the `an-scout`
     picture of the touched code are already loaded. A reset in the middle re-pays all of it per
     task. The place for a fresh session is the item boundary — after `/done <story-id>`, or
     between two stories of an epic, never inside a story run.
   - After each task, report it in one line and continue with the next. Keep the report to the
     outcome; the task's code scan, diffs and test output do not need repeating.
   - A task that fails verification, turns out blocked, or is underspecified **stops the run**.
     Never skip it and carry on — the remaining tasks may depend on it. Report which tasks are
     committed, which one stopped and why, and leave the branch as it is. Re-running
     `/implement <story-id>` resumes at the first task that is not yet `review` or `done` — the
     state lives in git and in the frontmatter, so a fresh session loses nothing.
8. **Story to review** — once every task is `review` (or already `done`), set the story's
   `status: review`; append `- <story-id> → review: <tasks umgesetzt, wie verifiziert>` to
   `an_project/CHANGELOG.md`; commit exactly this via
   `/commit <story-id> --paths <the story file> an_project/CHANGELOG.md` — two named paths, so
   this last commit carries the story's status change and nothing else.

Then summarize: the story is `review`, N tasks committed on one branch. `/done <story-id>`
merges the branch into the integration branch and sets story **and** its tasks to `done`.
