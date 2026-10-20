<!-- PURPOSE: Shared ANNA identity workflow — renders the banner and runtime-specific command overview. -->
# anna — identity and workflow overview

Run the framework renderer for the active runtime and reproduce its stdout verbatim in one text
block. Add no introduction, summary, or commentary.

- Claude Code adapter: `bash .an_framework/hooks/anna.sh claude`
- Codex adapter: `bash .an_framework/hooks/anna.sh codex`

This workflow is read-only. If the renderer is missing, stop and report that the framework is not
initialized; do not improvise the command list.
