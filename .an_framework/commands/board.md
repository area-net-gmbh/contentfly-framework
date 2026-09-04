<!-- PURPOSE: Slash command /board — generates a status overview from all work-item frontmatter. -->
# /board — status overview of the backlog

Read the frontmatter of every work item and print a status overview.

## Steps
1. **Collect** — scan `an_project/work/epic/`, `an_project/work/story/`, and `an_project/work/task/` for every `epic.md`, `story.md`, and `EEE-SSS-TTTT-*.md` task file; read each one's `id`, `title`, `status`, and `depends_on`.
2. **Group** — organize as Epic → Story → Task by parsing the composite id (`000` = no level). Show standalone stories and ad-hoc tasks under their own headings.
3. **Print** — a tree or table with each item's id, title, and status.
4. **Highlight**:
   - **Next up** — `todo` tasks whose `depends_on` are all `done`.
   - **Blocked** — tasks whose `depends_on` aren't `done` yet.
   - A one-line tally (e.g. "3 done · 2 todo · 1 blocked").

Read-only: `/board` never writes files (no `an_project/CHANGELOG.md` entry).
