---
id: 008-001-0002
title: /api/single und /api/list festhalten
status: done
depends_on: [008-001-0001]
---

# /api/single und /api/list festhalten

## Context
Die beiden meistgenutzten Endpunkte der Datenschnittstelle — und beide ohne einen einzigen Test.
Was sie zurückgeben, weiß heute nur der Code. Epic `009` tauscht den Kernel darunter aus; ohne
diese Tests gibt es keinen Maßstab für „unverändert".

## Umfang

### `/api/single`
- Einzelobjekt zu `entity` und `id`.
- Feldmenge und Form der Antwort.
- Verhalten bei fehlender Leseberechtigung: verjointe Objekte kommen als
  `array('id' => …, 'pim_blocked' => true)` statt als Objekt.
- Unbekannte `id`, unbekannte `entity`.

### `/api/list`
- Sortierung aus `sortBy`/`sortOrder` der Entity-Settings.
- Filter und ihre Wirkung auf die Treffermenge.
- Die `properties`-Einschränkung: Wird sie mitgegeben, kommt ein `partial`-Select — und
  verjointe Objekte tragen dann nur `id` plus die `labelProperty` des Ziels. **Genau hier hängt
  die Entscheidung aus `012-005-0002`**, `labelProperty` entgegen der Streichliste zu behalten;
  ein Test darauf schützt sie.

### Beiden gemeinsam — die Form der Antwort
Nicht nur der Statuscode, sondern die Gestalt:
- Der Envelope: `ts`, `data`, `version`, `hash`.
- **Datumsfelder**: Jedes `datetime` kommt als vier Felder — `LOCAL_TIME`, `LOCAL`, `ISO8601`,
  `TIMESTAMP`. Zeitwerte kommen als `H:i`.
- **Verschachtelungstiefe**: Verschachtelte Objekte liefern seit `012-005-0003` **alle**
  Eigenschaften; begrenzt wird allein über `DB_NESTED_LEVELS`. Das ist frisches Verhalten und
  deshalb besonders festnagelnswert.

### Fehlerfälle — Ist-Zustand, nicht Wunsch
Task `000-000-0006` hält fest, dass manche Fehler heute als **HTTP 500** herauskommen statt als
404 oder 401. Diese Tests halten den **Ist-Zustand** fest und verweisen im Kommentar auf
`000-000-0006`. Wer dort repariert, dreht den Test bewusst um — statt ihn verwundert zu löschen.

## Acceptance criteria
- [x] `/api/single`: Erfolgsfall, unbekannte `id`, unbekannte `entity` und der
      `pim_blocked`-Fall sind je durch einen Test festgehalten.
- [x] `/api/list`: Erfolgsfall, Sortierung, mindestens ein Filter und die
      `properties`-Einschränkung sind festgehalten.
- [x] Der `partial`-Select trägt die `labelProperty` des verjointen Ziels — mit einem Verweis
      auf `012-005-0002` im Testkommentar.
- [x] Der Envelope und die vier Datumsfelder sind geprüft, nicht nur der Statuscode.
- [x] Die Verschachtelungstiefe verschachtelter Objekte ist festgehalten, inklusive der Wirkung
      von `DB_NESTED_LEVELS`.
- [x] Wo eine Antwort heute 500 statt 404/401 ist, hält der Test das so fest und verweist auf
      `000-000-0006`.

## Verification
```sh
CONTENTFLY_TEST_BASE_URL=http://127.0.0.1:8145 \
CONTENTFLY_TEST_ADMIN_PASS=dev-only-secret \
  ./custom/vendor/bin/phpunit --testsuite integration
```
Alle Tests grün. Zusätzlich zur Gegenprobe: Die neuen Tests einmal gegen den Stand **vor** dieser
Story laufen lassen — sie müssen dort identisch grün sein, sonst prüfen sie Wunschverhalten
statt Ist-Verhalten.

