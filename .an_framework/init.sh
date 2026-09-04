#!/usr/bin/env bash
#
# ki-dev-framework — init.sh
# Sets a project up from the committed core in .an_framework/.
#
# Runs AFTER the bootstrap has placed the core:
#   git clone --depth 1 --branch <tag> <url> .an_framework
#   rm -rf .an_framework/.git
#   bash .an_framework/init.sh
#
# Contract:
#   * FILES ONLY — never runs `git commit`/`add`/`push`. It PRINTS the setup
#     commit for you to review and run, preserving the human-review-before-history
#     rule that /commit enforces.
#   * NON-DESTRUCTIVE — copies a skeleton file only if the target is absent.
#     A pre-existing CLAUDE.md is moved to CLAUDE-ARCHIVE.md (which /new-project
#     reads); any other collision is left untouched and recorded.
#   * MERGE, don't clobber — an existing .claude/settings.json is unioned, never
#     replaced; the stale deny path is the one thing rewritten. Same-named command
#     stubs are preserved, and the conflict is flagged for a human.
#
# Portability floor (verified): bash 3.2, BSD sed, jq optional. No associative
# arrays, no `${x,,}`, no `sed -i`, JSON tooling optional (python3 → defer).

set -eu

# ---------------------------------------------------------------- locate --
FW="$(cd "$(dirname "$0")" && pwd)"   # .an_framework
ROOT="$(dirname "$FW")"               # project root = parent of .an_framework
SKEL="$FW/skeleton"

# ---------------------------------------------------------------- flags ---
NAME=""
ASSUME_YES=0
PROFILE=""
DO_UPDATE=0
for arg in "$@"; do
  case "$arg" in
    --yes|-y)        ASSUME_YES=1 ;;
    --name=*)        NAME="${arg#--name=}" ;;
    --slim)          PROFILE="slim" ;;
    --full)          PROFILE="full" ;;
    --update)        DO_UPDATE=1 ;;
    -h|--help)
      cat <<'USAGE'
Usage: bash .an_framework/init.sh [--name="Project Name"] [--slim|--full] [--update] [--yes]
  --name=...   Fill the <project name> placeholder in CLAUDE.md.
  --slim       Slim project: only the work backlog + the git/guidelines overlays.
               No Docker, tech-stack, runbook or dev docs. Good for a plain HTML page.
  --full       Full project (the default): the complete doc set. If neither --slim nor
               --full is given, init.sh asks interactively (and falls back to full).
               On an --update run the existing an_project/.framework-profile answers
               this, so neither flag is needed.
  --update     Run against an ALREADY initialised project, after swapping .an_framework
               for a newer release: adds skeleton files that are missing (new command
               stubs, new doc templates), unions the new permissions into your
               .claude/settings.json. Never overwrites a file you have filled in, never
               commits. Without this flag an initialised project is left alone.
  --yes, -y    Do not prompt for confirmation.
Run from anywhere; the project root is the parent of .an_framework/.
USAGE
      exit 0 ;;
    *) printf 'init.sh: unknown argument: %s\n' "$arg" >&2; exit 2 ;;
  esac
done

# In a SLIM project these an_project docs are not copied (relative to an_project/).
# bash-3.2/BSD-safe: a plain case list, no associative arrays.
is_slim_excluded() {
  case "$1" in
    docs/architecture.md|docs/technical.md|docs/deployment.md|docs/styleguide.md|docs/dev-guide.md) return 0 ;;
    *) return 1 ;;
  esac
}

say()  { printf '%s\n' "$*"; }
warn() { printf '%s\n' "$*" >&2; }
die()  { printf 'init.sh: %s\n' "$*" >&2; exit 1; }

# report fragments accumulate here, flushed to an_project/SETUP-REPORT.md at the end
REPORT="$(mktemp -t ki-init-report.XXXXXX)"
trap 'rm -f "$REPORT"' EXIT
log_report() { printf '%s\n' "$*" >>"$REPORT"; }

# ------------------------------------------------------ S0  preflight -----
[ -f "$FW/AGENT-GUIDE.md" ] || die "core looks missing/corrupt ($FW/AGENT-GUIDE.md not found) — re-download the release."
[ -f "$FW/VERSION" ]        || die "core looks missing/corrupt ($FW/VERSION not found) — re-download the release."
[ -d "$SKEL/root" ] && [ -d "$SKEL/an_project" ] || die "core skeleton missing ($SKEL/root or /an_project) — re-download the release."

case "$ROOT" in
  "$HOME"|/) die "refusing to run: project root resolved to '$ROOT'. Put .an_framework inside a real project folder." ;;
esac

