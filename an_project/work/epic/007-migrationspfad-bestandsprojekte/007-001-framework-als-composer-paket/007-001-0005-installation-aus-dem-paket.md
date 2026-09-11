---
id: 007-001-0005
title: Eine Installation aus dem Paket bauen und die Vorlage nachziehen
status: review
depends_on: [007-001-0004]
---

# Eine Installation aus dem Paket bauen und die Vorlage nachziehen

## Context
**Die Probe aufs Exempel.** Die vier Tasks davor haben getrennt, umgestellt und zugeordnet — ob
das Paket tatsächlich beziehbar ist, zeigt erst ein Projekt, das es bezieht, statt es zu kopieren.

Das ist der Unterschied, an dem die ganze Story hängt: Ein Manifest, das `type: library` sagt,
ist noch kein Paket, das sich einbinden lässt. Jeder Pfad, der noch stillschweigend annimmt, im
selben Baum zu liegen, fällt genau hier auf und sonst nirgends.

**`custom/` bleibt die Referenz.** Die Vorlage zeigt, wie ein Projekt auf der neuen Version
aussieht — sie wird mitgezogen, nicht stehen gelassen. Was hier entsteht, ist zugleich der Text,
aus dem `007-004` den Abschnitt „von der Kopie auf das Paket" schreibt.

## Acceptance criteria
- [x] Eine frische Installation entsteht aus dem Paket, bezogen über Composer, nicht durch Kopieren des Baums.
- [x] Die volle Suite läuft gegen diese Installation, nicht nur gegen das Entwicklungs-Repo.
- [x] `custom/` zeigt den Zielzustand — die Beispiel-Artefakte sind mitgezogen und laufen.
- [x] Was ein Bestandsprojekt zu tun hat, um von Kopie auf Paket zu wechseln, ist so beschrieben, dass `007-004` es übernehmen kann.
- [x] Was dabei nicht glatt lief, steht dabei. Eine Anleitung, die nur den geglückten Weg kennt, hilft beim ersten Stolpern nicht.

## Verification
In einem leeren Verzeichnis ein Projekt anlegen, das Paket über eine `path`-Quelle beziehen,
`appcms:install` fahren und die volle Suite dagegen laufen lassen. Der Baum des
Entwicklungs-Repos wird dabei nicht kopiert — wenn doch etwas fehlt, ist genau das der Befund.

## Ergebnis

**Ein Projekt in einem leeren Verzeichnis, ohne eine Zeile Frameworkcode.** Es enthält
`composer.json`, `index.php`, `bin/`, `custom/`, `data/`, `plugins/` — und kein `lib/`. Das
Framework kommt über eine `path`-Quelle mit `"symlink": false`, also als **echte Kopie**:

```
- Installing areanet/contentfly (2.0.0): Mirroring from …/lib/contentfly
vendor/areanet/contentfly   echtes Verzeichnis, kein Verweis in den Quellbaum
45 Pakete (statt 72 im Entwicklungs-Repo — die Werkzeuge fehlen, wie vorgesehen)
```

`appcms:install` läuft durch, `/api/config` antwortet mit `{"devmode":false,"version":"2.0.0",…}`.

**Die volle Suite gegen diese Installation: `OK (495 tests, 1214 assertions)`,** 0 Deprecations,
0 Byte Postausgang. Die Gegenprobe im Entwicklungs-Repo, ohne die neue Variable, ist gleich
grün — beide Wege gelten.

## Was nicht glatt lief, und das ist der eigentliche Ertrag

**Erster Lauf: 127 von 495 Tests rot**, alle mit „Zu viele Anmeldeversuche". Die Suite leert
zwischen Tests den Speicher der Anmeldebremse — aber den **ihres eigenen** Baums, während die
Anwendung ihn in den des Fremdprojekts schrieb.

**Es ging leise schief, und daran hängt die Lehre.** Das eigene `data/cache` existiert ja, also
griff die Bedingung, das Aufräumen lief, und es räumte das Falsche. Die Meldung handelte danach
von einer Anmeldung, nicht von einem Verzeichnis. Der Kommentar an der Stelle hatte den Fall
sogar vorhergesehen — „stillschweigend, wenn das Verzeichnis nicht erreichbar ist" —, nur trat
er anders ein, als er erwartet wurde: Das Verzeichnis war erreichbar, es war bloss das falsche.

**Zweiter Lauf: 4 rot**, dieselbe Annahme an anderer Stelle. Tests, die `bin/console.php`
aufrufen, riefen das des Entwicklungs-Repos — dessen `custom/config.php` ist die Vorlage ohne
Zugangsdaten, also war `$app['orm.em']` null, und der Fehler kam als Fatal Error aus einem
Command.

**Beides hängt jetzt an einer Angabe, nicht an zweien.** `CONTENTFLY_TEST_PROJEKT` sagt, wo die
Anwendung liegt; `datenverzeichnis()` und `konsole()` leiten sich daraus ab. Zwei Variablen
könnten auseinanderlaufen, und dann prüfte ein Lauf zwei verschiedene Installationen, ohne es zu
merken. Ein falscher Wert wird abgewiesen statt stillschweigend übergangen — aus demselben Grund
wie in `007-001-0002`.

**Ohne die Variable bleibt alles wie bisher.** Das ist der Normalfall und der einzige, den die
Pipeline kennt; die Gegenprobe belegt es.

**Zwölf Stellen in der Suite nahmen an, im selben Baum zu liegen** — `IntegrationTestCase`,
`AnmeldebremseApiTest`, `FileApiTest`, `SystemControllerApiTest` und vier Konsolen-Aufrufe. Was
davon **Quelldateien** liest (Frameworkcode, Vorlage), zeigt weiter auf das Entwicklungs-Repo:
Diese Tests prüfen den Code selbst, nicht die laufende Installation.

**Ein Fehlgriff im Aufbau, meiner:** Ich hatte `tests/router.php` in die Wurzel des
Fremdprojekts gelegt. Der Helfer bindet `__DIR__.'/../index.php'` ein und suchte damit eine Ebene
zu hoch; HTTP 500, und das Serverlog sagte es sofort. Der Helfer gehört nach `tests/`, wie im
Entwicklungs-Repo.

## `custom/` zeigt den Zielzustand

Die Vorlage ist unverändert in das Fremdprojekt übernommen worden und läuft dort: `custom/app.php`
mountet seine Routen, `custom/config.php` findet die `.env` über `CONTENTFLY_PROJEKT`, die
Beispiel-Artefakte werden von der Suite mitgeprüft. **Sie wird kopiert, und das ist richtig** —
sie ist Projektinhalt, nicht Frameworkcode.

## Was `007-004` übernehmen kann

Der Ablauf steht als fünf Schritte in `an_project/docs/breaking-changes.md`, Abschnitt
*Paketgrenze*, zusammen mit der Liste, was mitgeht und was nicht. Er ist der **gemessene** Weg,
nicht der geplante — dieser Task ist ihn einmal gegangen.

**Zahlen:** Zwei volle Läufe `OK (495 tests, 1214 assertions)` — einer gegen das Fremdprojekt,
einer gegen das Entwicklungs-Repo —, je 0 Deprecations bei 0 Ausnahmen und 0 Byte Postausgang.
