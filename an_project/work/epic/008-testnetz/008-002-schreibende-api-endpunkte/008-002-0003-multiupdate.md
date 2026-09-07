---
id: 008-002-0003
title: /api/multiupdate festhalten
status: done
depends_on: [008-002-0001]
---

# /api/multiupdate festhalten

## Context
Ein Aufruf ändert mehrere Objekte. Die interessante Frage ist nicht der Erfolgsfall, sondern
der **Teilfehler**: Wenn das dritte von fünf Objekten scheitert — sind die ersten beiden
geschrieben, oder wird alles zurückgerollt?

## Umfang

- **Erfolgsfall**: mehrere Objekte in einem Aufruf, alle geändert.
- **Teilfehler**: ein Objekt im Stapel scheitert (unbekannte ID oder eine `unique`-Verletzung).
  Festzuhalten ist der **Ist-Zustand**, egal wie er ausfällt:
  - Bleiben die vorher verarbeiteten Objekte geändert?
  - Welchen Statuscode und welche Nutzlast liefert die Antwort?
  - Steht in der Antwort, welches Objekt scheiterte?
- **Form der Antwort** im Vergleich zu `/api/update`.
- Fehlender Token.

> **Ausdrücklich nicht:** eine Transaktion einbauen, wenn keine da ist. Die Abgrenzung des Epics
> ist eindeutig — *„Wenn `multiupdate` bei einem Fehler die bereits geschriebenen Objekte stehen
> lässt, dann ist das der Test — nicht die Transaktion, die man sich wünscht."* Fällt dabei ein
> echter Mangel auf, wird er als eigener Task notiert (`/new-task`), wie es mit `000-000-0007`
> in `008-001-0004` geschehen ist.

## Acceptance criteria
- [x] Der Erfolgsfall mit mindestens zwei Objekten ist festgehalten.
- [x] Das Verhalten bei einem Teilfehler ist festgehalten — inklusive der Frage, ob bereits
      verarbeitete Objekte geändert bleiben.
- [x] Die Form der Antwort ist geprüft, nicht nur der Statuscode.
- [x] Die Route weist ohne Token ab.
- [x] Ein dabei entdeckter Mangel ist als eigener Task notiert, nicht hier repariert.

## Verification
Mehrere vollständige Läufe grün. Der Teilfehler-Test muss **deterministisch** sein: Der Fehler
wird gezielt herbeigeführt (unbekannte ID oder `unique`-Verletzung auf `PIM\Tag.title`), nicht
dem Zufall überlassen.

## Ergebnis — 6 Tests in `tests/Integration/Api/MultiupdateApiTest.php`

Gesamtsuite: **93 Tests, 226 Assertions**, drei Läufe grün.

### Der Befund: kein Rollback, und die Antwort schweigt

Drei Objekte, das mittlere mit unbekannter Id:

| Id | vorher | nachher | |
|---|---|---|---|
| erster | `Erster` | **`Vor-dem-Fehler`** | vor dem Fehler verarbeitet — **bleibt geändert** |
| `gibtesnicht` | — | — | bricht den Stapel ab |
| letzter | `Letzter` | `Letzter` | nie erreicht |

`multiupdateAction()` läuft in einer schlichten `foreach`-Schleife über `$objects` und ruft je
`Api::doUpdate()`. Keine Transaktion, keine Fehlersammlung, kein Rollback. Die Antwort ist ein
nackter HTTP 500 — **der Client kann nicht feststellen, in welchem Zustand seine Daten sind.**

### Und der Erfolgsfall sagt auch nichts

`renderResponse(array())` liefert einen Rumpf aus genau `version` und `hash`. Kein `ts`, kein
`data`, keine Liste der aktualisierten Ids. Damit ist der **dünnste Envelope aller Endpunkte**
erreicht — die Reihe aus `008-001` und `008-002-0001` setzt sich fort:

| Endpunkt | Envelope |
|---|---|
| `/api/single` | `ts`, `data`, `version`, `hash` |
| `/api/list` | `data`, `totalItems`, `version`, `hash` |
| `/api/insert` | `ts`, `id`, `data`, `version`, `hash` |
| `/api/delete` | `ts`, `id`, `version`, `hash` |
| `/api/all` | `lastModified`, `data`, `version`, `hash` |
| **`/api/multiupdate`** | **`version`, `hash`** |

Sechs Endpunkte, sechs verschiedene Envelopes.

**Task `000-000-0009`** angelegt — mit drei möglichen Richtungen (Transaktion, Fehlersammlung,
oder nur die Antwort auswertbar machen), aber ohne Vorentscheidung: Die Wahl berührt den
Vertrag mit Bestandsprojekten und gehört zu Epic `007` beziehungsweise `009`.

## Verification
- [x] Drei vollständige Läufe grün bei zufälliger Ausführungsreihenfolge; `pim_tag` danach leer.
- [x] Der Teilfehler wird **deterministisch** herbeigeführt (unbekannte Id an zweiter
      Stelle), nicht dem Zufall überlassen.
- [x] „Ohne Token ändert sich nichts" ist gegen die Datenbank geprüft, nicht gegen die Antwort.
- [x] Der Mangel ist als `000-000-0009` notiert, nicht hier repariert.