FW_VERSION="$(cat "$FW/VERSION")"

# already-initialised guard (a fresh clone of a v2 project already carries everything).
# --update is the one legitimate reason to proceed anyway: the core was swapped for a
# newer release and the skeleton additions (new command stubs, new permissions, new doc
# templates) have to be pulled across. Everything below is additive by construction, so
# an update run cannot clobber project files — see tutorial/update.md.
INITIALISED=0
if [ -d "$ROOT/an_project" ] && [ -f "$ROOT/CLAUDE.md" ] && grep -q '@.an_framework/AGENT-GUIDE.md' "$ROOT/CLAUDE.md" 2>/dev/null; then
  INITIALISED=1
fi
if [ "$INITIALISED" -eq 1 ] && [ "$DO_UPDATE" -eq 0 ]; then
  say "This project already looks initialised (an_project/ exists and CLAUDE.md imports the core)."
  say "Nothing to do. If you cloned a v2 project, you are ready — do NOT re-run init.sh."
  say ""
  say "Updating to a newer framework release? Re-run with --update to pull in new command"
  say "stubs, new permissions and new doc templates. See .an_framework/tutorial/update.md."
  exit 0
fi

# mode: UPDATE beats RETROFIT beats NEW
MODE="NEW"
if [ -f "$ROOT/CLAUDE.md" ] || [ -d "$ROOT/.claude" ] || [ -d "$ROOT/.git" ]; then
  MODE="RETROFIT"
fi
if [ "$INITIALISED" -eq 1 ]; then MODE="UPDATE"; fi

say "ki-dev-framework $FW_VERSION"
say "Project root : $ROOT"
say "Mode         : $MODE"
if [ "$ASSUME_YES" -eq 0 ]; then
  if [ -t 0 ]; then
    printf 'Proceed? [y/N] '
    read -r reply </dev/tty || reply=""
    case "$reply" in y|Y|yes|YES) : ;; *) die "aborted." ;; esac
  else
    die "not a TTY and --yes not given; re-run with --yes to proceed non-interactively."
  fi
fi

# profile: slim vs full — decided HERE because init copies the docs before Claude ever runs.
# On a RE-RUN (framework update) an existing marker already answers this, so honour it
# instead of asking again: a slim project whose user just hits Enter would otherwise fall
# back to "full" and get the heavy overlay docs copied in, while the marker still said slim.
# An explicit --slim/--full still wins — that is the way to deliberately switch profile.
if [ -z "$PROFILE" ] && [ -r "$ROOT/an_project/.framework-profile" ]; then
  read -r PROFILE <"$ROOT/an_project/.framework-profile" || PROFILE=""
  case "$PROFILE" in
    slim|full) PROFILE_FROM_MARKER=1
               say "Profile      : $PROFILE (from an_project/.framework-profile)" ;;
    *)         PROFILE="" ;;   # unreadable or garbage → fall through and ask
  esac
fi
if [ -z "$PROFILE" ]; then
  if [ "$ASSUME_YES" -eq 0 ] && [ -t 0 ]; then
    printf 'Slim project? Only the work backlog + git/guidelines overlays — no Docker, tech-stack, runbook or dev docs. [y/N] '
    read -r sreply </dev/tty || sreply=""
    case "$sreply" in y|Y|yes|YES) PROFILE="slim" ;; *) PROFILE="full" ;; esac
  else
    PROFILE="full"
  fi
fi
[ "${PROFILE_FROM_MARKER:-0}" -eq 1 ] || say "Profile      : $PROFILE"

log_report "# ki-dev-framework — Setup Report"
log_report ""
log_report "- Framework version: **$FW_VERSION**"
log_report "- Mode: **$MODE**"
log_report "- Profile: **$PROFILE**"
log_report ""

# ------------------------------------------------------ S2  preserve ------
# A pre-existing root CLAUDE.md becomes CLAUDE-ARCHIVE.md — the exact name
# /new-project reads to seed project-description.md. Never overwrite an archive.
if [ -f "$ROOT/CLAUDE.md" ] && ! grep -q '@.an_framework/AGENT-GUIDE.md' "$ROOT/CLAUDE.md" 2>/dev/null; then
  if [ -e "$ROOT/CLAUDE-ARCHIVE.md" ]; then
    log_report "## Preserved"
    log_report "- Left existing \`CLAUDE.md\` in place (an existing \`CLAUDE-ARCHIVE.md\` blocked the move). **Review both by hand.**"
    warn "note: CLAUDE.md and CLAUDE-ARCHIVE.md both exist — left CLAUDE.md untouched; review by hand."
  else
    mv "$ROOT/CLAUDE.md" "$ROOT/CLAUDE-ARCHIVE.md"
    log_report "## Preserved"
    log_report "- Moved your old \`CLAUDE.md\` → \`CLAUDE-ARCHIVE.md\` (frozen; /new-project reads it to seed the charter)."
    say "Preserved old CLAUDE.md → CLAUDE-ARCHIVE.md"
  fi
