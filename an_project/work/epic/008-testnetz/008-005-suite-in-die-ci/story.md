---
id: 008-005-0000
title: Suite in der CI verankern und als Abnahmegrundlage festschreiben
status: todo
depends_on: [008-001-0000, 008-002-0000, 008-003-0000, 008-004-0000]
---

# Suite in der CI verankern und als Abnahmegrundlage festschreiben

## Goal
Ein Testnetz, das nur lokal läuft, ist beim Umbau wertlos — niemand fährt es dann noch. Diese
Story bringt die Suite in eine Pipeline und schreibt fest, dass sie **die** Abnahmegrundlage für
Epic `009` und `011` ist.

## Umfang

### A — Die Integrationstests in der CI zum Laufen bringen
Heute laufen sie nur, wenn jemand von Hand eine Datenbank hochfährt, installiert und einen Server
startet — sonst überspringen sie sich sauber (`CONTENTFLY_TEST_BASE_URL` fehlt). Genau dieses
Überspringen ist in einer Pipeline die Gefahr: **eine grüne Suite, die nichts geprüft hat.**

Zu lösen:
- Datenbank als Service im CI-Lauf (die `docker-compose.yml` beschreibt sie bereits: MySQL 8.0,
  `utf8mb3`, Port 3307).
- Installation über `php bin/console.php appcms:install` mit Optionen statt Rückfragen — der
  Command kann das seit `012-002`.
- Server über `tests/router.php` starten. Der Router ist **nicht optional**: Ohne ihn schickt der
  eingebaute PHP-Server jede Anfrage durch `index.php`, auch die für existierende Dateien, und
  die Dateiauslieferung hängt genau daran.
- **`APP_DEBUG=0`**, sonst überdeckt der Debug-Exception-Handler die Antworten der Anwendung.
- Ein Wächter, der den Lauf **rot** macht, wenn die Integrationstests übersprungen wurden.

### B — Der Stolperstein `custom/config.php`
Die Installation schreibt echte Zugangsdaten in `custom/config.php` — eine Datei, die als
**Vorlage** im Repo liegt. `tests/README.md` warnt bereits davor, sie so zu committen. In der CI
ist das unkritisch (der Checkout ist flüchtig), aber die Pipeline darf die Datei nicht
zurückschreiben, und der Ablauf gehört dokumentiert.

### C — Der Bezug zu Epic `006`
Epic `006` hängt an dieser Story. Zwei Dinge folgen daraus:
- Die Pipeline ruft die Suite heute über `./custom/vendor/bin/phpunit` auf. Nach `006-004` liegt
  PHPUnit im Root-`require-dev`, der Pfad wird zu `./vendor/bin/phpunit`. Die Pipeline ist so zu
  schreiben, dass diese Umstellung ein Einzeiler bleibt.
- `composer audit --locked` und das „0 Deprecations"-Gate kommen mit `006-005` in denselben
  Lauf. Diese Story legt die Pipeline an, in die sie sich einfügen.

### D — Als Abnahmegrundlage festschreiben
Die Suite ist ab hier mehr als ein Testordner. In `an_project/docs/` gehört festgehalten:
- **Für Epic `009`:** Der Kernel-Tausch gilt als gelungen, wenn diese Suite ohne inhaltliche
  Änderung grün bleibt. Eine Testanpassung ist ein Verhaltenswechsel und braucht eine Begründung
  — sie ist kein Wartungsschritt.
- **Für Epic `011`:** dieselbe Suite als Release-Kriterium.
- **Für Epic `007`:** die Suite als Referenz, an der ein Bestandsprojekt prüfen kann, ob sein
  eigener Umstieg geglückt ist.

## Offene Fragen
- **Welche CI.** Das Repo hat keine Pipeline-Definition. GitHub Actions, GitLab CI oder etwas
  anderes ist zu entscheiden und in `an_project/docs/deployment.md` festzuhalten. Die Entscheidung
  betrifft auch `006-005`, das dieselbe Pipeline erweitert.
- **PHP-Version im Lauf.** Heute läuft lokal 8.3, Ziel ist 8.5. Solange `006` nicht durch ist,
  läuft die Suite gegen den Silex-Stand — die CI-Version muss dazu passen und beim Umstieg
  mitwandern.

## Fertig, wenn
- Die Pipeline-Definition liegt im Repo und startet Datenbank, Installation und Testserver
  selbst.
- Beide Suiten laufen; **ein Überspringen der Integrationstests macht den Lauf rot.**
- Die Suite ist gegen den heutigen Silex-Stand grün.
- `an_project/docs/deployment.md` beschreibt die Pipeline; `an_project/docs/runbook.md` und
  `tests/README.md` sind auf den Stand gebracht.
- Die Rolle der Suite als Abnahmegrundlage für `009`, `011` und `007` ist schriftlich
  festgehalten — inklusive des Satzes, dass eine Testanpassung beim Kernel-Tausch begründet
  werden muss.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
- [ ] 008-005-0001 — Die Pipeline anlegen: Datenbank, Installation und Testserver
- [ ] 008-005-0002 — Ein übersprungener Integrationstest macht den Lauf rot
- [ ] 008-005-0003 — custom/config.php gegen versehentliches Committen absichern
- [ ] 008-005-0004 — Die Suite als Abnahmegrundlage festschreiben
