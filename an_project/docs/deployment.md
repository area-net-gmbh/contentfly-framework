<!-- PURPOSE: Umgebungen, CI/CD-Pipeline und Release-Prozess. Das lokale Setup nach dem Checkout steht im runbook.md, nicht hier. -->

# Deployment

## Aus diesem Repo wird nichts produktiv deployt

Dieses Repo ist die Werkbank für das Update des Frameworks (siehe *Scope* in
`an_project/project-description.md`). Es gibt hier keine Produktivumgebung, kein Rollout und
keine Ausfallzeit zu schützen. „Deployment" heißt in diesem Projekt: **Wie eine neue
Framework-Version an Bestandsprojekte ausgeliefert wird** — und wie diese darauf migrieren.

## Abhängigkeiten: Composer als Quelle

**Entschieden am 2026-09-04, hergestellt am 2026-09-09 mit Story `006-003`.** Composer ist die
einzige Quelle der Wahrheit für Abhängigkeiten.

- Im Repo liegen `composer.json` und `composer.lock` — **kein `vendor/`**. Beide Bäume sind seit
  `006-003-0001` aus dem Index gelöst (11 208 Dateien) und werden von `.gitignore` gefasst.
- Wo ein Zielsystem kein Composer hat, wird der `vendor/`-Baum in der Pipeline gebaut
  (`composer install --no-dev --optimize-autoloader`) und als Teil des Deployment-Artefakts
  ausgeliefert. In `006-003-0002` gegen einen frischen Klon geprüft: **49 Pakete, 2 694 Dateien,
  22 MB**, Console und HTTP booten daraus.
- `composer audit --locked` läuft als CI-Gate gegen den Lock. Steht noch aus — Story `006-005`.

**Was das für jeden Checkout heisst:** Ohne `composer install` ist er nicht lauffähig. Der
Ablauf steht in `an_project/docs/runbook.md`, Schritt 1.

> **Offen, und ein Blocker für die Pipeline:** Das CI-Image `php:8.3-cli` kann `composer install`
> heute nicht ausführen — es hat weder die `zip`-Extension noch `unzip`, `7z` oder `git`.
> Festgehalten in Task `000-000-0021`.

Der committete `vendor/`-Baum von früher wäre **kein** gültiger Ersatz für den gebauten gewesen:
kein Manifest, kein Lock, kein Upgrade-Pfad, und 27 von 78 Paketen lagen als `source` ohne
`.git` (siehe `an_project/docs/technical.md`).

## Auslieferung an Bestandsprojekte

Wie Projekte auf die neue Version kommen, ist Epic `007-000-0000` — Migrationspfad, Upgrade-Doku
und Werkzeuge. Bis dahin offen:

- Wird das Framework künftig als **Composer-Paket** (`areanet/contentfly`) bezogen statt als
  kopierter `lib/`-Baum? Das ist die naheliegende Antwort auf „migrierbar" und sollte in 007
  entschieden werden.
- Welche PHP-Version bringen die Bestandsprojekte mit? Zielplattform ist PHP 8.5 — das ist die
  Untergrenze, die ein migrierendes Projekt erreichen muss.

## Umgebungen

Zwei, und keine davon produktiv:

- **Lokal** — Datenbank aus `docker-compose.yml`, PHP von der Maschine. Der Ablauf steht in
  `an_project/docs/runbook.md`.
- **CI** — `.gitlab-ci.yml`, siehe unten.

## Die Pipeline

**Angelegt am 2026-09-08 mit Story `008-005`.** GitLab CI, weil der Remote GitLab ist.

| Job | Stage | Was er tut |
|---|---|---|
| `check:template-config` | `check` | Verhindert, dass eine installierte `custom/config.php` in die Historie gerät |
| `test:php8.3` | `test` | Beide Testsuiten gegen eine frisch installierte Instanz — **pflicht** |
| `test:php8.4` | `test` | Derselbe Lauf auf PHP 8.4, `allow_failure: true` |

