<!-- PURPOSE: Slash command /new-epic — creates an epic under an_project/work/epic/ with the next global epic number. -->
# /new-epic — create a new epic

**Title:** `$ARGUMENTS` (if empty, ask for it).

## Steps
1. **Next epic #** — scan every id across `an_project/work/` (folder + file names of the form `EEE-SSS-TTTT`). Take the highest `EEE` seen and add 1; zero-pad to 3 digits. First epic → `001`. The epic has no parent, so `EEE` is the **one** project-wide counter — `SSS` and `TTTT` are scoped to their parent (see `/new-story`, `/new-task`). Highest, never count: gaps from deleted epics stay gaps.
2. **Slug** — kebab-case the title (e.g. "User Authentication" → `user-authentication`).
3. **Create** — copy `.an_framework/templates/epic.md` to `an_project/work/epic/<EEE>-<slug>/epic.md`.
4. **Fill** — set `id: <EEE>-000-0000`, `title`, and the `#` heading. Drop the PURPOSE comment (template-only).
5. **Changelog** — append under today's date in `an_project/CHANGELOG.md`: `- <EEE>-000-0000 epic created: "<title>"`.
6. **Report** — print the new path and id.
