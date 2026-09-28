---
id: 015-000-0006
title: /file/upload überschreibt fremde Dateien ohne Eigentümerprüfung
status: review
depends_on: []
---

# /file/upload überschreibt fremde Dateien ohne Eigentümerprüfung

## Context
**Security-Scan 2026-09, MEDIUM. Finding F9.** Das Panel hat F9 von HIGH auf MEDIUM gesenkt.

Zeigt `id` bei `/file/upload` auf einen bestehenden `PIM\File`, prüft `uploadAction`
(`Controller/FileController.php:212`) nur das Entity-Recht `Permission::isWritable`, das auch bei
`OWN` und `GROUP` gilt. Anders als `overwriteAction` ruft es nie `assertFileWritable` auf. Der
Upload ersetzt dann die Datei des Opfers, die Thumbnails werden neu erzeugt, und `userCreated`
wird auf den Aufrufer umgeschrieben.

Ablauf: Ein Benutzer mit `writable=OWN` auf `PIM\File` liest die ID einer fremden Datei aus einer
`/file/get`-URL und lädt mit dieser `id` eine Ersatzdatei hoch. Öffentlich verlinkte Bilder oder
PDFs haben dann einen anderen Inhalt, und der Angreifer gilt als Eigentümer.

## Acceptance criteria
- [x] Löst `id` auf einen bestehenden Datensatz auf, prüft `uploadAction` vor jedem Schreiben dieselbe `OWN`/`GROUP`-Verengung wie `overwriteAction` und `Api::doUpdate` (`assertFileWritable`).
- [x] Ein Re-Upload auf einen bestehenden Datensatz ändert `userCreated` nicht.
- [x] Der eigene Re-Upload (Eigentümer, `ALL`) funktioniert wie bisher.

## Verification
Integrationstest: Benutzer A (`OWN`) lädt mit der ID einer Datei von Benutzer B hoch. Vor dem Fix
ist Bs Datei ersetzt und A als Ersteller eingetragen, nach dem Fix antwortet der Upload 403 und
Bs Datei ist unverändert.
