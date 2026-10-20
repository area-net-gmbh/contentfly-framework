<!-- PURPOSE: Shared migrate-adrs workflow — moves legacy inline architecture decisions into compact ADR files and leaves only a linked index. -->
# migrate-adrs — migrate legacy inline architecture decisions

This is an idempotent documentation migration. It writes only under `an_project/docs/` and
`an_project/CHANGELOG.md`; it does not change code, Git branches, or `.an_framework/`.

## Steps

1. **Preflight** — read `an_project/docs/architecture.md` and list existing files under
   `an_project/docs/adr/` if that directory exists. No architecture file or no inline decision
   content → report that there is nothing to migrate and change nothing.
2. **Identify decisions** — treat numbered ADR tables, headings such as `ADR 012`, and other
   clearly binding architecture decisions as candidates. Merge a summary row and its detailed
   section into one logical ADR. Do not migrate general architecture description, tentative
   options, implementation notes, or duplicates of an existing ADR.
3. **Preserve identity** — an explicit legacy number stays the same, zero-padded to four digits
   (`ADR 12` → `0012-<slug>.md`). For an unnumbered decision, use the highest number across
   legacy and existing ADRs plus one. Never count files, reuse a number, or renumber an ADR.
4. **Complete the evidence** — for every candidate, collect Status, Datum where recoverable,
   Kontext, Entscheidung, explicit Begründung, seriously considered alternatives, and positive
   and negative consequences. Use the inline text and directly referenced project documents as
   evidence. Do not invent rationale. If any required section cannot be supported, present one
   compact consolidated list of the gaps and ask the user for the missing facts before writing
   that ADR. A decision sentence by itself is not a completed ADR.
5. **Write records** — create each record from `.an_framework/templates/adr.md`, in German and
   normally well below one page. Preserve meaningful existing wording. Existing ADR files are
   never overwritten; if content conflicts, stop and report both sources.
6. **Reduce the architecture file** — keep Overview, Components, Boundaries/contracts, diagrams,
   and other architecture description unchanged. Replace inline ADR tables and full ADR bodies
   with one `## Architecture decisions` table containing only linked ADR number, short decision
   title, and status. A non-ADR section that merely references a decision may link to its ADR but
   must not repeat its rationale.
7. **Record the migration** — append one dated head entry to `an_project/CHANGELOG.md` naming
   the migrated ADR range/count and `architecture.md` index conversion. Follow the shared
   head-append rule and do not read the whole changelog.
8. **Verify** — every index link resolves; every migrated ADR contains all required headings;
   ADR numbers and filenames are unique; no full ADR body remains in `architecture.md`. Report
   created files, skipped existing records, and any decision intentionally left unmigrated.

