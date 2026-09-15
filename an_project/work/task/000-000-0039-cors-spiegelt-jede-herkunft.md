---
id: 000-000-0039
title: CORS — jede Herkunft wird mit Credentials zurückgespiegelt
status: todo
depends_on: []
---

# CORS — jede Herkunft wird mit Credentials zurückgespiegelt

## Context
**Gefunden bei `007-005-0001`** am Bestandsprojekt UFP, das die Herkunft in seiner Kopie aus der
Konfiguration setzt. Im neuen Framework ist sie offen.

**Gemessen an einer frischen Installation dieses Repos (2026-09-15):**
`GET /api/config` mit `Origin: https://evil.example` antwortet mit

```
Access-Control-Allow-Origin: https://evil.example
Access-Control-Allow-Credentials: true
```

**Warum das zählt:** Der `after`-Hook in `bootstrap-web.php` setzt `Allow-Origin` auf den `Origin`
jeder Anfrage, und `APP_ALLOW_CREDENTIALS_SDK` steht auf `true`. Damit darf jede fremde Seite im
Browser eines angemeldeten Benutzers Anfragen mit dessen Credentials stellen und die Antwort lesen.
`Config::$APP_ALLOW_ORIGIN` ist deklariert und wird **nirgends gelesen**.

**Betrifft den Prüfstand der IT-Security** `v2.0.0-pre-security-2026-09-14`, nicht auf der Liste
der bekannten offenen Befunde.

**Entschieden am 2026-09-15:** `APP_ALLOW_ORIGIN` nimmt die erlaubten Herkünfte. Nur eine davon wird
zurückgegeben; ohne Eintrag gibt es **kein** `Allow-Origin`. Eine Ionic-App (etwa
`capacitor://localhost`) muss eingetragen werden — das ist eine Bruchstelle mit klarer Anweisung.

## Acceptance criteria
- [ ] Eine Anfrage mit einer Herkunft, die nicht in `APP_ALLOW_ORIGIN` steht, bekommt **kein** `Access-Control-Allow-Origin` und **kein** `Access-Control-Allow-Credentials`.
- [ ] Eine eingetragene Herkunft bekommt genau sich selbst zurück, dazu `Vary: Origin`.
- [ ] Ohne Eintrag gilt dasselbe wie für eine fremde Herkunft.
- [ ] Der OPTIONS-Preflight folgt derselben Regel.
- [ ] Tests halten alle drei Fälle fest, mit den Headern, nicht nur dem Statuscode.
- [ ] Die Vorlage `custom/config.php` zeigt, wie Herkünfte eingetragen werden; die Suite und die CI tragen, was sie brauchen.
- [ ] `breaking-changes.md` und `technical.md` sind nachgezogen.

## Verification
Die Messung aus dem Context wiederholen: fremde Herkunft ohne Header, eingetragene mit genau
sich selbst. Volle Suite, PHPStan, Deprecation-Gate.
