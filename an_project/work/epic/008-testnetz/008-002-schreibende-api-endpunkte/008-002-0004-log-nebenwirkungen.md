---
id: 008-002-0004
title: Log-Nebenwirkungen festhalten
status: review
depends_on: [008-002-0001]
---

# Log-Nebenwirkungen festhalten

## Context
Jede Schreiboperation legt eine Zeile in `pim_log` an. Das ist die einzige Nebenwirkung der
Schreibseite, die **Daten persistiert, ohne in der Antwort sichtbar zu sein** — und damit
diejenige, die beim Kernel-Tausch am ehesten unbemerkt verschwindet.

## Umfang

### Die vier Modi
`Api` schreibt Log-Zeilen mit `mode` aus `Log::INSERTED` (`INS`), `UPDATED` (`UPT`),
`DELETED` (`DEL`) und `USERDEL`. Je Modus ist festzuhalten, dass die Zeile entsteht und was
in `model_name` und `model_id` steht.

`USERDEL` entsteht, wenn einem Objekt ein Benutzer aus der `users`-Liste entzogen wird — das
ist der Virtualjoin aus `Base`, den `012-005-0001` als datenrelevant behalten hat.

### `model_label` — der eigentliche Grund für diesen Task
`Api` füllt `model_label` aus der `labelProperty` der Entity. **Genau dieses Feld stand in
`012-005-0002` auf der Streichliste** und wurde nur behalten, weil es hier persistiert wird und
weil es den `partial`-Select steuert. Ein Test darauf ist der Nachweis, dass die Entscheidung
richtig war — und der Schutz davor, sie versehentlich zurückzunehmen.

`PIM\Tag` trägt `labelProperty="title"`; ein angelegter und wieder gelöschter Tag muss also eine
Log-Zeile mit `model_label = <Titel>` hinterlassen.

### Gelesen wird über die Datenbank
Es gibt keinen API-Endpunkt, der Log-Zeilen ausliefert (`PIM\Log` steht auf der
Ausschlussliste von `getDeleted()`, siehe `008-001-0004`). Die Zusicherungen laufen deshalb über
`pdo()` gegen `pim_log`. Spalten dort: `model_name`, `model_id`, `model_label`, `mode` — nicht
wie die Entity-Properties benannt.

## Acceptance criteria
- [x] Für `INS`, `UPT` und `DEL` ist je belegt, dass eine Log-Zeile mit den erwarteten
      `model_name`- und `model_id`-Werten entsteht.
- [x] `model_label` ist aus der `labelProperty` gefüllt — mit Verweis auf `012-005-0002` im
      Testkommentar.
- [x] Für `USERDEL` ist das Verhalten festgehalten, oder es ist begründet festgehalten, dass es
      im heutigen Aufbau nicht auslösbar ist.
- [x] Die Tests räumen die von ihnen erzeugten Log-Zeilen ab — sonst wächst `pim_log` mit
      jedem Lauf.

## Verification
Mehrere vollständige Läufe grün, und `pim_log` enthält danach keine Zeile aus dem Testlauf mehr.
Gegenprobe für `model_label`: `labelProperty` in `PIM\Tag` versuchsweise entfernen — der Test
muss rot werden. Danach zurücknehmen.

## Ergebnis — 9 Tests in `tests/Integration/Api/LogSideEffectApiTest.php`

Gesamtsuite: **102 Tests, 248 Assertions**, drei Läufe grün.

### `model_label` — die Entscheidung aus 012-005-0002 ist jetzt geschützt

Belegt: `model_label` wird aus der `labelProperty` der Entity gefüllt, und **jede Zeile hält
den Wert fest, der zum Zeitpunkt ihrer Operation galt** — die `INS`-Zeile den ursprünglichen
Titel, die `UPT`-Zeile den geänderten.

**Gegenprobe durchgeführt**, wie die Verification es verlangt: `labelProperty` versuchsweise
aus `PIM\Tag` entfernt →

```
model_label kommt aus der labelProperty der Entity — siehe 012-005-0002
FAILURES!  Tests: 9, Assertions: 21, Failures: 2.
```

Danach zurückgenommen, `git diff` auf `Entity/Tag.php` leer, alle neun wieder grün. Der Test
fängt den Rückfall also wirklich — er ist nicht bloß zufällig grün.

### `USERDEL` ist auslösbar
Das Entziehen eines Benutzers aus der `users`-Liste — dem Virtualjoin aus `Base`, den
`012-005-0001` als datenrelevant behalten hat — erzeugt eine eigene Log-Zeile mit diesem Modus.
Damit sind alle vier Modi abgedeckt, nicht nur drei.

### Ein Befund nebenbei: die Reihenfolge ist nicht rekonstruierbar
Der erste Anlauf des Lebenszyklus-Tests scheiterte an einer falschen Annahme von mir — ich
hatte `ORDER BY created, mode` erwartet und `INS, UPT, DEL` unterstellt, bekam aber
`DEL, INS, UPT`. Ursache: **`pim_log.created` ist ein `DATETIME` mit Sekundenauflösung.** Ein
Lebenszyklus, der in derselben Sekunde abläuft — und das ist bei je einem API-Aufruf der
Normalfall — hinterlässt Zeilen mit identischem Zeitstempel. Aus dem Protokoll allein lässt
sich dann nicht sagen, was zuerst geschah.

Der Test prüft jetzt die Menge der Modi statt ihrer Reihenfolge, und ein eigener Test hält die
Sekundenauflösung als solche fest.

### Was nicht protokolliert wird
Ein ohne Token abgewiesener Schreibversuch hinterlässt **keine** Log-Zeile — geprüft, weil ein
Protokoll, das Fehlversuche verschweigt, bei einer Sicherheitsfrage die falsche Auskunft gäbe.

## Verification
- [x] Drei vollständige Läufe grün; `pim_tag` und die `PIM\Tag`-Log-Zeilen danach auf null.
- [x] Gegenprobe für `model_label` durchgeführt (siehe oben); `Entity/Tag.php` unverändert.
- [x] Die Tests filtern durchgehend nach `model_id` — auf diesem Branch leakt `FileApiTest`
      noch (`000-000-0008` ist ein anderer Branch), `pim_log` enthält also Fremdzeilen.
