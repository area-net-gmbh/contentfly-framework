---
id: 009-004-0002
title: Die Dokumente auf den neuen Kernel nachziehen
status: review
depends_on: []
---

# Die Dokumente auf den neuen Kernel nachziehen

## Context
Sieben Dokumente nennen Silex noch als laufenden Stand. Gemessen:

| Datei | Fundstellen |
|---|---|
| `an_project/docs/abhaengigkeiten-inventar.md` | 11 |
| `an_project/docs/deployment.md` | 4 |
| `an_project/docs/tech-stack.md` | 2 |
| `an_project/docs/technical.md` | 2 |
| `tests/README.md` | 2 |
| `an_project/docs/architecture.md` | 1 |
| `README.md` | 1 |

**Nicht jede Fundstelle ist falsch.** `abhaengigkeiten-inventar.md` ist ein datierter,
generierter Schnappschuss — er behauptet nichts über den Jetzt-Zustand, und ihn umzuschreiben
hiesse, eine Messung zu fälschen. Dasselbe gilt für jede Stelle, die Silex in der
Vergangenheitsform als Begründung nennt: Sie erklärt, warum etwas so ist, und bleibt richtig.

Zu ändern ist, was Silex als **laufenden** Stand beschreibt. `technical.md` hat dazu einen
ganzen Abschnitt über Prioritäten bei `before()`/`after()`, der mit „Silex nimmt …" beginnt.

## Acceptance criteria
- [x] Jede der Fundstellen ist angesehen und entweder nachgezogen oder als richtig
      stehengelassen — mit Begründung im Ergebnis, nicht stillschweigend.
- [x] `abhaengigkeiten-inventar.md` bleibt unangetastet; die Begründung steht im Ergebnis.
- [x] `technical.md` beschreibt die Middleware-Reihenfolge auf dem neuen Kernel und verweist
      auf den Test, der sie nachweist (`HookReihenfolgeTest`).
- [x] `tech-stack.md` beschreibt Symfony 7.4 als **erreichten**, nicht als angestrebten Stand;
      der Satz über „aktuell noch Silex 2" ist aufgelöst.
- [x] Die Zusicherung „deprecation-frei bauen" aus `tech-stack.md` ist mit dem Stand belegt, den
      `009-003` hergestellt hat.

## Verification
`grep -ri silex` über die Dokumente: Was übrig bleibt, steht in der Vergangenheitsform oder in
einem datierten Schnappschuss. Jede verbliebene Stelle ist im Ergebnis genannt.

## Ergebnis

**23 Fundstellen angesehen, davon 12 nachgezogen und 11 begründet stehengelassen.** Die
Messung im Context zählte 23 in sieben Dateien; sie war vollständig, aber sie deckte nur `.md`
ab. Ausserhalb standen weitere Aussagen im Präsens, die schlicht falsch geworden waren — die
wichtigste in `lib/contentfly/bootstrap.php`: „Die Klasse erbt **heute** von Silex."

### Was stehengeblieben ist, und warum

| Stelle | Grund |
|---|---|
| `abhaengigkeiten-inventar.md` (11 Fundstellen) | Datierter, generierter Schnappschuss. Ihn umzuschreiben hiesse, eine Messung zu fälschen. Er behauptet nichts über den Jetzt-Zustand. |
| `architecture.md` — *Key decision 2026-09-04* | Eine Begründung, die zum Zeitpunkt der Entscheidung richtig war. „Silex läuft hier immer noch" **ist** das Argument, das die Entscheidung getragen hat. Stattdessen ein Absatz **Eingelöst mit Epic 009** darunter. |
| `breaking-changes.md`, `deprecations-ausnahmen.txt`, `dependency-assignment.json` | Durchweg Vergangenheitsform beziehungsweise datierte Zuordnung. |

### Was nachgezogen wurde

- **`tech-stack.md`** — Symfony 7.4 als **erreichter** Stand; „aktuell noch Silex 2" aufgelöst.
  Die Zusicherung „deprecation-frei bauen" ist mit dem Stand aus `009-003` belegt, samt der
  Zahl der übrigen Ausnahmen und ihres Auflösers.
