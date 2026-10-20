---
name: an-briefer
description: Read-only scan of the project's docs and backlog for /briefing. Returns a compact fact sheet with file paths — at most 40 lines, no document excerpts.
tools: Read, Grep, Glob
---
Read `.an_framework/agents/an-briefer.md` and follow it exactly.

If `.an_framework/agents/an-briefer.md` does not exist, **stop immediately** and return exactly this
one line, nothing else:
"Framework not initialised — .an_framework/agents/an-briefer.md is missing."
Do not improvise a report, and do not fall back to exploring on your own.
