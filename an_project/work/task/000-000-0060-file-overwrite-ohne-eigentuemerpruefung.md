---
id: 000-000-0060
title: /file/overwrite überschreibt fremde Dateien ohne Eigentümerprüfung
status: todo
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
- [ ] `destId` wird bei `OWN`/`GROUP` nur überschrieben, wenn der Benutzer diese Datei schreiben darf — dieselbe Verengung wie `Api::doUpdate()`.
- [ ] `sourceId` wird nur verwendet, wenn der Benutzer sie schreiben darf — sie wird dabei gelöscht.
- [ ] Die Zeile zu `overwriteAction()` in der Matrix-Tabelle von `PermissionMatrixApiTest.php` steht auf „korrekt".

## Verification
Integrationstest: Benutzer mit `writable = OWN` versucht, eine Datei des Admins mit gleichem
Namen zu überschreiben → 403, Inhalt der Zieldatei auf der Platte unverändert. Gegenstück: die
eigene Datei lässt sich überschreiben.
