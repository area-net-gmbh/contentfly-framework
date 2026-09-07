---
id: 008-002-0001
title: Anlegen und Löschen festhalten
status: review
depends_on: []
---

# Anlegen und Löschen festhalten

## Context
Die beiden Enden des Lebenszyklus. `insert` erzeugt die ID und setzt mehrere Felder automatisch,
`delete` räumt neben dem Objekt selbst noch weiteres ab. Beides ist ungetestet, und beides ist
die Vorbedingung für jeden anderen Schreibtest dieser Story.

## Umfang

### `/api/insert`
- **ID-Erzeugung** nach der GUID-Strategie (`--db-strategy=guid` bei der Installation,
  `APPCMS_ID_TYPE`/`APPCMS_ID_STRATEGY`).
- **Form der Antwort**: Der Envelope trägt neben `data` auch die erzeugte `id` auf oberster
  Ebene — festzuhalten, weil es von `single` und `list` abweicht.
- **Automatisch gesetzte Felder**: `created`, `modified`, `userCreated`. Zu prüfen ist, dass sie
  gesetzt sind, nicht auf welchen exakten Zeitpunkt.
- **Pflichtfelder**: Was passiert bei einem fehlenden `entity`, einem unbekannten `entity`,
  fehlenden `data`.

### `/api/delete`
- Das Objekt ist danach über `/api/single` nicht mehr erreichbar.
- **i18n-Geschwister**: `Api::delete()` entfernt bei einer i18n-Entity die übrigen
  Sprachvarianten mit (`DELETE … WHERE e.id = :id AND NOT e.lang = :lang`). Ohne konfigurierte
  Mehrsprachigkeit ist das heute nicht auslösbar — siehe `008-002-0005` für den Umgang mit
  solchen Lücken.
- **Fehlerfälle**: unbekannte ID, unbekannte Entity, fehlender Token.

### Was hier bewusst nicht geprüft wird
Die `Log`-Zeile, die jedes Insert und jedes Delete erzeugt, gehört zu `008-002-0004`. Hier geht
es um die Endpunkte selbst.

## Acceptance criteria
- [x] `/api/insert`: Erfolgsfall festgehalten — erzeugte ID, Form der Antwort, das angelegte
      Objekt ist über `/api/single` abrufbar.
- [x] `created`, `modified` und `userCreated` sind nach dem Anlegen gesetzt.
- [x] Mindestens zwei Fehlerfälle von `insert` sind festgehalten (unbekannte Entity, fehlende
      Daten) — in ihrer **heutigen** Form, auch wenn das ein HTTP 500 ist (`000-000-0006`).
- [x] `/api/delete`: Das Objekt ist danach nicht mehr abrufbar.
- [x] Mindestens ein Fehlerfall von `delete` ist festgehalten.
- [x] Beide Routen weisen ohne Token ab.
- [x] Die Tests räumen nach sich auf — auch die über die API angelegten Objekte.

## Verification
```sh
CONTENTFLY_TEST_BASE_URL=http://127.0.0.1:8145 \
CONTENTFLY_TEST_ADMIN_PASS=dev-only-secret \
  ./custom/vendor/bin/phpunit --testsuite integration
```
Mehrere Läufe hintereinander, wegen `executionOrder="random"`. Danach ist die Testdatenbank in
dem Zustand, in dem der Lauf sie vorgefunden hat — bei Schreibtests ist das die eigentliche
Hürde, nicht die Zusicherung selbst.

## Ergebnis — 12 Tests in `tests/Integration/Api/WriteApiTest.php`

Gesamtsuite: **80 Tests, 192 Assertions**, drei Läufe grün.

### Befunde

**1. `insert` und `delete` haben je einen eigenen Envelope.** Die Reihe der Inkonsistenzen aus
`008-001` setzt sich fort:

| Endpunkt | Schlüssel |
|---|---|
| `/api/single` | `ts`, `data`, `version`, `hash` |
| `/api/list` | `data`, `totalItems`, `version`, `hash` |
| `/api/insert` | `ts`, **`id`**, `data`, `version`, `hash` |
| `/api/delete` | `ts`, **`id`**, `version`, `hash` — **kein `data`** |

**2. `insert` und `single` stellen Boolesche Werte unterschiedlich dar.** Die Antwort von
`insert` reicht den Rohwert durch (`isIntern: 0`), `single` serialisiert über die Typ-Klassen
(`isIntern: false`). Für einen Client, der beide Antworten mit demselben Code liest, ist das
eine Falle — jetzt festgehalten.

**3. Der Zugriffsschutz greift, die Antwort ist die falsche.** Ohne Token liefern `insert` und
`delete` HTTP 500 statt 401 — aber **es entsteht nichts und es verschwindet nichts.** Beides
ist gegen die Datenbank geprüft, nicht nur gegen die Antwort. Das ist der Punkt, an dem
`000-000-0006` von einem Schönheitsfehler zu einer Sicherheitsfrage würde.

### Nebenbefund: `FileApiTest` räumt nicht auf

Beim Prüfen des eigenen Aufräumens aufgefallen: `FileApiTest` legt pro Lauf 8 Dateien, 8
`pim_file`- und 9 `pim_log`-Zeilen an und entfernt sie nie. Bestand inzwischen: 305 Datei-Zeilen,
306 Dateien auf der Platte. Stammt aus Story `012-003-0003`, als es die gemeinsame Basisklasse
noch nicht gab. **Task `000-000-0008`** angelegt; hier nicht repariert.

Die Tests dieses Tasks räumen vollständig ab — `pim_tag` und die zugehörigen `pim_log`-Zeilen
sind nach jedem Lauf auf dem Ausgangsstand.

## Verification
- [x] Drei vollständige Läufe grün bei zufälliger Ausführungsreihenfolge.
- [x] `pim_tag` nach den Läufen: 0 Zeilen. Die verbliebenen `pim_log`-Zeilen stammen
      nachweislich aus `FileApiTest` (`model_name = PIM\File`), nicht aus diesem Task.
- [x] Für „ohne Token entsteht nichts" und „ohne Token verschwindet nichts" wurde **gegen die
      Datenbank** geprüft, nicht gegen die API-Antwort.
