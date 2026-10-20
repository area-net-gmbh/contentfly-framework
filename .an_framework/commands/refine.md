<!-- PURPOSE: Shared refine workflow — reflects an artifact, then rewrites it clearly. -->
# refine — reflect on a filled-out artifact, then polish it

Turn a rough, human-filled artifact into a clear, well-structured document.
Works on any artifact: `an_project/project-description.md`, an epic, a story, or a task.

**Target file:** supplied by the active runtime adapter (if empty, ask which file to refine).

## Steps

1. **Understand** — Read the target file. Interpret what the author meant,
   including the intent behind terse or shorthand notes.

2. **Reflect** — State your understanding back to the user in 2–4 sentences:
   what this is, its goal, and anything ambiguous. **Do not edit the file yet.**

3. **Confirm** — List your assumptions and any open questions. Ask the user to
   confirm or correct before you rewrite. A section or field that reads `entfällt` is **not**
   an open question — it was declined deliberately; leave it as it is and do not raise it.

4. **Rewrite** — Once confirmed, rewrite the file:
   - Keep the artifact's required section headers and any YAML frontmatter keys.
   - Write the prose in **German** per the content-language rule in
     `.an_framework/AGENT-GUIDE.md`. Section headers and frontmatter keys stay exactly as they are.
   - Improve clarity and structure; expand terse notes into proper prose.
   - Fold in the details the user confirmed. Add reasonable, faithful detail.
   - Never invent facts. Leave genuine unknowns as a short „Offene Fragen"-Notiz.

5. **Changelog** — Append a dated entry to `an_project/CHANGELOG.md`: `- <file> refined: <one-line gist>`.
   **Head-append only:** read `an_project/CHANGELOG.md` with `limit: 30` and edit at the `# Changelog` header or the topmost date block — the file grows without bound and is never read for logic, so it must never enter the context as a whole.

6. **Summarize** — Briefly say what changed.

## Rules

- Preserve every section header and frontmatter key from the template.
- Never delete information the author provided — enrich it, don't replace it.
- Match the house style in `.an_framework/docs/guidelines.md`, narrowed by the project's own `an_project/docs/guidelines.md` where present (the project file wins on conflict).
