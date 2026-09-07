---
id: 008-001-0004
title: Sync-Endpunkte /api/all, /api/deleted und /api/count
status: todo
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
- [ ] `/api/all`: Erfolgsfall festgehalten.
- [ ] `excludeFromSync` ist in **beiden** Richtungen geprüft — ausgenommene Entity fehlt,
      normale Entity ist enthalten. Verweis auf `012-005-0002` im Kommentar.
- [ ] `/api/deleted`: Form der Antwort, Zeitstempel-Semantik und das Verhalten ohne Zeitstempel
      sind festgehalten.
- [ ] `/api/count` und `/api/list` liefern unter denselben Parametern dieselbe Menge — durch
      einen Test belegt, der beide aufruft und vergleicht.
- [ ] Alle drei Routen sind ohne Token abgewiesen.

## Verification
```sh
CONTENTFLY_TEST_BASE_URL=http://127.0.0.1:8145 \
CONTENTFLY_TEST_ADMIN_PASS=dev-only-secret \
  ./custom/vendor/bin/phpunit --testsuite integration
```
Für `excludeFromSync` braucht es eine Entity mit und eine ohne das Flag. Ob dafür eine
Test-Entity angelegt wird oder eine bestehende taugt, ist beim Umsetzen zu entscheiden.
