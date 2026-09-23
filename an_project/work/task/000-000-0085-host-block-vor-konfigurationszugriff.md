---
id: 000-000-0085
title: Den Host-Block wählen, bevor display_errors und is_installed die Konfiguration lesen
status: done
depends_on: []
---

# Den Host-Block wählen, bevor display_errors und is_installed die Konfiguration lesen

## Context
**Gefunden auf dem UFP-Staging mit `v2.2.0` (mittwald, PHP 8.4).** `bootstrap.php` lädt
`custom/config.php` (Z. 59) und definiert `HOST` (Z. 62), wählt den Host-Block aber erst in Z. 180
mit `Adapter::setHostname(HOST)`. Bis dahin steht `Adapter::$host` auf `'default'`
(`Classes/Config/Adapter.php`), und die beiden Zugriffe davor lesen deshalb den **Default-Block**:

- **Z. 153** — `if(Adapter::getConfig()->APP_DEBUG)`. Setzt der Default-Block `APP_DEBUG = true`,
  wie es für die lokale Entwicklung üblich ist, gilt `display_errors = 1` auf **jedem** Server,
  auch wenn Staging und Live im Host-Block `APP_DEBUG = false` setzen.
- **Z. 177** — `$app['is_installed'] = (Adapter::getConfig()->DB_HOST != '$SET_DB_HOST')`. Die
  Installationskennung richtet sich nach dem `DB_HOST` des Default-Blocks statt nach dem des Hosts.

Das ist genau die Lücke, die `000-000-0018` schließen sollte: Die Entscheidung wird getroffen, bevor
feststeht, für welchen Host sie gilt. Die Folge auf dem Staging: `Adapter::getConfig()->APP_DEBUG`
ist `false`, `ini_get('display_errors')` aber `'1'`. Jede Deprecation aus `Request::get()` landet als
HTML in der Antwort — mit absoluten Serverpfaden. Bei Ausgabe über `output_buffering` gehen die
Header vorzeitig raus, und die Antwort verlässt den Server als `200 text/html` ohne `Cache-Control`
und ohne `Access-Control-Allow-Origin`; der Browser verwirft sie. Wird der Host-Block vor Z. 153
gewählt, gilt `display_errors = 0`, und dieselbe Auswertung liefert 200 mit identischen Daten.

**Nicht Teil dieses Tasks:** das Patch-Release `v2.2.1` samt Changelog-Eintrag und Veröffentlichung
in `contentfly-framework-dist` — das wird ein eigener Task, wie `000-000-0066` und `000-000-0084`.
Ebenso wenig die Deprecation von `Request::get()` selbst; die rund 400 Aufrufe in UFPs
`backend/custom` werden dort separat umgestellt. Mit diesem Fix stehen diese Meldungen nur noch im
Log, nicht mehr in der Antwort.

## Acceptance criteria
- [x] `Adapter::setHostname(HOST)` steht direkt hinter der Definition von `HOST`, voll qualifiziert
      geschrieben, weil der `use`-Block erst dahinter beginnt.
- [x] Der Aufruf in Z. 180 ist entfernt; es bleibt genau ein `setHostname`-Aufruf im Bootstrap.
- [x] `display_errors` und `is_installed` lesen die Werte des Host-Blocks.
- [x] Der Kommentarblock „ERROR OUTPUT" hält fest, dass die Entscheidung den gewählten Host braucht.
- [x] Ein Regressionstest hält beide Richtungen fest: mit dem `SERVER_NAME` eines Host-Blocks, der
      `APP_DEBUG = false` und ein echtes `DB_HOST` setzt, gilt `display_errors === '0'` und
      `is_installed === true`; ohne `SERVER_NAME` greift der Default-Block mit `display_errors === '1'`
      und `is_installed === false`.
- [x] **Gegenprobe belegt:** ohne die Änderung ist der Test rot.
- [x] Registereintrag in `an_project/docs/breaking-changes.md` — auf einem Server, dessen Host-Block
      `APP_DEBUG = false` setzt, endet die Fehlerausgabe in der Antwort. Das ist die Absicht, fällt
      einem Bestandsprojekt aber auf.

## Verification
Der neue Test läuft in einem eigenen PHP-Prozess auf einem Scratch-Projekt — der Bootstrap definiert
Konstanten (`HOST`, `APP_CMS_MAIN_LANG`) und ist im selben Prozess nicht zweimal zu laden.
`tests/Unit/Kernel/StartupFailureResponseTest.php` ist das Muster dafür.

Fixture: eine `config.php` mit Default-Block (`APP_DEBUG = true`, `DB_HOST = '$SET_DB_HOST'`) und
Host-Block `example.test` (`APP_DEBUG = false`, `DB_HOST = 'db'`).

Suite grün, PHPStan ohne Fehler.

## Ergebnis (2026-09-23)
**Der Host-Block wird gewählt, bevor irgendetwas die Konfiguration liest.**
`Adapter::setHostname(HOST)` steht jetzt unmittelbar hinter `define('HOST', …)`; der Aufruf hinter
`is_installed` ist entfallen. Zwischen beiden Stellen lasen nur die zwei gefixten Zeilen die
Konfiguration — sonst stehen dort `use`-Deklarationen und `new Application()`, also ist nichts
weiter betroffen.

- `display_errors` folgt dem `APP_DEBUG` des Hosts, `is_installed` dessen `DB_HOST`.
- Der Kommentarblock „ERROR OUTPUT" hält die Abhängigkeit fest, damit der Aufruf nicht wieder nach
  unten wandert; an `is_installed` steht eine Zeile dazu.
- Registereintrag in `breaking-changes.md` unter *Konfiguration*, direkt hinter dem Eintrag aus
  `000-000-0018` — dessen Versprechen galt bisher nur dem Default-Block. Register jetzt 134
  Einträge, `migration.md` nachgezogen.

### Belegt
`HostBlockSelectionTest` mit zwei Fällen, jeder in einem eigenen PHP-Prozess auf einer
Scratch-Fixture (der Bootstrap definiert Konstanten, die Config-Factory ist ein Singleton — zwei
Fälle passen nicht in einen Prozess). `display_errors` wird jeweils auf den Gegenwert vorgesetzt,
damit die Zusage nicht bloss den Startwert wiederholt.

**Gegenprobe:** ohne den Fix ist `testTheHostBlockDecidesErrorOutputAndInstalledState` rot —
`display_errors` ist `'1'` statt `'0'`. Unit-Suite 327 Tests grün, PHPStan `[OK] No errors`,
`tools/check-template-config.sh` ohne Befund.

**Nicht gelaufen:** die Integration-Suite. Sie braucht ein separat installiertes Projekt, einen
laufenden Testserver und die Mail-Falle (`CONTENTFLY_TEST_BASE_URL`, `CONTENTFLY_TEST_PROJECT_DIR`,
`CONTENTFLY_TEST_MAIL_TRAP`); lokal ist nichts davon gesetzt. CI fährt sie auf dem Pull Request.
