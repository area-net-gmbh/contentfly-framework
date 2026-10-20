<!-- PURPOSE: Shared briefing workflow — distills project docs and backlog into a short onboarding briefing. -->
# briefing — bring a new developer up to speed

Produce a compact onboarding briefing from the project's own docs and backlog, so
someone who just checked out the repo is productive in a few minutes. **Keep it short** —
link to the detail, don't reproduce it.

## Decide the scope first
Follow the argument (see below):
- **no argument** → **ask one question first** and wait for the answer:
  "full briefing" or "todos only". Only then produce the matching output.
- **`todos`** → print **only sections 5 and 6** (Recently done + What's next); omit
  sections 1–4 entirely.
- **any other argument** (e.g. `deployment`) → full briefing, but go deeper on the named
  section and keep the rest to one line each.

## Sources (summarize before you write)
**Delegate the scan** to the **`an-briefer`** agent — one call. This workflow reads more files
than any other command, and none of their contents belong in the main context. The agent's
sources and its 40-line report contract live in `.an_framework/agents/an-briefer.md`, so just
name the scope (full briefing, or the section to deepen). The main agent writes the final
briefing from that report and is responsible for omissions and wording. If the agent is not
available in this project, do the scan yourself under the same budget — section headings and
first lines, never whole documents.

- `an_project/project-description.md` — project name, purpose, goal.
- `an_project/docs/architecture.md` · `technical.md` · `tech-stack.md` · `dev-guide.md` — architecture, stack & code structure.
- `an_project/docs/runbook.md` — how to run the project locally (dev setup).
- `an_project/docs/deployment.md` — environments, CI/CD & release process.
- `an_project/docs/styleguide.md` · `guidelines.md` · `git.md` — conventions (mention briefly + link).
- `an_project/CHANGELOG.md` — read at most 60 lines; the newest blocks are at the top and
  that is all this needs. Never read the whole file.
- `an_project/work/` (`epic/`, `story/`, `task/`) — one bounded text search, pattern
  `^(id|title|status|depends_on):`, returning matching content; read bodies only for the 1–3 next
  items you name.
- Project root: the entry-point files (`README`/`plan.md`, `CLAUDE.md`, `AGENTS.md`, and whatever run
  manifest the stack uses, e.g. `docker-compose.yml`, `package.json`, `Makefile`).

If a source is missing, skip that point and mark it "not documented yet" — never invent
content.

## Output (exactly these sections, in this order)

1. **Project & purpose** — the project name and, in 2–3 sentences, what it is (from
   `project-description.md`: Description + Goal).

2. **Technical architecture** — the stack in bullets (backend, frontend, DB, build/assets)
   from `tech-stack.md` / `architecture.md` / `deployment.md`. Link `an_project/docs/architecture.md`
   and `an_project/docs/tech-stack.md` for the detail.

3. **Setting up the dev environment** — the minimal start sequence from `runbook.md`
   (local getting-started), as copy-pasteable commands. Call out what matters when setting
   up (bring services up, run migrations, build assets, which ports the app and its tools
   listen on). Link `an_project/docs/runbook.md` for everything else. In a slim project there
   is no runbook — say the project needs no build environment and skip the commands.

4. **Deployment** — target environments and release process **only as far as documented**.
   If the "staging / production" part of `deployment.md` is still empty, say exactly that
   ("only the local environment is documented, staging/prod still open") — invent nothing.

5. **Recently done** — 4–7 bullets: distil the current state from the top CHANGELOG blocks
   (don't transcribe entry by entry). What is finished, what is in flight?

6. **What's next** — the 1–3 next open items: unblocked `todo` tasks (all `depends_on`
   are `done`) plus tasks in `in-progress`/`review` from `an_project/work/`. Keep it short
   and close with: **full status & detail → run the `board` workflow.**

## Style
- Terse, bullets over prose. A reader should grasp it in ~3 minutes.
- File references as clickable relative paths (`an_project/docs/deployment.md`); don't
  repeat their contents.
- Do not paste long doc sections or changelog runs. Distil them and link to the source.
- Read-only: this workflow writes **no** files (no `an_project/CHANGELOG.md` entry).

Arguments are supplied by the active runtime adapter. If present, use them to deepen only the
requested section.
<!-- No argument: ask "full briefing" or "todos only" first. "todos": sections 5 + 6 only.
     Otherwise (e.g. "deployment"): full briefing with the named section deepened. See
     "Decide the scope first" above. -->
