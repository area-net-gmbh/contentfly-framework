---
id: 000-000-0079
title: doInsert() gibt Doctrine- und SQL-Texte ohne Debug an den Client
status: done
depends_on: []
---

# doInsert() gibt Doctrine- und SQL-Texte ohne Debug an den Client

## Context
**Gefunden in `000-000-0075` (2026-09-22).** `0073` hält den Text unerwarteter Ausnahmen ohne
Debug aus der Antwort heraus — aber nur, wenn die Ausnahme **keine** `ContentflyException` ist.
`doInsert()` verpackt zwei Fälle genau so:

- `catch(Exception $e){ throw new ContentflyException($e->getMessage()); }` — der Text der
  Doctrine-Ausnahme steht dann in `code` **und** `detail` (gesehen bei `0078`).
- Die Unique-Verletzung hängt `$e->getMessage()` an den Kontext; `context.value` enthält die
  MySQL-Meldung samt `SQLSTATE[23000]` (gesehen bei `0080`).

Das ist dieselbe Art Informationsleck wie in `0073`. `doUpdate()` auf dieselben Muster prüfen.

## Acceptance criteria
- [x] Ohne Debug steht in keiner Antwort von `/api/insert` und `/api/update` der Text einer Doctrine-, DBAL- oder PDO-Ausnahme — weder in `code` noch in `detail` noch in `context`.
- [x] Der volle Text geht ins Log, wie in `0073`.
- [x] Mit Debug bleibt er sichtbar.
- [x] Ein Test prüft am ungeparsten Rumpf, dass `SQLSTATE`, `Doctrine` und der Tabellenname fehlen.

## Verification
Test in `ErrorResponseApiTest` oder `ReadPathApiTest`; die Suite bleibt grün.

## Ergebnis (2026-09-22)
**Ohne Debug steht in keiner Antwort von `/api/insert` und `/api/update` mehr der Text von
Doctrine oder MySQL.**

- **`catch(Exception $e)` in `doInsert()` und `doUpdate()` entfernt.** Er machte aus jedem
  Fehler beim `flush()` eine `ContentflyException` mit dem Ausnahmetext als Meldung — die gilt als
  erwarteter Fehler, und der Handler aus `0073` liess sie samt Text durch. Jetzt erreicht der Fehler
  den Handler als das, was er ist: `detail` = `contentfly_general_internal_error`, `code` = `null`,
  der volle Text im Log. Mit Debug unverändert sichtbar.
- **Unique-Verletzung in `doInsert()`:** Die MySQL-Meldung hängt nicht mehr an `context.value`,
  sondern geht per `error_log()` ins Log (`Contentfly: unique violation on <Entity>: …`). 500 und
  das falsche Feld bleiben — das ist `0080`.
- `doUpdate()` hatte kein zweites Muster; seine Unique-Zweige nennen nur Feld und Wert.

### Belegt
- `ErrorResponseApiTest::testAFailedInsertDoesNotShowItsInternals` und
  `…testAFailedUpdateDoesNotShowItsInternals`: ein zu langer Wert für `ExampleI18n.code`; geprüft
  am ungeparsten Rumpf, dass `SQLSTATE`, `Data too long`, `example_i18n`, `Doctrine` und `DBAL`
  fehlen.
- `ReadPathApiTest::testADuplicateOnAUniqueColumn…`: kein `SQLSTATE` und nicht der kollidierende
  Wert im Fehlereintrag.
- **Gegenprobe:** ohne die Änderung alle drei rot. Die Log-Zeilen im Serverlog nachgesehen.

Suite 785 Tests grün, PHPStan ohne Fehler.

