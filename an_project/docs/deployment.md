<!-- PURPOSE: Umgebungen, CI/CD-Pipeline und Release-Prozess. Das lokale Setup nach dem Checkout steht im runbook.md, nicht hier. -->

# Deployment

## Aus diesem Repo wird nichts produktiv deployt

Dieses Repo ist die Werkbank für das Update des Frameworks (siehe *Scope* in
`an_project/project-description.md`). Es gibt hier keine Produktivumgebung, kein Rollout und
keine Ausfallzeit zu schützen. „Deployment" heißt in diesem Projekt: **Wie eine neue
Framework-Version an Bestandsprojekte ausgeliefert wird** — und wie diese darauf migrieren.

## Abhängigkeiten: Composer als Quelle

**Entschieden am 2026-09-04.** Composer ist die einzige Quelle der Wahrheit für Abhängigkeiten.

- Im Repo liegen `composer.json` und `composer.lock` — kein `vendor/`.
- Wo ein Zielsystem kein Composer hat, wird der `vendor/`-Baum in der Pipeline gebaut
  (`composer install --no-dev --optimize-autoloader`) und als Teil des Deployment-Artefakts
  ausgeliefert. Der committete `vendor/`-Baum von heute ist **kein** gültiger Ersatz dafür: er
  hat kein Manifest, keinen Lock und keinen Upgrade-Pfad (siehe `an_project/docs/technical.md`).
- `composer audit --locked` läuft als CI-Gate gegen den Lock.

Umgesetzt in Epic `006-000-0000`. Da hier kein Betrieb daran hängt, kann `vendor/` sofort aus Git
verschwinden — es muss nicht bis zum Abschalten von Silex (Epic `009-000-0000`) warten.

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

### Kein `composer install` — noch nicht
`vendor/` und `custom/vendor/` liegen heute committed im Repo, die Pipeline ruft direkt
`./custom/vendor/bin/phpunit` auf. Nach `006-004` liegt PHPUnit im Root-`require-dev` und der
Pfad wird zu `./vendor/bin/phpunit`; er steht deshalb als Variable an **einer** Stelle in der
YAML. `composer audit --locked` und das „0 Deprecations"-Gate kommen mit `006-005` in dieselbe
Pipeline — die Stage-Struktur hat dafür Platz.

### Die Suite ist die Abnahmegrundlage
Was ein roter Test beim Kernel-Tausch bedeutet, ist in `an_project/docs/technical.md`
festgelegt — einschliesslich der Liste dessen, was die Suite **nicht** abdeckt.