## Ergebnis — 13 Tests in `tests/Integration/Api/ReadApiTest.php`

Sechs Läufe hintereinander grün bei `executionOrder="random"`. Gesamtsuite: **39 Tests,
83 Assertions**.

### Was dabei über das Erwartete hinaus herauskam

**1. Die beiden Endpunkte haben unterschiedliche Envelopes.**

| | Schlüssel |
|---|---|
| `/api/single` | `ts`, `data`, `version`, `hash` |
| `/api/list` | `data`, `totalItems`, `version`, `hash` |

`single` trägt einen Zeitstempel, aber keine Trefferzahl; `list` umgekehrt. Inkonsistent,
aber Ist-Zustand — jetzt festgehalten.

**2. `/api/list` wertet `sortBy` und `sortOrder` der Entity überhaupt nicht aus.**
`Api::getList()` sortiert allein nach dem `order`-Parameter des Requests; fehlt er, bleibt es
bei `ORDER BY id DESC`. Die Entity-Settings landen im Schema und werden dort von niemandem
gelesen.

Das ist zuerst als **fehlschlagender Test** aufgefallen: Meine erste Fassung prüfte, dass
`PIM\Tag` (mit `sortBy="title", sortOrder="ASC"`) alphabetisch sortiert zurückkommt — und war
bei zufälliger Testreihenfolge mal grün, mal rot, weil die Zufalls-Ids die Reihenfolge
bestimmten. Der Test prüfte Wunschverhalten. Genau davor warnt die Abgrenzung des Epics; er ist
jetzt auf den Ist-Zustand gedreht, mit deterministischen Ids, die id-DESC und title-ASC
unterscheidbar machen.

**3. Daraus folgt ein Befund zu `012-005-0002`.** Diese Story hat `sortBy`, `sortOrder` und
`sortRestrictTo` mit der Begründung „Sortierung der API-Antworten" behalten. Nachgeprüft:

| Feld | Leser im Framework |
|---|---|
| `sortRestrictTo` | **ja** — `JoinBidirectionalType.php:64` |
| `sortBy` | **nein** — nur in das Schema geschrieben |
| `sortOrder` | **nein** — nur in das Schema geschrieben |

`sortBy` und `sortOrder` stehen damit auf derselben Stufe wie das gestrichene `readonly`: im
Schema veröffentlicht, im Framework wirkungslos. Der Unterschied ist, dass ein Sync-Client sie
aus dem Schema lesen und selbst anwenden könnte — anders als `readonly`, das nur eine Maske
bedient hätte. **Das ist hier nur festgehalten, nicht entschieden**; eine Korrektur wäre ein
eigener Task.

**4. `/api/single` mit unbekannter Id liefert `200` und `data: {"headers":{}}`** — kein 404,
und der Rumpf ist ein Artefakt. Zusammen mit „500 statt 401 ohne Token" und „500 statt 404 bei
unbekannter Entity" ist das die dritte Ausprägung von Task `000-000-0006`; alle drei sind mit
Verweis im Testkommentar festgehalten.

## Verification
```sh
CONTENTFLY_TEST_BASE_URL=http://127.0.0.1:8145 \
CONTENTFLY_TEST_ADMIN_PASS=dev-only-secret \
  ./custom/vendor/bin/phpunit --testsuite integration
```
- [x] Sechs vollstaendige Laeufe hintereinander gruen, bei zufaelliger Ausfuehrungsreihenfolge.
- [x] **Gegenprobe erbracht:** `git diff master -- lib custom bin index.php` zeigt ausser der
      installationsbedingten `custom/config.php` **keine** Aenderung — `lib/`, `bin/` und
      `index.php` sind byte-identisch zu `master`. Die Tests laufen also per Konstruktion gegen
      den Anwendungsstand von vor dieser Story und koennen kein Wunschverhalten pruefen.
- [x] Die Testdatenbank ist nach jedem Lauf leer (`tearDown()` raeumt die angelegten Tags ab).
