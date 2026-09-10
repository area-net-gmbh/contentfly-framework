---
id: 010-003-0001
title: Was sich unter ORM 2.20 vorbereiten lässt
status: todo
depends_on: []
---

# Was sich unter ORM 2.20 vorbereiten lässt

## Context
Drei der bekannten Änderungen brauchen den Versionssprung nicht — ihre Ziele gibt es in ORM
2.20 schon. Sie kommen deshalb zuerst, und jeder Commit bleibt grün:

- **`EntityManager::create()` → `new EntityManager($connection, $config)`**, eine Zeile in
  `EntityManagerFactory`. Der Konstruktor ist in 2.20 public; `create()` ist dort deprecated.
- **`Lexer::T_*` → `TokenType::T_*`**, 11 Stellen in `FindInSet`, `Distance` und `PointStr`.
  `Parser::match()` ist in 2.20 untypisiert und nimmt beides.
- **`ClassMetadataInfo::GENERATOR_TYPE_NONE` → `ClassMetadata::GENERATOR_TYPE_NONE`**,
  2 Stellen in `Api.php` und `FileController.php`.

**Der Sinn ist derselbe wie in `010-001` und `010-002`:** Was gegen ein unverändertes ORM
gemessen werden kann, wird dort gemessen. Bleibt danach etwas rot, liegt es am Sprung — nicht
an diesen drei Umschreibungen.

## Acceptance criteria
- [ ] Keine der drei APIs kommt im eigenen Code noch vor; je über `grep` belegt.
- [ ] Die Suite ist grün, ohne eine geänderte Zusicherung — es sind Umbenennungen, keine Verhaltensänderungen.
- [ ] Die DQL-Funktionen liefern dieselben Ergebnisse: `Find_In_Set`, `Distance` und `PointStr` sind über eine Abfrage belegt, nicht nur über den Compiler.
- [ ] PHPStan bleibt `[OK] No errors`; die Muster für ORM 2 werden hier **nicht** angefasst, sie gehören `010-003-0003`.

## Verification
Volle Suite. Dazu eine Abfrage je eigener DQL-Funktion gegen die Testdatenbank: Ein
Token-Konstantenwechsel, der den Parser falsch füttert, fällt sonst erst bei einer Abfrage auf,
die kein Test stellt.
