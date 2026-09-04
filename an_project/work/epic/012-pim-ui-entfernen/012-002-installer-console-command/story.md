---
id: 012-002-0000
title: Installer als Console-Command
status: in-progress
depends_on: []
---

# Installer als Console-Command

## Goal
Der `InstallController` (203 Zeilen) und `install.twig` sind der einzige Weg, ein frisches
Contentfly aufzusetzen. Mit der Oberfläche fallen sie weg — ohne Ersatz hätte das Framework
keinen Installationsweg mehr, und ein Bestandsprojekt könnte auf der neuen Version nicht neu
aufsetzen.

**Umfang**
- Feststellen, was der `InstallController` tatsächlich tut: Schema anlegen, Basisdaten/erster
  Benutzer, Konfigurationsdateien, Verzeichnisse und Rechte, Plugin-Registrierung.
- Diese Schritte als **Console-Command** abbilden (`ConsoleManager` ist vorhanden, 14 Commands
  laufen bereits über Symfony Console).
- Nicht-interaktiv ausführbar machen — Parameter über Optionen oder Umgebungsvariablen, damit die
  Installation skript- und CI-fähig ist. Das kann die Twig-Maske nicht und ist der eigentliche
  Gewinn dieses Schnitts.
- Wiederholten Aufruf abfangen: erkennen, ob bereits installiert ist, statt bestehende Daten zu
  überschreiben.
- `InstallController`, `install.twig` und das dann leere `lib/contentfly-ui/` löschen, sobald der
  Command die Aufgabe übernimmt.
- **Twig endgültig entfernen**: Mit dem Installer fällt der letzte Verbraucher. Die
  Twig-Registrierung in `bootstrap.php` (`twig.path`, Service-Provider) und die Twig-Nutzung in
  `Classes/Controller/BaseController.php` gehen mit.

**Fertig, wenn**
- Ein frischer Checkout ist mit einem einzigen dokumentierten Befehl lauffähig.
- Der Weg steht im Runbook (`an_project/docs/runbook.md`).
- `InstallController`, `install.twig` und `lib/contentfly-ui/` sind aus dem Baum verschwunden.
- Keine Twig-Verwendung mehr im Code.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
- [ ] 012-002-0001 — Installationsschritte des InstallControllers erfassen
- [ ] 012-002-0002 — Install-Command implementieren
- [ ] 012-002-0003 — InstallController und Twig entfernen
