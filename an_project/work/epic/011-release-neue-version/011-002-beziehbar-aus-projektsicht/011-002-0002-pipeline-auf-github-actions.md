---
id: 011-002-0002
title: Die Pipeline auf GitHub Actions bringen
status: todo
depends_on: [011-002-0001]
---

# Die Pipeline auf GitHub Actions bringen

## Context
Die Prüfungen laufen in `.gitlab-ci.yml`: fünf Jobs in zwei Stages, ein MySQL-Service, zwei
PHP-Versionen. Mit dem Umzug aus `0001` fährt sie niemand mehr.

**Der Umbau ist kleiner, als er aussieht, und zwar wegen einer Entscheidung von damals:** Die
eigentlichen Schritte stehen in `tools/ci/*.sh`, nicht in der Pipeline-Datei — „eine
Pipeline-Definition, deren Schritte man nur in der Pipeline ausprobieren kann, ist beim Suchen
eines Fehlers nutzlos". Portiert wird das Gerüst, nicht die Logik.

**Nicht verwässern:** Die Gates sind blockierend und sollen es bleiben — Deprecation-Log auf 0
Ausnahmen, PHPStan ohne Fehler, `composer audit --locked --abandoned=fail`. Eine Ausnahme, die
nicht mehr greift, macht den Lauf rot.

## Acceptance criteria
- [ ] Dieselben fünf Prüfungen laufen als GitHub-Actions-Workflow: Vorlagen-Konfiguration, Audit, PHPStan, Suite auf PHP 8.3 und 8.4.
- [ ] Die Schritte kommen weiter aus `tools/ci/*.sh`; die Skripte laufen unverändert auch lokal in Docker.
- [ ] Die GitLab-spezifischen Umgebungsvariablen (`$CI_PROJECT_DIR` und Verwandte) sind ersetzt, nicht nur überschrieben.
- [ ] Die Geheimnisse stehen in den Repository-Secrets, nicht in der Workflow-Datei — wie zuvor bei `CONTENTFLY_TEST_ADMIN_PASS`.
- [ ] `.gitlab-ci.yml` ist entfernt, und die Doku (`technical.md`, `deployment.md`, `tests/README.md`) nennt den neuen Weg.
- [ ] Ein Lauf ist grün durchgegangen — belegt, nicht behauptet.

## Verification
Der Actions-Lauf auf `master` ist grün, und die Suite darin meldet dieselbe Zahl wie lokal.
Gegenprobe: Eine absichtlich eingebaute Deprecation macht den Lauf rot.
