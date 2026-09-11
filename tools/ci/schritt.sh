#!/bin/sh
#
# Ein CI-Schritt, der im Fehlerfall sagt, woran er gescheitert ist (000-000-0029).
#
# WIRD GESOURCED, NICHT AUSGEFUEHRT:
#
#     . "$(dirname "$0")/schritt.sh"
#     schritt "gd konfigurieren" docker-php-ext-configure gd --with-freetype
#
# ── Wogegen das schuetzt ──────────────────────────────────────────────────────────────
#
# Gefunden bei 013-005-0004: Der PHP-8.4-Lauf brach mit Exit 2 und NULL ZEILEN AUSGABE ab.
# Die Ursache war ein echter Fehler — `composer install` verlangte ext-ldap, das im Image
# fehlte — und sie stand woertlich in der Composer-Ausgabe. Nur sah sie niemand, weil der
# Schritt seine Ausgabe nach /dev/null schob und `set -eu` beim ersten Fehlschlag beendet.
#
# Sichtbar wurde der Fehler erst, als die Schritte von Hand einzeln und ohne Umleitung
# gefahren wurden. Das kostete mehrere Anlaeufe und die Vermutung, Docker selbst sei kaputt.
#
# ── Warum die Umleitung trotzdem bleibt ───────────────────────────────────────────────
#
# Die naheliegende Abhilfe waere, nichts mehr umzuleiten. Sie ist falsch: `apt-get` und
# `docker-php-ext-install` erzeugen zusammen mehrere hundert Zeilen Fortschritt, und ein
# Log, das niemand liest, verdeckt einen Fehler genauso zuverlaessig wie ein leeres. Die
# Ausgabe bleibt also weg — bis sie gebraucht wird.
#
# ── Warum eine Funktion und kein trap ─────────────────────────────────────────────────
#
# Ein `trap ... EXIT` kaeme ohne Umbau jeder Aufrufstelle aus, muesste aber trotzdem
# wissen, WELCHER Schritt gerade laeuft und WOHIN er geschrieben hat — also dieselbe
# Buchfuehrung, nur an zwei Stellen verteilt statt an einer. Die Funktion haelt den Schritt
# und sein Log zusammen, und die Aufrufstelle liest sich als das, was sie ist.

# Wie viele Zeilen im Fehlerfall gezeigt werden. Der Grund liegt am Ende: Ein Build bricht
# dort ab, wo er scheitert, und die Meldung steht in den letzten Zeilen, nicht in den ersten.
CONTENTFLY_CI_LOGZEILEN="${CONTENTFLY_CI_LOGZEILEN:-40}"

# Zaehlt die Schritte durch, damit zwei Logs im selben Lauf nicht dieselbe Datei sind.
_schritt_nummer=0

schritt() {
    _beschreibung="$1"
    shift

    _schritt_nummer=$((_schritt_nummer + 1))
    _log="${TMPDIR:-/tmp}/contentfly-ci-$$-${_schritt_nummer}.log"

    echo "→ $_beschreibung"

    # `|| _code=$?` statt `if`: Unter `set -e` beendet ein nacktes Kommando mit Fehlschlag
    # sofort das Skript — und zwar bevor diese Funktion etwas ausgeben koennte.
    _code=0
    "$@" > "$_log" 2>&1 || _code=$?

    if [ "$_code" -eq 0 ]; then
        rm -f "$_log"
        return 0
    fi

    {
        echo ""
        echo "✗ Fehlgeschlagen: $_beschreibung"
        echo "  Befehl:    $*"
        echo "  Exit-Code: $_code"
        echo ""
        echo "  Die letzten $CONTENTFLY_CI_LOGZEILEN Zeilen seiner Ausgabe:"
        echo "  ---------------------------------------------------------"
        tail -n "$CONTENTFLY_CI_LOGZEILEN" "$_log" | sed 's/^/  | /'
        echo "  ---------------------------------------------------------"
        echo ""
        echo "  Vollstaendig steht sie in $_log. In der Pipeline verschwindet die Datei"
        echo "  mit dem Job — was hier zu sehen ist, ist alles, was bleibt."
    } >&2

    exit "$_code"
}
