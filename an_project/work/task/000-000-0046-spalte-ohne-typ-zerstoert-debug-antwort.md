---
id: 000-000-0046
title: Spalte ohne Contentfly-Typ zerstört im Debug-Modus die Antwort
status: done
depends_on: []
---

# Spalte ohne Contentfly-Typ zerstört im Debug-Modus die Antwort

## Context
**Gefunden bei `007-005-0004`** am Bestandsprojekt UFP (Befund F-5).

Seit `000-000-0017` meldet `Api::getExtendedSchema()` eine Spalte, zu der kein Contentfly-Typ
passt, mit `trigger_error(..., E_USER_WARNING)` — damit sie nicht still aus dem Schema fällt. UFP hat
eine solche Spalte: `AI\VectorDocument::embedding` (`blob`).

**Mit `APP_DEBUG` setzt der Bootstrap `display_errors=1`, und die Warnung steht als HTML vor dem
JSON.** Gemessen: `POST /oauth2/user` antwortet `200` mit `text/html`, erste Zeile
`<b>Warning</b>: No Contentfly type matches AI\VectorDocument::embedding (column type "blob")`.
Das trifft jeden Aufruf, der das Schema baut — auf Entwicklungs- und Staging-Instanzen, die mit
Debug laufen. Ohne Debug landet die Warnung nur im Log.

**Entschieden am 2026-09-15:** Binärspalten (`blob`, `binary`) gelten als bewusst nicht Teil der API
und fallen ohne Warnung aus dem Schema — JSON kann Binärdaten ohnehin nicht tragen. Unbekannte
Spaltentypen warnen weiter. Verworfen: die Meldung nur ins Log (die Suite sähe sie über
`failOnWarning` nicht mehr) und das Probe-Backend ohne Debug zu betreiben (verschiebt das Problem).

## Acceptance criteria
- [x] Entscheidung dokumentiert (Ausschluss von `blob`/`binary`).
- [x] Eine Binärspalte zerstört mit `APP_DEBUG` keine JSON-Antwort mehr; eine Spalte mit unbekanntem Typ bleibt sichtbar gemeldet.
- [x] Test.
- [x] Volle Suite, PHPStan, Deprecation-Gate grün.

## Verification
Unit-Test der Weiche mit eigenem Fehler-Handler. Am UFP-Probe-Backend: Login und `POST /oauth2/user`
antworten `application/json`.

## Ergebnis

**Die Weiche liegt in `Classes\Type\UntypedColumn::report()`**, `Api::getExtendedSchema()` ruft sie
an der Stelle der bisherigen `trigger_error`-Zeilen. `blob` und `binary` kehren still zurück, jeder
andere unbekannte Typ löst dieselbe `E_USER_WARNING` mit demselben Wortlaut aus wie seit `0017`. Die
Begründung steht an der Klasse: JSON trägt keine Bytes, und die Warnung stand unter `APP_DEBUG` vor
jedem JSON, das das Schema baut.

**Test** `tests/Unit/Type/UntypedColumnTest.php`: Binärspalten ohne Meldung; `geometry` mit
`E_USER_WARNING` und vollem Wortlaut; `Api.php` meldet über die Klasse und trägt keine zweite Kopie
der Meldung. **Gegenproben:** ohne die Ausnahme rot (erster Test), mit dem `Api.php` von master rot
(dritter Test).

**Register:** `breaking-changes.md`, Abschnitt API, Eintrag „Binärspalten fallen ohne Warnung aus
dem Schema" direkt nach dem Eintrag aus `0017`. Kopf von `migration.md` auf 109.

**Gemessen am UFP-Probe-Backend** (`APP_DEBUG` wie in der Projektvorgabe, Container neu gestartet,
Framework-Stand dieses Branches inklusive `0044`/`0045`): In keiner der 164 Antworten steht die
Warnung. Die Aufzeichnung läuft damit erstmals vollständig durch — **164 von 164 Aufrufen, 103
identisch mit dem alten Backend**; vorher fehlten allen angemeldeten Aufrufen die Token.

**Verifiziert:** volle Suite `Tests: 572, Assertions: 1840, Skipped: 3`, PHPStan `[OK] No errors`,
Deprecation-Gate 0.
