---
id: 014-002-0005
title: Sprachwächter um die Security-Pfade erweitern
status: todo
depends_on: [014-002-0004]
---

# Sprachwächter um die Security-Pfade erweitern

## Context
Der Sprachwächter aus `014-001-0005` prüft bisher Kernel, Routing und die Bootstrap-Dateien.
Diese Story trägt ihre Pfade ein.

## Acceptance criteria
- [ ] `lib/contentfly/Classes/Security` und `lib/contentfly/Controller/AuthController.php` stehen in `PATHS` von `tests/Unit/EnglishOnlyTest.php`.
- [ ] Alle Ausnahmen, die `014-002` betreffen, sind gestrichen. Die übrigen Ausnahmen nennen weiterhin ihre Story.
- [ ] Der Test ist grün. Ein Mutationstest in einer Security-Datei (deutscher Kommentar) macht ihn rot.

## Verification
Unit-Suite grün, eine Mutation rot mit Datei und Zeile, zurückgesetzt, wieder grün. Volle Suite grün.
