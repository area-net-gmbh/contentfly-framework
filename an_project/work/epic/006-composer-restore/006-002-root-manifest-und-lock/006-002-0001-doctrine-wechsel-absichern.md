---
id: 006-002-0001
title: Den Doctrine-Wechsel absichern
status: review
depends_on: []
---

# Den Doctrine-Wechsel absichern

## Context
`doctrine/orm` steht heute auf einem **eigenen Fork**: `area-net-gmbh/doctrine2`, Branch
`bugfix-many2many`, Stand **2018-08-07** (`006-001-0003`). Mit `006-002-0002` wird daraus ein
Release — der grösste Einzelsprung des Epics, und der einzige, bei dem jemand vor sieben Jahren
einen Grund hatte, vom Upstream abzuweichen.

**Dieser Task kommt vor dem Manifest**, nicht danach. Sonst mischt sich ein möglicher
ManyToMany-Bruch mit vier weiteren Major-Sprüngen, und niemand kann ihn mehr zuordnen.

## Umfang

### Eine Klarstellung, die beim Schneiden auffiel
Die Story wirft zwei Dinge zusammen, die getrennt gehören:

| | |
|---|---|
| **Die Schreibprüfung in `MultijoinType`** | Ein **Berechtigungs**pfad im `acceptFrom`-Zweig. Nicht auslösbar, weil keine Eigenschaft im Baum ein `acceptFrom` trägt — festgehalten in `WritePermissionApiTest::testDieSchreibpruefungInMultijoinTypeIstNichtAusloesbar()`. |
| **Der ManyToMany-Pfad selbst** | Das **ORM**-Verhalten: Verknüpfungen anlegen, lesen, lösen. Sehr wohl auslösbar. |

Der Doctrine-Fork heisst `bugfix-many2many` — er betrifft das **zweite**, nicht das erste. Die
dokumentierte Lücke ist also enger als der Story-Text nahelegt, und der Teil, auf den es
ankommt, ist testbar.

### Der einzige ManyToMany im Baum
```php
// lib/contentfly/Entity/File.php:81
/**
 * @ORM\ManyToMany(targetEntity="Areanet\PIM\Entity\Tag")
 * @ORM\JoinTable(name="pim_file_tags", joinColumns={@ORM\JoinColumn(onDelete="CASCADE")})
 * @PIM\Config(isFilterable=true)
 */
protected $tags;
```

`PIM\File.tags`, mit der Verknüpfungstabelle `pim_file_tags` — die es in der Datenbank
tatsächlich gibt. **Die Suite deckt ihn nicht ab:** Der einzige Treffer auf „tags" in
`tests/Integration/` ist der Kommentar des oben genannten Berechtigungstests.

### Was zu tun ist
Charakterisierungstests für diesen Pfad — nach derselben Regel wie in Epic `008`: **festhalten,
was ist**, nicht was sein sollte.

Abzudecken sind mindestens:
- Eine Datei mit Tags **anlegen** und die Zeilen in `pim_file_tags` prüfen.
- Die Verknüpfung **lesen** — über `/api/single` und `/api/list`.
- Die Verknüpfung **ändern** (Tags ersetzen, entfernen) und den Zustand der Tabelle prüfen.
- Das `onDelete="CASCADE"` der Join-Spalte: Was geschieht mit `pim_file_tags`, wenn die Datei
  gelöscht wird?
- `isFilterable=true`: Lässt sich über `tags` filtern, und was liefert das?

Der Aufbau der Testdaten läuft wie in Epic `008` **an der API vorbei** (per PDO), damit ein
Lesetest nicht vom Schreibpfad abhängt, den er selbst prüft. `FileApiTest` und
`IntegrationTestCase` bringen mit, was dafür nötig ist — einschliesslich des Aufräumens
(`000-000-0008`).

> **Wenn sich ein Punkt nicht auslösen lässt**, gilt das Muster aus Epic `008`: die
> Vorbedingung festhalten und im Testkommentar benennen, warum. Nicht kaschieren.

## Abgrenzung
- **Kein** Test der Schreibprüfung im `acceptFrom`-Zweig — die bleibt nicht auslösbar, und der
  bestehende Test in `WritePermissionApiTest` hält das bereits fest. Er bleibt unverändert.
- **Keine** Änderung an der Vorlage, um Pfade auslösbar zu machen. Ein `acceptFrom` in
  `Example.php` würde die Vorlage für alle Folgeprojekte ändern und berührt `000-000-0017`.
- Kein Manifest, kein Doctrine-Wechsel — das ist `006-002-0002` und `-0003`.

## Acceptance criteria
- [x] Der ManyToMany-Pfad `PIM\File.tags` ist durch Tests abgedeckt: anlegen, lesen, ändern,
      und die Wirkung auf `pim_file_tags` ist je geprüft.
- [x] Das Verhalten beim Löschen der Datei (`onDelete="CASCADE"`) ist festgehalten.
- [x] Für `isFilterable=true` ist festgehalten, was ein Filter über `tags` liefert — oder
      begründet, warum sich das nicht auslösen lässt.
- [x] Die Tests laufen mehrfach hintereinander grün und lassen `pim_file`, `pim_tag`,
      `pim_file_tags` und `pim_log` auf dem Ausgangsstand.
- [x] `an_project/docs/technical.md` ist nachgezogen: Die Lückenliste unterscheidet jetzt
      zwischen der nicht auslösbaren Schreibprüfung und dem nunmehr abgedeckten ORM-Pfad.

