---
id: 010-003-0000
title: Der ORM-3-Sprung
status: todo
depends_on: [010-001-0000, 010-002-0000]
---

# Der ORM-3-Sprung

## Goal
`doctrine/orm` steht auf 3.x, die Suite ist grün, und die vier verbliebenen PHPStan-Muster für
ORM-2-APIs sind gegenstandslos.

**Der erste Task misst, statt zu schätzen.** Story `009-005` hat vorgeführt, warum: Dort waren
es nicht die neun erwarteten Aufrufstellen, sondern 173 rote Tests aus einer einzigen Ursache,
die in keiner Tabelle stand. Der Messtask löst `doctrine/orm ^3` probeweise auf, fährt die Suite
und schreibt auf, was tatsächlich bricht — erst danach steht der Umfang der Folge-Tasks fest.

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
