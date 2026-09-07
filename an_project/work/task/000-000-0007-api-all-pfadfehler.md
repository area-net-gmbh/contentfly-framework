---
id: 000-000-0007
title: /api/all wirft bedingungslos — Pfad zum Entity-Verzeichnis ist falsch
status: todo
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
- [ ] Der Pfad zum `custom/Entity/`-Verzeichnis in `Api::getAll()` ist korrigiert — vorzugsweise
      über `ROOT_DIR`, wie es `getSchema()` tut.
- [ ] `POST /api/all` antwortet mit HTTP 200 und liefert Daten; die Antwortform ist dokumentiert.
- [ ] Die fest verdrahtete Entity-Liste ist **bewertet**: entweder als Absicht begründet
      festgehalten oder auf denselben Weg wie in `getSchema()` umgestellt.
- [ ] `excludeFromSync` ist danach über `/api/all` nachweisbar wirksam. **Hinweis:** Heute setzt
      **keine einzige** Entity dieses Flag — ob das Feld damit praktisch tot ist, gehört mit
      beantwortet (siehe den Nachtrag zu `012-005-0002` im Changelog).
- [ ] Die Charakterisierungstests aus `008-001-0004`, die den 500er festhalten, sind auf das
      neue Verhalten gedreht — bewusst, nicht durch Löschen.

## Verification
```sh
# vorher: 500
curl -s -o /dev/null -w '%{http_code}\n' -X POST http://127.0.0.1:8145/api/all \
     -H "appcms-token: $TOKEN" -H 'Content-Type: application/json' -d '{}'
```
Nach der Korrektur HTTP 200 mit Daten. Dazu die Suite aus Epic `008` grün, mit den
umgedrehten Zusicherungen in `SyncApiTest`.
