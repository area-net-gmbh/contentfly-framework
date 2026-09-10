#!/bin/sh
#
# Das Sicherheits-Gate: prüft den committeten Lock gegen die Advisory-Datenbank.
#
# Läuft in der Pipeline (006-005-0001) und beim lokalen Nachspielen mit demselben Aufruf —
# dieselbe Begründung wie bei install-php-extensions.sh und prepare-test-environment.sh:
# Eine Pipeline-Definition, deren Schritte man nur in der Pipeline ausprobieren kann, ist
# beim Suchen eines Fehlers nutzlos.
#
# ── Warum --locked ────────────────────────────────────────────────────────────────────
#
# Geprüft wird der LOCK, nicht ein installiertes vendor/. Das hat zwei Vorteile: Der Job
# braucht kein `composer install` (und läuft damit auch dann, wenn der Testlauf an etwas
# anderem scheitert — ein Sicherheitsbefund soll nicht davon abhängen, ob die Suite grün
# ist), und er prüft genau das, was auch deployt wird.
#
# ── Warum --abandoned=fail ────────────────────────────────────────────────────────────
#
# SEIT 010-003-0003, UND DER SCHALTER IST EINGELÖST, NICHT VERLÄNGERT WORDEN.
#
# Hier stand `ignore`, mit dem Vermerk „auf fail umstellen, sobald Epic 010 durch ist".
# Der Weg dahin, je Paket:
#
#   silex/silex, knplabs/console-service-provider, symfony/debug   Epic 009
#   doctrine/annotations                                           010-001-0005
#   doctrine/cache                                                 010-003-0002, mit ORM 3
#
# Fünf Pakete, null übrig. `composer audit --locked` meldet weder ein Advisory noch ein
# abandoned Paket — die Ausnahmeliste in composer.json steht auf {}.
#
# WAS `fail` BEDEUTET: Ein abandoned Paket macht den Lauf rot. Das ist wieder eine Aussage
# statt einer Beschreibung des Altbestands — genau die Eigenschaft, die `ignore` gekostet
# hat. Wer ein solches Paket aufnehmen muss, trägt es einzeln unter config.audit.ignore ein,
# mit Begründung und dem Ticket, das es auflöst.
#
# ── Die Ausnahmeliste steht in composer.json ──────────────────────────────────────────
#
# `composer audit` hat KEINEN --ignore-Schalter für einzelne Advisories, auch nicht in
# 2.10. Die Ausnahmen leben unter config.audit.ignore in composer.json, je Kennung mit
# einer Begründung, die Composer in der Ausgabe als "Ignore reason" mit ausgibt.
#
# Das ist die bessere Stelle als ein Schalter hier: Sie gilt auch für den Entwickler, der
# `composer audit` von Hand aufruft, und die Begründung steht neben der Kennung statt in
# einem Skript, das niemand liest.
#
# Einzeln nach CVE-Kennung, NIE paketweise: symfony/http-foundation als Ganzes
# auszunehmen hiesse, auch jede künftige Meldung dieses Pakets zu verschlucken.

set -eu

# ── Composer muss --abandoned kennen ──────────────────────────────────────────────────
#
# Nachgemessen über mehrere Fassungen, statt aus dem Changelog geschlossen:
#
#   2.6.6   config.audit.ignore wird BEREITS beachtet (advisories: 0), aber
#           `--abandoned` gibt es nicht: "The --abandoned option does not exist." -> Exit 1
#   2.7.0   dieselbe Absage
#   2.7.7   dieselbe Absage
#   2.8.0   --abandoned vorhanden, Lauf endet mit Exit 0
#
# Die Untergrenze ist also 2.8.0, und sie haengt an --abandoned, NICHT an der
# Ausnahmeliste. (Eine frühere Fassung dieses Kommentars behauptete das Gegenteil und
# setzte 2.7.0 an — beides falsch, korrigiert mit 006-005-0002.)
#
# Geprüft wird eine Untergrenze, nicht eine feste Version: Die Pipeline installiert
# jeweils den aktuellen Composer, und das soll so bleiben — ein Sicherheitswerkzeug
# einzufrieren wäre die falsche Sparsamkeit.

MINDESTVERSION=2.8.0
VERSION=$(composer --version --no-ansi 2>/dev/null | sed -n 's/^Composer version \([0-9.]*\).*/\1/p')

if [ -z "$VERSION" ]; then
    echo "✗ Composer-Version nicht ermittelbar. Ist composer im PATH?" >&2
    exit 1
fi

if [ "$(printf '%s\n%s\n' "$MINDESTVERSION" "$VERSION" | sort -V | head -1)" != "$MINDESTVERSION" ]; then
    echo "✗ Composer $VERSION ist zu alt für dieses Gate (nötig: >= $MINDESTVERSION)." >&2
    echo "  Der Schalter --abandoned existiert dort nicht; der Aufruf unten schlaegt fehl," >&2
    echo "  ohne dass die Meldung den Grund nennt." >&2
    exit 1
fi

echo "→ composer audit --locked (Composer $VERSION)"
composer audit --locked --abandoned=fail --no-interaction

echo "✓ Keine unausgenommene Sicherheitsmeldung im Lock."
