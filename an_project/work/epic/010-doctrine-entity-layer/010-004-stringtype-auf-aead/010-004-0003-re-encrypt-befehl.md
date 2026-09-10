---
id: 010-004-0003
title: Der Re-Encrypt-Befehl mit Trockenlauf und Rückweg
status: todo
depends_on: [010-004-0002]
---

# Der Re-Encrypt-Befehl mit Trockenlauf und Rückweg

## Context
Ein Befehl, der vorhandene Werte vom alten auf das neue Format bringt. Er läuft **in dieser
Story auf keinen echten Daten** — im Baum gibt es keine Entity mit `encoded=true`. Belegt wird
er gegen eine eigene Testentity.

**Trockenlauf und Rückweg gehören zur Abnahme, nicht zur Kür.** Der Befehl läuft später in
fremden Bestandsprojekten auf deren Daten. Eine Migration, die man nicht vorher ansehen und
nicht zurücknehmen kann, wird zu Recht nicht ausgeführt.

**Was er finden muss:** jede Eigenschaft mit `encoded=true` in jeder Entity, auch in denen eines
Projekts — er darf nicht auf die Framework-Entities eingeschränkt sein.

## Acceptance criteria
- [ ] Der Befehl findet die betroffenen Eigenschaften über das Schema, nicht über eine feste Liste.
- [ ] `--dry-run` zeigt, was er täte, und ändert nichts — nachgewiesen an unveränderten Datenbankzeilen.
- [ ] Werte, die bereits im neuen Format liegen, werden übersprungen; ein zweiter Lauf ändert nichts.
- [ ] Der Rückweg ist beschrieben und ausführbar: was zu sichern ist, bevor er läuft, und wie man zurückkommt.
- [ ] Er arbeitet in Stapeln und hält den Speicher konstant — eine Tabelle mit vielen Zeilen darf ihn nicht umbringen.
- [ ] Ein Fehler mitten im Lauf lässt keinen halb umgeschlüsselten Datensatz zurück.

## Verification
Eine Testentity mit `encoded=true`, gefüllt mit Werten im alten Format. Dann: Trockenlauf
(nichts ändert sich), echter Lauf (alle Werte neu), zweiter Lauf (nichts mehr zu tun), und ein
Lesevorgang über die API, der den Klartext unverändert zurückgibt.
