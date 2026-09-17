---
id: 011-003-0000
title: Vorlage und Doku auf den Zielzustand bringen
status: todo
depends_on: [011-001-0000]
---

# Vorlage und Doku auf den Zielzustand bringen

## Goal
**Was ein Projekt zuerst liest, beschreibt den neuen Stand — nicht den alten.** Nach acht Epics
Umbau sind die Vorlage `custom/` und die Dokumente an mehreren Stellen älter als der Code.

**Die Vorlage** zeigt wieder eine brauchbare Referenz: Example-Controller, -Entity, -Service,
-Command und die Registrierungen in `custom/app.php` laufen auf der neuen Version und zeigen,
wie man es heute macht — Provider statt `LoginManager`, `RouteCollector` statt
`controllers_factory`, Listener über `$app->on()`, eigene Konfigurationsschlüssel.

**Die Doku** wird nachgezogen: `README.md`, `an_project/docs/runbook.md`, `technical.md` und
`dev-guide.md`. Der Migrationsleitfaden aus Epic `007` ist bereits am Bestandsprojekt geschärft
worden; hierher gehört, was `011-001` am Envelope ändert.

**Warum nach `011-001`:** Die Doku beschreibt sonst einen Vertrag, der sich im selben Epic noch
ändert.

**Fertig, wenn** ein Entwickler nach dem Lesen von README und Runbook eine Installation
aufsetzen kann und die Beispiele in `custom/` das tun, was sie behaupten.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. Geschnitten beim Start der Story. -->
- [ ] 011-003-0001 — Die Vorlage auf den Zielzustand bringen
- [ ] 011-003-0002 — Den README auf Contentfly 2 bringen
- [ ] 011-003-0003 — dev-guide und technical auf den echten Weg bringen