**Der 8.4-Job ist eine Frühwarnung, kein Gate.** Zielplattform ist PHP 8.5; was hier rot wird,
ist die Liste dessen, was Epic `006` und `009` auf dem Weg dorthin zu erledigen haben — aber
es darf die Pipeline heute nicht anhalten. Beim Anlegen lief er grün, mit einer einzigen
Deprecation aus Silex selbst.

### Was die Pipeline voraussetzt
- **Ein Runner mit Docker-Executor.** Ohne ihn funktionieren weder `image:` noch `services:`.
  Steht nur ein Shell-Runner zur Verfügung, muss der Job Datenbank und PHP selbst mitbringen —
  ein anderer Zuschnitt, keine kleine Änderung. Die Annahme steht im Kopf der
  `.gitlab-ci.yml`.
- **`CONTENTFLY_TEST_ADMIN_PASS`** als CI-Variable (Settings → CI/CD → Variables). Bewusst
  nicht in der YAML: Auch für eine flüchtige Datenbank gehört ein Passwort nicht ins Repo.

### Warum die Schritte in `tools/ci/` stehen
Eine Pipeline-Definition, deren Schritte man nur in der Pipeline ausprobieren kann, ist beim
Suchen eines Fehlers nutzlos. `install-php-extensions.sh` und `prepare-test-environment.sh`
laufen deshalb lokal in Docker mit demselben Aufruf — so wurde die Definition auch abgenommen,
bevor sie je in einem Runner lief.

### Der Baum entsteht im Job
**Seit `006-002-0004`, und seit `006-003` ohne Rückfallebene.** PHPUnit liegt im
Root-`require-dev`, die Pipeline ruft `./vendor/bin/phpunit`. Die Vorsorge aus `008-005-0001` —
den Pfad als Variable an *einer* Stelle zu halten — hat sich ausgezahlt: Es war genau eine Zeile.

Der Job baut den Abhängigkeitsbaum selbst (`composer install`). Bis `006-003` lag daneben noch
der committete Baum im **alten** Stand ohne PHPUnit; der Installationsschritt überschrieb ihn.
Jetzt ist er weg, und der Schritt ist die einzige Quelle.

Was das kostet, ist in `006-003-0002` im Pipeline-Image gemessen:

| Schritt | Dauer |
|---|---|
| `tools/ci/install-php-extensions.sh` | 14 s |
| Composer selbst installieren | 1 s |
| `composer install --prefer-dist`, kalter Cache | **5 s** |

Der Installationsschritt ist der kleinste Posten. Ein Composer-Cache in der Pipeline lohnt an
dieser Stelle nicht — was der Lauf kostet, kostet das Herrichten des Images.

> **Der Schritt läuft heute nicht durch.** `php:8.3-cli` hat weder die `zip`-Extension noch
> `unzip`, `7z` oder `git`, und `tools/ci/install-php-extensions.sh` baut nur `pdo_mysql` und
> `gd`. Damit scheitern beide Wege — `--prefer-dist` findet keinen Entpacker, der Quell-Fallback
> bräuchte `git`. Der Bruch entstand mit `006-002-0004` und fiel erst in `006-003-0002` auf, weil
> dort das Image zum ersten Mal seit `008-005-0001` wieder von Null gefahren wurde. Behoben wird
> er in `000-000-0021`; mit nachgerüstetem `unzip` und `git` läuft er durch — die Messung oben ist
> mit dieser Ergänzung entstanden.

`composer audit --locked` und das „0 Deprecations"-Gate kommen mit `006-005` in dieselbe
Pipeline — die Stage-Struktur hat dafür Platz.

### Die Suite ist die Abnahmegrundlage
Was ein roter Test beim Kernel-Tausch bedeutet, ist in `an_project/docs/technical.md`
festgelegt — einschliesslich der Liste dessen, was die Suite **nicht** abdeckt.
