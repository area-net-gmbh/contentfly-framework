---
id: 012-004-0001
title: Session-Verwendungen aufspüren und bewerten
status: todo
depends_on: []
---

# Session-Verwendungen aufspüren und bewerten

## Context
Die PHP-Session existiert wegen der Admin-Oberfläche. Bevor sie fällt, muss klar sein, ob irgendwo API-Zustand darin liegt — sonst verschwindet stillschweigend Verhalten.

## Acceptance criteria
- [ ] Alle Fundstellen von `$app['session']`, `$_SESSION` und `Auth::init()` sind erfasst.
- [ ] Jede ist eingestuft: UI-Anmeldung oder API-Zustand.
- [ ] Für jede API-Fundstelle ist entschieden, wohin der Zustand stattdessen gehört.
- [ ] Die Sonderbehandlung in `custom/app.php`, die für `/api/v1/*` die Session früh schließt, ist als gegenstandslos markiert.

## Verification
Die Liste wird gegen `grep -rn "session" lib custom` gegengelesen — keine Fundstelle bleibt unbewertet.
