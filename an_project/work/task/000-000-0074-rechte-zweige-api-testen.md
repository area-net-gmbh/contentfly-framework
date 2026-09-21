---
id: 000-000-0074
title: Die Rechte-Zweige in Api.php testen, die kein Test erreicht
status: todo
depends_on: [000-000-0072]
---

# Die Rechte-Zweige in Api.php testen, die kein Test erreicht

## Context
**Aus `000-000-0069`.** Jede Stelle mit `Permission::` oder `I18nPermission::` in `Api.php`, die die
Coverage nicht erreicht, gebündelt. Eine Rechteprüfung ohne Test ist eine Behauptung — `0072` hat
gezeigt, dass eine davon nie funktioniert hat.

| Methode | Zweig |
|---|---|
| `getAll` | Verengung bei `OWN` und `GROUP` (mit und ohne Gruppe) |
| `getDeleted` | Entity ohne Leserecht wird ausgelassen |
| `getCount` | Verengung bei `OWN` und `GROUP` (nach `0072`) |
| `getList` | `GROUP` für einen Benutzer ohne Gruppe |
| `getTree` / `getTree2` | `OWN`/`GROUP` ohne Gruppe bzw. `GROUP` mit Gruppe |
| `getTranslations` | ohne Leserecht (403), `GROUP` |
| `doInsert` / `doUpdate` / `doDelete` | Schreiben einer Sprache, die die Gruppe nicht schreiben darf (`I18nPermission`) |
| `doUpdate` | `PIM\User.pass` ändern ohne bzw. mit falschem aktuellen Passwort |

## Acceptance criteria
- [ ] Je Zeile der Tabelle ein Test, der beide Richtungen prüft: was sichtbar/erlaubt sein muss und was nicht.
- [ ] Die Coverage der `[PERM]`-Blöcke aus `0069` ist neu gemessen.

## Verification
Die neuen Tests und der Coverage-Lauf aus `0056`.
