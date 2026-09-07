---
id: 008-002-0001
title: Anlegen und Löschen festhalten
status: todo
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
- [ ] `/api/insert`: Erfolgsfall festgehalten — erzeugte ID, Form der Antwort, das angelegte
      Objekt ist über `/api/single` abrufbar.
- [ ] `created`, `modified` und `userCreated` sind nach dem Anlegen gesetzt.
- [ ] Mindestens zwei Fehlerfälle von `insert` sind festgehalten (unbekannte Entity, fehlende
      Daten) — in ihrer **heutigen** Form, auch wenn das ein HTTP 500 ist (`000-000-0006`).
- [ ] `/api/delete`: Das Objekt ist danach nicht mehr abrufbar.
- [ ] Mindestens ein Fehlerfall von `delete` ist festgehalten.
- [ ] Beide Routen weisen ohne Token ab.
- [ ] Die Tests räumen nach sich auf — auch die über die API angelegten Objekte.

## Verification
```sh
CONTENTFLY_TEST_BASE_URL=http://127.0.0.1:8145 \
CONTENTFLY_TEST_ADMIN_PASS=dev-only-secret \
  ./custom/vendor/bin/phpunit --testsuite integration
```
Mehrere Läufe hintereinander, wegen `executionOrder="random"`. Danach ist die Testdatenbank in
dem Zustand, in dem der Lauf sie vorgefunden hat — bei Schreibtests ist das die eigentliche
Hürde, nicht die Zusicherung selbst.
