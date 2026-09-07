---
id: 000-000-0008
title: FileApiTest räumt nicht auf — Testdatenbank und data/files wachsen mit jedem Lauf
status: review
depends_on: []
---

# FileApiTest räumt nicht auf — Testdatenbank und data/files wachsen mit jedem Lauf

## Context
Beim Aufbau des Schreib-Testnetzes (`008-002-0001`) aufgefallen: `tests/Integration/Api/FileApiTest.php`
legt bei jedem Lauf Dateien an und entfernt sie nie. Gemessen über einen einzelnen Lauf der
Datei:

| | vorher | nachher | pro Lauf |
|---|---|---|---|
| `pim_file` | 297 | 305 | **+8** |
| `pim_log` | 27 | 36 | **+9** |
| Dateien unter `data/files` | — | 306 | **+8** |

Die Zahlen sind bereits das Ergebnis vieler Läufe an einem Tag. Die Tests selbst sind grün und
korrekt — sie räumen nur nicht ab.

Der Task stammt aus Story `012-003-0003`, die das Testnetz für die Datei-API gebaut hat. Damals
gab es noch keine gemeinsame Basisklasse; seit `008-001-0001` gibt es mit
`IntegrationTestCase::nachTestLoeschen()` das passende Werkzeug.

## Warum das mehr ist als Unordnung
- **Die Läufe werden langsamer.** `/api/count` und `/api/list` zählen über eine Tabelle, die
  unbegrenzt wächst.
- **Log-Zusicherungen werden unschärfer.** Task `008-002-0004` prüft `pim_log`; je mehr
  Altlasten dort liegen, desto sorgfältiger muss jede Abfrage filtern.
- **Ein frischer Checkout verhält sich anders als eine gewachsene Umgebung.** Genau diesen
  Unterschied soll ein Testnetz nicht haben.
- `data/files` ist per `.gitignore` ausgenommen, die Dateien landen also nicht im Repo — sie
  bleiben aber auf der Platte jedes Entwicklers liegen.

## Acceptance criteria
- [x] `FileApiTest` meldet die von ihm angelegten Dateien über
      `IntegrationTestCase::nachTestLoeschen()` zum Aufräumen an — `pim_file` und die
      zugehörigen `pim_log`-Zeilen.
- [x] Die Dateien unter `data/files` werden ebenfalls entfernt; dafür braucht es entweder eine
      Erweiterung der Basisklasse (etwa `nachTestDateiLoeschen()`) oder einen eigenen
      `tearDown()` in `FileApiTest`.
- [x] Ein vollständiger Lauf lässt `pim_file`, `pim_log` und `data/files` unverändert.
- [x] **Keine Zusicherung von `FileApiTest` ist verändert** — es geht um Aufräumen, nicht um
      andere Tests.
- [x] Die vorhandenen Altlasten sind einmalig entfernt, und im Runbook steht, wie man die
      Testdatenbank zurücksetzt (`docker compose down -v` steht dort bereits).

## Verification
```sh
# Zähler vorher
docker exec contentfly-db mysql -ucontentfly -pcontentfly contentfly -e \
  "SELECT (SELECT COUNT(*) FROM pim_file) f, (SELECT COUNT(*) FROM pim_log) l;"
find data/files -type f | wc -l

CONTENTFLY_TEST_BASE_URL=http://127.0.0.1:8145 \
CONTENTFLY_TEST_ADMIN_PASS=dev-only-secret \
  ./custom/vendor/bin/phpunit

# Zähler nachher — muessen identisch sein
```

## Ergebnis

**Ein vollständiger Lauf lässt die Umgebung unverändert.** Drei Läufe hintereinander:

```
  vor allem:  pim_file=0 pim_log=0 pim_tag=0  Dateien=1
  Lauf 1: OK (68 tests, 159 assertions)
            pim_file=0 pim_log=0 pim_tag=0  Dateien=1
  Lauf 2: OK (68 tests, 159 assertions)
            pim_file=0 pim_log=0 pim_tag=0  Dateien=1
  Lauf 3: OK (68 tests, 159 assertions)
            pim_file=0 pim_log=0 pim_tag=0  Dateien=1
```

Die eine verbliebene Datei ist `data/files/.gitkeep` — die gehört dorthin.

### Was geändert wurde

**`IntegrationTestCase`** bekommt `nachTestVerzeichnisLoeschen()` und räumt in `tearDown()`
neben den Datenbankzeilen auch angemeldete Verzeichnisse ab, rekursiv. Ein fehlendes
Verzeichnis ist dabei kein Fehler — sonst würde ein bereits gescheiterter Test noch einen
zweiten Fehler erzeugen.

**`FileApiTest::upload()`** meldet nach jedem gelungenen Upload dreierlei an: die `pim_file`-Zeile,
die dabei entstandenen `pim_log`-Zeilen und das Verzeichnis `data/files/<id>`. Bei einem
abgewiesenen Upload gibt es keine Id — dann ist nichts anzumelden, und der Test für den
Upload ohne Token funktioniert unverändert.

**Keine einzige Zusicherung wurde angefasst.** Die Suite meldet vor und nach dem Umbau
identisch 68 Tests und 159 Assertions.

### Die Altlasten
353 `pim_file`-Zeilen, 90 `pim_log`-Zeilen und 354 Dateien auf der Platte. Die Prüfung, ob es
wirklich nur Testrückstände waren, ergab ausschließlich die Fixture-Namen aus `FileApiTest` —
`probe.txt` (48×), `bild.txt` (44×), `oeffentlich.txt` (44×), `rueckgabe.txt` (44×) und weitere.
Einmalig entfernt.

## Verification
- [x] Drei vollständige Läufe; `pim_file`, `pim_log`, `pim_tag` und die Dateizahl bleiben
      unverändert.
- [x] Testzahl vor und nach dem Umbau identisch (68 Tests, 159 Assertions) — es wurde
      aufgeräumt, nicht umgeschrieben.
- [x] Die Altlasten sind entfernt; `data/files` enthält nur noch `.gitkeep`.
- [x] `an_project/docs/runbook.md` beschreibt bereits, wie die Testdatenbank zurückgesetzt wird
      (`docker compose down -v`) — dort war nichts zu ergänzen.
