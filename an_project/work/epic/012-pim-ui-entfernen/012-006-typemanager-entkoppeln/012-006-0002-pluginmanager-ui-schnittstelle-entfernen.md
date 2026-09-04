---
id: 012-006-0002
title: UI-Schnittstelle des PluginManagers entfernen
status: todo
depends_on: [012-006-0001]
---

# UI-Schnittstelle des PluginManagers entfernen

## Context
Plugins konnten Oberflächenteile beisteuern — Menüs, Masken, Assets. Ohne Oberfläche entfällt dieser Teil der Plugin-Schnittstelle; die Erweiterbarkeit bleibt.

## Acceptance criteria
- [ ] Registrierungen von Menüs, Masken, JS- und CSS-Dateien sind aus `PluginManager` entfernt.
- [ ] Plugins können weiterhin Entities, Services und Commands beisteuern.
- [ ] `Classes/Plugin.php` und der Annotation-Driver für Plugin-Entities (`Plugin.php:177`) funktionieren unverändert.
- [ ] Der Wegfall ist als Breaking Change für den Migrationsleitfaden (Epic 007) notiert.

## Verification
Ein Beispiel-Plugin mit Entity und Command laden: Es wird registriert und funktioniert.
