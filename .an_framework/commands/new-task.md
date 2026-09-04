<!-- PURPOSE: Slash command /new-task — creates a task under a story, an epic, or standalone, with the next free task number inside that parent. -->
# /new-task — create a new task

**Args:** `$ARGUMENTS` — an optional parent id followed by a title.
- Parent is a story (`EEE-SSS`) → task nested in that story.
- Parent is an epic (`EEE`) → task nested directly in that epic (story part `000`).
- No parent → ad-hoc task in `an_project/work/task/` (epic & story parts `000`).

This is where the *Kein Code ohne Ticket* gate (`.an_framework/AGENT-GUIDE.md`) resolves to:
aktuelle Story → parent `EEE-SSS`; Epic → parent `EEE`; standalone → no parent.

If the title is missing, ask.

## Steps
1. **Resolve parent** and the id prefix `EEE-SSS`:
   - story `EEE-SSS` → locate `an_project/work/epic/<EEE>-*/<EEE>-<SSS>-*/`, or `an_project/work/story/000-<SSS>-*/` when EEE=`000`. Prefix = `EEE-SSS`.
   - epic `EEE` → locate `an_project/work/epic/<EEE>-*/`. Prefix = `EEE-000`.
   - none → prefix = `000-000`.
   Stop if a named parent folder doesn't exist.
2. **Next task #** — the number is **scoped to the parent**, not project-wide. Among every id whose `EEE-SSS` equals the prefix from step 1, take the highest `TTTT` and add 1; zero-pad to 4. No task under that parent yet → `0001`. Each prefix is its own counter — a story's tasks (`EEE-SSS`), an epic's own tasks (`EEE-000`) and the ad-hoc tasks (`000-000`) never share one.
   Two things this must get right:
   - **Match the `EEE-SSS` prefix, not the folder.** An epic folder *contains* its story folders, so scanning the folder for an epic-level task (`EEE-000`) would pull in the stories' tasks and skip the number forward.
   - **Highest, never count.** Gaps are legal (a task was deleted, or the project predates scoped numbering); counting would hand out a number twice and point an old branch or `Ref:` at the wrong item.
3. **Slug** — kebab-case the title.
4. **Create** — copy `.an_framework/templates/task.md` to `<parent folder>/<EEE>-<SSS>-<TTTT>-<slug>.md` (the story/epic folder, or `an_project/work/task/` for ad-hoc).
5. **Fill** — set `id: <EEE>-<SSS>-<TTTT>`, `title`, and the `#` heading. Drop the PURPOSE comment.
6. **Link** — if the parent is a story (has a `## Tasks` list), add `- [ ] <EEE>-<SSS>-<TTTT> — <title>`.
7. **Changelog** — append `- <full id> task created: "<title>"`.
8. **Report** — print the new path and id.
