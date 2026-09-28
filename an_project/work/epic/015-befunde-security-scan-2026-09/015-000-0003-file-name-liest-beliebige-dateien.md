---
id: 015-000-0003
title: File.name mit ../ liest beliebige Dateien über /api/all filedata
status: todo
depends_on: []
---

# File.name mit ../ liest beliebige Dateien über /api/all filedata

## Context
**Security-Scan 2026-09, HIGH. Finding F4.**

Die Spalte `name` eines `PIM\File`-Datensatzes lässt sich über `/api/update` und `/api/insert`
schreiben. `StringType::toDatabase` übernimmt den Wert unverändert, `UploadValidator` kommt dabei
nicht vor. `Api::getAll` (`Classes/Api.php:824`) baut daraus
`$path.'/'.$sizePrefix.$object->getName()` und liest die Datei mit `file_get_contents`. Die Grösse
`org` hängt kein Präfix an, also verlassen `../`-Segmente im Namen `data/files/<id>/`. Die
Allowlist aus `000-000-0075` prüft nur die Grösse, nicht den Namen.

Ablauf: Wer Lese- und Schreibrecht auf `PIM\File` hat (dasselbe Recht wie für den Upload), lädt
eine beliebige Datei hoch und setzt `name` auf `../../custom/config.php`. `/api/all` mit
`filedata: ["org"]` liefert dann `custom/config.php` samt DB-Zugangsdaten base64-kodiert aus.

## Acceptance criteria
- [ ] `File.name` (und `type`) lassen sich über die generische API nicht auf einen Wert mit Pfadanteil setzen. Gültig ist nur `name === basename(name)`, geprüft über dieselbe Regel wie `UploadValidator`.
- [ ] Vor jedem Dateisystemzugriff auf eine Datei eines `PIM\File` (Lesen in `getAll`, Thumbnails, Auslieferung) liegt der aufgelöste Pfad nachweislich unter `data/files/<id>/`.
- [ ] Bestehende Dateinamen ohne Pfadanteil funktionieren unverändert.

## Verification
Integrationstest: Datei hochladen, `name` per `/api/update` auf `../../custom/config.php` setzen.
Vor dem Fix enthält `/api/all` mit `filedata: ["org"]` den Inhalt der Config, nach dem Fix wird
das Update abgelehnt (400) und `filedata` enthält keine fremde Datei.
