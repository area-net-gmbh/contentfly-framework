---
id: 000-000-0040
title: Config — eigene Schlüssel eines Projekts zerstören die Antwort
status: done
depends_on: []
---

# Config — eigene Schlüssel eines Projekts zerstören die Antwort

## Context
**Gefunden bei `007-005-0003`** am Bestandsprojekt UFP (Befund F-1).

Ein Projekt setzt in `custom/config.php` seit jeher eigene Schlüssel auf `Classes\Config` —
UFP hat **35** davon (SMTP, OAuth, Community-API, Frontend-URLs). Bis PHP 8.1 war das erlaubt. **Ab
PHP 8.2 ist jede dynamische Property deprecated**, und `Config` erlaubt sie nicht ausdrücklich.

**Die Folge ist kein Log-Rauschen, sondern eine kaputte Antwort:** `config.php` läuft, bevor der
Bootstrap `display_errors` setzt. Ohne `display_errors=Off` in der `php.ini` des Servers — das
offizielle `php:*`-Image hat keine — schreibt PHP die 35 Meldungen als HTML **vor** das JSON.
Gemessen: `GET /api/v2/core/config` antwortete `200` mit `text/html` und 35 `Deprecated`-Blöcken.
Dazu macht es jedes Deprecation-Gate eines Projekts rot.

**Entschieden am 2026-09-15:** `Config` erlaubt eigene Schlüssel wieder, ausdrücklich. Eine
Unterklasse mit 35 Deklarationen je Projekt wäre ein Leitfaden-Schritt, der nichts schützt.

## Acceptance criteria
- [x] `Classes\Config` trägt `#[\AllowDynamicProperties]`, mit Begründung im Code.
- [x] Ein Test setzt einen nicht deklarierten Schlüssel und hält fest, dass keine Deprecation entsteht — und dass `Adapter::getConfig()` ihn zurückgibt.
- [x] Die Vorlage `custom/config.php` zeigt, dass eigene Schlüssel erlaubt sind.
- [x] Am UFP-Probe-Backend (`007-005`) antwortet `/api/v2/core/config` ohne `display_errors=Off` mit `application/json`.
- [x] Volle Suite, PHPStan, Deprecation-Gate grün.

## Verification
Test gegen `E_DEPRECATED` mit eigenem Fehler-Handler. Probe-Container ohne die Probe-`ini` neu
starten und `/api/v2/core/config` abrufen.

## Ergebnis

**`Classes\Config` trägt `#[\AllowDynamicProperties]`**, und die Begründung steht an der Klasse:
warum eine Deprecation hier die Antwort zerstört statt nur das Log zu füllen, und warum eine
Unterklasse je Projekt verworfen ist — sie schützt nichts, denn ein Schlüssel, den ein Projekt selbst
setzt und selbst liest, kann mit keinem des Frameworks kollidieren, und ein Tippfehler in einem
Framework-Schlüssel fiele durch die Deklaration der Projekt-Schlüssel ebenso wenig auf.

**Vorlage:** `custom/config.php` sagt jetzt, dass eigene Schlüssel erlaubt sind, und rät zu einem
Präfix (`CUSTOM_`), mit auskommentiertem Beispiel. `tools/check-template-config.sh` bleibt grün.

**Test** `tests/Unit/Config/ProjectKeysTest.php`: setzt einen nicht deklarierten Schlüssel unter
einem Fehler-Handler für `E_DEPRECATED` — keine Meldung, und `Adapter::getConfig()` liefert den Wert;
dazu, dass das Attribut an der Klasse steht. **Gegenprobe** mit `Config` von master: beide rot.

**Gemessen am UFP-Probe-Backend** (bindet das Framework-Repo direkt ein): Die Probe-Einstellung
`display_errors=Off` entfernt, Container neu gestartet (`display_errors=1`) —
`GET /api/v2/core/config` antwortet `200 application/json` mit sauberem JSON, **0** `Deprecated` in
der Antwort und im Log. Vorher: `text/html` mit 35 Meldungen.

**Verifiziert:** volle Suite `Tests: 563, Assertions: 1814, Skipped: 3`, PHPStan `[OK] No errors`,
Deprecation-Gate 0.

**Nicht in `breaking-changes.md`:** Die Änderung nimmt keinem Projekt etwas weg, sie gibt zurück, was
vor PHP 8.2 selbstverständlich war.
