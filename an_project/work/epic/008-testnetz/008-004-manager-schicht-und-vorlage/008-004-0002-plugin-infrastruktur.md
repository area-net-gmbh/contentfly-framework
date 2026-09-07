---
id: 008-004-0002
title: Plugin-Infrastruktur dauerhaft absichern
status: todo
depends_on: [008-004-0001]
---

# Plugin-Infrastruktur dauerhaft absichern

## Context
Story `012-006-0002` hat mit einem **Wegwerf-Plugin** nachgewiesen, dass die Erweiterbarkeit
den Rückbau der Oberfläche überlebt hat: Entity im Doctrine-Mapping, Command registriert,
`@PIM`-Annotationen ausgewertet. Danach wurde es gelöscht — der Nachweis existiert nur als Text
im Task.

Dieser Task macht daraus einen dauerhaften Test. Denn genau diese Schnittstelle sagt Epic `007`
den Bestandsprojekten zu, und Epic `009` muss sie reproduzieren.

## Umfang

### Die Zusicherungen
- `PluginManager::register()` lädt `Plugins\<Key>\<Key>Plugin`, prüft die Basisklasse und legt
  es unter seinem Key ab.
- `Plugin::useORM()` registriert einen Annotation-Driver für `plugins/<Key>/Entity` — die
  Entities des Plugins erscheinen im Doctrine-Mapping **und** im API-Schema, mit ausgewerteten
  `@PIM\Config`-Annotationen.
- `Plugin::getEntities()` sammelt sie ein; `Api::getSchema()` führt sie zusammen.
- Ein `CustomCommand` aus dem Plugin taucht unter `custom:<name>` in der Console auf.
- `Plugin::registerPluginType()` registriert einen eigenen Feldtyp.
- Ein unbekanntes Plugin führt zu einer `ContentflyException`.

### Das Hindernis: `plugins/*` ist ignoriert
`.gitignore` schließt `plugins/*` aus. Ein Testplugin kann also nicht committet werden.

**Lösung: Es entsteht zur Laufzeit.** Der Test schreibt die PHP-Dateien in `setUp()` und
entfernt sie in `tearDown()` — dafür gibt es seit `000-000-0008` bereits
`nachTestVerzeichnisLoeschen()`. Die PSR-4-Zuordnung `Plugins\` → `/plugins` in
`vendor/composer/autoload_psr4.php` greift auch für Dateien, die erst zur Laufzeit entstehen.

Zu beachten: Die Anwendung läuft im **Testserver-Prozess**, nicht im Testprozess. Das Plugin
muss also vor dem HTTP-Aufruf auf der Platte liegen, und es muss registriert werden — das
geschieht heute in `custom/app.php`. Ob der Test diese Datei zeitweise ergänzt oder ob es einen
saubereren Weg gibt (etwa eine Umgebungsvariable, die der Bootstrap auswertet), ist beim
Umsetzen zu entscheiden und zu begründen.

> Falls sich kein Weg findet, der die Vorlage unangetastet lässt: lieber begründet auf den
> Integrationstest verzichten und die Teile unit-testen, als `custom/app.php` dauerhaft mit
> Testcode zu belasten. Die Entscheidung gehört in den Task, nicht in einen stillen Kompromiss.

## Acceptance criteria
- [ ] Ein zur Laufzeit erzeugtes Testplugin wird registriert; seine Entity erscheint im
      API-Schema mit ausgewerteten `@PIM\Config`-Annotationen.
- [ ] Sein Console-Command ist unter `custom:<name>` aufrufbar — oder es ist begründet, warum
      das im Testaufbau nicht prüfbar ist.
- [ ] `register()` mit unbekanntem Plugin wirft die vorgesehene Ausnahme.
- [ ] Nach dem Test ist von dem Plugin nichts übrig — weder auf der Platte noch in der
      Vorlage noch in der Datenbank.
- [ ] Der gewählte Weg zur Registrierung ist begründet festgehalten.

## Verification
Mehrere vollständige Läufe; `plugins/` ist danach leer und `git status` sauber — insbesondere
`custom/app.php` unverändert.
