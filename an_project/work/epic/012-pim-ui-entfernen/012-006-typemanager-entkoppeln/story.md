---
id: 012-006-0000
title: TypeManager und PluginManager von UI-Belangen befreien
status: review
depends_on: [012-005-0000]
---

# TypeManager und PluginManager von UI-Belangen befreien

## Goal
Die Manager-Schicht ist der letzte Ort, an dem UI-Wissen sitzt. `TypeManager` und die Typen unter
`Classes/Type/` und `Classes/Types/` beschreiben Feldtypen — teils als Datenverhalten (Casting,
Validierung, Verschlüsselung, Serialisierung für die Sync-API), teils als Formulardarstellung.
Nach 012-005 ist klar, welche Annotationen es noch gibt; hier wird der Code darauf zurückgeschnitten.

**Umfang**
- **`TypeManager` und die Typ-Klassen**: Daten-Belange behalten — Casting, Validierung,
  `encoded`-Verschlüsselung, das Schema, das die Sync-API ausliefert. Formular-Belange entfernen —
  Widget-Auswahl, Darstellungsoptionen, alles, was aus den gelöschten Widget-Annotationen kam.
- **`PluginManager`** auf UI-Registrierungen prüfen: Plugins konnten bisher Oberflächenteile
  beisteuern. Was davon Menüs, Masken oder Assets registriert, entfällt; die Erweiterbarkeit für
  Entities, Services und Commands bleibt.
- **`Classes/Manager/`** insgesamt durchsehen — `RouteManager`, `ConsoleManager`, `LoginManager`
  auf verbliebene UI-Reste prüfen, nachdem 012-001 bis 012-005 durch sind.
- Was gelöschte Twig-Templates oder Assets erwartet hat, verschwindet mit.

**Fertig, wenn**
- Kein Manager und keine Typ-Klasse referenziert mehr Widgets, Templates oder Assets.
- Das API-Schema wird unverändert erzeugt — das ist der Vertrag mit den Sync-Clients und darf
  durch diese Aufräumarbeit nicht kippen.
- Plugins können weiterhin Entities, Services und Commands beisteuern; nur der UI-Teil der
  Plugin-Schnittstelle ist weg und im Migrationsleitfaden (Epic 007) als Bruch vermerkt.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
- [x] 012-006-0001 — TypeManager und Typ-Klassen von Formular-Belangen befreien
- [x] 012-006-0002 — UI-Schnittstelle des PluginManagers entfernen
- [x] 012-006-0003 — Verbleibende Manager auf UI-Reste durchsehen
