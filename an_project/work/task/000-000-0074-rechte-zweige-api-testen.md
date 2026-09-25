---
id: 000-000-0074
title: Die Rechte-Zweige in Api.php testen, die kein Test erreicht
status: done
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
- [x] Je Zeile der Tabelle ein Test, der beide Richtungen prüft: was sichtbar/erlaubt sein muss und was nicht.
- [x] Die Coverage der `[PERM]`-Blöcke aus `0069` ist neu gemessen.

## Verification
Die neuen Tests und der Coverage-Lauf aus `0056`.

## Ergebnis (2026-09-25)
**Jede Zeile der Tabelle ist beantwortet, durch einen neuen Test, einen bestehenden oder den
Nachweis, dass der Zweig nicht erreichbar ist.** Neu ist `tests/Integration/Api/PermissionBranchApiTest.php`
mit 17 Tests. Jeder prüft beide Richtungen und gleicht mit der Datenbank ab, wo geschrieben wird.

| Methode | Zweig | beantwortet durch |
|---|---|---|
| `getAll` | `OWN`, `GROUP`, ohne Leserecht, Benutzer ohne Gruppe | **neu**, vier Tests |
| `getDeleted` | Entity ohne Leserecht | bestehend: `PermissionMatrixApiTest::testTheDeletionLogReportsOnlyReadableEntities` (`0061`) |
| `getCount` | `OWN` | **neu**; `GROUP` bestehend in `ReadPermissionApiTest` (`0072`) |
| `getList` | `GROUP` ohne Gruppe | **unerreichbar**, siehe unten; das Verhalten (403) prüft `ReadPermissionApiTest::testUserWithoutGroupSeesNothing` |
| `getTree` / `getTree2` | `OWN`, `GROUP` mit Gruppe | bestehend: `PermissionMatrixApiTest::testTreeRoutesApplyTheReadLevel` (`0061`); ohne Gruppe unerreichbar |
| `getTranslations` | ohne Leserecht, `GROUP` | **neu**: 403 ohne Recht, Zählung mit Recht; `GROUP` bestehend (`0059`) |
| `doInsert` / `doUpdate` / `doDelete` | Sprache, die die Gruppe nicht schreiben darf | **neu**, sieben Tests: je gesperrt und erlaubt; dazu, dass ein Update in `de` die `i18n_universal`-Felder einer nur lesbaren Sprache nicht anfasst |
| `doUpdate` | `PIM\User.pass` ohne, mit falschem, mit richtigem aktuellen Passwort | **neu**, drei Tests, geprüft über einen Login mit altem und neuem Passwort |

### Zwei Korrekturen an der Einordnung aus `0069`
- **Die „ohne Gruppe“-Zweige sind unerreichbar.** Sieben Methoden tragen
  `elseif(GROUP){ if(!$group){ … } }`. `Permission::is()` gibt für einen Benutzer ohne Gruppe `NONE`
  zurück, bevor es eine Rechte-Zeile ansieht. Die Stufe `GROUP` entsteht ohne Gruppe also nie.
- **`continue;`-Zeilen zählt PCOV nie.** `getAll()` überspringt `_hash` bei jedem Aufruf, und die
  Zeile gilt trotzdem als offen. `getDeleted` „ohne Leserecht“ war deshalb nie ungetestet, nur
  unsichtbar. Die Schleifen-`continue` aus der Liste „begründet nicht abgedeckt“ in `0069` gehören
  ebenfalls hierher.

### Coverage der `[PERM]`-Blöcke, neu gemessen
Voller Lauf mit PCOV, Unit- und Server-Anteil zusammengeführt, 850 Tests grün.

| | vorher | nachher |
|---|---|---|
| `Api.php` gesamt | 941 / 1.238 (76,0 %) | **964 / 1.238 (77,9 %)** |
| `doDelete` | 49 / 63 | 54 / 63 |
| `doUpdate` | 76 / 94 | 82 / 94 |
| `getAll` | 64 / 76 | 71 / 76 |
| `getCount` | 72 / 83 | 75 / 83 |
| `getTranslations` | 27 / 31 | 28 / 31 |

(„vorher“ ist der Stand von `master` am 2026-09-25, nicht der von `0069`; `0072` und `0075` hatten
seitdem schon Zeilen geschlossen.)

**In den `[PERM]`-Blöcken offen bleiben:** die unerreichbaren „ohne Gruppe“-Zeilen (789, 954–955,
1253, 2172, 2230, 2381–2382), `continue;`-Zeilen, deren Verhalten die Tests belegen (563, 773,
1074), und die Array-Syntax von `getQuery` — `0076`.

### Zwei Befunde, nicht Teil dieses Tasks
- **`GROUP` in `getAll()` und `getCount()` lässt `users` weg.** `reachesRow()` legt fest: `GROUP`
  erreicht, was `OWN` erreicht (angelegt **oder** in `users`), und zusätzlich, was für die Gruppe
  freigegeben ist. Gemessen an einem Tag, das einem `GROUP`-Leser über `users` freigegeben ist:
  sichtbar in `/api/list` und `/api/single`, **fehlt** in `/api/all`, **nicht gezählt** in
  `/api/count`. Ein Sync-Client verpasst solche Datensätze. Die Tests hier prüfen den Fall deshalb
  nicht — er gehört in ein eigenes Ticket, das ihn behebt.
- **Eine i18n-Sperre antwortet mit HTTP 550.** `ContentflyI18NException` setzt den Code seit 1.x
  fest, der Envelope macht ihn zum Status. 550 ist kein HTTP-Status; als 5xx liest er sich wie ein
  Serverfehler, den ein Client oder Proxy wiederholen darf. Die Tests halten 550 fest und prüfen den
  Fehlercode `contentfly_i18n_permission_denied`, auf den sich ein Client verlassen kann.