fi

# ------------------------------------------------------ S3  copy skeleton -
# Copy a tree file-by-file, never overwriting. Records copies and skips.
COPIED=0; SKIPPED=0
copy_no_overwrite() {
  # $1 = src dir, $2 = dest dir
  src="$1"; dest="$2"
  [ -d "$src" ] || return 0
  # NUL-safe walk would need find -print0 + read -d ''; skeleton paths are plain ASCII.
  ( cd "$src" && find . -type f ) | while IFS= read -r rel; do
    rel="${rel#./}"
    if [ "$PROFILE" = slim ] && is_slim_excluded "$rel"; then
      printf 'slim-skip %s\n' "$dest/$rel"
    elif [ -e "$dest/$rel" ]; then
      printf 'skip %s\n' "$dest/$rel"
    else
      mkdir -p "$dest/$(dirname "$rel")"
      cp "$src/$rel" "$dest/$rel"
      printf 'copy %s\n' "$dest/$rel"
    fi
  done
}

log_report ""
log_report "## Copied into the project"

# root portion — but .claude/ is merged separately in S6, so exclude it here
CLAUDE_STUB="$SKEL/root/CLAUDE.md"
if [ -e "$ROOT/CLAUDE.md" ]; then
  log_report "- kept existing \`CLAUDE.md\`"
else
  cp "$CLAUDE_STUB" "$ROOT/CLAUDE.md"
  log_report "- \`CLAUDE.md\` (framework stub)"
  COPIED=$((COPIED+1))
fi

# an_project portion — the visible project-data folder
copy_no_overwrite "$SKEL/an_project" "$ROOT/an_project" | while IFS= read -r line; do
  log_report "- ${line% *}: \`${line#* }\`"
done

# profile marker — /new-project reads it to decide slim vs full (project zone, write target)
mkdir -p "$ROOT/an_project"
if [ ! -e "$ROOT/an_project/.framework-profile" ]; then
  printf '%s\n' "$PROFILE" >"$ROOT/an_project/.framework-profile"
  log_report "- wrote \`an_project/.framework-profile\` = **$PROFILE**"
fi

# ------------------------------------------------------ S4  .gitignore ----
SNIP="$SKEL/gitignore.snippet"
GI="$ROOT/.gitignore"
log_report ""
log_report "## .gitignore"
if [ ! -f "$GI" ]; then
  cp "$SNIP" "$GI"
  log_report "- created \`.gitignore\` from the framework snippet"
elif grep -q '^# --- ki-dev-framework' "$GI" 2>/dev/null; then
  log_report "- \`.gitignore\` already carries the framework block — left as is"
else
  # leading newline guard: a file without a trailing newline would otherwise
  # glue the first snippet line onto the last existing rule.
  { printf '\n'; cat "$SNIP"; } >>"$GI"
  log_report "- appended the framework block to your existing \`.gitignore\`"
fi

# ------------------------------------------------------ S5  project name --
if [ -n "$NAME" ] && [ -f "$ROOT/CLAUDE.md" ]; then
  # portable in-place edit: temp file + mv (BSD/GNU sed differ on -i)
  TMP="$(mktemp -t ki-claude.XXXXXX)"
  sed "s/<project name>/$NAME/g" "$ROOT/CLAUDE.md" >"$TMP" && mv "$TMP" "$ROOT/CLAUDE.md"
  log_report ""
  log_report "## Project name"
  log_report "- substituted \`<project name>\` → **$NAME** in \`CLAUDE.md\`"
fi

# ------------------------------------------------------ S6  merge .claude -
log_report ""
log_report "## .claude/"
mkdir -p "$ROOT/.claude/commands"

# --- settings.json: union, never clobber; rewrite the stale deny path ---
FW_SET="$SKEL/root/.claude/settings.json"
PROJ_SET="$ROOT/.claude/settings.json"
if [ ! -f "$PROJ_SET" ]; then
  cp "$FW_SET" "$PROJ_SET"
  log_report "- \`settings.json\` installed from the framework"
else
  merged=0
  if command -v python3 >/dev/null 2>&1; then
    python3 - "$PROJ_SET" "$FW_SET" <<'PY' && merged=1
