<!-- PURPOSE: Slash command /refine — reflects a filled-out artifact back to the user, then rewrites it clearly. -->
# /refine — reflect on a filled-out artifact, then polish it

Turn a rough, human-filled artifact into a clear, well-structured document.
Works on any artifact: `an_project/project-description.md`, an epic, a story, or a task.

**Target file:** `$ARGUMENTS` (if empty, ask which file to refine).

## Steps

1. **Understand** — Read the target file. Interpret what the author meant,
   including the intent behind terse or shorthand notes.

2. **Reflect** — State your understanding back to the user in 2–4 sentences:
   what this is, its goal, and anything ambiguous. **Do not edit the file yet.**

3. **Confirm** — List your assumptions and any open questions. Ask the user to
   confirm or correct before you rewrite.

4. **Rewrite** — Once confirmed, rewrite the file:
   - Keep the artifact's required section headers and any YAML frontmatter keys.
   - Write the prose in **German** (the content-language rule in
     `.an_framework/AGENT-GUIDE.md`); keep established technical terms English. Section
     headers and frontmatter keys stay exactly as they are.
   - Improve clarity and structure; expand terse notes into proper prose.
   - Fold in the details the user confirmed. Add reasonable, faithful detail.
   - Never invent facts. Leave genuine unknowns as a short „Offene Fragen"-Notiz.

5. **Changelog** — Append a dated entry to `an_project/CHANGELOG.md`: `- <file> refined: <one-line gist>`.

6. **Summarize** — Briefly say what changed.

## Rules

- Preserve every section header and frontmatter key from the template.
- Never delete information the author provided — enrich it, don't replace it.
- Match the house style in `.an_framework/docs/guidelines.md`, narrowed by the project's own `an_project/docs/guidelines.md` where present (the project file wins on conflict).
