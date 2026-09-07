---
id: 000-000-0007
title: /api/all wirft bedingungslos — Pfad zum Entity-Verzeichnis ist falsch
status: done
depends_on: []
---

# /api/all wirft bedingungslos — Pfad zum Entity-Verzeichnis ist falsch

## Context
Beim Aufbau des Testnetzes (Task `008-001-0004`) aufgefallen: `POST /api/all` antwortet
**immer** mit HTTP 500 — bei leerer wie bei gefüllter Datenbank. Das ist der Sync-Endpunkt eines
Produkts, das laut `an_project/docs/tech-stack.md` „reine Datenhaltung plus Core-Funktionen" ist
und dessen Zugriff „über die API und die Console" läuft.

Der Fehler stammt aus dem Initialimport (`b928409`, 2026-09-04), ist also Bestand und nicht
Folge des Umbaus.

## Die zwei Defekte

**1. Ein `../` zu viel.** `Api::getAll()` baut:

```php
$entityFolder = __DIR__.'/../../../../custom/Entity/';   // lib/contentfly/Classes/…
```

Vier Ebenen von `lib/contentfly/Classes` führen **über das Repo-Wurzelverzeichnis hinaus**. Der
Pfad existiert nicht, `DirectoryIterator` wirft sofort — noch bevor irgendwelche Daten
eingesammelt werden. Nachgewiesen:

```
php -r 'var_dump(is_dir("lib/contentfly/Classes/../../../../custom/Entity/"),
                 is_dir("lib/contentfly/Classes/../../../custom/Entity/"));'
bool(false)
bool(true)
```

`Api::getSchema()` macht es an der entsprechenden Stelle richtig und robuster:
`ROOT_DIR.'/custom/Entity/'`. Das ist die naheliegende Form auch hier — `ROOT_DIR` ist im
Bootstrap definiert und in `Api.php` bereits in Gebrauch.

**2. Die Entity-Liste ist fest verdrahtet.** Direkt darüber steht:

```php
$entities = array('Areanet\PIM\Entity\File', 'Areanet\PIM\Entity\User', 'Areanet\PIM\Entity\Group');
```

Auch mit korrigiertem Pfad synchronisieren damit nur diese drei Framework-Entities plus alles
aus `custom/Entity/`. `Tag`, `Folder`, `Option`, `Nav`, `NavItem`, `Permission` und
`ThumbnailSetting` blieben außen vor. Ob das Absicht war (Sync liefert nur Stammdaten) oder
ein zweiter Fehler, ist zu klären — nicht zu raten. `getSchema()` sammelt die Framework-Entities
über das Verzeichnis ein und wäre auch hier die konsistente Form.

## Acceptance criteria
- [x] Der Pfad zum `custom/Entity/`-Verzeichnis in `Api::getAll()` ist korrigiert — vorzugsweise
      über `ROOT_DIR`, wie es `getSchema()` tut.
- [x] `POST /api/all` antwortet mit HTTP 200 und liefert Daten; die Antwortform ist dokumentiert.
- [x] Die fest verdrahtete Entity-Liste ist **bewertet**: entweder als Absicht begründet
      festgehalten oder auf denselben Weg wie in `getSchema()` umgestellt.
- [x] `excludeFromSync` ist danach über `/api/all` nachweisbar wirksam. **Hinweis:** Heute setzt
      **keine einzige** Entity dieses Flag — ob das Feld damit praktisch tot ist, gehört mit
      beantwortet (siehe den Nachtrag zu `012-005-0002` im Changelog).
- [x] Die Charakterisierungstests aus `008-001-0004`, die den 500er festhalten, sind auf das
      neue Verhalten gedreht — bewusst, nicht durch Löschen.

## Verification
```sh
# vorher: 500
curl -s -o /dev/null -w '%{http_code}\n' -X POST http://127.0.0.1:8145/api/all \
     -H "appcms-token: $TOKEN" -H 'Content-Type: application/json' -d '{}'
```
Nach der Korrektur HTTP 200 mit Daten. Dazu die Suite aus Epic `008` grün, mit den
umgedrehten Zusicherungen in `SyncApiTest`.

