---
id: 000-000-0060
title: /file/overwrite überschreibt fremde Dateien ohne Eigentümerprüfung
status: done
depends_on: []
---

# /file/overwrite überschreibt fremde Dateien ohne Eigentümerprüfung

## Context
**Gefunden bei `000-000-0057`.** `FileController::overwriteAction()` ersetzt den Inhalt der Datei
`destId` durch den von `sourceId` und löscht danach das Verzeichnis der Quelle. Geprüft wird nur
`writable != 0` auf `PIM\File` — **die Eigentümerschaft keiner der beiden Dateien.**

Folge: Ein Benutzer mit `writable = OWN` auf `PIM\File` lädt eine eigene Datei hoch und
überschreibt damit jede fremde Datei gleichen Namens; zugleich verschwindet eine fremde Quelle.
Die einzige Hürde ist der gleiche Dateiname — bei `image.jpg` oder `logo.png` keine.

## Acceptance criteria
- [x] `destId` wird bei `OWN`/`GROUP` nur überschrieben, wenn der Benutzer diese Datei schreiben darf — dieselbe Verengung wie `Api::doUpdate()`.
- [x] `sourceId` wird nur verwendet, wenn der Benutzer sie schreiben darf — sie wird dabei gelöscht.
- [x] Die Zeile zu `overwriteAction()` in der Matrix-Tabelle von `PermissionMatrixApiTest.php` steht auf „korrekt".

## Verification
Integrationstest: Benutzer mit `writable = OWN` versucht, eine Datei des Admins mit gleichem
Namen zu überschreiben → 403, Inhalt der Zieldatei auf der Platte unverändert. Gegenstück: die
eigene Datei lässt sich überschreiben.

## Ergebnis
**`/file/overwrite` prüft jetzt beide Dateien.** `FileController::assertFileWritable()` verengt
das Schreibrecht auf `PIM\File` für Ziel **und** Quelle nach derselben Regel wie
`Api::doUpdate()`: `OWN` erreicht eigene Dateien und solche, die den Benutzer in `users` führen,
`GROUP` zusätzlich die für die eigene Gruppe freigegebenen.

**Die Quelle wird mitgeprüft, nicht nur das Ziel** — sie wird verschoben, nicht kopiert. Wer eine
fremde Quelle benutzen dürfte, könnte sie damit löschen.

**Tests in `FileApiTest.php`**, geprüft an Statuscode **und** Inhalt auf der Platte: fremdes Ziel,
fremde Quelle, eigene Dateien, und `GROUP` mit freigegebener gegen nicht freigegebene Datei. **Vor
dem Fix drei rot**, der Fall „eigene Dateien" grün; danach alle grün, die Suite 673 Tests, PHPStan
ohne Fehler. Die Zeile in der Matrix-Tabelle steht auf „korrekt".
