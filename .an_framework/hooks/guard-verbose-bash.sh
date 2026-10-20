#!/usr/bin/env bash
# PURPOSE: PreToolUse(Bash) hook — blocks a known test/build runner that has no output cap, and
# tells the model the exact capped form to retry with. A full green test log is the single most
# wasteful thing that can enter the context, and a prose rule does not reliably prevent it.
# Shipped in the read-only core; wired up by the active runtime adapter.
#
# FAIL OPEN: anything unexpected — no python3, unparsable input, unknown command — exits 0 and
# lets the call through. This guard may cost a retry; it must never cost the run.
set -u

command -v python3 >/dev/null 2>&1 || exit 0
cmd="$(python3 -c 'import sys,json
try:
    print(json.load(sys.stdin).get("tool_input", {}).get("command", ""))
except Exception:
    pass' 2>/dev/null)" || exit 0
[ -n "$cmd" ] || exit 0

# Already capped, redirected, quiet, or piped anywhere → nothing to do.
case "$cmd" in
  *"|"*|*" > "*|*">>"*|*"--quiet"*|*"--silent"*|*" -q"*|*"--reporter"*|*"tail"*|*"head"*|*"grep"*)
    exit 0 ;;
esac

# Only a short, explicit list of runners known to print an unbounded log on success.
case "$cmd" in
  "npm test"*|"npm run test"*|"npm run build"*|"yarn test"*|"pnpm test"*|"npx jest"*|"npx vitest"*) ;;
  "pytest"*|"python -m pytest"*|"python3 -m pytest"*) ;;
  "phpunit"*|"vendor/bin/phpunit"*|"./vendor/bin/phpunit"*|"bin/phpunit"*|"composer test"*) ;;
  "go test"*|"cargo test"*|"mvn test"*|"./gradlew test"*|"gradle test"*) ;;
  *) exit 0 ;;
esac

cat >&2 <<MSG
Blocked by the ki-dev-framework output guard (.an_framework/hooks/guard-verbose-bash.sh).

This run has no output cap. A full green test log is the single most wasteful thing that can
enter the context, and you only need the summary line when it passes.

Re-run it capped:

  $cmd 2>&1 | tail -n 30

If it fails, widen the tail or drop the pipe — that output is worth having. If it passes, carry
over the summary line only.
MSG
exit 2