## Ergebnis

`/api/all` antwortet mit **HTTP 200**. Drei Eingriffe waren nötig, nicht einer.

### 1. Der Pfad — der eigentliche Bug
`__DIR__.'/../../../../custom/Entity/'` → `ROOT_DIR.'/custom/Entity/'`, wie es `getSchema()`
tut. Dabei zeigte sich, dass der Pfad allein nicht reicht: Die Schleife filterte nur
Dot-Einträge und hätte das Unterverzeichnis `custom/Entity/Core/` als Entity-Klasse
`Custom\Entity\Core` ausgegeben. Die Verzeichnisbehandlung aus `getSchema()` ist deshalb
mitübernommen.

### 2. Die Entity-Liste — entschieden am 2026-09-07
Drei Methoden sammelten ihre Entities auf drei verschiedene Wegen:

| Methode | Verfahren |
|---|---|
| `getSchema()` | Lauf über `custom/Entity/` inkl. Unterordnern + Plugins + 12 fest verdrahtete PIM-Entities |
| `getAll()` (vorher) | fest verdrahtete **Einschluss**liste aus `File`, `User`, `Group` + kaputter Lauf über `custom/Entity/` |
| `getDeleted()` | ganzes Schema minus fest verdrahteter **Ausschluss**liste (8 Entities) |

Die Folge war eine Inkohärenz im Sync-Vertrag: `getDeleted()` meldete Löschungen für `PIM\Tag`,
`PIM\Option` und Custom-Entities, die `getAll()` **nie ausgeliefert** hatte. Ein Client erfuhr
vom Verschwinden von Objekten, die er nie bekommen hatte.

`getAll()` nutzt jetzt denselben Weg wie `getDeleted()` — Schema minus derselben
Ausschlussliste. Für Clients ist das **additiv**: `Tag`, `Option`, `OptionGroup` und die
Custom-Entities kommen hinzu, es fällt nichts weg.

### 3. `excludeFromSync` — ein Befund, der eine frühere Annahme korrigiert
Beim Nachweis der Wirksamkeit kam heraus: **Das Feld wurde ausschließlich in `getCount()`
geprüft, nie in `getAll()`.** Es wirkte also auf die Bestandsstatistik, nicht auf den Endpunkt,
nach dem es benannt ist.

Story `012-005-0002` hatte es mit der Begründung „steuert die Sync-API — `Classes/Api.php:755`"
behalten. Zeile 755 lag in `getCount()`. Die Entscheidung, das Feld zu behalten, war richtig —
ihre Begründung zeigte auf die falsche Methode.

Die Prüfung steht jetzt auch in `getAll()`; das Abnahmekriterium oben verlangt genau das.

## Verification
- [x] `POST /api/all` liefert HTTP 200 — vorher bedingungslos 500.
- [x] **Gegenprobe für `excludeFromSync` in beiden Richtungen:** `excludeFromSync=true`
      versuchsweise auf `PIM\Tag` gesetzt → die Entity fehlt in `/api/all`; zurückgenommen →
      sie ist wieder da. `git diff` auf `Entity/Tag.php` danach leer.
- [x] Die Charakterisierungstests in `SyncApiTest` sind **umgedreht**, nicht gelöscht — sie
      beschreiben jetzt den Sync-Vertrag statt seinen Ausfall. Neu dazu: ein Test, der belegt,
      dass `getAll()` und `getDeleted()` dieselben Entities ausschließen.
- [x] Drei vollständige Läufe grün: 68 Tests, 166 Assertions.
- [x] Console bootet; `orm:schema-tool:update --dump-sql` identisch zum Vergleichsstand — es
      wurde nichts am Datenbankschema geändert.

## Offen geblieben
Die drei Methoden sammeln ihre Entities weiterhin an zwei Stellen mit **dupliziertem Code**
(`getSchema()` und die Ausschlussliste in `getAll()`/`getDeleted()`). Ein gemeinsamer Helfer
würde verhindern, dass sie wieder auseinanderlaufen — das ist aber Refactoring und gehört nicht
in einen Bugfix.
