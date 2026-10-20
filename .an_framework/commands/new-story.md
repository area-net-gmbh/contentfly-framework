<!-- PURPOSE: Shared new-story workflow — creates the next story inside its optional epic. -->
# new-story — create a new story

**Arguments:** supplied by the active runtime adapter; an optional epic number followed by a title.
- With an epic number → the story is nested under that epic.
- Without one → the story is standalone (epic part = `000`).

If the title is missing, ask.

## Steps
1. **Resolve parent**:
   - Epic number `EEE` given → locate `an_project/work/epic/<EEE>-*/`. If it doesn't exist, stop and say so.
   - Otherwise standalone; epic part = `000`.
2. **Next story #** — the number is scoped to the parent epic. Search every existing `^id:` line
   under `an_project/work` in one bounded operation and parse only those matches. Never list and
   open every item. Among ids whose `EEE` equals the target epic (`000` for standalone), take the
   highest `SSS` and add 1; zero-pad to 3. No story there yet → `001`.
   Two things this must get right:
   - **Match the id prefix, not the folder.** Read `EEE` off the ids themselves.
   - **Highest, never count.** Gaps are legal (a story was deleted, or the project predates scoped numbering); counting would hand out a number twice and point an old branch or `Ref:` at the wrong item.
3. **Slug** — kebab-case the title.
4. **Create** — copy `.an_framework/templates/story.md` to:
   - under an epic → `an_project/work/epic/<EEE>-*/<EEE>-<SSS>-<slug>/story.md`
   - standalone → `an_project/work/story/000-<SSS>-<slug>/story.md`
5. **Fill** — set `id: <EEE>-<SSS>-0000` (EEE=`000` if standalone), `title`, and the `#` heading. Drop the PURPOSE comment.
6. **Link** — if under an epic, add to that epic's `## Stories` list: `- [ ] <EEE>-<SSS>-0000 — <title>`.
7. **Changelog** — append `- <EEE>-<SSS>-0000 story created: "<title>"` (note the epic if any). **Head-append only:** read `an_project/CHANGELOG.md` with `limit: 30` and edit at the `# Changelog` header or the topmost date block — the file grows without bound and is never read for logic, so it must never enter the context as a whole.
8. **Report** — print the new path and id.
