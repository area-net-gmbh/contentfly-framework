---
id: 009-004-0000
title: Vorlage und Dokumentation auf den neuen Kernel nachziehen
status: done
depends_on: [009-002-0000]
---

# Vorlage und Dokumentation auf den neuen Kernel nachziehen

## Goal
Nach dieser Story beschreiben die Dokumente den Stand, der da ist, und `custom/` zeigt ihn
vor. Solange beides den Silex-Stand beschreibt, ist der Umbau für ein Bestandsprojekt nicht
nachvollziehbar — und `007` hat keine Grundlage.

Betroffen:

- **`custom/app.php`** — die vier Muster (Service, Route, Middleware, Command) laufen auf dem
  neuen Kernel und heißen dort weiterhin so. Was sich in der Formulierung ändert, ändert sich
  sichtbar.
- **`custom/Command/ExampleCommand.php`** — die in `technical.md` festgehaltene Inkonsistenz
  ist zu entscheiden: Das Beispiel erbt von `Symfony\…\Command` und erwartet `$app` im
  Konstruktor, der `ConsoleManager` nimmt aber nur `CustomCommand`. Entweder das Beispiel
  umstellen oder den Manager öffnen — der Kernel-Wechsel ist der Zeitpunkt, an dem die Frage
  ohnehin auf dem Tisch liegt.
- **`an_project/docs/technical.md`** — die Abschnitte über Silex-Prioritäten, den
  `RouteManager` und den Session-Bootstrap in die Vergangenheitsform beziehungsweise auf den
  neuen Mechanismus.
- **`an_project/docs/architecture.md`**, **`dev-guide.md`**, **`runbook.md`**, **`README.md`**
  und **`tests/README.md`** — überall dort, wo Silex als laufender Stand beschrieben ist.
- **`an_project/docs/breaking-changes.md`** — was der Schnitt für ein Bestandsprojekt bedeutet,
  je Bruchstelle.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
- [x] 009-004-0004 — before/after/error dürfen den Dispatcher nicht einfrieren
- [x] 009-004-0001 — Die Vorlage custom/ auf den neuen Kernel nachziehen
- [x] 009-004-0002 — Die Dokumente auf den neuen Kernel nachziehen
- [x] 009-004-0003 — Die Bruchstellen des Kernel-Wechsels festhalten
