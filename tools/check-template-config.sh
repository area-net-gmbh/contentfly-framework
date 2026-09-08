#!/bin/sh
#
# Prüft, ob custom/config.php noch eine Vorlage ist — oder schon Zugangsdaten trägt.
#
# `custom/config.php` liegt versioniert im Repo, weil sie die Vorlage ist, aus der
# `appcms:install` die echte Konfiguration macht. Nach jeder Installation stehen dort also
# Host, Benutzer und Passwort der Datenbank, und `git status` meldet die Datei als geändert.
# Ein einziges `git add -A` genügt, damit sie in der Historie landen.
#
# Zweiter Schaden, unabhängig vom ersten: Eine committete Konfiguration macht die Vorlage für
# den nächsten Checkout unbrauchbar. `bootstrap.php` erkennt an einem gesetzten `DB_HOST`,
# dass das System bereits installiert sei ($app['is_installed']) — der Installer bricht dann
# ab, ohne dass der Grund ersichtlich wäre.
#
# Geprüft wird bewusst NICHT "die Datei ist unverändert": Die Vorlage darf sich
# weiterentwickeln. Geprüft wird, dass die Platzhalter stehen.
#
# Aufruf:
#   tools/check-template-config.sh [datei]
#
# Ohne Argument wird custom/config.php geprüft. Der pre-commit-Hook übergibt stattdessen die
# GESTAGTE Fassung — sonst blockierte er einen Commit, der die Datei gar nicht enthält.

set -eu

DATEI="${1:-custom/config.php}"
ANZEIGE="${2:-custom/config.php}"

# Geprüft werden die ZUWEISUNGEN, nicht blosse Vorkommen der Zeichenkette. Der Unterschied
# zählt: `$SET_DB_HOST` steht auch im Kommentar am Dateikopf, und eine Prüfung, die nur
# irgendwo sucht, hinge daran, wie dieser Kommentar formuliert ist.
FELDER='DB_HOST DB_PORT DB_NAME DB_USER DB_PASS DB_GUID_STRATEGY'

if [ ! -f "$DATEI" ]; then
    echo "✗ $ANZEIGE nicht gefunden." >&2
    exit 1
fi

FEHLEND=""
for feld in $FELDER; do
    if ! grep -qE '\$configDefault->'"$feld"'[[:space:]]*=[[:space:]]*.\$SET_'"$feld" "$DATEI"; then
        FEHLEND="$FEHLEND \$SET_$feld"
    fi
done

if [ -z "$FEHLEND" ]; then
    exit 0
fi

cat >&2 <<MELDUNG
✗ $ANZEIGE trägt keine Platzhalter mehr — die Datei ist installiert.

  Fehlend:$FEHLEND

  Diese Datei ist die VORLAGE, aus der appcms:install die Konfiguration macht. Sie enthält
  jetzt vermutlich Host, Benutzer und Passwort der Datenbank. Committet würde beides
  passieren: Die Zugangsdaten stünden in der Historie, und der nächste Checkout liesse sich
  nicht mehr installieren — bootstrap.php hielte das System für bereits eingerichtet.

  Weg zurück:

      git checkout HEAD -- custom/config.php

  Das HEAD ist wichtig: Ist die Datei bereits gestagt — und genau dann meldet sich der
  pre-commit-Hook —, holt \`git checkout -- <pfad>\` sie aus dem INDEX zurück und schreibt die
  installierte Fassung erneut in den Arbeitsbaum. Es sieht aus wie eine Wiederherstellung und
  ist keine.

  Danach ist lokal neu zu installieren, wenn weitergearbeitet wird; der Befehl steht in
  an_project/docs/runbook.md. In der Pipeline erledigt das tools/ci/prepare-test-environment.sh
  bei jedem Lauf von selbst.
MELDUNG

exit 1
