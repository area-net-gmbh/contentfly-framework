---
id: 011-002-0002
title: Die Pipeline auf GitHub Actions bringen
status: done
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
- [x] Ein Lauf ist grün durchgegangen — belegt, nicht behauptet.

## Verification
Der Actions-Lauf auf `master` ist grün, und die Suite darin meldet dieselbe Zahl wie lokal.
Gegenprobe: Eine absichtlich eingebaute Deprecation macht den Lauf rot.

## Ergebnis

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

**Korrektur an der eigenen Begründung:** `workflow_dispatch` war als Weg für den ersten Lauf
gedacht. Er taugt dafür nicht — die Referenz sagt „This event will only trigger a workflow run if
the workflow file exists on the default branch". Der erste Lauf kommt also aus einem Pull Request,
dessen Kopf diese Datei trägt; `workflow_dispatch` ist danach das Mittel, einen Lauf ohne neuen
Commit anzustossen. Der Kommentar in der Workflow-Datei sagt das jetzt so.

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

## Der grüne Lauf — und was drei Anläufe gezeigt haben

**Grün am 2026-09-17**, alle fünf Jobs: die drei Prüfungen ohne Umgebung sowie die Suite auf PHP
8.3 und 8.4.

**Das Gerüst trug auf Anhieb.** Container, MySQL-Service, der neue Zeichensatz-Schritt,
Installation, Testserver und das Deprecation-Gate liefen im ersten Anlauf durch — genau der Teil,
der sich lokal nicht prüfen liess. Rot war die Suite, und zwar an zwei echten Befunden:

### 1. Der Wächter hat meinen eigenen Commit gemeldet

`CiStepsTest` — der Wächter aus `000-000-0029`, der Schritte verbietet, die ihre Ausgabe
unterdrücken, ohne durch `tools/ci/schritt.sh` zu gehen. Ausgelöst hat ihn die Diagnose-Zeile, die
ich **eine Stunde vorher** eingebaut hatte: ` >&2 2>&1`.

Er hatte recht, und die Behebung war keine Ausnahme, sondern das Entfernen: Das `php -r` schreibt
ohnehin über `fwrite(STDERR, …)`. Der Redirect war Rauschen.

### 2. `git` fehlt im Image — ein latenter Defekt, kein Regress

`InventoryToolTest` scheiterte, weil `php:8.x-cli` kein `git` mitbringt.
`tools/migration/inventory.php` ruft `git log` auf der Framework-Kopie eines Projekts auf; **nur
deren Historie** kann sagen, ob ein Konfigurationsschlüssel vom Framework **entfernt** wurde oder ob
das Projekt ihn selbst **hinzugepatcht** hat — Befund L-6 am Bestandsprojekt UFP, das vier solche
Schlüssel hatte.

**Das ist kein Regress der Portierung.** Der Test stammt aus Epic `007` und ist jünger als der
letzte GitLab-Lauf; dort ist es nie aufgefallen. Der Umzug hat den Defekt sichtbar gemacht, nicht
verursacht.

**Die verlockende falsche Behebung wäre gewesen, den Test zu überspringen.** Ohne `git` fällt das
Werkzeug auf `old_copy_only` zurück — eine **dokumentierte** Degradation (`inventory.php:430`), kein
Fehler. Ein Skip wäre also „vertretbar" gewesen und hätte die CI dauerhaft nur den Notnagel fahren
lassen, während der Hauptpfad des Werkzeugs ungeprüft bliebe. Ein Projekt, das migriert, hat `git`
— sonst hätte es keine Kopie mit Historie. Also kommt `git` ins Image.

`000-000-0021` hatte es 2026 bewusst draussen gelassen: *„wird für nichts gebraucht: Alle 77 Pakete
des Locks kommen als dist."* **Für Composer stimmt das unverändert** — der neue Grund ist ein
anderer. Die alte Begründung steht weiter im Skript, als überholt markiert statt gelöscht; sie war
zu ihrer Zeit richtig, und der Unterschied ist die eigentliche Information.

### Was der Lauf nebenbei belegt hat

Der Wartelauf auf die Datenbank nennt seit diesem Task den **Grund** statt nur die Folge: Ausnahme,
Meldung und die geladenen PDO-Treiber. Fehlt `mysql` in dieser Liste, ist es nicht die Datenbank,
sondern ein fehlendes `pdo_mysql` — und man sucht nicht 90 Sekunden am falschen Ende. Gebraucht
wurde die Diagnose diesmal nicht, weil die Umgebung durchlief; eingebaut ist sie trotzdem geblieben.
