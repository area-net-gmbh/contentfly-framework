---
id: 014-003-0004
title: File- und SystemController, Provider und Manager auf Englisch
status: done
depends_on: [014-003-0003]
---

# File- und SystemController, Provider und Manager auf Englisch

## Context
Umfang:
- `Controller/FileController.php` und `Controller/SystemController.php`, mit Meldungen wie
  „Schema-Cache wurde geleert!"
- `Classes/Controller/**`: `BaseController`, `BaseControllerProvider` mit `anmelden()` →
  `authenticate()` und der Meldung „Contentfly ist nicht installiert …", `Route` und die fünf
  Provider unter `Base/` mit „Zugriff verweigert"
- `Classes/Manager/**`: `RouteManager`, `ConsoleManager`, `TypeManager`, `PluginManager`
- `Classes/Command/CustomCommand.php`

## Acceptance criteria
- [x] Die Dateien des Tasks sind vollständig englisch: Namen, Methoden, Eigenschaften, Variablen, Konstanten, Meldungen und Kommentare.
- [x] Werte, die gespeichert oder über die API ausgeliefert werden (Tabellen- und Spaltennamen, Message-Keys, Token-`purpose`), bleiben unverändert, wenn sie schon englisch sind. Ein deutscher gespeicherter Wert wird nur nach Rückfrage geändert.
- [x] Alle Aufrufer im Baum sind mitgezogen (`lib`, `custom`, `bin`, `tests`, `tools`, `.gitlab-ci.yml`, `phpstan.neon.dist`, `rector.php`, `dev-guide.md`, soweit ein Test daran abgleicht). Testklassen, die nach einer umbenannten Klasse heissen, sind mit umbenannt.
- [x] Die Ausnahmen im Sprachwächter, die dieser Task auflöst, sind gestrichen.
- [x] Verhalten unverändert: volle Suite mit gleicher Testzahl, PHPStan `[OK]`, Deprecation-Gate grün, `console list` läuft, `custom/config.php` ohne Zugangsdaten.

## Verification
Volle Suite gegen `contentfly-db-0004` mit Vergleich der Zahlen, PHPStan (Result-Cache geleert),
`tools/ci/deprecations-pruefen.sh`, Nachsuche nach jedem alten Namen (auch in umgebrochenen Aufrufen),
Suche nach deutschen Wörtern in den Dateien des Tasks, `sh tools/check-template-config.sh`.

## Ergebnis

**File- und SystemController, `Classes/Controller/**`, die vier Manager und `CustomCommand`
sind englisch.** Das sind 15 Dateien. Die Kommentare hat ein Agent übersetzt, die Code-Token
sind identisch. Namen und Meldungen habe ich danach umgestellt.

**Im Code:**
- `BaseControllerProvider::anmelden()` → `authenticate()`, in den fünf Providern; `$benutzer` →
  `$user`
- `SystemController`: `$konfiguration` → `$configuration`
- Meldungen:

| bisher | jetzt |
|---|---|
| Contentfly ist nicht installiert. Installation ausfuehren: … | Contentfly is not installed. Run the installation: … |
| Zugriff verweigert | Access denied |
| Zugriff nur für Administratoren gestattet | Access is restricted to administrators |
| Zugriff auf PIM\File verweigert. | Access to PIM\File denied. |
| Methode … nicht verfügbar. | Method … is not available. |
| Schema-Cache wurde geleert! | Schema cache cleared! |
| Die Datenbank wurde erfolgreich aktualisiert. | The database was updated successfully. |
| Token ungültig · Token und/oder Referrer ungültig · Benutzer ungültig | Invalid token · Invalid token and/or referrer · Invalid user |
| Der Token ist bereits vorhanden. | The token already exists. |

Statuscodes bleiben gleich. Der apidoc-Name `Ausführen` heisst jetzt `Execute`, wie die übrigen
Endpunkte (`Upload`, `Get`).

**Aufrufer:** `SystemControllerApiTest` prüft sieben Meldungen und eine Quelltextzeile jetzt
englisch. `Auth.php` und `TokenAuthenticator.php` verweisen im Kommentar auf `authenticate()`. Die
Ausnahme im Sprachwächter für `anmelden` ist gestrichen.

**Für `014-003-0007` notiert:** Der Detektor erkennt „Zugriff verweigert" nicht; es fehlt ein
Wortstamm. Er kommt beim Erweitern auf das ganze Paket dazu.

Geprüft: volle Suite `OK (528 tests, 1702 assertions)` wie vorher, PHPStan `[OK] No errors`,
Deprecation-Gate grün. Der Detektor findet in allen Dateien des Tasks nichts.
