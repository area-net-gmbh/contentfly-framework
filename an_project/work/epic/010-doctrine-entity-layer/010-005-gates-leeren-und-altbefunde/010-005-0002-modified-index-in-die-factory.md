---
id: 010-005-0002
title: Den modified_index-Listener in die Factory ziehen
status: done
depends_on: []
---

# Den modified_index-Listener in die Factory ziehen

## Context
`Classes/Events/LoadMetadata` hängt jeder Entity einen Index auf `modified` an. Registriert
wird der Listener in `bootstrap.php` — **innerhalb von `if($app['is_installed'])`**.

`appcms:install` läuft genau dann, wenn `is_installed` **falsch** ist. Der Listener greift dort
also nicht, und der Index wird nie angelegt. Danach greift er, Doctrine vergleicht das Mapping
mit der Datenbank und meldet, dass sie nicht deckungsgleich sind — bei jeder Installation, seit
es den Listener gibt.

**Das ist die dritte Auflage desselben Fehlers in diesem Epic.** Eine Angabe, die für jeden
EntityManager gelten muss, steht neben der Factory statt darin:

| Fall | Task |
|---|---|
| Der Metadaten-Cache erreichte die Factory nie | `010-002-0005` |
| Der Installer wiederholte den Mapping-Block | `010-003-0002` |
| Der `modified_index`-Listener greift beim Installieren nicht | dieser Task |

`InstallCommand::bootDoctrine()` baut seinen eigenen EntityManager. Solange die Registrierung
im Bootstrap steht, muss sie dort **zweimal** stehen — und genau daran ist es gescheitert.

## Acceptance criteria
- [x] Der Listener wird in `EntityManagerFactory::erzeugen()` registriert; jeder EntityManager trägt ihn, der des Installers eingeschlossen.
- [x] `appcms:install` legt `modified_index` an — nachgemessen mit `SHOW INDEX`, nicht erschlossen.
- [x] `orm:validate-schema` meldet die Datenbank als deckungsgleich; was übrig bleibt, ist der Mapping-Fehler aus `010-005-0003`.
- [x] Die Registrierung steht nicht mehr im Bootstrap — sonst liefe sie doppelt.
- [x] Die Suite bleibt grün, und ein Datenbankvergleich zeigt genau einen Unterschied je Tabelle: den neuen Index.

## Verification
Frische Datenbank, `appcms:install`, dann `SHOW INDEX` über alle Tabellen — `modified_index`
muss da sein. Dazu `orm:validate-schema` und die volle Suite. Der Datenbankvergleich gegen den
Stand vor der Änderung zeigt, welche Tabellen den Index bekommen und ob sonst etwas wandert.

## Ergebnis

**`appcms:install` legt `modified_index` an, und der Datenbank-Teil der Validierung ist grün:**

```
Database
--------
 [OK] The database schema is in sync with the mapping files.
```

Seit es den Listener gibt, meldete `orm:validate-schema` bei **jeder** Installation, dass Schema
und Mapping nicht deckungsgleich sind. Das ist vorbei.

### Der Datenbankvergleich

Zwei Installationen, vorher gegen nachher, als **Menge** verglichen statt zeilenweise — sonst
hätte die Sortierung Verschiebungen als Unterschiede ausgewiesen:

| | |
|---|---|
| Indexzeilen | 67 → **82** |
| Nur nachher vorhanden | 15 Zeilen, **alle** `modified_index` |
| Nur vorher vorhanden | **keine** |

Es kommt also genau das dazu, was dazukommen soll, und es verschwindet nichts.

### Die dritte Auflage desselben Fehlers

Der Listener wurde in `bootstrap.php` registriert, **innerhalb von** `if($app['is_installed'])`.
`appcms:install` läuft genau dann, wenn das falsch ist — der Listener griff dort nie.

Das ist in diesem Epic der dritte Fall derselben Form:

| Fall | Task |
|---|---|
| Der Metadaten-Cache wurde nach dem EntityManager gesetzt und erreichte die Factory nie | `010-002-0005` |
| Der Installer wiederholte den Mapping-Block, und die Wiederholung wich ab | `010-003-0002` |
| Der Listener griff beim Installieren nicht | dieser Task |

**Die Regel steht jetzt im Code:** Was für jeden EntityManager gelten muss, gehört in die
Factory. Steht es beim Aufrufer, muss es dort mehrfach stehen — und irgendwo fehlt es dann.

`addEventListener` kommt im ganzen Baum genau **einmal** vor. `InstallCommand::bootDoctrine()`
braucht keine eigene Zeile mehr, weil er dieselbe Factory benutzt.

### Zwei Importe weniger

`LoadMetadata` und `Doctrine\ORM\Events` waren im Bootstrap nur noch für diese zwei Zeilen da.
Mit ihnen sind sie gegangen — nachgezählt, nicht angenommen.

### Nachweis

| Probe | Ergebnis |
|---|---|
| `appcms:install` auf frischer Datenbank | läuft durch |
| `modified_index` danach | in **15** Tabellen |
| Indexvergleich als Menge | +15 Zeilen, alle `modified_index`; nichts entfernt |
| `orm:validate-schema`, Datenbank-Teil | `[OK] in sync` |
| `addEventListener` im Baum | genau 1 Stelle |
| Volle Suite | `OK (282 tests, 692 assertions)`, 0 übersprungen |
| PHPStan | `[OK] No errors` |

**Offen bleibt der Mapping-Teil der Validierung** — die zwei Fehler in `BaseI18nTree`. Sie
gehören `010-005-0003`.
