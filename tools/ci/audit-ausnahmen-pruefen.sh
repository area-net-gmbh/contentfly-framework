#!/bin/sh
#
# Prüft, ob jede Ausnahme aus config.audit.ignore noch gebraucht wird (006-005-0002).
#
# ── Wogegen das schützt ───────────────────────────────────────────────────────────────
#
# `006-005-0001` nimmt fünf CVEs vom Gate aus, weil sie bis Epic 009 unbehebbar sind. Das
# ist richtig — und es ist die Stelle, an der solche Gates sterben.
#
# Epic 009 hebt Symfony auf 7.4. In diesem Moment verschwinden die fünf Meldungen, und die
# Liste nennt fünf Kennungen, die es nicht mehr gibt. Sie ist ab da eine Erlaubnis ins
# Leere, die stillschweigend weiterwirkt: Träfe später eine dieser Kennungen wieder zu,
# wäre sie schon ausgenommen. Dasselbe, wenn ein ausgenommenes Paket entfernt wird.
#
# Eine Ausnahme läuft hier deshalb IN DER SACHE ab, nicht im Kalender: Sie fällt, wenn ihr
# Grund wegfällt. Ein Datum wäre die schwächere Lösung — es greift entweder zu früh oder zu
# spät, und beides hängt an einer Schätzung von heute.
#
# Das Muster ist im Projekt bekannt: 008-005-0002 verhindert dasselbe an anderer Stelle,
# nämlich eine grüne Suite, die nichts geprüft hat. Hier ist es ein grünes Gate, das nichts
# mehr durchlässt, weil es alles ausnimmt.
#
# ── Wie ──────────────────────────────────────────────────────────────────────────────
#
# `composer audit --format=json` trennt selbst zwischen "advisories" (nicht ausgenommen,
# macht das Gate rot) und "ignored-advisories" (ausgenommen, greift also noch). Verglichen
# werden die Kennungen aus config.audit.ignore gegen die zweite Liste:
#
#   in composer.json, aber NICHT in ignored-advisories  -> abgelaufen, dieser Lauf wird rot
#   in advisories, aber nicht ausgenommen               -> faengt bereits tools/ci/audit.sh
#
# Die Liste steht nur in composer.json, nicht zusätzlich hier. Eine Liste, die an zwei
# Stellen gepflegt werden muss, läuft auseinander — derselbe Grund, aus dem 006-004-0001
# die Einordnungsregel nicht nach architecture.md kopiert hat.

set -eu

MINDESTVERSION=2.8.0
VERSION=$(composer --version --no-ansi 2>/dev/null | sed -n 's/^Composer version \([0-9.]*\).*/\1/p')

if [ -z "$VERSION" ] \
   || [ "$(printf '%s\n%s\n' "$MINDESTVERSION" "$VERSION" | sort -V | head -1)" != "$MINDESTVERSION" ]; then
    echo "✗ Composer >= $MINDESTVERSION noetig (gefunden: ${VERSION:-keiner})." >&2
    echo "  Begruendung siehe tools/ci/audit.sh." >&2
    exit 1
fi

echo "→ Ausnahmeliste gegen den Ist-Stand pruefen (Composer $VERSION)"

# Die Rohdaten einmal holen. --abandoned=ignore, damit der Aufruf denselben Blickwinkel hat
# wie das Gate; ob abandoned Pakete gemeldet werden, ist hier ohne Belang.
AUSGABE=$(composer audit --locked --format=json --abandoned=ignore --no-interaction 2>/dev/null || true)

if [ -z "$AUSGABE" ]; then
    echo "✗ composer audit hat nichts geliefert." >&2
    echo "  Ohne Daten kann diese Pruefung nichts sagen — und darf deshalb nicht gruen sein." >&2
    exit 1
fi

# Der Vergleich selbst. PHP statt jq: jq liegt in keinem der CI-Images, PHP per Definition
# in jedem. Der Exit-Code des Skripts kommt aus diesem Aufruf.
printf '%s' "$AUSGABE" | php -r '
$roh = stream_get_contents(STDIN);
$daten = json_decode($roh, true);

if (!is_array($daten)) {
    fwrite(STDERR, "✗ Die Ausgabe von composer audit ist kein gueltiges JSON.\n");
    fwrite(STDERR, "  Ohne Daten darf diese Pruefung nicht gruen sein.\n");
    exit(1);
}

$manifest = json_decode(file_get_contents("composer.json"), true);
$ausgenommen = array_keys($manifest["config"]["audit"]["ignore"] ?? array());

if ($ausgenommen === array()) {
    echo "  Keine Ausnahmen eingetragen — nichts zu pruefen.\n";
    exit(0);
}

/*
 * Wonach verglichen wird: Composer fuehrt jede ausgenommene Meldung unter
 * "ignored-advisories", je Paket eine Liste. Eine Meldung traegt sowohl eine CVE-Kennung
 * als auch eine composer-eigene advisoryId (PKSA-...). Beide werden eingesammelt, weil die
 * Ausnahmeliste jede der beiden Schreibweisen enthalten darf.
 */
$greifen = array();
foreach (($daten["ignored-advisories"] ?? array()) as $liste) {
    foreach ($liste as $meldung) {
        foreach (array("cve", "advisoryId") as $feld) {
            if (!empty($meldung[$feld])) {
                $greifen[$meldung[$feld]] = true;
            }
        }
    }
}

$abgelaufen = array_values(array_diff($ausgenommen, array_keys($greifen)));

if ($abgelaufen !== array()) {
    fwrite(STDERR, "✗ ".count($abgelaufen)." Ausnahme(n) greifen nicht mehr:\n\n");
    foreach ($abgelaufen as $kennung) {
        fwrite(STDERR, "    ".$kennung."\n");
    }
    fwrite(STDERR, "\n  composer audit meldet sie nicht mehr — der Grund fuer die Ausnahme ist\n");
    fwrite(STDERR, "  entfallen. ZU TUN: die Kennung aus config.audit.ignore in composer.json\n");
    fwrite(STDERR, "  streichen.\n\n");
    fwrite(STDERR, "  Eine Ausnahme, die niemand zurueckzieht, ist ein abgeschaltetes Gate:\n");
    fwrite(STDERR, "  Traefe die Kennung spaeter wieder zu, waere sie bereits ausgenommen.\n");
    exit(1);
}

printf("  %d Ausnahme(n) eingetragen, %d greifen noch.\n", count($ausgenommen), count($ausgenommen));
'

echo "✓ Jede Ausnahme wird noch gebraucht."
