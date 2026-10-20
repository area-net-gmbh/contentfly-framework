<!-- PURPOSE: Shared board workflow — generates a status overview from all work-item frontmatter. -->
# board — status overview of the backlog

Read the frontmatter of every work item and print a status overview. Read-only, frontmatter-only:
never load a work-item body just to draw the board.

## Steps
1. **Collect — one bounded text search, not one file read per item.** Pattern
   `^(id|title|status|depends_on):` under `an_project/work`, returning matching content. That gives
   four lines per item, with the file path on each — everything the board needs, in a single
   result that does not grow with the size of the work items, only with their number. Never list
   the backlog and open every file.
2. **Group** — organize as Epic → Story → Task by parsing the composite id (`000` = no level). Show standalone stories and ad-hoc tasks under their own headings.
3. **Print** — a tree or table with each item's id, title, and status.
4. **Highlight**:
   - **Next up** — `todo` tasks whose `depends_on` are all `done`.
   - **Blocked** — tasks whose `depends_on` aren't `done` yet.
   - A one-line tally (e.g. "3 done · 2 todo · 1 blocked").

With a large backlog, print the `done` items as a count per parent instead of listing them all;
the board is for what is open. The full list stays one bounded search away.

Read-only: this workflow never writes files (no `an_project/CHANGELOG.md` entry).
