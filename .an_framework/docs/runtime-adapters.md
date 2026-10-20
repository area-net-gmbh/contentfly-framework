<!-- PURPOSE: Defines the shared core and the runtime-specific Claude Code and Codex adapters. -->

# Runtime adapters

ANNA is the runtime-neutral framework identity. Claude Code and Codex use the same committed
framework core and the same project data. Both
adapters are installed together. Switching runtime means ending one session and starting the
other from the project root; no migration or regeneration is required.

## Shared contract

The following paths are runtime-independent and are the source of truth:

- `.an_framework/commands/`, `agents/`, `templates/`, and `docs/`
- `an_project/work/`, `an_project/docs/`, `an_project/CHANGELOG.md`
- work-item IDs, YAML frontmatter, status transitions, branches, commits, and output language

The core describes workflows by unprefixed name. Runtime adapters only discover a workflow,
pass its arguments, enforce runtime permissions, and load the shared body.

## Adapter mapping

| Concern | Claude Code | Codex |
|---|---|---|
| Project guidance | `CLAUDE.md` | `AGENTS.md` |
| Workflows | `.claude/commands/<name>.md` | `.agents/skills/an-<name>/SKILL.md` |
| Invocation | `/implement <id>` | `$an-implement <id>` |
| Read-only roles | `.claude/agents/*.md` | `.codex/agents/*.toml` |
| Hooks | `.claude/settings.json` | `.codex/hooks.json` |
| Runtime config | `.claude/settings.json` | `.codex/config.toml` |
| Permissions | `.claude/settings.json` | `.codex/config.toml` + `.codex/rules/anna.rules` |

Invocation syntax is intentionally the only visible difference. Artifacts and lifecycle state
remain shared, so a task started with Claude Code can be resumed with Codex and vice versa.

Codex cannot render a project hook before its first prompt because no session exists yet. Its
immediate ANNA presence therefore uses the supported built-in status-line items configured in
`.codex/config.toml`; the full identity and workflow overview is `$an-anna`. Review the remaining
project hooks with `/hooks` and trust them after checking their paths. Codex records trust for the
exact hook content and asks again when a hook
changes. This is runtime security behavior, not a framework migration.

## Adapter rules

1. Never duplicate workflow logic in an adapter. It loads exactly one matching file from
   `.an_framework/commands/`.
2. Every new workflow ships both adapters in the same release.
3. Runtime-specific placeholders and tool names stay in adapters, not shared workflow bodies.
4. Do not pin a model in project adapters. Runtime and model selection remain user concerns.
5. Run `scripts/check-contracts.sh` before release. It verifies adapter parity and performs a
   setup smoke test.

The generated prose of two different models cannot be guaranteed byte-for-byte identical. The
framework contract guarantees the same schema, files, state transitions, language, and Git
rules. Deterministic mechanics belong in scripts when exact bytes matter.
