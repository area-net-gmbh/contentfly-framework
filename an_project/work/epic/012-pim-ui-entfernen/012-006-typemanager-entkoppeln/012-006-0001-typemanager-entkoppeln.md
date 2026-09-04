---
id: 012-006-0001
title: TypeManager und Typ-Klassen von Formular-Belangen befreien
status: todo
depends_on: []
---

# TypeManager und Typ-Klassen von Formular-Belangen befreien

## Context
`TypeManager` und die Klassen unter `Classes/Type/` und `Classes/Types/` beschreiben Feldtypen — teils Datenverhalten, teils Darstellung. Nach 012-005 ist klar, welche Annotationen es noch gibt.

## Acceptance criteria
- [ ] Casting, Validierung, `encoded`-Verschlüsselung und die Serialisierung für die Sync-API sind unverändert erhalten.
- [ ] Widget-Auswahl und Darstellungsoptionen, die aus den gelöschten Annotationen stammten, sind entfernt.
- [ ] `getCustomTypes` / `getPluginTypes` / `getSystemTypes` sind auf verbliebene Verbraucher geprüft — der `UiController` war ihr Hauptnutzer.
- [ ] Keine Typ-Klasse referenziert mehr Templates oder Assets.

## Verification
Schema-Erzeugung und Sync-Abruf durchspielen; das API-Schema ist gegenüber dem Stand vor dem Umbau unverändert.
