<!-- PURPOSE: Slash command /implement-task — kept as an alias of /implement so projects installed before v2.2 keep working. All logic lives in implement.md. -->
# /implement-task — alias of `/implement`

Since v2.2 this command is `/implement`, and it takes the id of **any** work item — a task, a
story, or an epic — deciding the level from the id itself.

**Read `.an_framework/commands/implement.md` and follow it exactly.** Pass `$ARGUMENTS`
through unchanged.

This file exists only so that projects set up before v2.2 keep working: their
`.claude/commands/implement-task.md` stub is project-owned, and the init script never deletes
or overwrites a stub it already finds. Do not put logic here — it would drift from
`implement.md` immediately.

After the run, mention once that `/implement <id>` is the current name and now also accepts
story and epic ids.
