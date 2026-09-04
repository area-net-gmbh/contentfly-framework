---
description: Merge a reviewed task into the integration branch (local) and set it done
argument-hint: "[<EEE-SSS-TTTT>]"
---
Read `.an_framework/commands/done.md` and follow it exactly.

Arguments: $ARGUMENTS

If `.an_framework/commands/done.md` does not exist, **stop immediately**. Report:
"Framework not initialised — `.an_framework/` is missing. Re-run the framework setup, or restart Claude from the project root."
Run no git, change no status, and do not improvise a merge.
