---
id: 015-000-0021
title: /file/overwrite löscht die Quelldatei ohne Löschrecht
status: done
depends_on: []
---

# /file/overwrite löscht die Quelldatei ohne Löschrecht

## Context
**Security-Scan 2026-09, LOW. Finding F35.**

`FileController::overwriteAction` entfernt den Datensatz `sourceId` samt Verzeichnis
(`Controller/FileController.php:578-580`, `$this->em->remove($fileSource)`). Vorher prüft es nur
`Permission::isWritable` und die Schreib-Eigentümerschaft. `Permission::isDeletable`, das
`Api::doDelete` für dieselbe Operation verlangt, wird nie geprüft, und es entsteht kein
DELETED-Logeintrag.

Ablauf: Eine Gruppe hat auf `PIM\File` `writable=ALL` und `deletable=NONE`, soll also Dateien
bearbeiten, aber nicht löschen dürfen. Ein Redakteur lädt eine eigene Datei mit demselben Namen wie
das Ziel in einen anderen Ordner hoch und ruft `/file/overwrite` mit `sourceId=<ziel>` und
`destId=<eigene Datei>` auf. Der Ziel-Datensatz ist weg, Verweise auf seine ID brechen, der Inhalt
lebt unter der ID des Redakteurs weiter.

## Acceptance criteria
- [x] `overwriteAction` verlangt für den Quell-Datensatz `Permission::isDeletable` auf `PIM\File` und die `OWN`/`GROUP`-Regel fürs Löschen.
- [x] Das Entfernen der Quelle schreibt einen DELETED-Logeintrag wie `Api::doDelete`.

## Verification
Integrationstest mit `writable=ALL`, `deletable=NONE`: `/file/overwrite` auf eine fremde Quelle.
Vor dem Fix ist der Quell-Datensatz gelöscht, nach dem Fix antwortet der Request 403 und beide
Datensätze bestehen.