import json, sys
proj_path, fw_path = sys.argv[1], sys.argv[2]
proj = json.load(open(proj_path)); fw = json.load(open(fw_path))
p = proj.setdefault("permissions", {}); f = fw.get("permissions", {})
allow = p.get("allow", [])
for x in f.get("allow", []):
    if x not in allow: allow.append(x)
p["allow"] = allow
# rewrite the stale deny path, then union with the framework's deny list
deny = ["Edit(/.an_framework/**)" if x == "Edit(/.framework/**)" else x for x in p.get("deny", [])]
for x in f.get("deny", []):
    if x not in deny: deny.append(x)
p["deny"] = deny
json.dump(proj, open(proj_path, "w"), indent=2)
open(proj_path, "a").write("\n")
PY
  fi
  if [ "$merged" -eq 1 ]; then
    log_report "- merged \`settings.json\` (unioned allow/deny, rewrote the deny path to \`.an_framework\`)"
  else
    cp "$FW_SET" "$ROOT/.claude/settings.framework.json"
    log_report "- **Manual merge needed:** python3 unavailable. Framework settings saved as \`.claude/settings.framework.json\`."
    log_report "  Ask Claude: \"Merge every entry from .claude/settings.framework.json into .claude/settings.json — union allow/deny, keep no stale \`Edit(/.framework/**)\` (it must read \`.an_framework\`), then delete settings.framework.json.\""
  fi
fi

# --- command stubs: install missing; flag same-named conflicts, never clobber ---
CONFLICTS=0
for stub in "$SKEL"/root/.claude/commands/*.md; do
  n="$(basename "$stub")"
  tgt="$ROOT/.claude/commands/$n"
  if [ ! -e "$tgt" ]; then
    cp "$stub" "$tgt"
  elif cmp -s "$stub" "$tgt"; then
    : # identical, nothing to do
  else
    mkdir -p "$ROOT/an_project/_pre-framework/claude-commands"
    cp "$tgt" "$ROOT/an_project/_pre-framework/claude-commands/$n"
    CONFLICTS=$((CONFLICTS+1))
    log_report "- **Command conflict:** your \`/.claude/commands/$n\` differs from the framework's. Yours was backed up to \`an_project/_pre-framework/claude-commands/$n\` and left ACTIVE. Decide by hand which to keep."
  fi
done
[ "$CONFLICTS" -eq 0 ] && log_report "- command stubs installed (no conflicts)"

# ------------------------------------------------------ S7  report --------
mkdir -p "$ROOT/an_project"
{
  cat "$REPORT"
  printf '\n## Next steps (manual)\n\n'
  if [ "$MODE" = "UPDATE" ]; then
    printf '1. Review this report — it names every file that was added.\n'
    printf '2. Make the framework commit (printed below). It stays SEPARATE from project work.\n'
    printf '3. Restart Claude from the project root so new slash commands are picked up.\n'
    printf '4. Full walkthrough: `.an_framework/tutorial/update.md`.\n'
  else
    printf '1. Review this report and anything under `an_project/_pre-framework/`.\n'
    printf '2. Set the project name in `CLAUDE.md` (if the `<project name>` placeholder is still there).\n'
    printf '3. Make the setup commit (printed below), then start Claude from the project root.\n'
    printf '4. In Claude, run `/new-project` to fill the charter (a slim project skips the tech-stack step).\n'
  fi
} >"$ROOT/an_project/SETUP-REPORT.md"

# ------------------------------------------------------ S8  print commit --
ARCHIVE_PATH=""
[ -f "$ROOT/CLAUDE-ARCHIVE.md" ] && ARCHIVE_PATH=" CLAUDE-ARCHIVE.md"

say ""
if [ "$MODE" = "UPDATE" ]; then
  say "Update complete. Report written to an_project/SETUP-REPORT.md"
  say ""
  say "Review the changes, then make the framework commit BY HAND (init.sh never commits)."
  say "Keep it separate from project work — /commit refuses staged .an_framework as such:"
  say ""
  say "  git add -- .an_framework .claude an_project"
  say "  git commit -m \"chore(framework): Framework auf $FW_VERSION\""
  say ""
  say "Then restart Claude from the project root so new slash commands show up."
else
  say "Setup complete. Report written to an_project/SETUP-REPORT.md"
  say ""
  say "Review the changes, then make the setup commit BY HAND (init.sh never commits):"
  say ""
  say "  git add -- .an_framework an_project .claude CLAUDE.md .gitignore${ARCHIVE_PATH}"
  say "  git commit -m \"chore(setup): Projektgeruest aus ki-dev-framework $FW_VERSION\""
  say ""
  say "Then start Claude from the project root and run /new-project."
fi
