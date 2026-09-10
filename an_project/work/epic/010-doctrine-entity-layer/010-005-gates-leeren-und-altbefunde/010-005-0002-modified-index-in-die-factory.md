---
id: 010-005-0002
title: Den modified_index-Listener in die Factory ziehen
status: todo
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
- [ ] Der Listener wird in `EntityManagerFactory::erzeugen()` registriert; jeder EntityManager trägt ihn, der des Installers eingeschlossen.
- [ ] `appcms:install` legt `modified_index` an — nachgemessen mit `SHOW INDEX`, nicht erschlossen.
- [ ] `orm:validate-schema` meldet die Datenbank als deckungsgleich; was übrig bleibt, ist der Mapping-Fehler aus `010-005-0003`.
- [ ] Die Registrierung steht nicht mehr im Bootstrap — sonst liefe sie doppelt.
- [ ] Die Suite bleibt grün, und ein Datenbankvergleich zeigt genau einen Unterschied je Tabelle: den neuen Index.

## Verification
Frische Datenbank, `appcms:install`, dann `SHOW INDEX` über alle Tabellen — `modified_index`
muss da sein. Dazu `orm:validate-schema` und die volle Suite. Der Datenbankvergleich gegen den
Stand vor der Änderung zeigt, welche Tabellen den Index bekommen und ob sonst etwas wandert.
