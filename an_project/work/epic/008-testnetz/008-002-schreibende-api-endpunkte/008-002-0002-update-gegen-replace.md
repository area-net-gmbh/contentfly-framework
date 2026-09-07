---
id: 008-002-0002
title: update gegen replace abgrenzen
status: review
depends_on: [008-002-0001]
---

# update gegen replace abgrenzen

## Context
Zwei Endpunkte, die beide ein bestehendes Objekt ändern, und **nirgends steht, worin sie sich
unterscheiden**. Ein Client, der den falschen wählt, verliert Daten. Beim Kernel-Tausch ist das
eine Stelle, an der eine Abweichung niemandem auffällt, bis es zu spät ist.

## Umfang

Der Kern dieses Tasks ist ein einziger Vergleich, sauber aufgesetzt:

1. Ein Objekt mit **mehreren** gefüllten Feldern anlegen.
2. Über `/api/update` **ein** Feld ändern → was ist mit den anderen?
3. Dasselbe über `/api/replace` → was ist mit den anderen?

Die Differenz ist das Ergebnis des Tasks. Sie gehört als Zusicherung festgehalten **und** im
Testkommentar in einem Satz erklärt, damit man sie nicht jedes Mal neu herleiten muss.

Dazu:
- **Was `modified` tut** — wird es bei beiden aktualisiert?
- **Was mit verjointen Feldern passiert**, wenn sie im Request fehlen.
- **Fehlerfälle**: unbekannte ID, unbekannte Entity, fehlender Token.

## Acceptance criteria
- [x] Ein Test belegt für `/api/update`, welche nicht mitgesendeten Felder unverändert bleiben.
- [x] Ein Test belegt dasselbe für `/api/replace`.
- [x] Der Unterschied zwischen beiden ist als Zusicherung festgehalten und im Kommentar in
      einem Satz benannt.
- [x] Das Verhalten von `modified` ist für beide festgehalten.
- [x] Je Route mindestens ein Fehlerfall, in seiner heutigen Form.
- [x] Beide Routen weisen ohne Token ab.

## Verification
Mehrere vollständige Läufe grün. Zusätzlich: Der Unterschied zwischen `update` und `replace`
muss aus den Testnamen und ihren Meldungen **ablesbar** sein, ohne den Code von `Api` zu öffnen —
das ist der eigentliche Zweck dieses Tasks.

## Ergebnis — 7 Tests in `tests/Integration/Api/UpdateReplaceApiTest.php`

Gesamtsuite: **87 Tests, 213 Assertions**, drei Läufe grün.

### Der Unterschied — und er ist nicht der erwartete

**`replace` ist ein Upsert, kein Vollersetzen.**

`ApiController::replaceAction()` prüft, ob ein Objekt mit der gegebenen Id existiert, und
delegiert dann per **Sub-Request**:

| Fall | `replace` delegiert an | `update` tut |
|---|---|---|
| Id existiert | `/api/update` | dasselbe |
| Id existiert nicht | `/api/insert`, mit genau dieser Id | scheitert |

Auf einem vorhandenen Objekt sind die beiden also **Feld für Feld identisch**: Nicht gesendete
Werte bleiben bei *beiden* stehen. Empirisch bestätigt mit `views = 5`, das weder `update` noch
`replace` zurücksetzt.

**Der Name führt in die Irre.** Wer „replace" liest und erwartet, dass nicht gesendete Felder
zurückgesetzt werden, irrt sich — und würde vermutlich `replace` wählen, um genau das zu
erreichen. Der einzige Unterschied liegt beim Nicht-Existieren der Id: `replace` legt an,
`update` scheitert (heute mit HTTP 500 statt 404, siehe `000-000-0006`).

Das war die eigentliche Aufgabe dieses Tasks — der Unterschied stand nirgends und ist jetzt aus
den Testnamen ablesbar, ohne `Api.php` zu öffnen.

### Warum jede Zusicherung gegen die Datenbank prüft
Die API-Antwort zeigt das serialisierte Objekt und würde ein zurückgesetztes Feld nicht
zwingend verraten. Alle Aussagen über „bleibt stehen" und „entsteht nicht" lesen deshalb
`pim_tag` direkt.

## Verification
- [x] Drei vollständige Läufe grün bei zufälliger Ausführungsreihenfolge; `pim_tag` danach leer.
- [x] Der Unterschied ist aus den Testnamen ablesbar:
      `testReplaceLaesstNichtGesendeteFelderEbenfallsUnberuehrt` gegen
      `testReplaceLegtEinNichtVorhandenesObjektMitDerVorgegebenenIdAn` gegen
      `testUpdateAufEineUnbekannteIdScheitert`.
