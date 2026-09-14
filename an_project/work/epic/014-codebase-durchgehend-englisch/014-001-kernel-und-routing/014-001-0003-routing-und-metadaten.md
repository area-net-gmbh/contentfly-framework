---
id: 014-001-0003
title: Routing und Metadaten auf Englisch
status: done
depends_on: [014-001-0002]
---

# Routing und Metadaten auf Englisch

## Context
Umfang: `Kernel/Routing/` und `Classes/Metadaten/`.
- `Routensammlung` → `RouteCollector` mit `collection()` statt `sammlung()`.
- `Routeneintrag` → `RouteEntry`.
- `AbsicherungListener` → `RouteSecurityListener`.
- `ControllerResolver`: Meldungen und Kommentare.
- Das Verzeichnis `Metadaten/` → `Metadata/`, `Metadatenleser` → `MetadataReader` mit
  `forClass()` und `forProperty()`.

Die Aufrufer liegen vor allem in den Controller-Providern (`Classes/Controller/Provider/**`) und
im `RouteManager`. Dort werden nur die Aufrufe umgestellt; deren übrige Namen und Kommentare
gehören zu `014-003`.

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

**Vier Klassen umbenannt, eine übersetzt, und alle fünf sind vollständig englisch:**
- `Routensammlung` → `RouteCollector` mit `collection()`
- `Routeneintrag` → `RouteEntry`
- `AbsicherungListener` → `RouteSecurityListener`
- `Metadaten\Metadatenleser` → `Metadata\MetadataReader` mit `forClass()` und `forProperty()`
- `ControllerResolver`: Meldung, Variablen und Kommentare

**Aufrufer:** die fünf Controller-Provider, `Classes/Api.php`, `Types/JoinBidirectionalType.php`,
`Application`, `ControllerProviderInterface`, `custom/app.php` (Kommentar), `rector.php` (Pfad im
Kommentar), `RoutenNamenTest` und `AuthApiTest`. In den Providern sind nur die Aufrufe
umgestellt. Deren übrige Namen und Kommentare gehören zu `014-003`.

**Eine Stelle bewusst angepasst:** Der Kommentar in `RouteCollector` zitiert die PHP-Meldung, an
der der erste Entwurf gescheitert ist. Das Zitat nennt jetzt die englischen Namen. Die Aussage,
dass `RouteCollection::get()` beim Namen sucht und die Provider das HTTP-Verb meinen, bleibt
dieselbe.

Die verbleibenden Treffer für „Metadaten" und „Absicherung" sind deutsche Prosa in Dateien
späterer Stories, keine Namen.

Geprüft: volle Suite `OK (524 tests, 1688 assertions)` wie vorher, PHPStan `[OK] No errors`,
Deprecation-Gate grün, `console list` läuft. Die Suche nach deutschen Wörtern in
`Kernel/Routing/` und `Metadata/` findet nichts.
