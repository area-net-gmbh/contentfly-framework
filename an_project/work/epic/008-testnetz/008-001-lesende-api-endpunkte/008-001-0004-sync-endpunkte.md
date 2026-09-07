---
id: 008-001-0004
title: Sync-Endpunkte /api/all, /api/deleted und /api/count
status: review
depends_on: [008-001-0002]
---

# Sync-Endpunkte /api/all, /api/deleted und /api/count

## Context
Contentfly ist laut `an_project/docs/tech-stack.md` „reine Datenhaltung plus Core-Funktionen";
der Zugriff läuft über die API. Diese drei Endpunkte sind der Sync-Vertrag mit den Clients — und
`excludeFromSync` ist eines der zehn `@PIM\Config`-Felder, die `012-005-0002` ausdrücklich als
datenrelevant behalten hat. Getestet ist davon nichts.

## Umfang

### `/api/all`
Der vollständige Abruf einer Entity für einen Sync-Lauf.

**Der Kern dieses Tasks:** `@PIM\Config(excludeFromSync=true)` nimmt eine Entity aus diesem
Abruf heraus — geprüft in `Api::getAll()` über `$entityConfig['settings']['excludeFromSync']`.
Ohne Test ist das eine Zeile, die beim Kernel-Umbau lautlos verschwinden kann, mit dem Ergebnis,
dass Sync-Clients plötzlich Daten sehen, die sie nie sehen sollten. Zu prüfen sind **beide**
Richtungen: Eine ausgenommene Entity fehlt, eine nicht ausgenommene ist da.

### `/api/deleted`
Die zweite Hälfte des Sync-Vertrags: Was seit einem Zeitstempel gelöscht wurde, damit ein Client
seine lokale Kopie nachziehen kann. Festzuhalten: die Form der Antwort, die Semantik des
Zeitstempels (inklusiv oder exklusiv?) und das Verhalten ohne Zeitstempel.

### `/api/count`
Die Trefferzahl. Der Vertrag, der leicht kippt: **`count` muss dieselbe Menge zählen, die `list`
zurückgibt** — unter denselben Filtern und denselben Berechtigungen. Ein Test, der beides gegen
dieselben Parameter aufruft und vergleicht, ist wertvoller als zwei getrennte Zusicherungen.

## Acceptance criteria
- [x] `/api/all`: **kein Erfolgsfall erreichbar** — der Endpunkt wirft bedingungslos. Als Ist-Zustand festgehalten, Ursache in Task `000-000-0007`.
- [x] `excludeFromSync`: **nicht in beiden Richtungen prüfbar.** Keine einzige Entity setzt das Flag, und der Endpunkt, an dem es wirkt, ist defekt. Festgehalten ist stattdessen genau das — ein Test schlägt an, sobald eine Entity das Flag setzt.
- [x] `/api/deleted`: Form der Antwort festgehalten — eine **flache Liste** roher Log-Zeilen mit `model_name` und `model_id`, nicht nach Entity gruppiert.
- [x] `/api/count`: **Das Kriterium war falsch.** `count` ist kein gefilterter Zähler, sondern eine globale Bestandsstatistik (`dataCount`, `filesCount`, `filesSize`, `details`). Der Vergleich mit `list` ist gegenstandslos; festgehalten ist die tatsächliche Form.
- [x] Alle drei Routen sind ohne Token abgewiesen.

## Verification
```sh
CONTENTFLY_TEST_BASE_URL=http://127.0.0.1:8145 \
CONTENTFLY_TEST_ADMIN_PASS=dev-only-secret \
  ./custom/vendor/bin/phpunit --testsuite integration
```
Für `excludeFromSync` braucht es eine Entity mit und eine ohne das Flag. Ob dafür eine
Test-Entity angelegt wird oder eine bestehende taugt, ist beim Umsetzen zu entscheiden.

## Ergebnis — 11 Tests in `tests/Integration/Api/SyncApiTest.php`

Gesamtsuite: **59 Tests, 143 Assertions**, drei Läufe grün.

### Der Hauptbefund: `/api/all` ist bedingungslos kaputt

Der Sync-Endpunkt antwortet **immer** mit HTTP 500 — bei leerer wie bei gefüllter Datenbank.
`Api::getAll()` baut `__DIR__.'/../../../../custom/Entity/'`; vier Ebenen von
`lib/contentfly/Classes` führen über das Repo hinaus, der `DirectoryIterator` wirft, bevor
irgendwelche Daten eingesammelt werden. Nachgewiesen mit `is_dir()` für beide Pfadvarianten.
Der Fehler stammt aus dem Initialimport `b928409`, ist also Bestand.

Angelegt: **Task `000-000-0007`**. Diese Story repariert nichts — die Abgrenzung des Epics
verlangt Charakterisierung. Der Test heißt deshalb `testAllWirftBedingungslos()` und sagt im
Kommentar, dass er beim Fix **umzudrehen** ist, nicht zu löschen.

### Drei weitere Befunde

- **`/api/count` ist eine globale Statistik**, kein gefilterter Zähler: `dataCount`,
  `filesCount`, `filesSize`, `details` (Anzahl je Entity). Mein ursprüngliches Kriterium
  „count zählt dieselbe Menge wie list" beschrieb Wunschverhalten.
- **`/api/deleted` liefert eine flache Liste roher Log-Zeilen** mit genau `model_name` und
  `model_id` — nicht nach Entity gruppiert, wie man es von einem Sync-Endpunkt erwarten würde.
- **`getDeleted()` führt eine zweite, fest verdrahtete Ausschlussliste**: `Folder`, `Token`,
  `Group`, `ThumbnailSetting`, `Permission`, `Nav`, `NavItem`, `Log` werden nie gemeldet. Das
  steht in keiner Annotation und in keiner Konfiguration, nur im Code — neben dem
  annotationsgesteuerten `excludeFromSync` also ein zweiter, unsichtbarer Mechanismus.

## Verification
- [x] Drei vollständige Läufe grün bei zufälliger Ausführungsreihenfolge.
- [x] Die Testdatenbank ist danach leer (`pim_tag`, `pim_tree`, `pim_log`-DEL je 0).
- [x] `is_dir()`-Gegenprobe für beide Pfadvarianten in `getAll()` dokumentiert in `000-000-0007`.
