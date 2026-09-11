---
id: 007-001-0005
title: Eine Installation aus dem Paket bauen und die Vorlage nachziehen
status: todo
depends_on: [007-001-0004]
---

# Eine Installation aus dem Paket bauen und die Vorlage nachziehen

## Context
**Die Probe aufs Exempel.** Die vier Tasks davor haben getrennt, umgestellt und zugeordnet — ob
das Paket tatsächlich beziehbar ist, zeigt erst ein Projekt, das es bezieht, statt es zu kopieren.

Das ist der Unterschied, an dem die ganze Story hängt: Ein Manifest, das `type: library` sagt,
ist noch kein Paket, das sich einbinden lässt. Jeder Pfad, der noch stillschweigend annimmt, im
selben Baum zu liegen, fällt genau hier auf und sonst nirgends.

**`custom/` bleibt die Referenz.** Die Vorlage zeigt, wie ein Projekt auf der neuen Version
aussieht — sie wird mitgezogen, nicht stehen gelassen. Was hier entsteht, ist zugleich der Text,
aus dem `007-004` den Abschnitt „von der Kopie auf das Paket" schreibt.

## Acceptance criteria
- [ ] Eine frische Installation entsteht aus dem Paket, bezogen über Composer, nicht durch Kopieren des Baums.
- [ ] Die volle Suite läuft gegen diese Installation, nicht nur gegen das Entwicklungs-Repo.
- [ ] `custom/` zeigt den Zielzustand — die Beispiel-Artefakte sind mitgezogen und laufen.
- [ ] Was ein Bestandsprojekt zu tun hat, um von Kopie auf Paket zu wechseln, ist so beschrieben, dass `007-004` es übernehmen kann.
- [ ] Was dabei nicht glatt lief, steht dabei. Eine Anleitung, die nur den geglückten Weg kennt, hilft beim ersten Stolpern nicht.

## Verification
In einem leeren Verzeichnis ein Projekt anlegen, das Paket über eine `path`-Quelle beziehen,
`appcms:install` fahren und die volle Suite dagegen laufen lassen. Der Baum des
Entwicklungs-Repos wird dabei nicht kopiert — wenn doch etwas fehlt, ist genau das der Befund.
