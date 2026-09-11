---
id: 007-001-0001
title: Die Trennlinie zwischen Framework und Projekt ziehen und festschreiben
status: review
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
- [x] Jeder der 26 versionierten Top-Level-Einträge ist zugeordnet: Paket, Projekt, oder bleibt im Entwicklungs-Repo — keiner bleibt offen.
- [x] Die Paketform ist gewählt und begründet, mitsamt der verworfenen Alternative.
- [x] Es steht fest, wie das Paket die Konfiguration des Projekts findet — als Weg, nicht als Absichtserklärung.
- [x] Es steht fest, wie ein Projekt Frameworkverhalten überschreibt, wenn Danebenlegen nicht mehr geht.
- [x] Die Zukunft von `custom/vendor/` und der Zusicherung aus `006-004-0001` ist entschieden.
- [x] Alles davon steht in `an_project/docs/architecture.md` unter *Key decisions*, mit Datum und verworfenen Alternativen — nicht in diesem Task.

## Verification
Die vier folgenden Tasks lassen sich aus dem geschriebenen Abschnitt ableiten, ohne dass eine
der vier Fragen erneut gestellt werden muss. Wer den Abschnitt liest, kann sagen, wohin eine
beliebige Datei des heutigen Baums gehört.

## Ergebnis

**Die Entscheidung steht in `an_project/docs/architecture.md` unter *Key decisions*, datiert auf
den 2026-09-11** — nicht hier. Dieser Abschnitt sagt, was entschieden wurde und was beim
Entscheiden aufgefallen ist.

**Die alte Entscheidung hat sich selbst abgelöst.** Der Eintrag vom 2026-09-09 („Zwei
Composer-Bäume, Root vor `custom/`") endet mit *Revidieren, wenn Epic `007` entscheidet, das
Framework als Composer-Paket auszuliefern*. Genau das ist passiert. Der Eintrag ist als revidiert
gekennzeichnet und **bleibt stehen**: Er trägt die Begründung des Zustands, den ein
Bestandsprojekt heute noch vorfindet, und der Migrationsleitfaden aus `007-004` braucht ihn.

**Gewählt: ein Bibliothekspaket, und dieses Repo ist zugleich das Skeleton.** `areanet/contentfly`
mit `type: library` und einem einzigen Namensraum. Ein zweites, eigens gepflegtes Skeleton-Paket
entsteht nicht — es liefe auseinander, weil es niemand fährt, und die Wurzel dieses Repos wird
auf jedem Commit von der vollen Suite gefahren. Das ist dieselbe Überlegung, aus der
`000-000-0029` einen Test statt eines Kommentars bekommen hat: Was nicht läuft, verfällt.

**Alle 26 versionierten Top-Level-Einträge sind zugeordnet**, in drei Töpfe: Paket, Projekt,
Entwicklungs-Repo. Die Tabelle steht in `architecture.md`. Keiner blieb offen.

**Der wichtigste Fund macht die Grenze erst ziehbar: Das Framework verweist auf keine
Projektklasse.** Die beiden einzigen Verweise sind tote Importe — `Classes/Types/OnejoinType`
importiert `Custom\Entity\TestMeta`, `Controller/SystemController` importiert
`Custom\Entity\Ansprechpartner` — und **beide Klassen existieren nicht**, in keinem der beiden
Bäume. Ein ungenutztes `use` wertet PHP nie aus, deshalb hat es nie jemanden gestört. Hätte auch
nur einer der beiden Verweise etwas getroffen, wäre die Paketgrenze nicht zu ziehen gewesen, ohne
vorher eine Abhängigkeit umzudrehen. Die Importe fallen mit `007-001-0004`; das Kriterium ist
dort nachgetragen, mit dem Vermerk, woher es kommt.

**Der Doppel-Autoloader fällt, und die Frage stellt sich danach nicht mehr.** Ein Projekt hat
künftig einen Composer-Baum. Damit gibt es keine zwei Bäume, zwischen denen eine Rangfolge zu
zusichern wäre. **Der Ersatz ist stärker als die Zusicherung, die er ablöst:** Die alte Regel
hielt eine Überschneidung fern, solange ein Test die Bedingung prüfte; Composer verweigert
unvereinbare Constraints beim Auflösen. Der Fall, an dem das jahrelang scheiterte — `psr/log` in
1.1.3 und 3.0.2 gleichzeitig im Prozess — kann nicht mehr entstehen.
`AutoloaderUeberschneidungTest` wird umgedreht, nicht gelöscht: Er prüft danach, dass es genau
einen Baum gibt.

**Die Konfiguration wird übergeben, nicht gesucht.** Der Einstiegspunkt reicht das
Projektverzeichnis durch; darunter erwartet das Paket `custom/config.php`, und fehlt sie, nennt
die Meldung den erwarteten Pfad. Verworfen wurden eine Umgebungsvariable (im Code unsichtbar,
global falsch setzbar) und das Suchen nach oben bis zur nächsten `composer.json` — letzteres mit
Nachdruck, denn das ist Raten, und Raten ist genau das, was `ROOT_DIR` tut.

**Überschrieben wird über die Registrierungsstellen, die es schon gibt,** nicht mehr durch
Danebenlegen. Fehlt für einen Fall eine Stelle, ist das eine Lücke des Frameworks und wird
aufgeschrieben — eine Umgehung, die funktioniert, verhindert, dass die Lücke je geschlossen wird.

**Kein Anwendungscode geändert.** Das war der Zweck dieses Tasks: Solange die Trennlinie nicht
steht, ist jede Änderung am Manifest geraten.
