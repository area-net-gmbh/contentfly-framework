---
id: 015-000-0001
title: Leeres Passwort überschreibt fremde Passwörter und öffnet den Admin-Login
status: done
depends_on: []
---

# Leeres Passwort überschreibt fremde Passwörter und öffnet den Admin-Login

## Context
**Security-Scan 2026-09, HIGH. Findings F2, F3, F5, F6.** Das ist derselbe Fehler an vier Stellen.

Ein Nicht-Admin mit Schreibrecht auf `PIM\User` sendet
`POST /api/update {"entity":"PIM\\User","id":"<admin id>","data":{"pass":null}}`. Danach ist
das Admin-Passwort leer, und `POST /auth/login {"alias":"admin"}` liefert ein Admin-Token. Genau
diese Eskalation sollte `RightsManagement` aus `000-000-0090` verhindern.

Die drei Prüfstellen sind sich nicht einig, ob ein leerer Wert eine Änderung ist:
- `RightsManagement::assertMayWrite` (`Classes/Security/RightsManagement.php:88`) wertet `pass`
  nur als Änderung, wenn `self::id($data['pass']) !== null` gilt. `null`, `""` und `[]` gelten
  deshalb als „keine Änderung“.
- `Api::doUpdate` (`Classes/Api.php:550`) verlangt das aktuelle Passwort nur bei
  `isset($data['pass'])`. `isset(null)` ist `false`, bei `null` wird also nicht nachgefragt.
- `StringType::toDatabase` (`Classes/Types/StringType.php:55`) macht aus jedem leeren Wert
  `setPass('')`. `User::setPass` (`Entity/User.php:166`) speichert `password_hash('')`.
- `User::isPass` bzw. der Login akzeptieren ein leeres oder fehlendes Passwort.

Betroffen ist auch `/api/multiupdate` und `/api/replace`. Der bestehende Test
`RightsManagementApiTest::testANonAdminCannotSetTheAdminsPassword` prüft nur einen nicht-leeren
Wert. Mit `{"pass":null}` auf dem **eigenen** Datensatz entfällt ausserdem die Rückfrage nach
dem aktuellen Passwort: Ein gestohlenes Token wird so zu einem dauerhaften Zugang.

## Acceptance criteria
- [x] `RightsManagement`, `Api::doUpdate` und der Schreibpfad werten jedes Vorkommen des Schlüssels `pass` als Passwortänderung (`array_key_exists`, nicht Wert-Vergleich und nicht `isset`).
- [x] Ein leeres oder `null`-Passwort wird nie gehasht. Es wird abgelehnt (400) oder ausdrücklich als „unverändert“ behandelt.
- [x] `User::isPass` und `/auth/login` lehnen ein leeres oder fehlendes Passwort ab.
- [x] Für `salt`, `loginManager` und `externalId` gilt in `RightsManagement` dieselbe Regel für `null` und leere Werte.
- [x] Tests decken `null`, `""` und `[]` für fremde Datensätze (Nicht-Admin) und für den eigenen Datensatz ohne Bestätigung ab.

## Verification
Integrationstest: Ein Nicht-Admin mit Schreibrecht ALL auf `PIM\User` sendet `pass: null`, dann
`""`, dann `[]` auf den Admin. Vor dem Fix ist danach ein Login als Admin ohne Passwort
möglich, nach dem Fix antwortet jeder Versuch mit 403 und der Admin-Hash ist unverändert. Ein
Login mit leerem `pass` gegen ein beliebiges Konto antwortet 401.
