---
id: 000-000-0090
title: Rechte verwalten nur Admins — auch beim Schreiben von Gruppen, Rechte-Zeilen und Benutzern
status: done
depends_on: []
---

# Rechte verwalten nur Admins — auch beim Schreiben von Gruppen, Rechte-Zeilen und Benutzern

## Context
**Gefunden bei der Arbeit an `000-000-0088`.** Beim Lesen sind die Rechte einer Gruppe Admins
vorbehalten: `PermissionsType::fromDatabase()` liefert sie Nicht-Admins nicht. Beim Schreiben prüfte
die API nichts dergleichen. Wer als Nicht-Admin auf eine von drei Entities schreiben durfte, war
faktisch Admin. Belegt gegen eine lokale Instanz, mit einem Nicht-Admin und nur dem jeweils
genannten Recht:

| Schreibrecht auf | Weg | Ergebnis |
|---|---|---|
| `PIM\Group` | `/api/update` der eigenen Gruppe mit `permissions` | `/api/list` auf `PIM\User`: vorher 403, danach 200 |
| `PIM\Permission` | `/api/insert` einer Zeile für die eigene Gruppe | ebenso |
| `PIM\User` (`OWN` genügt) | `/api/update` des eigenen Datensatzes mit `isAdmin: true` | `isAdmin` = 1 |
| `PIM\User` | `/api/update` eines **fremden** Benutzers mit `pass`, bestätigt nur das eigene Passwort | Passwort des Admins gesetzt, Anmeldung als Admin mit 200 |

Die Passwortprüfung in `doUpdate()` bestätigt das Passwort des **Aufrufers**, nicht das des
Benutzers, der geändert wird. `OWN` auf `PIM\User` erlaubt ausdrücklich, den eigenen Datensatz zu
schreiben — und damit bisher auch `isAdmin`.

Entschieden am 2026-09-24: Rechteverwaltung ist Admins vorbehalten, auch beim Schreiben. Kein
bekanntes Projekt lässt Nicht-Admins Benutzer oder Gruppen verwalten.

## Acceptance criteria
- [x] Ein Nicht-Admin bekommt `403` `contentfly_general_permission_denied` für: `permissions` an `PIM\Group` (Anlegen und Ändern); `PIM\Permission` anlegen, ändern, löschen; `isAdmin` oder `group` eines Benutzers ändern (auch beim Anlegen); `pass`, `salt`, `loginManager` oder `externalId` eines anderen Benutzers ändern.
- [x] Die Wirkung ist geprüft, nicht nur der Status: Rechte-Zeilen, `isAdmin`, Gruppe und Passwort-Hash bleiben unverändert, und der Zugriff auf `PIM\User` bleibt verweigert.
- [x] Weiterhin erlaubt und getestet: Gruppe umbenennen, eigenes Passwort mit Bestätigung ändern, den eigenen Datensatz unverändert zurückschicken.
- [x] `/api/multiupdate` ist abgedeckt.
- [x] Registereintrag unter *API*; Zählung in `migration.md` nachgezogen.

## Verification
`tests/Integration/Api/RightsManagementApiTest.php`; Gegenprobe ohne Fix. Danach die volle Suite,
PHPStan und das Deprecation-Gate.

## Ergebnis (2026-09-24)
**`Classes\Security\RightsManagement` prüft jeden Schreibzugriff über die API**, eingehängt in
`Api::doInsert()`, `doUpdate()` und `doDelete()`: nach der bestehenden Rechteprüfung, vor dem ersten
Schreiben. Die Endpunkte und `OnejoinType` gehen alle über diese drei Methoden; Provisioning und
Console schreiben über das ORM und sind nicht betroffen.

Ein Wert, der sich nicht ändert, ist keine Änderung — verglichen wird mit dem Datensatz, wie er
ist. Ein Client, der den eigenen Datensatz unverändert zurückschickt, bekommt weiter 200. Ein
Passwort ist als Hash gespeichert und lässt sich nicht vergleichen: Jeder nicht leere Wert für einen
anderen Benutzer ist eine Änderung.

### Belegt
Lokal gegen eine eigene Datenbank, Aufbau mit `tools/ci/prepare-test-environment.sh`.
- `RightsManagementApiTest`, 16 Fälle. **Gegenprobe ohne Fix:** alle 13 „darf nicht“-Fälle rot,
  die 3 „darf weiterhin“-Fälle grün.
- Volle Suite 815 Tests grün (3 übersprungen), PHPStan ohne Fehler, Deprecation-Gate 0 Meldungen.

### Zwei Fallen, festgehalten
- **Der eingebaute Testserver cached PHP-Dateien.** OPcache gilt für `php -S` (`cli-server`), auch
  wenn `opcache.enable_cli` aus ist, und prüft Dateien nur alle 2 Sekunden. Die erste Gegenprobe
  direkt nach dem Tausch von `Api.php` lief deshalb teils gegen die Fassung **mit** Fix — zwei Fälle
  waren „grün ohne Fix“. Wer Code tauscht, wartet danach mehr als 2 Sekunden.
- **`/api/insert` ohne `data` übergibt `null`.** Die erste Fassung der Prüfung verlangte ein Array
  und warf dort einen `TypeError` — der Status blieb 500, den `WriteApiTest` festhält, die Ursache
  aber war eine andere. Gefunden im Serverlog, nicht in der Suite. Die Prüfung nimmt jetzt jeden
  Wert an; ohne Array gibt es kein Feld zu prüfen.
