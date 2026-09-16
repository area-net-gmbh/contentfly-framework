---
id: 011-002-0002
title: Die Pipeline auf GitHub Actions bringen
status: in-progress
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
- [x] Dieselben fünf Prüfungen laufen als GitHub-Actions-Workflow: Vorlagen-Konfiguration, Audit, PHPStan, Suite auf PHP 8.3 und 8.4.
- [x] Die Schritte kommen weiter aus `tools/ci/*.sh`; die Skripte laufen unverändert auch lokal in Docker.
- [x] Die GitLab-spezifischen Umgebungsvariablen (`$CI_PROJECT_DIR` und Verwandte) sind ersetzt, nicht nur überschrieben.
- [x] Die Geheimnisse stehen in den Repository-Secrets, nicht in der Workflow-Datei — wie zuvor bei `CONTENTFLY_TEST_ADMIN_PASS`.
- [x] `.gitlab-ci.yml` ist entfernt, und die Doku (`technical.md`, `deployment.md`, `tests/README.md`) nennt den neuen Weg.
- [ ] Ein Lauf ist grün durchgegangen — belegt, nicht behauptet.

## Verification
Der Actions-Lauf auf `master` ist grün, und die Suite darin meldet dieselbe Zahl wie lokal.
Gegenprobe: Eine absichtlich eingebaute Deprecation macht den Lauf rot.

## Ergebnis (Stand: noch nicht abgenommen)

**Das Gerüst ist portiert, die Logik nicht angefasst.** `.github/workflows/pipeline.yml` fährt
dieselben Prüfungen wie zuvor, und die Schritte stehen unverändert in `tools/ci/*.sh` — die
Entscheidung von `000-000-0029` zahlt hier: „Eine Pipeline-Definition, deren Schritte man nur in
der Pipeline ausprobieren kann, ist beim Suchen eines Fehlers nutzlos."

| vorher (GitLab) | jetzt (Actions) |
|---|---|
| `stages: check, test` | `needs:` auf dem Testjob |
| `check:template-config` im `alpine:3` | ohne Container auf `ubuntu-latest` — das Skript ist POSIX sh und liest eine Datei |
| `test:php8.3` / `test:php8.4` | ein Job, `strategy.matrix.php`, `fail-fast: false` |
| `allow_failure` (seit `010-003-0003` nicht mehr gesetzt) | **beide blockierend**, ausdrücklich so entschieden |
| Push auf jeden Branch | Push auf `master` und jeder Pull Request, dazu `workflow_dispatch` |

`workflow_dispatch` ist kein Komfort: Ohne ihn liesse sich der erste Lauf nur durch einen Merge
nach `master` oder einen Pull Request auslösen — also erst, nachdem er bereits hätte grün sein
müssen.

### Die eine Stelle, die sich nicht übertragen liess

Die GitLab-Pipeline übergab dem MySQL-Container `--character-set-server=utf8mb3` und
`--collation-server=utf8mb3_unicode_ci` als Kommandozeile. **GitHub Actions kann das nicht:** Ein
Service-Container nimmt `image`, `env`, `ports`, `volumes` und `options` — und `options` sind
docker-Optionen **vor** dem Imagenamen, die Flags müssten dahinter stehen. Für einen Schlüssel
`command:` finden sich Blogbeiträge, aber kein Eintrag in der Referenz; darauf zu bauen hiesse,
den Zeichensatz von etwas Undokumentiertem abhängig zu machen.

Der Zeichensatz wird deshalb gesetzt, **nachdem** die Datenbank antwortet — als Schritt `1b` in
`prepare-test-environment.sh`, mit Gegenprobe aus `information_schema`. Das Ergebnis ist dasselbe
und hängt nicht mehr daran, wie der Server gestartet wurde: Lokal aus `docker-compose.yml` (die die
Flags weiterhin mitgibt) ist der Aufruf ein Leerlauf, in der Pipeline stellt er den Zustand her.
Lokal gemessen: vorher `utf8mb3_unicode_ci`, nachher `utf8mb3_unicode_ci`, kein Rechtefehler.

### Ein Widerspruch in der Doku, nebenbei

`deployment.md` führte `test:php8.4` mit `allow_failure: true` und nannte es „eine offene
Entscheidung". **Die Pipeline hatte sie mit `010-003-0003` längst getroffen** — `allow_failure`
stand dort nicht mehr. Die Entscheidung „blockierend" bestätigt also den Ist-Zustand; zu ändern war
die Doku.

### Ein Befund, der ein eigenes Ticket bekommen hat

Von vier Läufen derselben Suite gegen dieselbe Instanz war **einer rot** —
`AuthApiTest::testUserDeactivationTakesEffectImmediatelyWithoutRevocationList`, allein aufgerufen
grün. PHPUnit würfelt den Seed; die Suite ist also nicht reihenfolgeunabhängig. Bis hierher lief
sie an einer Stelle, ab jetzt bei jedem Push und jedem Pull Request — ein grundlos roter Lauf
kostet dann jedes Mal eine Untersuchung. Festgehalten als `000-000-0050`.

### Lokal geprüft, was lokal prüfbar ist

| | |
|---|---|
| `prepare-test-environment.sh` end-to-end | ✓ inklusive des neuen Schritts `1b` |
| Volle Suite gegen die so gebaute Instanz | `Tests: 615, Assertions: 2506, Skipped: 3` |
| PHPStan | `[OK] No errors` |
| Deprecation-Gate | 0 |
| `check-template-config` | Exit 0 nach Wiederherstellen der Vorlage — und Exit 1, solange die installierte Fassung im Baum lag, also greift der Wächter |
| `audit.sh` | **nicht lokal fahrbar:** verlangt Composer ≥ 2.8, diese Maschine hat 2.6.6. Das Skript sagt es selbst; in der Pipeline installiert `install-composer.sh` eine aktuelle Fassung. |
| Workflow-Datei | als YAML geparst; Jobs, Trigger, Matrix und `needs` sind die beabsichtigten |

**Was hier nicht steht, ist der grüne Lauf.** Er ist das letzte Acceptance-Kriterium und lässt sich
nur auf GitHub erbringen. Dafür fehlt noch das Repository-Secret `CONTENTFLY_TEST_ADMIN_PASS`.
