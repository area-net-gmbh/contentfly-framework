#!/bin/sh
#
# Das „0 Deprecations"-Gate, Laufzeit-Hälfte (006-005-0003).
#
# an_project/docs/tech-stack.md macht es zur Pflicht: Der spätere Sprung auf Symfony 8.4
# LTS ist nur dann ein reiner Constraint-Bump, wenn keine in 8.0 entfernten APIs benutzt
# werden — "von Tag 1 an, nicht erst vor dem Upgrade".
#
# ── Die Quelle ────────────────────────────────────────────────────────────────────────
#
# tools/ci/prepare-test-environment.sh startet den Testserver mit log_errors=On und
# display_errors=Off. Beides ist Voraussetzung, und beides aus einem eigenen Grund:
#
#   display_errors=Off  sonst landen Deprecations im ANTWORTSTROM und verfaelschen
#                       Statuscodes — beim ersten CI-Lauf sind daran sechs Tests
#                       gescheitert, die lokal gruen waren (008-005-0001).
#   log_errors=On       sonst verschwinden sie spurlos, und dieses Gate liest ins Leere.
#
# Gelesen wird die Datei, in die der Testserver schreibt: CONTENTFLY_CI_LOG, sonst
# /tmp/testserver.log — derselbe Vorgabewert wie im Vorbereitungsskript.
#
# ACHTUNG, DIE FALLE, IN DIE ICH SELBST GETRETEN BIN: Die Deprecations stehen in DIESER
# Datei, nicht im Job-Log auf stdout. Wer das Job-Log durchsucht, findet nur, was PHP-CLI
# gemeldet hat (in 000-000-0021 waren das zwei Zeilen) — nicht die 148 des Testservers.
#
# ── Blockierend, mit Ausnahmeliste ────────────────────────────────────────────────────
#
# Der Ist-Stand ist nicht null: Auf PHP 8.3 protokolliert der Testserver 148 Zeilen, 21
# eindeutige Meldungen, VIER Paare aus Datei und Meldung. Ein Gate, das dagegen ohne
# Ausnahmen blockiert, ist ab dem ersten Lauf rot — und wird abgeschaltet statt
# abgearbeitet.
#
# Also dasselbe Muster wie beim Audit-Gate (006-005-0001): Die bekannten Meldungen stehen
# namentlich in tools/ci/deprecations-ausnahmen.txt, jede mit Begruendung und aufloesendem
# Ticket. Eine FUENFTE Meldung macht den Lauf sofort rot, und eine Ausnahme, die nicht mehr
# greift, ebenfalls.
#
# ── Warum das Skript nicht zwischen 8.3 und 8.4 unterscheidet ─────────────────────────
#
# Es prueft immer dasselbe. Der Unterschied zwischen "blockiert" und "nur Fruehwarnung"
# steht dort, wo er hingehoert: in .gitlab-ci.yml. Der Job test:php8.4 traegt
# allow_failure: true, der Job test:php8.3 nicht.
#
# Eine Fallunterscheidung nach PHP-Version im Skript waere dieselbe Aussage an einer
# zweiten Stelle — und die zweite laeuft irgendwann von der ersten weg.
#
# ── Ein leeres Log ist kein Beweis ────────────────────────────────────────────────────
#
# Faende dieses Skript die Datei nicht, meldete es null Deprecations und waere gruen. Das
# ist derselbe stille Durchwinker, gegen den 008-005-0002 den Umgebungswaechter gebaut
# hat. Fehlt die Datei oder ist sie nicht lesbar, bricht dieses Skript deshalb ab.

set -eu

LOG="${CONTENTFLY_CI_LOG:-/tmp/testserver.log}"
AUSNAHMEN="$(dirname "$0")/deprecations-ausnahmen.txt"

if [ ! -f "$LOG" ]; then
    echo "✗ Serverlog nicht gefunden: $LOG" >&2
    echo "  Ohne Quelle kann dieses Gate nichts sagen — und darf deshalb nicht gruen sein." >&2
    echo "  Lief tools/ci/prepare-test-environment.sh, und zeigt CONTENTFLY_CI_LOG dorthin?" >&2
    exit 1
fi

if [ ! -r "$LOG" ]; then
    echo "✗ Serverlog nicht lesbar: $LOG" >&2
    exit 1
fi

if [ ! -r "$AUSNAHMEN" ]; then
    echo "✗ Ausnahmeliste nicht lesbar: $AUSNAHMEN" >&2
    exit 1
fi

echo "→ Deprecations im Serverlog ($LOG)"

# PHP statt awk/grep-Kaskade: Der Vergleich braucht Normalisierung (Zeilennummer weg,
# Pfadpraefix weg) und zwei Richtungen. PHP liegt in jedem CI-Image, jq nicht.
php -d error_reporting=E_ALL -r '
$log       = $argv[1];
$ausnahmen = $argv[2];

