---
id: 008-001-0002
title: /api/single und /api/list festhalten
status: todo
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
- [ ] `/api/single`: Erfolgsfall, unbekannte `id`, unbekannte `entity` und der
      `pim_blocked`-Fall sind je durch einen Test festgehalten.
- [ ] `/api/list`: Erfolgsfall, Sortierung, mindestens ein Filter und die
      `properties`-Einschränkung sind festgehalten.
- [ ] Der `partial`-Select trägt die `labelProperty` des verjointen Ziels — mit einem Verweis
      auf `012-005-0002` im Testkommentar.
- [ ] Der Envelope und die vier Datumsfelder sind geprüft, nicht nur der Statuscode.
- [ ] Die Verschachtelungstiefe verschachtelter Objekte ist festgehalten, inklusive der Wirkung
      von `DB_NESTED_LEVELS`.
- [ ] Wo eine Antwort heute 500 statt 404/401 ist, hält der Test das so fest und verweist auf
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
