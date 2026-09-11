#!/bin/sh
#
# Holt Composer in ein php:<version>-cli-Image (000-000-0029).
#
# ── Warum das hier steht und nicht in der .gitlab-ci.yml ──────────────────────────────
#
# Es stand dort, dreimal woertlich gleich — in check:audit, in check:phpstan und in
# .testlauf. Drei Kopien heisst: Wer eine davon verbessert, verbessert eine von dreien. Der
# Kopf der .gitlab-ci.yml sagt den Grund schon fuer die anderen Schritte: Eine
# Pipeline-Definition, deren Schritte man nur in der Pipeline ausprobieren kann, ist beim
# Suchen eines Fehlers nutzlos.
#
# ── Der stille Fehlschlag, der hier mit erledigt wird ─────────────────────────────────
#
# Die alte erste Zeile war:
#
#     php -r "copy('https://getcomposer.org/installer','/tmp/composer-setup.php');"
#
# `copy()` gibt im Fehlerfall `false` zurueck — und `php -r` beendet trotzdem mit 0. Ein
# Netzwerkfehler lief also durch, und erst der naechste Schritt scheiterte: an einer Datei,
# die es nicht gibt oder die eine HTML-Fehlerseite enthaelt. Die Meldung handelte dann von
# PHP-Syntax und nicht von einem Download. Das ist derselbe Befund wie der, der diesen Task
# ausgeloest hat, nur eine Zeile frueher: Ein Fehler, der sich als etwas anderes ausgibt.
#
# Der zweite Schritt trug `--quiet`, womit auch er im Fehlerfall nichts sagte. Beide laufen
# jetzt ueber `schritt` — still, solange sie gelingen.
#
# NICHT GELOEST: Der Installer wird ohne Pruefsumme ausgefuehrt. Das war vorher so und
# bleibt es; es zu aendern heisst, sich fuer eine Bezugsquelle der Pruefsumme zu
# entscheiden, und das ist eine eigene Frage — nicht eine, die man nebenbei in einem Task
# ueber Fehlermeldungen beantwortet.

set -eu

. "$(dirname "$0")/schritt.sh"

schritt "Composer-Installer holen" php -r '
    $quelle = "https://getcomposer.org/installer";
    $ziel   = "/tmp/composer-setup.php";

    $roh = @file_get_contents($quelle);

    if ($roh === false) {
        $fehler = error_get_last();
        fwrite(STDERR, "Der Installer liess sich nicht laden: " . $quelle . "\n");
        fwrite(STDERR, ($fehler["message"] ?? "kein Grund von PHP gemeldet") . "\n");
        exit(1);
    }

    if (strpos($roh, "<?php") !== 0) {
        fwrite(STDERR, "Was geladen wurde, ist kein PHP-Installer — die ersten 200 Zeichen:\n");
        fwrite(STDERR, substr($roh, 0, 200) . "\n");
        exit(1);
    }

    if (file_put_contents($ziel, $roh) === false) {
        fwrite(STDERR, "Der Installer liess sich nicht nach " . $ziel . " schreiben.\n");
        exit(1);
    }
'

schritt "Composer einrichten" \
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer --quiet

rm -f /tmp/composer-setup.php

echo "✓ $(composer --version --no-ansi)"
