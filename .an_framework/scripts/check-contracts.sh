#!/usr/bin/env bash
# PURPOSE: Verify that Claude Code and Codex expose the same shared framework workflows.
set -eu

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
FAILURES=0

fail() {
  printf 'FAIL: %s\n' "$*" >&2
  FAILURES=$((FAILURES + 1))
}

for script in "$ROOT/init.sh" "$ROOT"/hooks/*.sh; do
  bash -n "$script" || fail "invalid shell syntax: ${script#$ROOT/}"
done

WORKFLOWS="anna board briefing commit doc done implement migrate-adrs new-epic new-project new-story new-task refine"
for name in $WORKFLOWS; do
  core="$ROOT/commands/$name.md"
  claude="$ROOT/skeleton/root/.claude/commands/$name.md"
  codex="$ROOT/skeleton/root/.agents/skills/an-$name/SKILL.md"

  [ -f "$core" ] || fail "missing shared workflow: commands/$name.md"
  [ -f "$claude" ] || fail "missing Claude adapter: $name"
  [ -f "$codex" ] || fail "missing Codex adapter: an-$name"

  grep -Fq ".an_framework/commands/$name.md" "$claude" 2>/dev/null ||
    fail "Claude adapter does not load commands/$name.md"
  grep -Fq ".an_framework/commands/$name.md" "$codex" 2>/dev/null ||
    fail "Codex adapter does not load commands/$name.md"
  grep -Eq "^name: an-$name$" "$codex" 2>/dev/null ||
    fail "Codex skill name mismatch: an-$name"
done

for name in an-scout an-briefer; do
  [ -f "$ROOT/agents/$name.md" ] || fail "missing shared agent: $name"
  [ -f "$ROOT/skeleton/root/.claude/agents/$name.md" ] || fail "missing Claude agent: $name"
  [ -f "$ROOT/skeleton/root/.codex/agents/$name.toml" ] || fail "missing Codex agent: $name"
done

[ -f "$ROOT/skeleton/root/CLAUDE.md" ] || fail "missing Claude project guide"
[ -f "$ROOT/skeleton/root/AGENTS.md" ] || fail "missing Codex project guide"
[ -f "$ROOT/skeleton/root/.claude/settings.json" ] || fail "missing Claude settings"
if grep -Fq 'Write(' "$ROOT/skeleton/root/.claude/settings.json"; then
  fail 'Claude settings contain obsolete Write(path) permission rules'
fi
grep -Fq '"Edit(**)"' "$ROOT/skeleton/root/.claude/settings.json" ||
  fail 'Claude settings do not allow project-wide edits'
grep -Fq '"Bash(git *)"' "$ROOT/skeleton/root/.claude/settings.json" ||
  fail 'Claude settings do not allow all Git commands'
[ -f "$ROOT/skeleton/root/.codex/hooks.json" ] || fail "missing Codex hooks"
[ -f "$ROOT/skeleton/root/.codex/rules/anna.rules" ] || fail "missing Codex execution rules"
grep -Fq 'pattern = ["git"]' "$ROOT/skeleton/root/.codex/rules/anna.rules" ||
  fail 'Codex execution rules do not allow all Git commands'
grep -Fq 'sandbox_mode = "workspace-write"' "$ROOT/skeleton/root/.codex/config.toml" ||
  fail 'Codex config does not allow project-wide writes'
grep -Fq 'Hallo, ich bin ANNA, deine digitale Entwickler-Kollegin bei AREA-NET.' \
  "$ROOT/hooks/session-brief.sh" ||
  fail 'SessionStart hook misses the ANNA identity'
grep -Fq '/briefing (Claude) oder \$an-briefing (Codex)' "$ROOT/hooks/session-brief.sh" ||
  fail 'SessionStart hook misses the runtime-neutral briefing introduction'
grep -Fq '.an_framework/hooks/check-update.sh' "$ROOT/hooks/session-brief.sh" ||
  fail 'SessionStart hook misses the bounded ANNA update check'
grep -Fq '.an_framework/hooks/session-brief.sh' "$ROOT/skeleton/root/.claude/settings.json" ||
  fail 'Claude adapter does not use the shared SessionStart hook'
grep -Fq 'ANNA_HOOK_OUTPUT=json' "$ROOT/skeleton/root/.claude/settings.json" ||
  fail 'Claude SessionStart hook does not request visible JSON output'
if grep -Fq 'session-brief.sh' "$ROOT/skeleton/root/.codex/hooks.json"; then
  fail 'Codex hooks still promise an impossible pre-prompt startup banner'
fi
grep -Fq 'status_line = ["project-name", "git-branch", "model-with-reasoning", "codex-version"]' \
  "$ROOT/skeleton/root/.codex/config.toml" || fail 'Codex config misses the ANNA status line'
[ -f "$ROOT/templates/adr.md" ] || fail "missing shared ADR template"
for heading in '## Kontext' '## Entscheidung' '## Begründung' '## Erwogene Alternativen' '## Konsequenzen'; do
  grep -Fq "$heading" "$ROOT/templates/adr.md" || fail "ADR template misses $heading"
done
grep -Fq 'an_project/docs/adr/' "$ROOT/AGENT-GUIDE.md" || fail "public guide misses ADR rule"
grep -Fq '| ADR | Entscheidung | Status |' "$ROOT/skeleton/an_project/docs/architecture.md" ||
  fail 'architecture skeleton misses linked ADR index'

if grep -RIl '\$ARGUMENTS' "$ROOT/commands" --include='*.md' 2>/dev/null |
  grep -v '/implement-task.md$' >/dev/null 2>&1; then
  fail 'shared workflows contain Claude-only $ARGUMENTS placeholders'
fi

TMP_ROOT="$(mktemp -d "${TMPDIR:-/tmp}/ki-framework-contracts.XXXXXX")"
trap 'rm -rf "$TMP_ROOT"' EXIT
mkdir -p "$TMP_ROOT/project/.an_framework"
cp -R "$ROOT"/. "$TMP_ROOT/project/.an_framework/"
mkdir -p "$TMP_ROOT/tmp"
TMPDIR="$TMP_ROOT/tmp" bash "$TMP_ROOT/project/.an_framework/init.sh" --yes --slim \
  --name="Contract Test" >/dev/null

for path in \
  CLAUDE.md AGENTS.md \
  .claude/commands/implement.md .claude/agents/an-scout.md .claude/settings.json \
  .agents/skills/an-implement/SKILL.md \
  .codex/config.toml .codex/hooks.json .codex/rules/anna.rules .codex/agents/an-scout.toml \
  an_project/.framework-profile \
  an_project/stakeholder/steckbrief.md an_project/stakeholder/index.md \
  an_project/stakeholder/130-quellen.md; do
  [ -e "$TMP_ROOT/project/$path" ] || fail "setup smoke test missed $path"
done

# The slim profile must leave these three stakeholder chapters out, and what remains
# must still build — the generator is shipped, so a broken skeleton is a broken release.
for path in an_project/stakeholder/060-prozesse.md \
  an_project/stakeholder/100-stand-und-roadmap.md \
  an_project/stakeholder/120-entscheidungen.md; do
  if [ -e "$TMP_ROOT/project/$path" ]; then
    fail "slim setup copied $path"
  fi
done
if command -v python3 >/dev/null 2>&1; then
  if ( cd "$TMP_ROOT/project" && python3 .an_framework/scripts/build-stakeholder-doc.py --quiet ); then
    [ -f "$TMP_ROOT/project/an_project/stakeholder/html/index.html" ] ||
      fail 'stakeholder build produced no index.html'
    [ -f "$TMP_ROOT/project/an_project/stakeholder/html/assets/search-index.js" ] ||
      fail 'stakeholder build produced no search index'
    # A chapter the project does not have must build, and must drop out of the gap report.
    CHAPTER="$TMP_ROOT/project/an_project/stakeholder/010-ueberblick.md"
    sed 's/^status:.*$/status: entfaellt/' "$CHAPTER" >"$CHAPTER.tmp" && mv "$CHAPTER.tmp" "$CHAPTER"
    if ( cd "$TMP_ROOT/project" &&
         python3 .an_framework/scripts/build-stakeholder-doc.py >"$TMP_ROOT/entfaellt.log" 2>&1 ); then
      grep -Fq 'Überblick & Zweck' "$TMP_ROOT/entfaellt.log" &&
        fail 'a chapter marked entfällt is still reported as a gap'
    else
      fail 'generator rejects status: entfaellt'
    fi
  else
    fail 'stakeholder documentation does not build from the shipped skeleton'
  fi
else
  printf 'SKIP: python3 missing — stakeholder documentation build not verified\n' >&2
fi

CLAUDE_ANNA="$(cd "$TMP_ROOT/project" && bash .an_framework/hooks/anna.sh claude)"
CODEX_ANNA="$(cd "$TMP_ROOT/project" && bash .an_framework/hooks/anna.sh codex)"
printf '%s\n' "$CLAUDE_ANNA" | grep -Fq "ANNA · Version $(cat "$ROOT/VERSION")" ||
  fail 'Claude anna overview misses the framework version'
printf '%s\n' "$CLAUDE_ANNA" | grep -Fq '/new-project' ||
  fail 'Claude anna overview misses /new-project'
printf '%s\n' "$CODEX_ANNA" | grep -Fq '$an-anna' ||
  fail 'Codex anna overview misses $an-anna'
printf '%s\n' "$CODEX_ANNA" | grep -Fq '$an-migrate-adrs' ||
  fail 'Codex anna overview misses $an-migrate-adrs'

mkdir -p "$TMP_ROOT/retrofit/.an_framework" "$TMP_ROOT/retrofit/an_project/docs"
cp -R "$ROOT"/. "$TMP_ROOT/retrofit/.an_framework/"
printf '# Existing Codex rules\n\nKeep this line.\n' >"$TMP_ROOT/retrofit/AGENTS.md"
printf '# Existing Architecture\n\n## Key decisions\n\n### ADR 1 - Legacy\n' > \
  "$TMP_ROOT/retrofit/an_project/docs/architecture.md"
TMPDIR="$TMP_ROOT/tmp" bash "$TMP_ROOT/retrofit/.an_framework/init.sh" --yes --slim >/dev/null
grep -q '^Keep this line\.$' "$TMP_ROOT/retrofit/AGENTS.md" ||
  fail 'retrofit replaced existing AGENTS.md content'
grep -q '<!-- ki-dev-framework:start -->' "$TMP_ROOT/retrofit/AGENTS.md" ||
  fail 'retrofit did not append the Codex framework loader'
grep -q 'ADR migration needed' "$TMP_ROOT/retrofit/an_project/SETUP-REPORT.md" ||
  fail 'retrofit did not detect legacy inline ADRs'

# A Codex-only project must still enter UPDATE mode; detection must not depend on CLAUDE.md.
mkdir -p "$TMP_ROOT/codex-only/.an_framework" "$TMP_ROOT/codex-only/an_project" \
  "$TMP_ROOT/codex-only/.claude"
cp -R "$ROOT"/. "$TMP_ROOT/codex-only/.an_framework/"
cp "$ROOT/skeleton/root/AGENTS.md" "$TMP_ROOT/codex-only/AGENTS.md"
mkdir -p "$TMP_ROOT/codex-only/.codex"
printf '%s\n' '{"hooks":{"SessionStart":[{"matcher":"startup|resume|compact","hooks":[{"type":"command","command":"ANNA_HOOK_OUTPUT=json bash \"$(git rev-parse --show-toplevel)/.an_framework/hooks/session-brief.sh\"","timeout":10}]}]}}' > \
  "$TMP_ROOT/codex-only/.codex/hooks.json"
cp "$ROOT/skeleton/root/.codex/config.toml" "$TMP_ROOT/codex-only/.codex/config.toml"
sed '/^\[tui\]$/,$d' "$TMP_ROOT/codex-only/.codex/config.toml" > \
  "$TMP_ROOT/codex-only/.codex/config.toml.old"
mv "$TMP_ROOT/codex-only/.codex/config.toml.old" "$TMP_ROOT/codex-only/.codex/config.toml"
printf '%s\n' '{"permissions":{"allow":["Edit(src/**)","Write(src/**)"]},"hooks":{"SessionStart":[{"matcher":"startup|resume|clear|compact","hooks":[{"type":"command","command":"bash \"${CLAUDE_PROJECT_DIR:-.}/.an_framework/hooks/session-brief.sh\"","timeout":10}]}]}}' > \
  "$TMP_ROOT/codex-only/.claude/settings.json"
TMPDIR="$TMP_ROOT/tmp" bash "$TMP_ROOT/codex-only/.an_framework/init.sh" --update --yes --slim \
  >"$TMP_ROOT/codex-only-update.log"
grep -q '^Mode[[:space:]]*: UPDATE$' "$TMP_ROOT/codex-only-update.log" ||
  fail 'Codex-only project was not recognized as UPDATE mode'
if grep -Fq 'Write(' "$TMP_ROOT/codex-only/.claude/settings.json"; then
  fail 'update did not remove obsolete Claude Write(path) permission rules'
fi
grep -Fq 'ANNA_HOOK_OUTPUT=json' "$TMP_ROOT/codex-only/.claude/settings.json" ||
  fail 'update did not migrate Claude SessionStart to visible JSON output'
if grep -Fq 'session-brief.sh' "$TMP_ROOT/codex-only/.codex/hooks.json"; then
  fail 'update did not remove ANNA Codex SessionStart hook'
fi
grep -Fq 'status_line = ["project-name", "git-branch", "model-with-reasoning", "codex-version"]' \
  "$TMP_ROOT/codex-only/.codex/config.toml" || fail 'update did not install Codex status line'
grep -Fq 'sandbox_mode = "workspace-write"' "$TMP_ROOT/codex-only/.codex/config.toml" ||
  fail 'update did not install Codex workspace-write mode'
grep -Fq 'approval_policy = "on-request"' "$TMP_ROOT/codex-only/.codex/config.toml" ||
  fail 'update did not install Codex approval policy'
[ -f "$TMP_ROOT/codex-only/.codex/rules/anna.rules" ] ||
  fail 'update did not install Codex execution rules'
grep -Fq '"Edit(**)"' "$TMP_ROOT/codex-only/.claude/settings.json" ||
  fail 'update did not install project-wide Claude edit permissions'

# --- Optional interview answers: the settled state ---------------------------------------
# "offen" means ask again; "entfällt" means never ask again. Lose the second state and every
# field a project simply does not have becomes a question at every doc and done run.
grep -Fq 'entfällt' "$ROOT/docs/stakeholder-doc.md" ||
  fail 'stakeholder baseline does not document the entfällt state'
grep -Fq 'entfällt' "$ROOT/commands/new-project.md" ||
  fail 'new-project does not record declined answers as entfällt'
for workflow in doc done; do
  grep -Fq 'entfällt' "$ROOT/commands/$workflow.md" ||
    fail "$workflow still asks for fields marked entfällt"
done
grep -Fq '"entfällt"' "$ROOT/scripts/build-stakeholder-doc.py" ||
  fail 'generator does not accept status: entfällt'

# Testing happens locally; a server environment needs to be named by the user.
grep -Fq 'Getestet wird lokal' "$ROOT/AGENT-GUIDE.md" ||
  fail 'public guide misses the local-testing rule'

# --- Tutorials: no hard-coded version in a fetch command ---------------------------------
# A copied command with a real version number silently installs an outdated framework. The
# {VERSION} placeholder fails loudly instead, which is the whole point.
if grep -nE '(--branch v[0-9]|an_framework-v[0-9])' "$ROOT"/tutorial/*.md >/dev/null 2>&1; then
  fail 'a tutorial fetch command carries a hard-coded version instead of {VERSION}'
fi
for guide in quickstart update; do
  grep -Fq '{VERSION}' "$ROOT/tutorial/$guide.md" ||
    fail "tutorial/$guide.md does not use the {VERSION} placeholder"
done

# --- Release packaging ------------------------------------------------------------------
# The download route and the git route must hand out the same tree. A drift here only shows
# up when somebody unpacks a release into a project — by then the tag is public.
grep -Eq '^[0-9]+\.[0-9]+\.[0-9]+$' "$ROOT/VERSION" ||
  fail 'VERSION is not a three-part number — the tag pipeline would refuse the release'
[ -f "$ROOT/.gitlab-ci.yml" ] || fail 'missing release pipeline: .gitlab-ci.yml'
sh -n "$ROOT/scripts/build-release-zip.sh" ||
  fail 'invalid shell syntax: scripts/build-release-zip.sh'
grep -Fq 'scripts/build-release-zip.sh' "$ROOT/.gitlab-ci.yml" ||
  fail '.gitlab-ci.yml does not build the archive through scripts/build-release-zip.sh'
grep -Fq 'an_framework-$CI_COMMIT_TAG.zip' "$ROOT/.gitlab-ci.yml" ||
  fail '.gitlab-ci.yml release asset does not point at the built archive name'

if sh "$ROOT/scripts/build-release-zip.sh" --out-dir "$TMP_ROOT/dist" >/dev/null; then
  HEAD_VERSION="$(git -C "$ROOT" show HEAD:VERSION 2>/dev/null |
    sed -n '1{s/^v//;s/[[:space:]]*$//;p;}')"
  RELEASE_ZIP="$TMP_ROOT/dist/an_framework-v$HEAD_VERSION.zip"
  if [ ! -f "$RELEASE_ZIP" ]; then
    fail 'release archive is not named after the committed VERSION'
  elif command -v unzip >/dev/null 2>&1; then
    unzip -Z1 "$RELEASE_ZIP" | grep -v '/$' | sed 's|^\.an_framework/||' | sort \
      >"$TMP_ROOT/zip.list"
    git -C "$ROOT" ls-tree -r --name-only HEAD | sort >"$TMP_ROOT/head.list"
    diff "$TMP_ROOT/zip.list" "$TMP_ROOT/head.list" >/dev/null ||
      fail 'release archive is not file-for-file the committed tree under .an_framework/'
  else
    printf 'SKIP: unzip missing — release archive contents not verified\n' >&2
  fi
else
  fail 'release archive does not build'
fi

if [ "$FAILURES" -ne 0 ]; then
  printf '%s contract check(s) failed.\n' "$FAILURES" >&2
  exit 1
fi

printf 'Contract checks passed: %s shared workflows, 2 runtime adapters, 2 read-only agents.\n' \
  "$(printf '%s\n' $WORKFLOWS | wc -l | tr -d ' ')"
