---
id: 000-000-0008
title: FileApiTest räumt nicht auf — Testdatenbank und data/files wachsen mit jedem Lauf
status: todo
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
- [ ] `FileApiTest` meldet die von ihm angelegten Dateien über
      `IntegrationTestCase::nachTestLoeschen()` zum Aufräumen an — `pim_file` und die
      zugehörigen `pim_log`-Zeilen.
- [ ] Die Dateien unter `data/files` werden ebenfalls entfernt; dafür braucht es entweder eine
      Erweiterung der Basisklasse (etwa `nachTestDateiLoeschen()`) oder einen eigenen
      `tearDown()` in `FileApiTest`.
- [ ] Ein vollständiger Lauf lässt `pim_file`, `pim_log` und `data/files` unverändert.
- [ ] **Keine Zusicherung von `FileApiTest` ist verändert** — es geht um Aufräumen, nicht um
      andere Tests.
- [ ] Die vorhandenen Altlasten sind einmalig entfernt, und im Runbook steht, wie man die
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
