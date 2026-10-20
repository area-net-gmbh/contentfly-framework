#!/usr/bin/env bash
# PURPOSE: Render the ANNA identity and runtime-specific workflow overview.
set -u

RUNTIME="${1:-}"
ROOT="${CLAUDE_PROJECT_DIR:-$PWD}"
cd "$ROOT" 2>/dev/null || exit 0
VERSION="$(sed -n '1p' .an_framework/VERSION 2>/dev/null || true)"
[ -n "$VERSION" ] || VERSION="unbekannt"

cat <<'BANNER'
------------------------------------------------------------------


    _    _   _ _   _    _
   / \  | \ | | \ | |  / \
  / _ \ |  \| |  \| | / _ \
 / ___ \| |\  | |\  |/ ___ \
/_/   \_\_| \_|_| \_/_/   \_\

BANNER
printf 'ANNA · Version %s\n' "$VERSION"
cat <<'BANNER'

------------------------------------------------------------------

Hallo, ich bin ANNA, deine digitale Entwickler-Kollegin bei AREA-NET.

BANNER

case "$RUNTIME" in
  claude)
    prefix="/"
    suffix=""
    ;;
  codex)
    prefix='$an-'
    suffix=""
    ;;
  *)
    printf 'Runtime fehlt: anna.sh erwartet "claude" oder "codex".\n'
    exit 0
    ;;
esac

printf 'Befehle in %s\n\n' "$( [ "$RUNTIME" = claude ] && echo 'Claude Code' || echo 'Codex' )"
printf '%-22s %s\n' "${prefix}anna${suffix}"         "ANNA und diese Übersicht anzeigen"
printf '%-22s %s\n' "${prefix}board${suffix}"        "Backlog und Projektstatus anzeigen"
printf '%-22s %s\n' "${prefix}briefing${suffix}"     "Kurze Einführung in das Projekt"
printf '%-22s %s\n' "${prefix}doc${suffix}"          "Stakeholder-Doku bauen und öffnen"
printf '%-22s %s\n' "${prefix}implement${suffix}"    "Task, Story oder Epic bearbeiten"
printf '%-22s %s\n' "${prefix}done${suffix}"         "Geprüfte Arbeit abschließen"
printf '%-22s %s\n' "${prefix}commit${suffix}"       "Änderungen regelkonform committen"
printf '%-22s %s\n' "${prefix}new-project${suffix}" "Projektbeschreibung initialisieren"
printf '%-22s %s\n' "${prefix}new-epic${suffix}"     "Epic anlegen"
printf '%-22s %s\n' "${prefix}new-story${suffix}"    "Story anlegen"
printf '%-22s %s\n' "${prefix}new-task${suffix}"     "Task anlegen"
printf '%-22s %s\n' "${prefix}refine${suffix}"       "Artefakt reflektieren und schärfen"
printf '%-22s %s\n' "${prefix}migrate-adrs${suffix}" "Bestehende Inline-ADRs migrieren"

exit 0
