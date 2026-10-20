# ANNA — Setup Report

- Framework version: **3.4.0**
- Mode: **UPDATE**
- Profile: **full**


## Copied into the project
- kept existing `CLAUDE.md`
- `AGENTS.md` (Codex framework stub)
- skip: `/Users/florian.schmid/Documents/Entwicklung/Projekte_Git_V2/Intern/contentfly-framework/an_project/project-description.md`
- skip: `/Users/florian.schmid/Documents/Entwicklung/Projekte_Git_V2/Intern/contentfly-framework/an_project/CHANGELOG.md`
- copy: `/Users/florian.schmid/Documents/Entwicklung/Projekte_Git_V2/Intern/contentfly-framework/an_project/stakeholder/100-stand-und-roadmap.md`
- copy: `/Users/florian.schmid/Documents/Entwicklung/Projekte_Git_V2/Intern/contentfly-framework/an_project/stakeholder/030-technik.md`
- copy: `/Users/florian.schmid/Documents/Entwicklung/Projekte_Git_V2/Intern/contentfly-framework/an_project/stakeholder/050-module.md`
- copy: `/Users/florian.schmid/Documents/Entwicklung/Projekte_Git_V2/Intern/contentfly-framework/an_project/stakeholder/110-risiken-und-offene-punkte.md`
- copy: `/Users/florian.schmid/Documents/Entwicklung/Projekte_Git_V2/Intern/contentfly-framework/an_project/stakeholder/020-funktionen.md`
- copy: `/Users/florian.schmid/Documents/Entwicklung/Projekte_Git_V2/Intern/contentfly-framework/an_project/stakeholder/010-ueberblick.md`
- copy: `/Users/florian.schmid/Documents/Entwicklung/Projekte_Git_V2/Intern/contentfly-framework/an_project/stakeholder/steckbrief.md`
- copy: `/Users/florian.schmid/Documents/Entwicklung/Projekte_Git_V2/Intern/contentfly-framework/an_project/stakeholder/120-entscheidungen.md`
- copy: `/Users/florian.schmid/Documents/Entwicklung/Projekte_Git_V2/Intern/contentfly-framework/an_project/stakeholder/130-quellen.md`
- copy: `/Users/florian.schmid/Documents/Entwicklung/Projekte_Git_V2/Intern/contentfly-framework/an_project/stakeholder/index.md`
- copy: `/Users/florian.schmid/Documents/Entwicklung/Projekte_Git_V2/Intern/contentfly-framework/an_project/stakeholder/080-deployment.md`
- copy: `/Users/florian.schmid/Documents/Entwicklung/Projekte_Git_V2/Intern/contentfly-framework/an_project/stakeholder/090-daten-und-datenschutz.md`
- copy: `/Users/florian.schmid/Documents/Entwicklung/Projekte_Git_V2/Intern/contentfly-framework/an_project/stakeholder/070-organisation.md`
- copy: `/Users/florian.schmid/Documents/Entwicklung/Projekte_Git_V2/Intern/contentfly-framework/an_project/stakeholder/040-schnittstellen.md`
- copy: `/Users/florian.schmid/Documents/Entwicklung/Projekte_Git_V2/Intern/contentfly-framework/an_project/stakeholder/060-prozesse.md`
- skip: `/Users/florian.schmid/Documents/Entwicklung/Projekte_Git_V2/Intern/contentfly-framework/an_project/docs/architecture.md`
- skip: `/Users/florian.schmid/Documents/Entwicklung/Projekte_Git_V2/Intern/contentfly-framework/an_project/docs/dev-guide.md`
- skip: `/Users/florian.schmid/Documents/Entwicklung/Projekte_Git_V2/Intern/contentfly-framework/an_project/docs/guidelines.md`
- skip: `/Users/florian.schmid/Documents/Entwicklung/Projekte_Git_V2/Intern/contentfly-framework/an_project/docs/deployment.md`
- skip: `/Users/florian.schmid/Documents/Entwicklung/Projekte_Git_V2/Intern/contentfly-framework/an_project/docs/git.md`
- skip: `/Users/florian.schmid/Documents/Entwicklung/Projekte_Git_V2/Intern/contentfly-framework/an_project/docs/technical.md`
- skip: `/Users/florian.schmid/Documents/Entwicklung/Projekte_Git_V2/Intern/contentfly-framework/an_project/docs/styleguide.md`
- skip: `/Users/florian.schmid/Documents/Entwicklung/Projekte_Git_V2/Intern/contentfly-framework/an_project/work/epic/.gitkeep`
- skip: `/Users/florian.schmid/Documents/Entwicklung/Projekte_Git_V2/Intern/contentfly-framework/an_project/work/task/.gitkeep`
- skip: `/Users/florian.schmid/Documents/Entwicklung/Projekte_Git_V2/Intern/contentfly-framework/an_project/work/story/.gitkeep`

## .gitignore
- `.gitignore` already carries the framework block — left as is

## .claude/
- merged `settings.json` (unioned allow/deny, removed obsolete `Write(...)` rules, migrated the ANNA SessionStart output, rewrote the deny path to `.an_framework`)
- `settings.json`: framework hooks installed (SessionStart orientation + Bash output guard)
- command stubs installed (no conflicts)
- agent stubs installed (no conflicts)

## Codex adapter
- Codex workflow skills installed (no conflicts)
- Codex config, permissions, ANNA status line, hooks and read-only agents installed (no conflicts)
- **ADR migration needed:** legacy inline decisions found in `an_project/docs/architecture.md`. After setup run `/migrate-adrs` in Claude Code or `$an-migrate-adrs` in Codex.

## Next steps (manual)

1. Review this report — it names every file that was added.
2. Make the framework commit (printed below). It stays SEPARATE from project work.
3. Restart Claude Code or Codex from the project root so new adapters are picked up.
4. Migrate legacy inline ADRs: `/migrate-adrs` (Claude Code) or `$an-migrate-adrs` (Codex).
5. Full walkthrough: `.an_framework/tutorial/update.md`.
