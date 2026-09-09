---
id: 006-005-0001
title: composer audit als blockierendes Gate mit begründeter Ausnahmeliste
status: done
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
- [x] Ein CI-Job in der Stage `check` führt `composer audit --locked` aus und bricht bei einem
      Fund ab.
- [x] Die fünf CVEs sind **einzeln** ausgenommen, nicht paketweise; jede trägt eine Begründung
      und den Bezug auf Epic `009`.
- [x] `--abandoned=ignore` ist gesetzt **und begründet**, samt der Bedingung, wann es auf `fail`
      wechselt.
- [x] Der Job braucht kein `composer install` — er läuft gegen den Lock.
- [x] Festgehalten, dass die beiden offenen Punkte aus Abschnitt D der Story bereits erfüllt
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

## Ergebnis
**Das Gate steht und ist scharf.** `check:audit` in der Stage `check` endet auf dem Ist-Stand
mit Exit 0, zeigt dabei alle fünf ausgenommenen Meldungen samt Begründung — und wird rot,
sobald eine Meldung dazukommt, die nicht auf der Liste steht.

### Die Abgrenzung des Tasks war falsch — `composer audit` hat keinen `--ignore`-Schalter
Der Task schrieb *„Keine Änderung an `composer.json` oder `composer.lock`"* und ging von einem
CLI-Schalter aus. Den gibt es nicht, in keiner Fassung:

| Composer | `--ignore` für einzelne Advisories |
|---|---|
| 2.6.6 (lokal) | nein |
| 2.10.3 (in der CI installiert) | nein — nur `--ignore-severity` und `--ignore-unreachable` |

Die Ausnahmen leben unter **`config.audit.ignore` in `composer.json`**, je Kennung mit einer
Begründung, die Composer in der Ausgabe als *Ignore reason* mitdruckt.

**Der Sinn der Abgrenzung ist trotzdem gewahrt.** Sie begründete sich mit „Die CVEs sind nicht
durch ein Constraint zu beheben; wer es hier versucht, bricht die Kette aus `006-001-0003`".
Angefasst wurde kein Constraint: `composer.lock` ist **unverändert**, und der Diff an
`composer.json` besteht ausschliesslich aus dem neuen `audit`-Block. Das ist die bessere Stelle
als ein Schalter im Skript — sie gilt auch für den Entwickler, der `composer audit` von Hand
aufruft, und die Begründung steht neben der Kennung statt in einer Datei, die niemand liest.

### Einzeln nach Kennung, nie paketweise
Fünf Einträge, jeder mit der betroffenen Version, dem Grund der Unbehebbarkeit und dem Epic,
das ihn auflöst. `symfony/http-foundation` als Ganzes auszunehmen hätte auch jede **künftige**
Meldung dieses Pakets verschluckt — die CVE-Kennung ist die kleinste Einheit, die den Zweck
erfüllt.

### `--abandoned=ignore`, mit benannter Bedingung
Ohne das Flag ist der Job **rot** — nachgemessen: Exit 1, obwohl alle fünf Advisories
ausgenommen sind. Die fünf abandoned Pakete sind kein Sicherheitsbefund, sondern der bekannte
Ist-Stack. Im Skript steht, wann es umgestellt wird: *„Auf `fail` umstellen, sobald Epic 010
durch ist."*

### Eine Falle, die der Task nicht vorhergesehen hat
Das Gate braucht eine Mindestversion von Composer, sonst scheitert es an einer Meldung, die den
Grund nicht nennt. `tools/ci/audit.sh` prüft sie und bricht mit Klartext ab. Eine
**Untergrenze**, keine feste Version: Die Pipeline installiert jeweils den aktuellen Composer,
und ein Sicherheitswerkzeug einzufrieren wäre die falsche Sparsamkeit.

> **Korrektur, nachgetragen mit `006-005-0002`.** Dieser Abschnitt stand hier zuerst falsch: Er
> behauptete, `config.audit.ignore` gebe es erst ab Composer 2.7 und ältere Fassungen übergingen
> den Block stillschweigend. **Beides ist unzutreffend.** Composer 2.6.6 beachtet die
> Ausnahmeliste sehr wohl — nachgemessen: `advisories: 0`. Woran es wirklich hängt, ist der
> Schalter **`--abandoned`**, und den gibt es erst ab **2.8.0**:
>
> | Composer | `config.audit.ignore` | `--abandoned` |
> |---|---|---|
> | 2.6.6 | beachtet | fehlt — *„The `--abandoned` option does not exist."* |
> | 2.7.0 · 2.7.7 | beachtet | fehlt |
> | 2.8.0 | beachtet | vorhanden, Lauf endet mit Exit 0 |
>
> Die Untergrenze im Skript stand deshalb mit 2.7.0 zu niedrig — eine 2.7.x wäre durch die
> Prüfung gekommen und danach an `--abandoned` gescheitert. Korrigiert auf **2.8.0**, samt der
> richtigen Begründung.

### Verification — drei Richtungen
Gefahren in `php:8.3-cli`, mit demselben Aufruf wie die Pipeline, gegen `git archive HEAD` plus
dieser Änderung:

| Lauf | Exit | Beleg |
|---|---|---|
| Ist-Stand | **0** | fünf Meldungen mit *Ignore reason*, `✓ Keine unausgenommene Sicherheitsmeldung im Lock.` |
| eine Ausnahme (`CVE-2024-50343`) entfernt | **1** | `Found 1 security vulnerability advisory`, nennt die Kennung; kein Erfolgs-Häkchen |
| Composer auf 2.6.6 gezwungen | **1** | `✗ Composer 2.6.6 ist zu alt für dieses Gate` (die Schranke stand hier noch auf 2.7.0 — siehe Korrektur oben) |

Die zweite Zeile ist die eigentliche Abnahme. Ein Gate, das nur im Gutfall vorgeführt wurde, ist
nicht vorgeführt.

### Was sich von selbst erledigt hatte
Abschnitt D der Story nannte zwei offene Punkte. Beide waren es nicht mehr:

- *„Das Repo hat heute keine Pipeline-Definition."* — `.gitlab-ci.yml` liegt seit
  `008-005-0001` im Repo.
- *„Das Manifest löst gegen `platform.php: 8.5` auf; der CI-Container muss dazu passen."* —
  `platform.php` steht auf **8.3** (`006-002-0002`), der Job läuft auf `php:8.3-cli`. Die
  Forderung war bereits erfüllt.

Der Job braucht **kein** `composer install`: Sein `before_script` installiert nur Composer
selbst, dann läuft `--locked` gegen den committeten Lock. Damit hängt ein Sicherheitsbefund
nicht daran, ob die Suite grün ist.
