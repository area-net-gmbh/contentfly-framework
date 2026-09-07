---
id: 012-006-0002
title: UI-Schnittstelle des PluginManagers entfernen
status: done
depends_on: [012-006-0001]
---

# UI-Schnittstelle des PluginManagers entfernen

## Context
Plugins konnten Oberflächenteile beisteuern — Menüs, Masken, Assets. Ohne Oberfläche entfällt dieser Teil der Plugin-Schnittstelle; die Erweiterbarkeit bleibt.

## Acceptance criteria
- [x] Registrierungen von Menüs, Masken, JS- und CSS-Dateien sind aus `PluginManager` entfernt.
- [x] Plugins können weiterhin Entities, Services und Commands beisteuern.
- [x] `Classes/Plugin.php` und der Annotation-Driver für Plugin-Entities funktionieren unverändert.
- [x] Der Wegfall ist als Breaking Change für den Migrationsleitfaden (Epic 007) notiert.

## Befund: die UI-Schnittstelle lag nicht im PluginManager

`PluginManager` selbst ist schon sauber — er kann `register()`, `getEntities()` und
`getPlugin()`, mehr nicht. Menüs, Masken oder Asset-Registrierungen gibt es dort nicht;
sie sind entweder mit `012-001` gefallen oder haben nie in dieser Klasse gelebt.

Der Frontend-Anteil sitzt in **`Classes/Plugin.php`**, der Basisklasse, von der jedes Plugin
erbt. Dort entfernt:

| Entfernt | Was es tat | Aufrufer |
|---|---|---|
| `getFrontendPath()` | gab `/plugins/<KEY>/Frontend` zurück — den per Symlink im Webroot freigegebenen Asset-Ordner | keine |
| `useFrontend()` | legte diesen Symlink an; war bereits ein leerer Rumpf mit dem Kommentar `//Deprecated` | keine |
| `normalizePath()` | private Hilfsfunktion, die nur zum Frontend-Pfad gehörte | keine |

Unverändert geblieben ist alles, was Erweiterbarkeit ausmacht: `useORM()` mit `initORM()` und
dem Annotation-Driver, `getEntities()`, `registerPluginType()`, `init()`, `initComposer()`,
`getKey()`, `getNamespace()`.

> Das Kriterium verweist auf `Plugin.php:177` — die Datei hatte schon vor diesem Task nur
> 147 Zeilen. Gemeint ist `initORM()`, die Zeilenangabe war veraltet.

## Nebenbefund — nicht angefasst

`PluginManager::getPlugin()` wirft bei unbekanntem Plugin
`new ContentflyException(Messages::contentfly_general_unknown_plugin, $key)` — die Variable
`$key` **existiert dort nicht**; gemeint ist `$pluginName`. Unter PHP 8 wäre das im Fehlerfall
ein `Undefined variable`-Fehler statt der gedachten Ausnahme. Kein UI-Belang und damit nicht
Teil dieser Story, aber ein echter Fehler auf einem Pfad, den heute niemand aufruft
(`getPlugin()` hat keinen Verbraucher).

## Verification
Ein Beispiel-Plugin mit Entity und Command laden: Es wird registriert und funktioniert.

Wegwerf-Plugin `plugins/Demo` gebaut — Plugin-Klasse mit `useORM()`, eine Entity `DemoItem`
mit `@PIM\Config`, ein Console-Command — über `$app['pluginManager']->register('Demo')`
registriert. Ergebnis:

- [x] **Command registriert und ausführbar:** `custom:demo:ping` erscheint in
      `php bin/console.php list` und gibt beim Aufruf `demo:ping ok` aus.
- [x] **Entity im Doctrine-Mapping:** `orm:schema-tool:update --dump-sql` enthält
      `CREATE TABLE demo_item` samt der von `Base` geerbten Spalten und beider Fremdschlüssel
      auf `pim_user`. Der Annotation-Driver aus `initORM()` arbeitet also unverändert.
- [x] **Entity im API-Schema, mit ausgewerteten Annotationen:**
      `Plugins\Demo\Entity\DemoItem` erscheint in `GET /api/schema` (14 statt 13 Entities),
      `settings.labelProperty` steht auf `title`, und `properties.title.isFilterable` ist `true`.
- [x] Plugin und Testregistrierung anschließend restlos entfernt; das API-Schema ist wieder
      **identisch zur Baseline** (13 Entities), Testsuite grün mit 26 Tests, 45 Assertions.
