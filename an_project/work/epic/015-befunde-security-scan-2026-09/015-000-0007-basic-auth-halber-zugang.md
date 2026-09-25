---
id: 015-000-0007
title: HTTP-Basic-Sperre lässt mit halbem Zugang durch
status: todo
depends_on: []
---

# HTTP-Basic-Sperre lässt mit halbem Zugang durch

## Context
**Security-Scan 2026-09, MEDIUM. Findings F10, F12, F16.** Alle drei beschreiben dieselbe Zeile.

Die optionale Sperre über `APP_HTTP_AUTH_USER`/`APP_HTTP_AUTH_PASS` in
`lib/contentfly/bootstrap-web.php:66` verknüpft die beiden Ungleich-Prüfungen mit `&&`:

```php
if ($_SERVER['PHP_AUTH_USER'] != ...->APP_HTTP_AUTH_USER && $_SERVER['PHP_AUTH_PW'] != ...->APP_HTTP_AUTH_PASS) {
```

Abgelehnt wird also nur, wenn Benutzer **und** Passwort falsch sind. Ein richtiger Wert genügt:
`Authorization: Basic base64("staging:beliebig")` kommt an einer Sperre mit dem Benutzer
`staging` vorbei. Dahinter liegen `/auth/login`, `/auth/refresh`, `/api/config`, `/file/get/*`
und Custom-Routen mit `isSecure=false`. Der Vergleich ist ausserdem lose (`!=`) und nicht
zeitkonstant.

Nur relevant, wenn ein Projekt `APP_HTTP_AUTH_USER` setzt. Die Vorgabe ist `null`.

## Acceptance criteria
- [ ] Der Zugang wird nur gewährt, wenn Benutzer **und** Passwort übereinstimmen.
- [ ] Verglichen wird mit `hash_equals()` auf Strings.
- [ ] Ein Header ohne `:` oder mit leerem Passwort wird abgelehnt.

## Verification
Test mit gesetzter Sperre: richtiger Benutzer mit falschem Passwort, falscher Benutzer mit
richtigem Passwort, beides richtig. Vor dem Fix kommen die ersten beiden durch, nach dem Fix
antworten sie 401. Der dritte Fall antwortet vorher und nachher mit Durchlass.
