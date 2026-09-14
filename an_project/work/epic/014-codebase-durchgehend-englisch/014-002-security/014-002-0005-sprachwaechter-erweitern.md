---
id: 014-002-0005
title: Sprachwächter um die Security-Pfade erweitern
status: review
depends_on: [014-002-0004]
---

# Sprachwächter um die Security-Pfade erweitern

## Context
Der Sprachwächter aus `014-001-0005` prüft bisher Kernel, Routing und die Bootstrap-Dateien.
Diese Story trägt ihre Pfade ein.

## Acceptance criteria
- [x] `lib/contentfly/Classes/Security` und `lib/contentfly/Controller/AuthController.php` stehen in `PATHS` von `tests/Unit/EnglishOnlyTest.php`.
- [x] Alle Ausnahmen, die `014-002` betreffen, sind gestrichen. Die übrigen Ausnahmen nennen weiterhin ihre Story.
- [x] Der Test ist grün. Ein Mutationstest in einer Security-Datei (deutscher Kommentar) macht ihn rot.

## Verification
Unit-Suite grün, eine Mutation rot mit Datei und Zeile, zurückgesetzt, wieder grün. Volle Suite grün.

## Ergebnis

**`lib/contentfly/Classes/Security` und `Controller/AuthController.php` stehen in `PATHS`.** Alle
zehn Ausnahmen, die `014-002` betrafen, sind in den Tasks 0001 bis 0003 schon gestrichen worden.

**Die Erweiterung hat eine Lücke im Detektor gezeigt, und die ist geschlossen.** Er meldete
`anmelden()` und `abgleich`, sah aber sechs deutsche Entity-Methoden nicht:
`istRefreshToken()`, `hashen()`, `getKlartext()`, `ZWECK_REFRESH`, `brauchtNeuenHash()` und
`passwortSperren()`. Ohne Korrektur hätten diese Namen den Wächter unbemerkt passiert, und ihre
Umbenennung in `014-003` hätte keine Ausnahme rot gemacht.
- **Neue Regel `VERB_PREFIXES`:** deutsche Verben vor einem Grossbuchstaben (`ist…`, `wird…`,
  `braucht…`). Die Wortregel sieht sie nicht, weil in `istRefreshToken` keine Wortgrenze liegt.
- **Sieben weitere Wortstämme:** `hashen`, `zweck`, `klartext`, `brauchtneu`, `passwort`,
  `sperren`, `faellt`.
- **Der Selbsttest des Detektors** prüft jetzt zusätzlich `istRefreshToken()` und
  `getKlartext()` als deutsch und `isActive()`/`hasGroup()` als englisch.

**Elf neue Ausnahmen, jede mit der Story, die sie auflöst:** zehn für Entity-Methoden und den
`BaseControllerProvider` (`014-003`), eine für einen Testnamen im Kommentar von
`FieldEncryption` (`014-005`).

**Mutationstest, zweimal einzeln:**
- Ein deutscher Kommentar in `LoginThrottle.php` ist rot: `LoginThrottle.php:112 [word "einen"]`.
- Die gestrichene Ausnahme für `istRefreshToken` in `TokenHandler.php` ist rot: `[verb prefix
  "istRefreshToken"]`. Das belegt die neue Regel am echten Code.

Nach dem Zurücksetzen war der Test wieder grün.

Geprüft: volle Suite `OK (528 tests, 1702 assertions)`. Die drei Assertions mehr sind die neuen
Detektor-Fälle. PHPStan `[OK] No errors`, Deprecation-Gate grün.
