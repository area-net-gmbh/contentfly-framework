---
id: 000-000-0035
title: Englische Strukturdoku im Root für die Übergabe der Codebase
status: todo
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
- [ ] Im Root liegt eine englische Markdown-Datei. Sie erklärt: welche Ordner und Root-Dateien
      zur Codebase gehören · die Trennung zwischen dem Framework-Paket (`lib/contentfly`) und dem
      Projekt (`custom/`) · den Weg eines Requests und eines Konsolenaufrufs vom Einstiegspunkt
      bis zur Antwort · den Aufbau von `lib/contentfly` nach Bereichen · Container, Routen,
      Middleware und Commands aus Sicht eines Projekts · Authentifizierung · Konfiguration ·
      Datenverzeichnis · Tests · Installation und Testlauf.
- [ ] Jede Aussage über Pfade, Klassen, Befehle und Routen ist am Code nachgeprüft, nicht aus
      den Projekt-Dokus übernommen.
- [ ] Die Datei verweist nirgends auf `an_project/`, `.an_framework/`, `tools/` oder Work-Item-IDs.
- [ ] Die Datei nennt, was beim Export fehlt und worauf der Empfänger deshalb stößt: die Tests,
      die ausgelassene Ordner lesen, und die Verweise auf `an_project/…` in Kommentaren.
- [ ] Keine offene Sicherheitsliste und keine Befundbewertung in der Datei.

## Verification
- Jeder in der Datei genannte Pfad existiert (`test -e`), jede genannte Klasse lässt sich im
  Baum finden, jede Route ist im Router registriert (`console` bzw. Routensammlung).
- `grep` findet in der Datei weder `an_project` noch `.an_framework` noch `tools/` noch ein Muster
  `\d{3}-\d{3}-\d{4}`.
- Die genannten Tests, die ausgelassene Ordner lesen, sind durch Suche im Baum belegt.
