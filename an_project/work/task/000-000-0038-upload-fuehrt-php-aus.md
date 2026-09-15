---
id: 000-000-0038
title: Upload — eine hochgeladene PHP-Datei wird ausgeführt
status: done
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
- [x] Ein Upload, dessen Name in **irgendeinem** Punkt-Segment eine ausführbare Endung trägt (`.php`, `.phtml`, `.phar`, `.pht`, … — auch `shell.php.jpg`) oder der als Ganzes ein Konfigurationsname ist (`.htaccess`, `.user.ini`, …), wird mit `415` abgewiesen, und es entsteht **keine** Datei und **keine** Zeile.
- [x] Der gespeicherte Name wird vom Framework gebildet: keine Pfadbestandteile, keine Steuerzeichen, kein Dotfile.
- [x] Ist `FILE_ALLOWED_TYPES` gesetzt, gilt zusätzlich die Whitelist: Endung muss darin stehen, und der **aus dem Inhalt** ermittelte Typ muss zu ihr passen.
- [x] `/file/overwrite` ist genauso geschützt wie `/file/upload`.
- [x] Die Sperrliste lässt sich über die Konfiguration **nicht** aufweichen.
- [x] Die Messung von oben ist wiederholt und antwortet nicht mehr `EXECUTED-42`; ein Test hält fest, dass die Datei gar nicht erst entsteht.
- [x] `.txt`-Uploads der bestehenden Suite laufen unverändert.
- [x] `breaking-changes.md` und `technical.md` (Befundtabelle) sind nachgezogen.

## Verification
Die Messung aus dem Context wiederholen. Volle Suite, PHPStan, Deprecation-Gate.

## Ergebnis

**`Classes/File/UploadValidator` entscheidet, bevor etwas geschrieben wird.** `FileController`
ruft ihn als Erstes nach dem Lesen der Datei; ein abgewiesener Upload antwortet `415`
(`contentfly_file_invalid_type`) und hinterlässt weder Datei noch Zeile.

- **Die Sperrliste** (`FORBIDDEN_EXTENSIONS`, `FORBIDDEN_NAMES`) ist eine Konstante und prüft
  **jedes** Punkt-Segment, also auch `shell.php.jpg`. Eine gesperrte Endung in
  `FILE_ALLOWED_TYPES` wird verworfen; die Konfiguration kann die Sperre nicht öffnen.
- **Der gespeicherte Name** wird vom Framework gebildet: ohne Pfadbestandteile und Steuerzeichen,
  Sonderzeichen zu `-`, Endung kleingeschrieben, nie ein Dotfile (`.txt` → `file.txt`).
- **Die Whitelist ist optional** (`Config::$FILE_ALLOWED_TYPES`, Standard `null`). Gesetzt, muss
  die Endung darin stehen und der Typ **aus dem Inhalt** passen. Ermittelt mit ext-fileinfo, nicht
  mit `symfony/mime`: Das Paket ist nicht im Baum (`getMimeType()` warf im ersten Testlauf
  „Mime component is not installed"). Fehlt fileinfo bei gesetzter Whitelist, bricht der Upload mit
  einer Meldung zur Konfiguration ab, statt die Prüfung zu überspringen.
- **Bestand:** Ein Datensatz mit ausführbarem Namen aus der Zeit davor wird beim erneuten
  Hochladen umbenannt, die alte Datei gelöscht; `/file/overwrite` lehnt ihn mit `415` ab.
- `FileController::sanitizeFileName()` und `$uploadName` sind durch die Änderung verwaist und
  entfernt; die Bruchstelle steht in `breaking-changes.md`.

**Die Messung, wiederholt:** Derselbe Upload `probe-upload.php` antwortet jetzt
`HTTP 415` mit `"message_value":"php"`; unter `data/files/` liegt keine `.php`-Datei.

**Tests:**

- `UploadValidatorTest` (14): acht ausführbare Namen, Namensbildung, Whitelist mit Inhaltstyp
  (ein echtes PNG mit falschem Client-Typ wird als `image/png` erkannt; ein falsches PNG und ein
  nicht gelistetes PDF abgewiesen), Sperre gegen die Whitelist, gespeicherte Namen, fehlende Datei.
- `FileApiTest::testAnExecutableUploadIsRejectedAndLeavesNothingBehind` (3 Fälle) am echten
  Endpunkt: `415`, keine neue Zeile in `pim_file`, kein neues Verzeichnis unter `data/files/`.
  **Gegenprobe** mit dem `FileController` von master: alle drei rot.
- Die bestehenden `.txt`-Uploads der Suite laufen unverändert.

**Doku:** `technical.md` führt den Befund als ~~A-7~~ mit Behebung; `breaking-changes.md` hat einen
Eintrag mit einer Abfrage, die gespeicherte ausführbare Namen im Bestand findet (gegen MySQL
geprüft: trifft `probe.php`, `shell.php.jpg`, `x.phtml`, nicht `page.html`, `report.pdf`,
`phpinfo.txt`); der Kopf von `migration.md` steht auf 105 Einträge.

**Verifiziert:** volle Suite `Tests: 553, Assertions: 1791, Skipped: 3`, PHPStan
`[OK] No errors`, Deprecation-Gate 0. Der Sprachwächter hat ein deutsches Testbeispiel gemeldet
(`Über uns.PDF`); ersetzt durch `Résumé Draft.PDF`.

**Nicht Teil dieses Tasks:** Ein Webserver, der `data/files/` ohne diese Prüfung beschreibt oder
PHP für weitere Endungen ausführt, bleibt Sache des Deployments. Eine `.htaccess` in
`data/files/`, die dort die Skriptausführung abschaltet, wäre eine zweite Schicht nur für Apache —
als eigener Task denkbar.
