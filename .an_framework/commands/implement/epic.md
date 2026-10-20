<!-- PURPOSE: /implement, epic level — plans only: no branch, no code, one story at a time with a confirmation between them. Reached from commands/implement.md §1; the context budget and the rules for every level there stay in force. -->
# implement — epic level

An epic is **never implemented directly**: it opens no branch, writes no code and produces no
commit of its own. It plans, and hands each story to `.an_framework/commands/implement/story.md`
one at a time, with a confirmation between them. That is deliberate — an unattended epic would
otherwise produce dozens of commits before anyone looks.

1. **Read** — the epic's Goal and its `## Stories` list. Get children with one bounded search
   for `^(id|title|status|depends_on):` under `an_project/work/epic`; keep rows with this epic's
   `EEE`, `SSS ≠ 000` and `TTTT = 0000` for the
   stories, and count the `TTTT ≠ 0000` rows per story to get its task count. Read a full story
   body only for a blocked or next-to-run story where the plan needs the detail.
2. **No stories yet** → propose a story breakdown (2–5 stories, title + one line each), ask for
   confirmation, then create them via `/new-story <EEE> "<title>"` and fill each out via
   `/refine <path>` — same handoff rule as `implement/story.md` step 3, `/new-story` owns ids and
   the epic's `## Stories` list. Then stop and report the plan: the stories exist, their tasks do
   not yet. Point at `/implement <story-id>`, which cuts the tasks. Do not chain straight into
   implementation from a freshly cut epic.
3. **Plan** — order the stories by `depends_on`, then by id. Print the plan: each story's id,
   title, status and task count, in execution order, with anything blocked called out. State
   plainly that each story will be a separate branch and a separate merge.
4. **Confirm the first story only.** Ask whether to start with the first not-yet-`done` story.
   Never ask once for the whole epic — the confirmation is per story, and that is the point of
   this level.
5. **Run one story** — set the epic's `status: in-progress` (if it isn't already) and append
   `- <epic-id> → in-progress` to `an_project/CHANGELOG.md` (head-append rule:
   `implement/task.md` step 8) before the first story. Then read
   `.an_framework/commands/implement/story.md` and hand the story to it, in full, exactly as if
   it had been called directly.
6. **Checkpoint** — when the story reaches `review`, stop. Report what landed, that the story
   branch is **not** merged yet, and name the next story. Ask whether to continue. Only on an
   explicit yes go back to step 5 with the next one — and since every finished story is fully
   persisted (branch, `status`, CHANGELOG), a fresh session is the cheaper way to continue a
   long epic: `/implement <next-story-id>` picks it up with no history to carry. A story that
   stops mid-run stops the epic too.
7. **Epic to review** — when every child story is `review` or `done`, set the epic's
   `status: review` and append `- <epic-id> → review` to `an_project/CHANGELOG.md`. The epic has
   no branch, so there is nothing to commit here on its own: fold this edit into the next
   `/commit`, or leave it for `/done <epic-id>`, which commits it on the integration branch.

The epic is closed with `/done <epic-id>` — after its stories are merged. It has no branch and
nothing to merge; `/done` only records the status.
