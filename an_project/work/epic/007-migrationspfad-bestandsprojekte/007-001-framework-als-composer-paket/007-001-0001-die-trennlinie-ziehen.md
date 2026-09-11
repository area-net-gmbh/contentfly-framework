---
id: 007-001-0001
title: Die Trennlinie zwischen Framework und Projekt ziehen und festschreiben
status: todo
depends_on: []
---

# Die Trennlinie zwischen Framework und Projekt ziehen und festschreiben

## Context
**Dieser Task schreibt keinen Anwendungscode.** Er entscheidet, was gebaut wird — und die
folgenden vier setzen es um. Der Grund für die Trennung: Solange nicht feststeht, was Paket und
was Projekt ist, ist jede Änderung am Manifest geraten.

Die Story nennt die Trennlinie „die eigentliche Arbeit dieser Story, nicht das Umstellen des
Manifests". Hier findet sie statt.

**Der Ist-Stand, nachgemessen am 2026-09-11.** Versioniert sind 26 Einträge auf oberster Ebene.
Jeder gehört zugeordnet — Paket, Projekt, oder bleibt hier im Entwicklungs-Repo:

`lib/` · `custom/` · `plugins/` · `bin/` · `index.php` · `data/` · `tools/` · `tests/` ·
`composer.json` · `composer.lock` · `phpunit.xml.dist` · `phpstan.neon.dist` ·
`docker-compose.yml` · `.gitlab-ci.yml` · `build.xml` · `apidoc.json` · `.htaccess` ·
`robots.txt` · `favicon.ico` · `.gitignore` · `LICENSE` · `README.md` · `CLAUDE.md` ·
`an_project/` · `.an_framework/` · `.claude/`

**Vier Fragen hängen daran, und keine hat heute eine Antwort im Repo:**

1. **Ein Paket oder zwei?** Bibliothekspaket allein, oder zusätzlich ein Skeleton, mit dem ein
   neues Projekt anfängt. Am 2026-09-11 ausdrücklich offen gelassen und hierher verwiesen.
2. **Wie findet das Paket die Konfiguration des Projekts?** `custom/config.php` liegt heute im
   selben Baum und wird aus `bootstrap.php` unbedingt geladen.
3. **Wie überschreibt ein Projekt etwas?** Heute durch Danebenlegen im selben Baum. Das geht
   nicht mehr, sobald der Frameworkcode in `vendor/` liegt und bei jedem Update überschrieben
   wird.
4. **Was wird aus `custom/vendor/`?** Ein zweiter Composer-Baum mit eigenem Manifest, dessen
   `require` heute leer ist. Er trägt die Zusicherung aus `006-004-0001` („Framework schlägt
   Projekt"), die ein eigener Test prüft.

**Zwei Beobachtungen am Rand, die hier mit entschieden werden:** `plugins/` steht im Autoload und
ist leer — versioniert liegt dort keine Datei. `lib/contentfly-ui/` steht noch auf der Platte,
enthält aber nur eine `.DS_Store` und ist nicht versioniert; ein Rest aus Epic `012`.

## Acceptance criteria
- [ ] Jeder der 26 versionierten Top-Level-Einträge ist zugeordnet: Paket, Projekt, oder bleibt im Entwicklungs-Repo — keiner bleibt offen.
- [ ] Die Paketform ist gewählt und begründet, mitsamt der verworfenen Alternative.
- [ ] Es steht fest, wie das Paket die Konfiguration des Projekts findet — als Weg, nicht als Absichtserklärung.
- [ ] Es steht fest, wie ein Projekt Frameworkverhalten überschreibt, wenn Danebenlegen nicht mehr geht.
- [ ] Die Zukunft von `custom/vendor/` und der Zusicherung aus `006-004-0001` ist entschieden.
- [ ] Alles davon steht in `an_project/docs/architecture.md` unter *Key decisions*, mit Datum und verworfenen Alternativen — nicht in diesem Task.

## Verification
Die vier folgenden Tasks lassen sich aus dem geschriebenen Abschnitt ableiten, ohne dass eine
der vier Fragen erneut gestellt werden muss. Wer den Abschnitt liest, kann sagen, wohin eine
beliebige Datei des heutigen Baums gehört.
