<!-- PURPOSE: Shared new-epic workflow — creates the next globally numbered epic. -->
# new-epic — create a new epic

**Title:** supplied by the active runtime adapter (if empty, ask for it).

## Steps
1. **Next epic #** — Search every existing `^id:` line under `an_project/work` in one bounded
   operation and parse only those matches. Never list and open every work-item file; frontmatter
   `id:` is the contract and the folder name only mirrors it. Take the highest `EEE` and add 1;
   zero-pad to 3 digits. First epic → `001`. Highest, never count: gaps stay gaps.
2. **Slug** — kebab-case the title (e.g. "User Authentication" → `user-authentication`).
3. **Create** — copy `.an_framework/templates/epic.md` to `an_project/work/epic/<EEE>-<slug>/epic.md`.
4. **Fill** — set `id: <EEE>-000-0000`, `title`, and the `#` heading. Drop the PURPOSE comment (template-only).
5. **Changelog** — append under today's date in `an_project/CHANGELOG.md`: `- <EEE>-000-0000 epic created: "<title>"`. **Head-append only:** read `an_project/CHANGELOG.md` with `limit: 30` and edit at the `# Changelog` header or the topmost date block — the file grows without bound and is never read for logic, so it must never enter the context as a whole.
6. **Report** — print the new path and id.
