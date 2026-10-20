#!/usr/bin/env bash
# PURPOSE: SessionStart hook — prints the ANNA banner and a compact orientation so a fresh session (especially
# after a reset or compaction) does not need a full board or briefing to find its state.
# Shipped in the read-only core; wired up by the active runtime adapter.
#
# FAIL OPEN: every failure path exits 0 with no output. A broken hook must never block work.
set -u

ROOT="${CLAUDE_PROJECT_DIR:-$PWD}"
cd "$ROOT" 2>/dev/null || exit 0
[ -d an_project/work ] || exit 0        # not a framework project — say nothing at all

render_output() {
{
  cat <<'BANNER'
------------------------------------------------------------------


    _    _   _ _   _    _
   / \  | \ | | \ | |  / \
  / _ \ |  \| |  \| | / _ \
 / ___ \| |\  | |\  |/ ___ \
/_/   \_\_| \_|_| \_/_/   \_\


------------------------------------------------------------------


BANNER
  version="$(sed -n '1p' "$ROOT/.an_framework/VERSION" 2>/dev/null || true)"
  [ -n "$version" ] && echo "ANNA · Version $version"
  echo ""
  echo "Hallo, ich bin ANNA, deine digitale Entwickler-Kollegin bei AREA-NET."
  echo "Ich begleite dich bei Planung, Umsetzung und Dokumentation im Projekt."
  echo ""
  echo "Neu hier? Starte mit /briefing (Claude) oder \$an-briefing (Codex)."
  echo ""
  echo ""
  echo "------------------------------------------------------------------"
  echo ""
  echo ""
  echo "Projektstand"
  bash "$ROOT/.an_framework/hooks/check-update.sh" 2>/dev/null || true

  branch="$(git symbolic-ref --short -q HEAD 2>/dev/null || true)"
  if [ -n "$branch" ]; then
    if [ -n "$(git status --porcelain 2>/dev/null || true)" ]; then
      echo "- Branch \`$branch\` · Worktree hat uncommittete Änderungen"
    else
      echo "- Branch \`$branch\` · Worktree sauber"
    fi
  fi

  n=0
  for f in $(grep -rl -e '^status: in-progress' -e '^status: review' an_project/work 2>/dev/null | sort); do
    [ "$n" -ge 5 ] && break
    id="$(sed -n 's/^id: *//p' "$f" 2>/dev/null | head -1)"
    [ -n "$id" ] || continue
    title="$(sed -n 's/^title: *//p' "$f" 2>/dev/null | head -1)"
    status="$(sed -n 's/^status: *//p' "$f" 2>/dev/null | head -1)"
    echo "- $status: $id — $title"
    n=$((n + 1))
  done
  [ "$n" -eq 0 ] && echo "- kein Item in in-progress oder review"

  if [ -f an_project/CHANGELOG.md ]; then
    # Nur Bullets nach einer ECHTEN Datums-Überschrift (^## 2026-…). Die Vorlage trägt im
    # Kopfkommentar ein `## YYYY-MM-DD` samt Beispiel-Bullets — die sähen sonst wie Einträge aus.
    latest="$(awk '/^## [0-9]/{seen=1} seen && /^- /{print "  " $0; c++} c>=3{exit}' an_project/CHANGELOG.md 2>/dev/null || true)"
    if [ -n "$latest" ]; then
      echo "- zuletzt im CHANGELOG:"
      echo "$latest"
    fi
  fi

  # Stakeholder-Doku: nur melden, wenn sie wirklich hinterherlaeuft oder noch leer ist.
  # Ein Hook darf Projektdaten nur lesen — gepflegt wird sie im doc-Workflow.
  if [ -d an_project/stakeholder ]; then
    doc_commit="$(git log -1 --format=%H -- an_project/stakeholder 2>/dev/null || true)"
    drift=0
    if [ -n "$doc_commit" ]; then
      drift="$(git rev-list --count "$doc_commit"..HEAD 2>/dev/null || true)"
    fi
    offen="$(grep -l '^status: offen' an_project/stakeholder/*.md 2>/dev/null | wc -l | tr -d ' ')"
    if [ "${drift:-0}" -ge 10 ] 2>/dev/null; then
      echo "- Stakeholder-Doku: ${drift} Commits seit der letzten Aktualisierung · doc-Workflow"
    elif [ "${offen:-0}" -ge 1 ] 2>/dev/null; then
      echo "- Stakeholder-Doku: ${offen} Kapitel noch offen · doc-Workflow"
    fi
  fi

  echo "- Vollbild: board-Workflow · weiterarbeiten: implement <id>"
} 2>/dev/null
}

if [ "${ANNA_HOOK_OUTPUT:-plain}" = "json" ]; then
  # Claude Code only renders SessionStart output when returned as systemMessage JSON.
  # additionalContext preserves the orientation for Claude itself. Escape without jq/python.
  render_output | head -c 2000 | awk '
    {
      gsub(/\\/, "\\\\", $0)
      gsub(/\"/, "\\\"", $0)
      gsub(/\t/, "\\t", $0)
      gsub(/\r/, "\\r", $0)
      text = text $0 "\\n"
    }
    END {
      printf "{\"systemMessage\":\"%s\",", text
      printf "\"hookSpecificOutput\":{\"hookEventName\":\"SessionStart\","
      printf "\"additionalContext\":\"%s\"}}\n", text
    }
  '
else
  render_output | head -c 2000
fi

exit 0
