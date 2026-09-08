---
id: 006-002-0001
title: Den Doctrine-Wechsel absichern
status: todo
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
- [ ] Der ManyToMany-Pfad `PIM\File.tags` ist durch Tests abgedeckt: anlegen, lesen, ändern,
      und die Wirkung auf `pim_file_tags` ist je geprüft.
- [ ] Das Verhalten beim Löschen der Datei (`onDelete="CASCADE"`) ist festgehalten.
- [ ] Für `isFilterable=true` ist festgehalten, was ein Filter über `tags` liefert — oder
      begründet, warum sich das nicht auslösen lässt.
- [ ] Die Tests laufen mehrfach hintereinander grün und lassen `pim_file`, `pim_tag`,
      `pim_file_tags` und `pim_log` auf dem Ausgangsstand.
- [ ] `an_project/docs/technical.md` ist nachgezogen: Die Lückenliste unterscheidet jetzt
      zwischen der nicht auslösbaren Schreibprüfung und dem nunmehr abgedeckten ORM-Pfad.

## Verification
Mehrere vollständige Läufe. Die neuen Tests sind der **Massstab für `006-002-0003`**: Bleiben
sie nach dem Doctrine-Wechsel grün, ist der Fork-Fix entweder im Release aufgegangen oder war
für diesen Pfad nie relevant. Werden sie rot, ist der Bruch genau lokalisiert — und das ist der
ganze Zweck, diesen Task vorzuziehen.

Der Zustand der Verknüpfungstabelle wird **direkt per SQL** geprüft, nicht nur über die
API-Antwort: Ein ORM-Wechsel kann die Antwort richtig aussehen lassen und die Tabelle trotzdem
falsch füllen.
