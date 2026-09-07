---
id: 008-002-0004
title: Log-Nebenwirkungen festhalten
status: todo
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
- [ ] Für `INS`, `UPT` und `DEL` ist je belegt, dass eine Log-Zeile mit den erwarteten
      `model_name`- und `model_id`-Werten entsteht.
- [ ] `model_label` ist aus der `labelProperty` gefüllt — mit Verweis auf `012-005-0002` im
      Testkommentar.
- [ ] Für `USERDEL` ist das Verhalten festgehalten, oder es ist begründet festgehalten, dass es
      im heutigen Aufbau nicht auslösbar ist.
- [ ] Die Tests räumen die von ihnen erzeugten Log-Zeilen ab — sonst wächst `pim_log` mit
      jedem Lauf.

## Verification
Mehrere vollständige Läufe grün, und `pim_log` enthält danach keine Zeile aus dem Testlauf mehr.
Gegenprobe für `model_label`: `labelProperty` in `PIM\Tag` versuchsweise entfernen — der Test
muss rot werden. Danach zurücknehmen.
