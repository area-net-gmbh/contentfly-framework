<!-- PURPOSE: Shared doc workflow — builds the stakeholder documentation, opens it in the browser, and (with `update`) brings its chapters back in line with the project. -->
# doc — build, open and maintain the stakeholder documentation

`an_project/stakeholder/` holds the project documentation **for outsiders** — the client,
designers, management, a new colleague. This workflow turns it into HTML that opens in a
browser with no server and no internet, and keeps it in step with the project.

Arguments: *(none)* → build and open · `update` → maintenance pass over the chapters ·
`check` → report only, change nothing.

**Context budget.** Without an argument and with `check`, read **nothing** — the build script
does the work and its stdout is the report. Only `update` reads
`.an_framework/docs/stakeholder-doc.md`, once, plus the chapters it actually changes. Never
read the generated HTML under `an_project/stakeholder/html/`; it is output, not a source.

**Zones.** The script lives in the read-only core and writes only into
`an_project/stakeholder/html/`. Never edit anything under `.an_framework/`, and never edit a
file under `an_project/stakeholder/html/` by hand — the next build overwrites it.

## Steps — no argument (look at it)
1. **Build and open** — one command:

   ```
   python3 .an_framework/scripts/build-stakeholder-doc.py --open
   ```

   Exit code 2 means the build failed: report the script's message **verbatim**, then stop.
   Do not guess at a cause and do not repair a chapter unasked.
2. **Report what it said** — the script prints the chapter count, the number of diagrams and
   every chapter that is not yet `gepflegt`. Pass that on in German, unembellished.
3. **Name drift, do not fix it silently** — list the commits made since the documentation was
   last touched:

   ```
   git log --oneline "$(git log -1 --format=%H -- an_project/stakeholder)"..HEAD
   ```

   Judge those subjects against the six triggers in `.an_framework/docs/stakeholder-doc.md`
   (external service · module · deployment · visible feature · decision · personal data) —
   from the subjects alone, without reading the file. If one plausibly applies, say which
   commit and offer `doc update`. If none does, say the documentation looks current. Never
   start an update from this mode.

The build is deterministic: with unchanged sources it produces byte-identical output, so
looking at the documentation leaves the worktree clean. If `git status` does show changes
afterwards, the Markdown really did change since the last build — those files belong in the
next commit.

## Steps — `update` (maintenance pass)
1. **Load the rules** — read `.an_framework/docs/stakeholder-doc.md` once. It carries the
   audience rules, the four hard rules and the source for each chapter. The four that matter
   most: no secrets, no prices, never invent, no second source of truth.
2. **Work out what changed** — the `git log --oneline` range from step 3 above, plus
   `an_project/CHANGELOG.md` (head only, `limit: 30`). From that, pick the chapters that are
   actually affected. A pass that rewrites all fourteen chapters is wrong: it costs a fortune
   and overwrites wording somebody approved.
3. **Fill the gaps** — for each affected chapter, and for every chapter still carrying
   `status: offen`, write what the project's own documents support. Where the answer is not in
   the repo (contacts, processes, provider accounts, client obligations), **ask the user** — one
   bundled set of questions in German, not one per chapter. Leave what nobody can answer open
   and say so; never fill a gap with something plausible.
   **`status: entfällt` and a steckbrief field reading `entfällt` are settled** — skip them
   entirely and never put them in the question set. The user already said this project does not
   have that; asking again every run is the behaviour this state exists to stop. If the user
   says in passing that a chapter does not apply, set it to `entfällt` — but only then.
4. **Update the frontmatter** — in each chapter you touched set `stand:` to today's date and
   `status: gepflegt` once it genuinely is. A chapter still waiting on an answer keeps
   `status: offen` — that is what makes the gap visible in the document. A chapter the user
   declared irrelevant gets `status: entfällt` and is then left alone for good.
5. **Carry the fact sheet** — check `steckbrief.md` against reality: status, version, release
   date, URLs, contacts, `stand:`. It is the first thing anyone reads.
6. **Build** — `python3 .an_framework/scripts/build-stakeholder-doc.py`. A failure here is
   yours to fix: it is almost always a malformed table or an unclosed fence in a chapter you
   just wrote.
7. **Changelog** — **head-append only:** read `an_project/CHANGELOG.md` with `limit: 30` and
   edit at the `# Changelog` header or the topmost date block. One line, e.g.
   `- Stakeholder-Doku aktualisiert: Schnittstellen, Module`.
8. **Hand over, don't commit** — report which chapters changed and offer `commit`. This
   workflow never commits by itself: the generated HTML and the Markdown belong in one commit
   together, and `commit` is the only place that knows the rules for that.

## Steps — `check` (report only)
1. Run `python3 .an_framework/scripts/build-stakeholder-doc.py --check`. Exit 0 means the HTML
   matches the sources, exit 1 means it is stale — harmless, the next build fixes it.
2. Add the drift check from step 3 of the first mode.
3. Report in German. Write nothing, build nothing, open nothing.

## When the folder is missing
`an_project/stakeholder/` only exists in projects set up with framework 3.3.0 or newer. If it
is missing, say so and point at the framework update (`init.sh --update` copies the chapter
skeleton in). Never create the chapters by hand — the skeleton belongs to the setup step.
