---
id: 014-001-0004
title: bootstrap.php, bootstrap-web.php und phpunit.xml.dist auf Englisch
status: todo
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
- [ ] In den Dateien des Tasks steht kein deutsches Wort mehr: Bezeichner, Strings und Kommentare.
- [ ] Kommentare sind übersetzt, nicht gekürzt. Verweise auf Work-Item-IDs und `an_project/docs` bleiben.
- [ ] Alle Aufrufer im Baum sind mitgezogen (`lib`, `custom`, `bin`, `tests`, `tools`, `.gitlab-ci.yml`, `phpstan.neon.dist`, `rector.php`). Eine Suche nach jedem alten Namen findet nichts mehr außer in `an_project/` und `CHANGELOG.md`.
- [ ] Verhalten unverändert: volle Suite mit gleicher Test- und Assertion-Zahl wie vorher, PHPStan `[OK]`, Deprecation-Gate 0, `php bin/console.php list` läuft, `custom/config.php` wiederhergestellt.

## Verification
Vor dem Task: Test- und Assertion-Zahl der vollen Suite notieren. Danach: volle Suite gegen
`contentfly-db-0004`, PHPStan mit `--memory-limit=512M`, `tools/ci/deprecations-pruefen.sh`,
`grep -rnw` nach jedem alten Namen, `sh tools/check-template-config.sh`.
