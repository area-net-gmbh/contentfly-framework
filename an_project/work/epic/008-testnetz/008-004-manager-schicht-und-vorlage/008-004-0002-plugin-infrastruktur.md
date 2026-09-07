---
id: 008-004-0002
title: Plugin-Infrastruktur dauerhaft absichern
status: review
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
- [x] Ein zur Laufzeit erzeugtes Testplugin wird registriert. **Der Weg über das API-Schema ist bewusst nicht gegangen** — Begründung unten.
- [x] Der Console-Command ist **nicht** über die HTTP-Instanz prüfbar; die Registrierung selbst ist in `008-004-0001` abgedeckt (`ConsoleManager`). Begründet unten.
- [x] `register()` mit unbekanntem Plugin wirft die vorgesehene Ausnahme.
- [x] Nach dem Test ist von dem Plugin nichts übrig — weder auf der Platte noch in der
      Vorlage noch in der Datenbank.
- [x] Der gewählte Weg zur Registrierung ist begründet festgehalten.

## Verification
Mehrere vollständige Läufe; `plugins/` ist danach leer und `git status` sauber — insbesondere
`custom/app.php` unverändert.

## Ergebnis — 10 Unit-Tests in `tests/Unit/Manager/PluginManagerTest.php`

Gesamtsuite: **181 Tests, 408 Assertions**; die Unit-Suite wächst von 29 auf **39**.

### Die Grenze hat gehalten — und warum das die richtige Entscheidung war
Der Task hatte vorab festgelegt: lieber begründet auf den Integrationstest verzichten, als
`custom/app.php` dauerhaft mit Testcode zu belasten. Die Prüfung ergab, dass es **keinen
anderen Weg gibt**:

- Ein Plugin wird ausschließlich in `custom/app.php` registriert. Es gibt **keinen
  konfigurationsgesteuerten Weg** — `Classes/Config.php` kennt kein Plugin-Feld.
- `bootstrap-web.php` endet mit `$app->run()`. Zwischen dem Aufbau der Anwendung und dem
  Bearbeiten des Requests gibt es keine Naht, in die `tests/router.php` sich einhängen könnte.

Damit hätte ein Integrationstest die **Vorlage** anfassen müssen — die Referenz, an der sich
jedes migrierende Projekt orientiert (Epic `007`). Der Preis wäre zu hoch gewesen.

**Dass es keinen Registrierungs-Hook gibt, ist selbst ein Befund** und gehört in die
Zusammenfassung der Story: Ein Projekt kann Plugins nur aus seinem eigenen Bootstrap laden,
nicht über Konfiguration.

### Was stattdessen abgedeckt ist
Alles außer dem Zusammenspiel mit einem echten EntityManager — und das mit einem **Spion** auf
der Doctrine-Konfiguration statt eines Nachbaus der Anwendung:

| Zusicherung | wie geprüft |
|---|---|
| `register()` legt unter dem Key ab | direkt |
| Key und Namespace kommen aus dem Klassennamen | direkt — die Konvention `Plugins\<Key>\<Key>Plugin` ist zwingend und nirgends dokumentiert |
| unbekanntes Plugin → `ContentflyException` | direkt |
| Klasse ohne `Plugin`-Basis → `ContentflyException` | direkt |
| `useORM()` hängt den Annotation-Driver ein | Spion: geprüft werden **Pfad und Namespace** |
| `useORM()` legt `Entity/` an, wenn es fehlt | direkt |
| `getEntities()` sammelt die Klassen ein | direkt |
| `registerPluginType()` setzt den Plugin-Key | direkt |

Die Plugin-Dateien entstehen zur Laufzeit unter `plugins/` — von `.gitignore` ohnehin
ausgeschlossen — mit je eindeutigem Key, weil PHP eine geladene Klasse nicht vergessen kann.

### Ein Befund, der ein eigenes Ticket korrigiert
`000-000-0011` behauptete, der undefinierte `$key` in `getPlugin()` ergebe unter PHP 8 einen
`Error`. **Falsch.** Eine undefinierte Variable ist eine **Warning**; der Ausdruck ergibt
`null`. Die `ContentflyException` kommt also wie vorgesehen — aber **ohne den Namen des
gesuchten Plugins**. Wer den Fehler untersucht, erfährt nicht, wonach gesucht wurde.

Der Test hält beides fest, die Ausnahme und die Warning; das Ticket ist korrigiert.

## Verification
- [x] **12 vollständige Läufe grün** bei zufälliger Ausführungsreihenfolge.
- [x] `plugins/` ist danach leer — die zur Laufzeit erzeugten Plugins sind restlos entfernt.
- [x] `custom/app.php` ist **unverändert**; `git status` zeigt nur die installationsbedingte
      `custom/config.php`.