## Verification
Mehrere vollständige Läufe. Die neuen Tests sind der **Massstab für `006-002-0003`**: Bleiben
sie nach dem Doctrine-Wechsel grün, ist der Fork-Fix entweder im Release aufgegangen oder war
für diesen Pfad nie relevant. Werden sie rot, ist der Bruch genau lokalisiert — und das ist der
ganze Zweck, diesen Task vorzuziehen.

Der Zustand der Verknüpfungstabelle wird **direkt per SQL** geprüft, nicht nur über die
API-Antwort: Ein ORM-Wechsel kann die Antwort richtig aussehen lassen und die Tabelle trotzdem
falsch füllen.

## Ergebnis
`tests/Integration/Api/ManyToManyApiTest.php` — 9 Tests, 28 Assertions. Gesamtsuite
**247 Tests / 603 Assertions** (vorher 238 / 575). Vier Gesamtläufe; `pim_file`, `pim_tag`,
`pim_file_tags` und `pim_log` danach auf dem Ausgangsstand.

### Abgedeckt
| Was | Ergebnis |
|---|---|
| **Lesen** | `/api/single` liefert die Tags als **volle Objekte**, nicht nur Ids — mit `created`, `views`, `users`, `groups`. Ohne Verknüpfung: leere Liste, nicht `null`. |
| **Schema** | `type: multijoin`, `accept`, `foreign: pim_file_tags`, `dbfield`/`dbfield_foreign`, `isFilterable` — der Vertrag, an dem ein Client hängt. |
| **Setzen** | `/api/update` mit `tags` schreibt die Zeilen; **per SQL geprüft**, nicht über die Antwort. |
| **Ersetzen** | Eine neue Menge **ersetzt** die alte vollständig — entfernte Tags verschwinden aus der Tabelle. |
| **Leeren** | `tags: []` löst alle Verknüpfungen. |
| **Löschen der Datei** | Keine verwaisten Zeilen. |
| **Löschen des Tags** | Ebenfalls keine — siehe unten. |
| **Filtern** | `where: {tags: <id>}` findet die verknüpfte Datei und nur sie. |

### Zwei falsche Annahmen von mir, beide korrigiert
**1. Die Reihenfolge.** Zwei Tests verglichen Tag-Listen in Anlegereihenfolge. Weder die
Entity noch die Abfrage sichern eine Reihenfolge zu — und weil die Ids zufällig sind, wäre
das ein Test gewesen, der irgendwann ohne Grund rot wird. Jetzt wird sortiert verglichen.

**2. Das CASCADE, und das ist der eigentliche Fund.** Ich hatte einen Test geschrieben, der
festhält, dass eine gelöschte *Tag*-Zeile ihre Verknüpfung als Waise zurücklässt — die
Annotation setzt `onDelete="CASCADE"` schliesslich nur auf den `joinColumns` (`file_id`).
Der Test wurde rot. Nachgesehen:

```
CONSTRAINT FK_D36D828346F22BC  FOREIGN KEY (file_id) REFERENCES pim_file (id) ON DELETE CASCADE
CONSTRAINT FK_D36D8283DD1FDCE8 FOREIGN KEY (tag_id)  REFERENCES pim_tag  (id) ON DELETE CASCADE
```

**Doctrine legt es auf beiden Fremdschlüsseln an.** Das Schema ist symmetrisch, die
Annotation beschreibt es asymmetrisch. Wer nur die Entity liest, erwartet verwaiste Zeilen,
die es nicht gibt — deshalb steht der Befund jetzt im Kopf der Testdatei.

### Ein Rückstand, den ich selbst eingeführt und wieder beseitigt habe
Nach den ersten vier Läufen wuchs `pim_log` um **drei Zeilen pro Lauf** — je eine `UPT`-Zeile
aus den drei Update-Tests. Genau der Rückstand, gegen den `000-000-0008` geschrieben wurde.

Behoben mit einem eigenen `tearDown()`, das nach `model_id` und `file_id` aufräumt: Beides
erreicht `nachTestLoeschen()` nicht, weil es über `id` löscht — und die entscheidenden
Logzeilen entstehen erst **nach** der Anmeldung.

### Warum die Testdaten per PDO entstehen
`/api/insert` scheidet für `PIM\File` aus: `pim_file` verlangt `hash` und `type` als NOT NULL,
und der Insert-Pfad füllt sie nicht (live geprüft — der Aufruf endet in einer
SQL-Exception). Dateien entstehen sonst über `/file/upload`; dieser Pfad hat mit ManyToMany
nichts zu tun und brächte eigene Fehlerquellen mit, die `FileApiTest` bereits charakterisiert.

### `technical.md` nachgezogen
Die Lückenliste unterscheidet jetzt ausdrücklich: Die **Schreibprüfung** des `MultijoinType`
bleibt nicht auslösbar (Berechtigungspfad im `acceptFrom`-Zweig), das **ORM-Verhalten** ist
abgedeckt. Ohne diese Trennung liest man die Lücke breiter, als sie ist.

### Der Massstab für 006-002-0003
Diese 9 Tests sind der Massstab für den Doctrine-Wechsel. Bleiben sie grün, ist der Fix des
Forks entweder im Release aufgegangen oder war für diesen Pfad nie relevant. Werden sie rot,
ist der Bruch lokalisiert — und genau dafür wurde dieser Task vorgezogen.
