---
id: 012-004-0002
title: Session-Bootstrap entfernen
status: todo
depends_on: [012-004-0001]
---

# Session-Bootstrap entfernen

## Context
`Auth::init()` startet die Session bei jedem Request, auch bei reinen API-Aufrufen. Ohne Admin-Oberfläche hat das keinen Zweck mehr.

## Acceptance criteria
- [ ] Der Session-Start in `lib/contentfly/bootstrap.php` / `Auth::init()` ist entfernt.
- [ ] Session-Abhängigkeiten in `LoginManager` und `AuthController` sind herausgelöst; der Token-Weg bleibt vollständig.
- [ ] Die Session-Sonderbehandlung in `custom/app.php` ist ersatzlos entfernt.
- [ ] Kein Request startet mehr eine PHP-Session.

## Verification
Einen API-Aufruf ausführen und prüfen, dass keine Session-Datei entsteht und kein `PHPSESSID`-Cookie gesetzt wird.
