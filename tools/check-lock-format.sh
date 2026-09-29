#!/bin/sh
#
# Prüft, ob composer.lock von einer zu alten Composer-Version zurückgestuft wurde (000-000-0106).
#
# ── Wogegen ───────────────────────────────────────────────────────────────────────────
#
# Viermal derselbe Handgriff — `000-000-0084`, `0091`, `0098`, `0105`. Jedes Release zieht den
# Lock nach, und jedes Mal schreibt eine ältere Composer-Version dieselben vier Zeilen um:
#
#     "stability-flags": {}      ->  []
#     "platform": {}             ->  []
#     "platform-dev": {}         ->  []
#     "plugin-api-version": "2.9.0"  ->  "2.6.0"
#
# Jedes Mal hat jemand sie von Hand zurückgesetzt. Ein Handgriff, der sich viermal wiederholt,
# ist keine Sorgfaltsfrage mehr, sondern eine fehlende Prüfung.
#
# ── Die Ursache, damit die Meldung sie nennen kann ────────────────────────────────────
#
# Kein Composer-Fehler, sondern ein Versions-Gefälle: Der Lock im Repository ist von einer
# Composer 2.9.x geschrieben (die Pipeline holt jeweils die neueste), eine Entwicklermaschine
# hatte 2.6.6. Wer lokal den Lock anfasst, stuft ihn zurück; wer es nicht merkt, committet das.
#
# ── Warum das mehr ist als Kosmetik ───────────────────────────────────────────────────
#
# Heute sind die Formen funktional gleichwertig, und der Schaden ist Diff-Rauschen. Aber ein
# Lock, den zwei Composer-Versionen abwechselnd umschreiben, macht JEDEN Lock-Diff unlesbar —
# und genau dort will man sehen, ob sich eine Abhängigkeit geändert hat. Bei `0105` standen die
# vier Formatzeilen neben den drei, auf die es ankam.
#
# Aufruf:
#   tools/check-lock-format.sh [datei]
#
# Ohne Argument wird composer.lock geprüft.

set -eu

DATEI="${1:-composer.lock}"
ANZEIGE="${2:-composer.lock}"

# DIE ERWARTETE FORM STEHT AN EINER STELLE. Ändert eine künftige Composer-Version das Format
# bewusst, wird hier eine Zeile angepasst — und nicht an vier verteilten Stellen gesucht.
# Feld:erwartet:falsch, getrennt durch '|', weil die Werte Leerzeichen tragen können.
ERWARTUNGEN='stability-flags:{}:[]|platform:{}:[]|platform-dev:{}:[]'
MIN_PLUGIN_API='2.9.0'

if [ ! -f "$DATEI" ]; then
    echo "✗ $ANZEIGE nicht gefunden." >&2
    exit 1
fi

BEFUNDE=""

# Die drei Objekt-Felder. Geprüft wird die ZUWEISUNG am Zeilenanfang, nicht ein Vorkommen
# irgendwo: "platform" steht auch in den Paketblöcken darüber.
OLD_IFS=$IFS
IFS='|'
for eintrag in $ERWARTUNGEN; do
    IFS=$OLD_IFS
    feld=$(echo "$eintrag" | cut -d: -f1)
    gut=$(echo "$eintrag" | cut -d: -f2)
    schlecht=$(echo "$eintrag" | cut -d: -f3)

    if grep -qE '^    "'"$feld"'": \'"$schlecht"'' "$DATEI" 2>/dev/null \
       || grep -qF '    "'"$feld"'": '"$schlecht" "$DATEI"; then
        ZEILE=$(printf '    "%s": %s' "$feld" "$schlecht")
        BEFUNDE="$BEFUNDE
$(printf '%-44s erwartet: %s' "$ZEILE" "$gut")"
    fi
    IFS='|'
done
IFS=$OLD_IFS

# plugin-api-version: eine zurückgestufte Zahl ist der deutlichste Hinweis auf die Ursache.
GEFUNDENE_API=$(grep -E '^    "plugin-api-version":' "$DATEI" | sed -E 's/.*"plugin-api-version": "([^"]+)".*/\1/')

if [ -n "$GEFUNDENE_API" ] && [ "$GEFUNDENE_API" != "$MIN_PLUGIN_API" ]; then
    KLEINER=$(printf '%s\n%s\n' "$GEFUNDENE_API" "$MIN_PLUGIN_API" | sort -V | head -1)
    if [ "$KLEINER" = "$GEFUNDENE_API" ]; then
        ZEILE=$(printf '    "plugin-api-version": "%s"' "$GEFUNDENE_API")
        BEFUNDE="$BEFUNDE
$(printf '%-44s erwartet: "%s" oder neuer' "$ZEILE" "$MIN_PLUGIN_API")"
    fi
fi

if [ -z "$BEFUNDE" ]; then
    exit 0
fi

cat >&2 <<MELDUNG
✗ $ANZEIGE wurde von einer zu alten Composer-Version zurückgestuft.
$BEFUNDE

  Das ist kein Composer-Fehler, sondern ein Versions-Gefälle: Der Lock im Repository stammt
  von einer neueren Composer-Version als der, die ihn zuletzt angefasst hat. Die Pipeline holt
  jeweils die neueste; eine Entwicklermaschine kann Jahre zurückliegen.

  Ihre Version:
MELDUNG

echo "      $(composer --version --no-ansi 2>/dev/null || echo 'composer nicht im PATH')" >&2

cat >&2 <<'MELDUNG'

  Weg zurück, zwei Möglichkeiten:

      composer self-update           # die eigene Version nachziehen, dann den Lock neu schreiben

  oder die betroffenen Zeilen von Hand auf die erwartete Form setzen. Das haben 000-000-0084,
  0091, 0098 und 0105 getan — es funktioniert, und genau deshalb gibt es diese Prüfung: Damit
  der fünfte Fall auffällt, bevor er in master landet, und nicht erst beim nächsten Lock-Diff.

  Was am Lock ÄNDERN darf: content-hash, version und reference. Alles andere gehört
  nachgesehen, bevor es committet wird.
MELDUNG

exit 1