- **`technical.md`** — die Prioritätsregel für `before()`/`after()` beschreibt jetzt den
  Symfony-Dispatcher und verweist auf `HookReihenfolgeTest`, der sie nachweist. Zwei Zeilen der
  Tabelle *Was den Alt-Baum an PHP 8.5 hindert* sind als erledigt durchgestrichen.
  **Ein Nebenbefund für ein Portierungsmuster:** `$app->protect()` gibt es im neuen Container
  nicht, weil im Baum niemand eine Closure als Wert ablegt — wer das Pro-Tenant-JWT-Secret
  portiert, braucht es zuerst. Steht jetzt an der Stelle.
- **`deployment.md`** — die Jobtabelle kannte `check:audit` und `check:phpstan` nicht. Die
  Ausnahmetabelle und *Was die Gates heute melden* waren durchweg überholt.
- **`README.md`**, **`tests/README.md`** — Technologien und zwei Erklärungen.
- **`composer.json`** — der Warnblock „BEVOR JEMAND DIE CONSTRAINTS ANFASST" beschrieb eine
  Kette, die es nicht mehr gibt.
- **Code und Tooling** — `lib/contentfly/bootstrap.php`, `tests/bootstrap.php`,
  `SystemControllerApiTest`, `tools/ci/audit.sh`, `tools/ci/prepare-test-environment.sh`.

### Der Warnblock im Manifest war die gefährlichste Stelle

Er beschrieb die Constraint-Kette, an der bis `009` alles hing: ORM als Untergrenze, `silex` und
`knplabs` als Obergrenze, dazwischen genau Symfony 4.4, und `doctrine/dbal` bewusst auf `^2.13`.
**Jede dieser vier Aussagen ist heute falsch.** Der Deckel hat die Seite gewechselt: Symfony 7.4
ist die Untergrenze, ORM 2.20 die Obergrenze, dazwischen DBAL 3.x. Ein Text, der zum Nachrechnen
auffordert und dabei die falsche Kette nennt, ist schlimmer als keiner.

**`extra` geht in Composers `content-hash` ein**, der Lock war danach als veraltet gemeldet.
`composer update --lock` hat ihn erneuert; ein Vergleich beider Fassungen ohne das Hash-Feld
zeigt **keinen weiteren Unterschied** — kein Paket, keine Version.

### Die Messung, die das Dokument brauchte

`deployment.md` behauptete für PHP 8.4 „46 Stellen ohne Ausnahme". Die Zahl stammt aus einem
Lauf mit Silex im Baum. Statt sie zu streichen, ist sie **neu gemessen** — im Pipeline-Image
`php:8.4-cli` gegen einen `mysql:8.0`-Service, mit dem Wortlaut des Jobs, auf einem frischen
Klon des Story-Branches:

| | PHP 8.3 | PHP 8.4 |
|---|---|---|
| Suite | `OK (267 tests, 639 assertions)` | `OK (267 tests, 639 assertions)` |
| übersprungen | 0 | 0 |
| Deprecations im Serverlog | 0 Zeilen, 0 Ausnahmen | 0 Zeilen, 0 Ausnahmen |
| Postausgang der Versandfalle | 0 Byte | 0 Byte |

**Das beantwortet eine Frage, die in der `.gitlab-ci.yml` ausdrücklich offen stand.** Dort
begründet `allow_failure: true` sich damit, der Symfony-7.4-Stand sei auf 8.4 nicht gemessen —
„Ob der Job blockierend werden kann, entscheidet ein Lauf auf 8.4, nicht diese Zeile." Der Lauf
liegt jetzt vor, und er ist grün. **Der Schalter ist trotzdem nicht gezogen:** Ein Gate
scharfzustellen ist eine Änderung am Verhalten der Pipeline und gehört nicht in eine
Dokumentationsänderung. Sie ist als Entscheidung benannt, in `deployment.md` und hier.

**Eine Abweichung beim Nachmessen:** `009-004-0004` und `-0001` melden `640 assertions`, hier
und auf 8.4 sind es reproduzierbar `639` — zweimal lokal, einmal im Container. Die Testzahl ist
in allen Läufen 267 und alle sind grün. Die eine Assertion ist nicht zugeordnet; festgehalten
statt stillschweigend angeglichen.
