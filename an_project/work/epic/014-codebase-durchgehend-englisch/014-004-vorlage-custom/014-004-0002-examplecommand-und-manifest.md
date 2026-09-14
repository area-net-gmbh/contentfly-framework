---
id: 014-004-0002
title: ExampleCommand und Root-Manifest auf Englisch
status: todo
depends_on: [014-004-0001]
---

# ExampleCommand und Root-Manifest auf Englisch

## Context
Umfang:
- `custom/Command/ExampleCommand.php`: Beschreibung „Beispiel-Command der Vorlage — tut nichts."
  und Ausgabe „Der Beispiel-Command der Vorlage ist gelaufen."
- Root-`composer.json`: `description` und `extra.hinweis` → `extra.notes`, alles englisch. Der
  `content-hash` in `composer.lock` wird mit `composer update --lock` nachgezogen, ohne dass sich
  eine Paketversion bewegt.
- übrige deutsche Reste in `custom/`, die der Detektor findet. Der Kommentarverweis auf
  `HookReihenfolgeTest` bleibt bis `014-005`.

## Acceptance criteria
- [ ] Die Dateien des Tasks sind vollständig englisch: Namen, Variablen, Schlüssel, Strings und Kommentare.
- [ ] Alle Aufrufer im Baum sind mitgezogen (`lib`, `custom`, `bin`, `tests`, `tools`, `.gitlab-ci.yml`, `phpstan.neon.dist`), soweit nötig auch `dev-guide.md`, wenn ein Test daran abgleicht.
- [ ] Verhalten unverändert: volle Suite mit gleicher Testzahl, PHPStan `[OK]`, Deprecation-Gate grün, `console list` läuft, `custom/config.php` ohne Zugangsdaten.

## Verification
Volle Suite gegen `contentfly-db-0004` mit Vergleich der Zahlen, PHPStan (Result-Cache geleert),
Deprecation-Gate, Nachsuche nach jedem alten Namen, Detektor des Sprachwächters über die Dateien des
Tasks, `sh tools/check-template-config.sh`.
