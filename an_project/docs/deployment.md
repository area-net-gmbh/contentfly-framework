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
<!-- Vom Team ergänzen, sofern für dieses Repo überhaupt relevant (Dev/CI). -->
