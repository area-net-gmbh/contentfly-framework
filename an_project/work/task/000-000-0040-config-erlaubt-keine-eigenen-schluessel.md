---
id: 000-000-0040
title: Config — eigene Schlüssel eines Projekts zerstören die Antwort
status: todo
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
- [ ] `Classes\Config` trägt `#[\AllowDynamicProperties]`, mit Begründung im Code.
- [ ] Ein Test setzt einen nicht deklarierten Schlüssel und hält fest, dass keine Deprecation entsteht — und dass `Adapter::getConfig()` ihn zurückgibt.
- [ ] Die Vorlage `custom/config.php` zeigt, dass eigene Schlüssel erlaubt sind.
- [ ] Am UFP-Probe-Backend (`007-005`) antwortet `/api/v2/core/config` ohne `display_errors=Off` mit `application/json`.
- [ ] Volle Suite, PHPStan, Deprecation-Gate grün.

## Verification
Test gegen `E_DEPRECATED` mit eigenem Fehler-Handler. Probe-Container ohne die Probe-`ini` neu
starten und `/api/v2/core/config` abrufen.
