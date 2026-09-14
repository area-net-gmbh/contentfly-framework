---
id: 000-000-0035
title: Englische Strukturdoku im Root für die Übergabe der Codebase
status: done
depends_on: []
---

# Englische Strukturdoku im Root für die Übergabe der Codebase

## Context
Die IT-Security bekommt die Codebase des neuen Contentfly zur Prüfung (siehe `000-000-0033`).
**Übergeben wird nur die Codebase, ohne Framework- und Projekt-Ordner.** So entschieden am
2026-09-14. Nicht dabei sind `.an_framework/`, `.claude/`, `CLAUDE.md`, `an_project/`,
`tools/`, `.gitlab-ci.yml`, `rector.php` und `phpstan.neon.dist`.

Dabei sind die Ordner `lib/`, `custom/`, `bin/`, `tests/` und `data/`, dazu die Root-Dateien,
ohne die sich nichts installieren oder testen lässt: `index.php`, `.htaccess`,
`composer.json`, `composer.lock`, `phpunit.xml.dist`, `docker-compose.yml` und `LICENSE`.

Damit fehlt dem Empfänger jede Erklärung, wie der Code aufgebaut ist. Die liegt heute in
`an_project/docs/` und ist deutsch. `README.md` im Root ist ebenfalls deutsch und beschreibt
noch eine AngularJS-Oberfläche, die mit Epic `012` entfernt wurde.

**Gebraucht wird eine englische Datei im Root, die nur Aufbau und Struktur erklärt.** Sie muss
ohne `an_project/` verständlich sein und darf nicht dorthin verweisen.

**Nicht Teil dieses Tasks** (entschieden am 2026-09-14):
- Das Export-Archiv selbst erstellt der Auftraggeber.
- Die Liste der bekannten offenen Punkte (`A-5`, `000-000-0024`, Envelope) steht nicht in dieser
  Datei. Sie geht auf einem eigenen Weg an die Security.

## Acceptance criteria
- [x] Im Root liegt eine englische Markdown-Datei. Sie erklärt: welche Ordner und Root-Dateien
      zur Codebase gehören · die Trennung zwischen dem Framework-Paket (`lib/contentfly`) und dem
      Projekt (`custom/`) · den Weg eines Requests und eines Konsolenaufrufs vom Einstiegspunkt
      bis zur Antwort · den Aufbau von `lib/contentfly` nach Bereichen · Container, Routen,
      Middleware und Commands aus Sicht eines Projekts · Authentifizierung · Konfiguration ·
      Datenverzeichnis · Tests · Installation und Testlauf.
- [x] Jede Aussage über Pfade, Klassen, Befehle und Routen ist am Code nachgeprüft, nicht aus
      den Projekt-Dokus übernommen.
- [x] Die Datei schickt den Leser nirgends nach `an_project/`, `.an_framework/` oder `tools/` und
      nennt keine Work-Item-ID. **Präzisiert bei der Umsetzung:** Der Ordnername `an_project/docs/`
      steht genau einmal in der Datei, und zwar als nicht enthalten. Der Empfänger stösst in
      Kommentaren und zwei Exception-Meldungen darauf und soll wissen, was er vor sich hat.
- [x] Die Datei nennt, was beim Export fehlt und worauf der Empfänger deshalb stößt: die Tests,
      die ausgelassene Ordner lesen, und die Verweise auf `an_project/…` in Kommentaren.
- [x] Keine offene Sicherheitsliste und keine Befundbewertung in der Datei.

## Verification
- Jeder in der Datei genannte Pfad existiert (`test -e`), jede genannte Klasse lässt sich im
  Baum finden, jede Route ist im Router registriert (`console` bzw. Routensammlung).
- `grep` findet in der Datei weder `an_project` noch `.an_framework` noch `tools/` noch ein Muster
  `\d{3}-\d{3}-\d{4}`.
- Die genannten Tests, die ausgelassene Ordner lesen, sind durch Suche im Baum belegt.

## Ergebnis

**`STRUCTURE.md` im Root hat 16 Abschnitte**, in der Reihenfolge, in der ein fremder Leser
sie braucht: was Contentfly ist · was die Lieferung enthält und was nicht · die zwei Schichten ·
der Weg eines Requests und eines Konsolenaufrufs · der Aufbau von `lib/contentfly` · die
HTTP-API mit Auth-Spalte je Route · Authentifizierung und Berechtigungen · die
Erweiterungspunkte in `custom/` · Datenmodell · Feldtypen · Konfiguration · `data/`, Plugins
und Commands · Tests · lokaler Betrieb · ein Glossar der deutschen Bezeichner.

**Das Glossar ist kein Beiwerk.** Klassen wie `Anmeldetreiber`, `Tokenquellen` und
`Anmeldebremse` liegen genau dort, wo eine Sicherheitsprüfung zuerst hinschaut. Ohne Übersetzung
muss ein Prüfer sie sich erst erschliessen.

**Die Liste der fehlschlagenden Tests ist gemessen, nicht aus dem Code abgeleitet.** Der
Export-Umfang wurde mit `git archive` als Wegwerfkopie im Scratchpad nachgebaut, mit
`composer install` installiert und die Unit-Suite darin gefahren: **18 von 251 scheitern**, und
zwar `CiSchritteTest` (4), `MigrationsleitfadenTest` (3) und `RectorRegelTest` (11). Die
Ableitung aus dem Code hatte für `RectorRegelTest` 13 genannt. Gemessen sind es 11, zehn davon
wegen `rector.php` und einer wegen der Doku. In der Integrations-Suite liest
`ContainerSchluesselTest::testDieListenStimmenMitDemDevGuideUeberein` den dev-guide; das ist aus
dem Code belegt, nicht gemessen, weil ohne Datenbank.

**In derselben Kopie gemessen:** `php bin/console.php list` läuft, und die API antwortet vor der
Installation mit `503` und der Meldung „nicht installiert". Daraufhin ist eine Aussage
korrigiert: Die Doku sagte zuerst, jeder unbekannte Pfad antworte mit `405`. Das gilt nur für
eine installierte Instanz (belegt durch `SystemControllerApiTest`); davor kommt `503` für
`OPTIONS`, und `GET /` endet mit `500`.

Geprüft: Jeder in der Datei genannte Pfad existiert (`test -e`), jede genannte Security-Klasse
und jede Annotation ist im Baum gefunden, die Routentabelle deckt sich mit den `post()`/`get()`-
Aufrufen der vier Provider, die Command-Liste mit `console list`. `grep` findet weder
`.an_framework` noch `tools/` noch eine Work-Item-ID; `an_project` genau einmal, wie oben
beschrieben.

**Nicht Teil dieses Tasks, aber beim Nachprüfen aufgefallen:** Beim Nachlesen des Codes sind
Stellen aufgefallen, die in der offenen Liste von `an_project/docs/uebergabe-security.md` nicht
stehen. Sie sind dem Auftraggeber gemeldet, nicht in `STRUCTURE.md` aufgenommen und nicht behoben.
