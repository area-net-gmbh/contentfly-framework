---
id: 013-004-0003
title: Rollen- und Gruppenabbildung
status: todo
depends_on: [013-004-0001]
---

# Rollen- und Gruppenabbildung

## Context
Heute nimmt `createManagedUser($alias, $group, $isAdmin)` Gruppe und Adminflag als Argumente
entgegen — das heisst, jedes Projekt entscheidet fuer sich, wie es von „das Fremdsystem sagt,
der Benutzer ist in der Gruppe *Redaktion*" zu einer Contentfly-Gruppe kommt. Was dabei
herauskommt, steht in Projektcode, den niemand mehr liest.

**Die Abbildung gehoert an eine Stelle und in die Konfiguration**, nicht in jede Anmeldung neu.
Ein Provider liefert, was das Fremdsystem sagt — Gruppennamen, Attribute —, und die Abbildung
entscheidet daraus.

**Was NICHT dazugehoert:** Das Contentfly-Berechtigungsmodell umzubauen. `Permission`,
`I18nPermission` und `Group` bleiben, wie sie sind; abgebildet wird auf sie, nicht an ihrer
Stelle. Dieselbe Grenze wie in `013-002-0001`.

## Acceptance criteria
- [ ] Was ein Provider an Gruppen oder Attributen liefert, wird an einer Stelle auf Contentfly-Gruppen und das Adminflag abgebildet.
- [ ] Die Abbildung ist konfigurierbar, ohne Framework-Code zu aendern.
- [ ] Liefert das Fremdsystem nichts Passendes, bekommt der Benutzer die Vorgabe — und **nicht** Adminrechte. Ein Test haelt das fest.
- [ ] Aendert sich die Zuordnung im Fremdsystem, wirkt sie bei der naechsten Anmeldung; ein Test meldet denselben Benutzer zweimal mit verschiedenen Gruppen an.
- [ ] `Permission`, `I18nPermission` und `Group` sind unveraendert.

## Verification
Unit-Tests der Abbildung fuer Treffer, Nichttreffer und Mehrfachtreffer. Ein Integrationstest,
der einen Benutzer zweimal mit verschiedener Zuordnung anmeldet und die Gruppe in `pim_user`
nachsieht. Volle Suite.
