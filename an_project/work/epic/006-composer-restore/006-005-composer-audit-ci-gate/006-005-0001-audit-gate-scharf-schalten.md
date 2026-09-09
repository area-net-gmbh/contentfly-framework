---
id: 006-005-0001
title: composer audit als blockierendes Gate mit begründeter Ausnahmeliste
status: todo
depends_on: []
---

# composer audit als blockierendes Gate mit begründeter Ausnahmeliste

## Context
Der Grund, aus dem sich Epic `006` später auszahlt: Ein Manifest, das niemand prüft, verfällt
genauso still wie der eingefrorene Vendor-Baum, den es ersetzt.

**Der Ist-Stand ist gemessen, nicht angenommen.** `composer audit --locked` meldet heute:

| Paket | Version im Lock | CVE |
|---|---|---|
| `symfony/http-foundation` | v4.4.49 | CVE-2025-64500 · CVE-2024-50345 |
| `symfony/routing` | v4.4.44 | CVE-2026-48784 · CVE-2026-45065 |
| `symfony/validator` | v4.4.48 | CVE-2024-50343 |

Dazu fünf `abandoned`-Meldungen: `silex/silex`, `doctrine/annotations`, `doctrine/cache`,
`knplabs/console-service-provider`, `symfony/debug`. Exit-Code **3** — Advisories (1) plus
abandoned (2).

**Alle fünf CVEs sind heute unbehebbar.** Jede listet `>=4.0.0,<5.0.0` als betroffen, **ohne
Obergrenze innerhalb 4.x**: Es gibt keinen 4.4-Fix, Symfony 4.4 ist seit November 2023 EOL. Der
Ausweg wäre Symfony 5+, und genau das deckelt die Constraint-Kette aus `006-001-0003`
(`knplabs/console-service-provider` cappt auf `symfony/console ^4.0`) bis Epic `009`.

> **Abschnitt B des Story-Texts liegt hier falsch.** Er sagt, nach `006-002` bis `006-004` seien
> die Meldungen „wieder handhabbar". Sie sind es nicht — sie sind bis Epic `009` unauflösbar.
> Das ändert nichts am Sinn des Gates, aber alles an seiner Ausgestaltung.

## Umfang

### Das Gate blockiert — mit namentlicher Ausnahmeliste
Entschieden: Der Job bricht ab. Die fünf CVEs stehen **einzeln und namentlich** als `--ignore`,
jede mit Begründung. Eine **sechste** CVE macht den Lauf sofort rot — das ist der Unterschied
zwischen einer Ausnahme und einem abgeschalteten Gate.

Nicht `--ignore` auf Paketebene: `symfony/http-foundation` als Ganzes auszunehmen hiesse, auch
jede künftige Meldung dieses Pakets zu verschlucken. Die CVE-Kennung ist die kleinste Einheit,
die den Zweck erfüllt.

### `--abandoned=ignore`, und warum das keine Nachlässigkeit ist
Die fünf abandoned Pakete sind kein Sicherheitsbefund, sondern der bekannte Ist-Stack: Silex ist
seit 2018 EOL und trägt den Kernel, `doctrine/annotations` trägt die `@PIM`- und
`@ORM`-Annotationen. Beide fallen mit Epic `009` bzw. `010` — so steht es in
`tools/dependency-assignment.json` bei jedem einzelnen.

Ein Gate, das an einem Zustand scheitert, den zwei geplante Epics beheben und den heute niemand
ändern kann, wird nach zwei Läufen abgeschaltet. Die Entscheidung gehört trotzdem
ausgesprochen — mit dem Hinweis, dass `--abandoned=fail` der richtige Stand ist, sobald `010`
durch ist.

### Wo der Job hingehört
`.gitlab-ci.yml` hat eine Stage `check` für Prüfungen, die keine Umgebung brauchen — dort liegt
schon `check:template-config`. Das Audit gehört daneben: Es braucht **kein** `vendor/`, nur den
Lock. Genau dafür ist `--locked` da.

Damit läuft es auch dann, wenn der Testlauf an etwas anderem scheitert — ein Sicherheitsbefund
soll nicht davon abhängen, ob die Suite grün ist.

### Was sich von selbst erledigt hat
Abschnitt D der Story nennt zwei offene Punkte, die es nicht mehr sind:

- *„Das Repo hat heute keine Pipeline-Definition."* — `.gitlab-ci.yml` liegt seit
  `008-005-0001` im Repo, GitLab ist entschieden (der Remote ist GitLab).
- *„Das Manifest löst gegen `platform.php: 8.5` auf; der CI-Container muss dazu passen."* —
  `platform.php` steht auf **8.3** (`006-002-0002`), und der Job läuft auf `php:8.3-cli`. Die
  Forderung ist bereits erfüllt; das gehört festgehalten, damit niemand danach sucht.

## Abgrenzung
Keine Erkennung abgelaufener Ausnahmen — das ist `006-005-0002`. Kein Deprecation-Gate — das
sind `006-005-0003` und `-0004`. Keine Dokumentation — das ist `006-005-0005`.

**Keine Änderung an `composer.json` oder `composer.lock`.** Die CVEs sind nicht durch ein
Constraint zu beheben; wer es hier versucht, bricht die Kette aus `006-001-0003` und damit die
Suite.

## Acceptance criteria
- [ ] Ein CI-Job in der Stage `check` führt `composer audit --locked` aus und bricht bei einem
      Fund ab.
- [ ] Die fünf CVEs sind **einzeln** ausgenommen, nicht paketweise; jede trägt eine Begründung
      und den Bezug auf Epic `009`.
- [ ] `--abandoned=ignore` ist gesetzt **und begründet**, samt der Bedingung, wann es auf `fail`
      wechselt.
- [ ] Der Job braucht kein `composer install` — er läuft gegen den Lock.
- [ ] Festgehalten, dass die beiden offenen Punkte aus Abschnitt D der Story bereits erfüllt
      sind.

## Verification
Beide Richtungen, nicht nur die grüne:

1. **Grün auf dem Ist-Stand.** Der Job läuft lokal in Docker mit demselben Image und demselben
   Aufruf wie in der Pipeline und endet mit Exit 0.
2. **Rot bei einer neuen Meldung.** Eine sechste, erfundene CVE lässt sich nicht herbeiführen —
   also die Gegenprobe über die Ausnahmeliste: **eine** der fünf aus der `--ignore`-Liste
   entfernen; der Lauf muss rot werden und genau diese CVE nennen. Danach zurücksetzen.

Der Aufruf gehört wie die übrigen Schritte in `tools/ci/`, damit er lokal nachspielbar ist —
dieselbe Begründung wie bei `install-php-extensions.sh` und `prepare-test-environment.sh`.