/*
 * Normalisieren: Aus
 *   [Datum] PHP Deprecated:  strtolower(): Passing null ... in /src/lib/x.php on line 42
 * wird
 *   lib/x.php  +  strtolower(): Passing null ...
 *
 * Die Zeilennummer faellt weg — sonst braeche die Ausnahmeliste bei jeder Codeaenderung
 * oberhalb der Fundstelle. Der Pfadpraefix faellt weg, damit dieselbe Liste lokal und im
 * Container passt (dort liegt das Projekt unter /src, hier woanders).
 */
$gefunden = array();
$zeilen   = 0;

foreach (file($log, FILE_IGNORE_NEW_LINES) as $zeile) {
    if (strpos($zeile, "Deprecated:") === false) {
        continue;
    }
    $zeilen++;

    if (!preg_match("#Deprecated:\s*(.+?)\s+in\s+(\S+)\s+on line\s+\d+#", $zeile, $t)) {
        // Eine Deprecation ohne Dateiangabe laesst sich nicht zuordnen — sie zaehlt als
        // unbekannt, damit sie nicht stillschweigend durchfaellt.
        $gefunden["?|".trim($zeile)] = true;
        continue;
    }

    $datei = preg_replace("#^.*?((?:lib|custom|bin|tests|tools|vendor)/.*)$#", "$1", $t[2]);
    $gefunden[$datei."|".$t[1]] = true;
}

$regeln = array();
foreach (file($ausnahmen, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $zeile) {
    $zeile = trim($zeile);
    if ($zeile === "" || $zeile[0] === "#") {
        continue;
    }
    $teile = explode("|", $zeile, 3);
    if (count($teile) < 2) {
        fwrite(STDERR, "✗ Unbrauchbare Zeile in der Ausnahmeliste: ".$zeile."\n");
        exit(1);
    }
    $regeln[] = array("datei" => trim($teile[0]), "meldung" => trim($teile[1]), "traf" => false);
}

$unbekannt = array();
foreach (array_keys($gefunden) as $schluessel) {
    list($datei, $meldung) = array_pad(explode("|", $schluessel, 2), 2, "");
    $gedeckt = false;
    foreach ($regeln as $i => $regel) {
        if ($regel["datei"] === $datei && strpos($meldung, $regel["meldung"]) === 0) {
            $regeln[$i]["traf"] = true;
            $gedeckt = true;
        }
    }
    if (!$gedeckt) {
        $unbekannt[] = $schluessel;
    }
}

$abgelaufen = array();
foreach ($regeln as $regel) {
    if (!$regel["traf"]) {
        $abgelaufen[] = $regel["datei"]." | ".$regel["meldung"];
    }
}

printf("  %d protokollierte Zeile(n), %d Paar(e) aus Datei und Meldung, %d davon ausgenommen.\n",
    $zeilen, count($gefunden), count($gefunden) - count($unbekannt));

$fehler = false;

if ($unbekannt !== array()) {
    $fehler = true;
    fwrite(STDERR, "\n✗ ".count($unbekannt)." Deprecation(s) ohne Ausnahme:\n\n");
    foreach ($unbekannt as $eintrag) {
        fwrite(STDERR, "    ".str_replace("|", "\n      ", $eintrag)."\n\n");
    }
    fwrite(STDERR, "  ZU TUN: beheben. Geht das nicht, eine Zeile in\n");
    fwrite(STDERR, "  tools/ci/deprecations-ausnahmen.txt eintragen — mit Begruendung und dem\n");
    fwrite(STDERR, "  Ticket, das sie aufloest. Ohne benanntes Ticket ist es keine Ausnahme.\n");
}

if ($abgelaufen !== array()) {
    $fehler = true;
    fwrite(STDERR, "\n✗ ".count($abgelaufen)." Ausnahme(n) greifen nicht mehr:\n\n");
    foreach ($abgelaufen as $eintrag) {
        fwrite(STDERR, "    ".$eintrag."\n");
    }
    fwrite(STDERR, "\n  Die Meldung tritt nicht mehr auf — der Grund fuer die Ausnahme ist\n");
    fwrite(STDERR, "  entfallen. ZU TUN: die Zeile aus tools/ci/deprecations-ausnahmen.txt\n");
    fwrite(STDERR, "  streichen. Eine Erlaubnis ins Leere greift spaeter stillschweigend wieder.\n");
}

exit($fehler ? 1 : 0);
' "$LOG" "$AUSNAHMEN"

echo "✓ Keine unausgenommene Deprecation, und jede Ausnahme wird noch gebraucht."
