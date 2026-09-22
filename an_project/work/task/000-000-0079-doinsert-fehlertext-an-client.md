---
id: 000-000-0079
title: doInsert() gibt Doctrine- und SQL-Texte ohne Debug an den Client
status: todo
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
- [ ] Ohne Debug steht in keiner Antwort von `/api/insert` und `/api/update` der Text einer Doctrine-, DBAL- oder PDO-Ausnahme — weder in `code` noch in `detail` noch in `context`.
- [ ] Der volle Text geht ins Log, wie in `0073`.
- [ ] Mit Debug bleibt er sichtbar.
- [ ] Ein Test prüft am ungeparsten Rumpf, dass `SQLSTATE`, `Doctrine` und der Tabellenname fehlen.

## Verification
Test in `ErrorResponseApiTest` oder `ReadPathApiTest`; die Suite bleibt grün.
