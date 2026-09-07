---
id: 012-006-0001
title: TypeManager und Typ-Klassen von Formular-Belangen befreien
status: review
depends_on: []
---

# TypeManager und Typ-Klassen von Formular-Belangen befreien

## Context
`TypeManager` und die Klassen unter `Classes/Type/` und `Classes/Types/` beschreiben Feldtypen — teils Datenverhalten, teils Darstellung. Nach 012-005 ist klar, welche Annotationen es noch gibt.

## Acceptance criteria
- [x] Casting, Validierung, `encoded`-Verschlüsselung und die Serialisierung für die Sync-API sind unverändert erhalten.
- [x] Widget-Auswahl und Darstellungsoptionen, die aus den gelöschten Annotationen stammten, sind entfernt.
- [x] `getCustomTypes` / `getPluginTypes` / `getSystemTypes` sind auf verbliebene Verbraucher geprüft — der `UiController` war ihr Hauptnutzer.
- [x] Keine Typ-Klasse referenziert mehr Templates oder Assets.

## Umfang

Der Löwenanteil war bereits mit `012-005-0001` erledigt: `RteType`, `PasswordType` und
`EntitySelectorType` sind dort gefallen, die Darstellungsfelder von `Checkbox` und `Radio`
(`horizontalAlignment`, `columns`, `select`) ebenfalls. Hier bleibt der Rest:

| Entfernt | Verbraucher |
|---|---|
| `TypeManager::getCustomTypes()`, `getSystemTypes()`, `getPluginTypes()` | **keine.** Der `UiController` listete die Typen nach Kategorie, um die Feldpalette der Maske zu bauen; er ist mit `012-001-0001` gefallen. |
| Der `$mode`-Parameter von `getTypes()` samt der Konstanten `CUSTOM`, `PLUGINS`, `SYSTEM` | **keine.** Nur die drei Getter oben nutzten ihn. `getTypes()` ohne Argument bleibt — `Api::getSchema()` ruft es so auf. |
| `Type::renderJSON()` | **keine.** Leerer Haken, über den Typen ihre Darstellung an das Frontend gaben. |

Templates oder Assets referenziert keine Typ-Klasse — die Suche nach `twig`, `render`, `asset`,
`template`, `widget`, `.js`, `.css` über `Classes/Types/`, `Classes/Type.php` und `Classes/Type/`
bleibt ohne Treffer.

## Zwei Befunde, die bewusst stehen bleiben

- **`$schema['multipe']` und `$schema['multiple']`** — an neun Stellen gesetzt (sechs davon unter
  dem Tippfehler `multipe`), **nirgends gelesen**. Ein reiner Widget-Hinweis. Sie zu entfernen
  hieße aber, das API-Schema zu ändern — und genau das schließt diese Story aus („Das API-Schema
  wird unverändert erzeugt — das ist der Vertrag mit den Sync-Clients und darf durch diese
  Aufräumarbeit nicht kippen"). Gehört in einen eigenen Task, zusammen mit der Frage, ob der
  Tippfehler überhaupt je jemandem aufgefallen ist.
- **`Type::$insertCallback` und `$updateCallback`** — zwei öffentliche Felder der abstrakten
  Basisklasse, die **niemand setzt und niemand liest**. Kein UI-Belang, deshalb hier nicht
  angefasst; als öffentliche Erweiterungspunkte könnten Bestandsprojekte sie beschreiben, ohne
  dass es je eine Wirkung hätte.

## Verification
Schema-Erzeugung und Sync-Abruf durchspielen; das API-Schema ist gegenüber dem Stand vor dem Umbau unverändert.

- [x] `GET /api/schema` vor und nach dem Umbau gegen dieselbe Datenbank: die JSON-Strukturen
      sind **identisch** (Python-Vergleich der geparsten Daten, nicht nur der Byte-Länge).
      13 Entities auf beiden Seiten.
- [x] Testsuite grün: 26 Tests, 45 Assertions.
