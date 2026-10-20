#!/usr/bin/env bash
# PURPOSE: Print one short notice when a newer stable ANNA tag exists.
# Cached for 24 hours and bounded to two seconds; network/auth failures stay silent.
# FAIL OPEN: update discovery must never delay or block normal project work.
set -u

ROOT="${CLAUDE_PROJECT_DIR:-$PWD}"
VERSION_FILE="$ROOT/.an_framework/VERSION"
[ -f "$VERSION_FILE" ] || exit 0

CURRENT="$(sed -n '1{s/^v//;p;}' "$VERSION_FILE" 2>/dev/null)"
[ -n "$CURRENT" ] || exit 0

CACHE="$(git -C "$ROOT" rev-parse --git-path anna-update-cache 2>/dev/null || true)"
[ -n "$CACHE" ] || exit 0
case "$CACHE" in
  /*) ;;
  *) CACHE="$ROOT/$CACHE" ;;
esac

NOW="$(date +%s 2>/dev/null || true)"
LATEST=""
if [ -f "$CACHE" ] && [ -n "$NOW" ]; then
  CACHED_AT="$(sed -n '1p' "$CACHE" 2>/dev/null || true)"
  case "$CACHED_AT" in
    *[!0-9]*|'') ;;
    *)
      if [ $((NOW - CACHED_AT)) -lt 86400 ] 2>/dev/null; then
        LATEST="$(sed -n '2p' "$CACHE" 2>/dev/null || true)"
      fi
      ;;
  esac
fi

if [ -z "$LATEST" ]; then
  REMOTE="${ANNA_UPDATE_REMOTE:-http://gitlab.in.area-net.de/intern/ki-dev-framework.git}"
  TMP="${CACHE}.tmp.$$"
  mkdir -p "$(dirname "$CACHE")" 2>/dev/null || exit 0

  GIT_TERMINAL_PROMPT=0 git -c credential.interactive=never ls-remote \
    --tags --refs "$REMOTE" 'refs/tags/v*' >"$TMP" 2>/dev/null &
  QUERY_PID=$!
  (
    sleep 2
    kill "$QUERY_PID" 2>/dev/null || true
  ) &
  WATCHDOG_PID=$!
  wait "$QUERY_PID" 2>/dev/null || true
  kill "$WATCHDOG_PID" 2>/dev/null || true
  wait "$WATCHDOG_PID" 2>/dev/null || true

  LATEST="$(awk '
    $2 ~ /refs\/tags\/v[0-9]+\.[0-9]+\.[0-9]+$/ {
      tag=$2; sub(/^.*\/v/, "", tag); split(tag, n, ".")
      if (n[1] > a || (n[1] == a && n[2] > b) ||
          (n[1] == a && n[2] == b && n[3] > c)) {
        a=n[1]; b=n[2]; c=n[3]; best=tag
      }
    }
    END { print best }
  ' "$TMP" 2>/dev/null || true)"
  rm -f "$TMP"

  if [ -n "$LATEST" ] && [ -n "$NOW" ]; then
    printf '%s\n%s\n' "$NOW" "$LATEST" >"$CACHE" 2>/dev/null || true
  fi
fi

[ -n "$LATEST" ] || exit 0
NEWER="$(awk -v current="$CURRENT" -v latest="$LATEST" 'BEGIN {
  split(current, c, "."); split(latest, l, ".")
  print (l[1] > c[1] || (l[1] == c[1] && l[2] > c[2]) ||
    (l[1] == c[1] && l[2] == c[2] && l[3] > c[3])) ? "yes" : "no"
}')"

if [ "$NEWER" = "yes" ]; then
  printf 'Update verfügbar: ANNA %s → %s · Anleitung: .an_framework/tutorial/update.md\n' \
    "$CURRENT" "$LATEST"
fi

exit 0
