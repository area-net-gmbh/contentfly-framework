<!-- PURPOSE: Shared implement workflow — routes one item to its task, story, or epic workflow. -->
# implement — implement a work item end-to-end

**Arguments:** supplied by the active runtime adapter; the id of the item to implement. If
missing or ambiguous, ask.

**No item, no code.** If you were sent here by a bare instruction with no backing work item,
do **not** implement. Run the ticket gate first — the *Kein Code ohne Ticket* rule in
`.an_framework/AGENT-GUIDE.md` carries the verbatim German question and the parent mapping.
Only a real id gets past this line.

## 0. Resolve the id and the level

Strip a leading `#`. Accept three forms and normalise to the full address `EEE-SSS-TTTT`:

| Given | Normalised | Level |
|---|---|---|
| `001` | `001-000-0000` | epic |
| `001-001` | `001-001-0000` | story |
| `EEE-SSS-TTTT` | as given | see below |

Then read the level off the normalised id — the three cases are disjoint, so this never guesses:

- `TTTT ≠ 0000` → **task** — this covers `001-001-0001`, the epic-level task `001-000-0007` and the ad-hoc task `000-000-0004` alike.
- `TTTT = 0000` and `SSS ≠ 000` → **story**, standalone (`000-002-0000`) included.
- `TTTT = 0000`, `SSS = 000`, `EEE ≠ 000` → **epic**.
- `000-000-0000` is not an id → stop and ask.

Anything that is not one of the three forms — a stray flag, a free-form string — **stop and
ask**; never read a free-form string as an id.

**Verify against the file, don't trust the parse.** One bounded text search finds it, whatever
the backlog size: pattern `^id: <EEE-SSS-TTTT>` under `an_project/work`, returning matching
filenames. Never list and open the whole backlog. The file that carries the id
decides the level: `epic.md` → epic, `story.md` → story, `EEE-SSS-TTTT-*.md` → task. No file
resolves for the id → stop; do not improvise a work item (ticket gate above). File and parse
disagree → stop and report both; that is a broken id somewhere, not something to paper over.

## 1. Read exactly one level file, then follow it

| Level | Read |
|---|---|
| **task** | `.an_framework/commands/implement/task.md` |
| **story** | `.an_framework/commands/implement/story.md` |
| **epic** | `.an_framework/commands/implement/epic.md` |

Read **only** that one. The other two do not apply and would cost context for nothing —
`story.md` and `epic.md` say themselves where they enter the next level down.

## 2. Context budget — binding for the whole run, at every level

- **Read each rule file once per session.** `.an_framework/docs/guidelines.md`,
  `.an_framework/docs/git-branching.md`, `.an_framework/commands/commit.md` and the project
  overlays `an_project/docs/guidelines.md` · `an_project/docs/git.md` (project wins on
  conflict). In a story run they are in context from the first task on — do **not** read them
  again for the next task.
- **Search backlog frontmatter; never open every file.** Id lookup is as in §0. For an overview,
  search `^(id|title|status|depends_on):` once under `an_project/work` — four lines per item
  instead of one whole file
  each. Narrow it to open work with `^status: (todo|in-progress|review|blocked)` where
  finished items don't matter. Read a full work-item body only for the item being worked on
  and its direct blockers.
- **Delegate the code scan** to the **`an-scout`** agent — exactly one call, before the first
  edit. Its role and its 25-line report contract live in `.an_framework/agents/an-scout.md`; do
  not restate them in the prompt, just give it the item's intent and a starting point. Its
  runtime configuration makes it read-only. Then open only the places it names, in bounded
  ranges. If the agent is not available in this
  project, do the scan yourself under the same 25-line budget.
- **Never delegate** decisions, edits, git, or the final user-facing summary. Those stay with
  the main agent.
- **Verification output stays out of the log.** Run tests with their quiet/summary flag; on
  success carry over the summary line only, on failure the failing part. Never paste a full
  successful test run.
- **Never read** `.an_framework/tutorial/`, `.an_framework/docs/framework-architecture.md` or
  `.an_framework/README.md` — they are for humans, no command needs them. Exclude
  `.an_framework/` from every code search.

## Rules for every level

- **Never widen the item.** Work that turns up on the way and isn't covered by the item's
  acceptance criteria gets its own task (`/new-task`) — the branch is not inflated. This is the
  branch rule from `.an_framework/docs/git-branching.md`: a branch belongs to exactly one work
  item, and for a story that item is the story.
- **Never compose a commit message** — always `/commit`, always with the id of the item the
  commit is about (the *task* id inside a story).
- **Never touch `.an_framework/`** — read-only at runtime.
- Everything produced is German per the content-language rule in `.an_framework/AGENT-GUIDE.md`;
  frontmatter keys and status values stay English.
