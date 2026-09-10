---
id: 010-003-0000
title: Der ORM-3-Sprung
status: in-progress
depends_on: [010-001-0000, 010-002-0000]
---

# Der ORM-3-Sprung

## Goal
`doctrine/orm` steht auf 3.x, die Suite ist grün, und die vier verbliebenen PHPStan-Muster für
ORM-2-APIs sind gegenstandslos.

**Gemessen vor dem Schnitt, am 2026-09-10.** Die Story sah dafür einen eigenen Task vor; die
Messung ist stattdessen als Wegwerf-Probe in einem eigenen Worktree gelaufen, mit eigener
Datenbank und ohne einen Commit auf `master`. Das Ergebnis steht unten — einen Task, der sie
wiederholt, gäbe es dann nur noch der Form halber.

### Die Auflösung

`doctrine/orm` 2.20.13 → **3.7.0**. Entfernt werden `doctrine/cache`, `doctrine/common` und
`symfony/polyfill-php72`, dazu kommt `symfony/polyfill-php86`. **DBAL bleibt auf 3.10.6.**

### Zwei Blocker, beide beim Laden

Ungepatcht sind **196 von 268 Tests** rot — aus genau zwei Ursachen. Die Unit-Suite ist dabei
schon grün (61/61): Sie fasst den EntityManager nie an.

| Blocker | Umfang |
|---|---|
| `EntityManager::create()` entfernt | 1 Zeile in `EntityManagerFactory` |
| `ContentflyQuoteStrategy` erfüllt die typisierte Oberklasse nicht | 7 Methoden |

### Was kleiner ist als befürchtet

ORM 3 macht aus den Mapping-Arrays **Objekte**. Das klingt nach dem grössten Posten und ist
gemessen der kleinste: Im ganzen eigenen Code lesen **zwei** Stellen ein Mapping-Array, beide in
der Quote-Strategie.

### Was sich schon unter ORM 2.20 vorbereiten lässt

Drei der fünf bekannten Änderungen brauchen den Sprung nicht — die Ziele gibt es in 2.20 bereits:
`TokenType`, `ClassMetadata::GENERATOR_TYPE_NONE` und der public Konstruktor des
EntityManagers. Damit greift derselbe Schnitt wie in `010-001` und `010-002`: vorbereiten unter
der alten Version, dann umlegen. **Nicht** vorbereitbar ist die Quote-Strategie — ORM 2 übergibt
Arrays, ORM 3 Objekte, und die Signatur muss die der Oberklasse treffen.

### Statisch bekannt: 37 PHPStan-Befunde gegen ORM 3

11 × `Lexer::T_*` in `FindInSet`, `Distance`, `PointStr`; 10 an der Quote-Strategie; 5 entfallene
Console-Commands in `bin/console.php`; 2 × `ClassMetadataInfo`; `ConsoleRunner::createHelperSet()`
in `bin/cli-config.php`; dazu Rückgabetypen an den DQL-Funktionen.

Bekannt ist schon jetzt:

- **`Lexer::T_*` → `TokenType::T_*`**, 11 Stellen in `FindInSet`, `Distance` und `PointStr`.
- **`EntityManager::create()`** ist entfernt; der Ersatz ist `new EntityManager(...)`.
- **`ClassMetadataInfo`** ist in `ClassMetadata` aufgegangen, 4 Stellen.
- **`AbstractIdGenerator::generateId()`** hat eine andere Signatur — betrifft den
  `UuidGenerator` aus `009-005-0002`.
- **Die Doctrine-Console-Commands**: Ein Teil ist entfallen. `009-005-0003` hat dieselbe Frage
  schon einmal beantwortet, damals für DBAL 3 — von achtzehn Commands fiel genau einer weg.

**DBAL bewegt sich nicht.** ORM 3.7 akzeptiert `dbal ^3.8.2`, und der Baum steht auf 3.10.6.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
- [ ] 010-003-0001 — Was sich unter ORM 2.20 vorbereiten lässt
- [ ] 010-003-0002 — Der Sprung auf ORM 3
- [ ] 010-003-0003 — Die Gates auf den neuen Stand bringen
