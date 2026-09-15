---
id: 000-000-0038
title: Upload — eine hochgeladene PHP-Datei wird ausgeführt
status: todo
depends_on: []
---

# Upload — eine hochgeladene PHP-Datei wird ausgeführt

## Context
**Gefunden bei `007-005-0001`** am Bestandsprojekt UFP. Das Projekt hat die Lücke in seiner
Framework-Kopie geschlossen („upload RCE remediation"); im neuen Framework ist sie offen.

**Gemessen an einer frischen Installation dieses Repos (2026-09-15):** `POST /file/upload` mit
`probe-upload.php` (Inhalt `<?php echo "EXECUTED-" . (6*7);`) wird unter
`data/files/<id>/probe-upload.php` gespeichert. Der Abruf dieser Adresse antwortet
**`EXECUTED-42`** — der Code lief.

**Warum das geht:** `FileController` übernimmt Endung und Namen vom Client
(`getClientOriginalName()`, `getClientMimeType()`) und bereinigt nur den Basisnamen. `data/files/`
liegt im Document-Root, und die `.htaccess` liefert alles, was dort existiert, direkt aus — der
Webserver behandelt eine `.php`-Datei dann als Skript.

**Wer das auslösen kann:** jeder mit einem gültigen Token. In einem Projekt, dessen LoginProvider
Tokens ohne Zugangsdaten vergeben (anonyme Teilnehmer, wie in UFP), ist das **ohne
Zugangsdaten**.

**Betrifft den Prüfstand der IT-Security** `v2.0.0-pre-security-2026-09-14`; der Befund steht dort
nicht auf der Liste der bekannten offenen. Den Nachtrag an die IT-Security übernimmt der
Auftraggeber.

**Entschieden am 2026-09-15:** Eine Sperrliste greift immer; eine Whitelist mit Inhaltsprüfung ist
optional über `FILE_ALLOWED_TYPES`. Ein allgemeiner Datenspeicher soll `.txt` oder `.pdf` ohne
Konfiguration annehmen — aber nie etwas Ausführbares.

## Acceptance criteria
- [ ] Ein Upload, dessen Name in **irgendeinem** Punkt-Segment eine ausführbare Endung trägt (`.php`, `.phtml`, `.phar`, `.pht`, … — auch `shell.php.jpg`) oder der als Ganzes ein Konfigurationsname ist (`.htaccess`, `.user.ini`, …), wird mit `415` abgewiesen, und es entsteht **keine** Datei und **keine** Zeile.
- [ ] Der gespeicherte Name wird vom Framework gebildet: keine Pfadbestandteile, keine Steuerzeichen, kein Dotfile.
- [ ] Ist `FILE_ALLOWED_TYPES` gesetzt, gilt zusätzlich die Whitelist: Endung muss darin stehen, und der **aus dem Inhalt** ermittelte Typ muss zu ihr passen.
- [ ] `/file/overwrite` ist genauso geschützt wie `/file/upload`.
- [ ] Die Sperrliste lässt sich über die Konfiguration **nicht** aufweichen.
- [ ] Die Messung von oben ist wiederholt und antwortet nicht mehr `EXECUTED-42`; ein Test hält fest, dass die Datei gar nicht erst entsteht.
- [ ] `.txt`-Uploads der bestehenden Suite laufen unverändert.
- [ ] `breaking-changes.md` und `technical.md` (Befundtabelle) sind nachgezogen.

## Verification
Die Messung aus dem Context wiederholen. Volle Suite, PHPStan, Deprecation-Gate.
