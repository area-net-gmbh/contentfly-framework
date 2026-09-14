---
id: 014-001-0004
title: bootstrap.php, bootstrap-web.php und phpunit.xml.dist auf Englisch
status: done
depends_on: [014-001-0003]
---

# bootstrap.php, bootstrap-web.php und phpunit.xml.dist auf Englisch

## Context
Die beiden Bootstrap-Dateien sind der Ort, an dem ein Leser den Ablauf eines Requests
nachvollzieht. Zusammen haben sie rund 850 Zeilen, der größte Teil davon Kommentare.

**Die Dateien werden hier vollständig englisch**, auch die Abschnitte zur Security. Kommentare,
Meldungen und lokale Variablen (etwa `$cachePoolBauen`) werden in einem Durchgang übersetzt,
statt Absatz für Absatz über mehrere Stories verteilt. Die Security-Container-Schlüssel und
Klassennamen darin (`anmeldeanbieter`, `loginbremse` …) benennt erst `014-002` um, zusammen mit
ihren Klassen.

Dazu kommen `phpunit.xml.dist` (Kommentare) und die JSON-Fehlermeldung „Contentfly ist nicht
installiert …", sofern sie in diesen Dateien steht.

## Acceptance criteria
- [x] In den Dateien des Tasks steht kein deutsches Wort mehr: Bezeichner, Strings und Kommentare.
- [x] Kommentare sind übersetzt, nicht gekürzt. Verweise auf Work-Item-IDs und `an_project/docs` bleiben.
- [x] Alle Aufrufer im Baum sind mitgezogen (`lib`, `custom`, `bin`, `tests`, `tools`, `.gitlab-ci.yml`, `phpstan.neon.dist`, `rector.php`). Eine Suche nach jedem alten Namen findet nichts mehr außer in `an_project/` und `CHANGELOG.md`.
- [x] Verhalten unverändert: volle Suite mit gleicher Test- und Assertion-Zahl wie vorher, PHPStan `[OK]`, Deprecation-Gate 0, `php bin/console.php list` läuft, `custom/config.php` wiederhergestellt.

## Verification
Vor dem Task: Test- und Assertion-Zahl der vollen Suite notieren. Danach: volle Suite gegen
`contentfly-db-0004`, PHPStan mit `--memory-limit=512M`, `tools/ci/deprecations-pruefen.sh`,
`grep -rnw` nach jedem alten Namen, `sh tools/check-template-config.sh`.

## Ergebnis

**`bootstrap.php`, `bootstrap-web.php` und `phpunit.xml.dist` sind in einem Durchgang englisch
geworden**, mit allen Kommentaren, auch in den Security-Abschnitten. Englisch sind jetzt:
- die Startmeldung („Contentfly cannot start …")
- die drei Cache-Treiber-Meldungen (`apc`, `apcu`, `memcached`)
- die lokalen Variablen: `$paketverzeichnis` → `$packageDir`, `$projektKonfiguration` →
  `$customDir`, `$cachePoolBauen` → `$buildCachePool`, `$cachesWaehlen` → `$selectCaches`,
  `$verbindungen` → `$connections` und weitere.

**Bewusst noch nicht umbenannt:** die Security-Klassen und -Schlüssel in `bootstrap.php` und
`VertrauteProxies::anwenden()` in `bootstrap-web.php`. Sie gehören zu `014-002`, zusammen mit
ihren Klassen. Ebenso `EntityManagerFactory::erzeugen()`, das zu `014-003` gehört. Die
Kommentare verweisen auf diese Namen, solange sie so heissen.

**Eine Stelle angepasst:** Der Kommentar zum Statuscode zitierte `new
AccessDeniedHttpException('Zugriff verweigert', null, 401)` aus `SystemControllerProvider`. Die
Meldung ändert sich in `014-003`. Das Zitat lässt den Text deshalb weg, weil die Aussage am
dritten Argument hängt.

Die JSON-Meldung „Contentfly ist nicht installiert" steht nicht in diesen Dateien, sondern in
`BaseControllerProvider`. Sie gehört damit zu `014-003`.

Geprüft: volle Suite `OK (524 tests, 1688 assertions)` wie vorher, PHPStan `[OK] No errors`,
Deprecation-Gate grün, `xmllint` für `phpunit.xml.dist` sauber, `console list` läuft. Die Suche
nach deutschen Wörtern in den drei Dateien findet nichts.
