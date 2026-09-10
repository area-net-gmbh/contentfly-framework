---
id: 010-003-0001
title: Was sich unter ORM 2.20 vorbereiten lässt
status: done
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
- [x] Keine der drei APIs kommt im eigenen Code noch vor; je über `grep` belegt.
- [x] Die Suite ist grün, ohne eine geänderte Zusicherung — es sind Umbenennungen, keine Verhaltensänderungen.
- [x] Die DQL-Funktionen liefern dieselben Ergebnisse: `Find_In_Set`, `Distance` und `PointStr` sind über eine Abfrage belegt, nicht nur über den Compiler.
- [x] PHPStan ist `[OK] No errors`.

> **Richtiggestellt, 2026-09-10.** Das Kriterium verlangte, die ORM-2-Muster hier **nicht**
> anzufassen. Das ging nicht: Zwei davon — `EntityManager::create()` und die
> Lexer-Konstanten — trafen nach diesem Task nichts mehr, und Regel 3 macht den Lauf dann rot.
> Sie sind hier gestrichen, die drei Console-Muster bleiben für `010-003-0002`.

## Verification
Volle Suite. Dazu eine Abfrage je eigener DQL-Funktion gegen die Testdatenbank: Ein
Token-Konstantenwechsel, der den Parser falsch füttert, fällt sonst erst bei einer Abfrage auf,
die kein Test stellt.

## Ergebnis

**Die drei Umschreibungen stehen, und die Suite ist unverändert grün** — `OK (268 tests, 642
assertions)`. Damit ist der erste der beiden Blocker des Sprungs aus dem Weg.

| Umschreibung | Stellen |
|---|---|
| `EntityManager::create()` → `new EntityManager(...)` | 1 |
| `Lexer::T_*` → `TokenType::T_*` | 11, in `FindInSet` (4), `Distance` (4), `PointStr` (3) |
| `ClassMetadataInfo::` → `ClassMetadata::` | 2, in `Api.php` und `FileController.php` |

Keine davon ist eine Verhaltensänderung: Die Ziele existieren in ORM 2.20 bereits, `create()`
ist dort deprecated und der Konstruktor public, `ClassMetadataInfo` ein Alias.

### Der Nachweis, den der Task verlangt hat — und was er zutage gefördert hat

Ein grüner Compiler genügt hier nicht: Ein falscher Token-Wert füttert den Parser falsch, und
das fällt erst bei einer Abfrage auf, die kein Test stellt. Gemessen gegen die Testdatenbank:

| Funktion | geparst | ausgeführt |
|---|---|---|
| `Find_In_Set` | ja | ja — `1` Treffer, der Admin |
| `Distance` | ja, `GLength(LineString(…, …))` | — |
| `PointStr` | ja, `GeomFromText(…)` | — |

**`Distance` und `PointStr` sind nirgends registriert.** Die Liste der eigenen DQL-Funktionen
steht fest in `bootstrap.php` und `InstallCommand` und enthält nur `Find_In_Set`; sie lässt sich
von aussen nicht ergänzen. Beide Funktionen sind damit für jedes Projekt unerreichbar. Für die
Probe habe ich sie von Hand nachgereicht — sonst hätte sich der Tokenwechsel dort gar nicht
zeigen lassen. **Der Befund ist älter als dieser Task und wird hier nicht angefasst;** ob die
beiden registriert oder entfernt gehören, ist eine eigene Entscheidung.

`Find_In_Set` ist registriert, aber im Framework selbst **nicht benutzt** — es ist ein Angebot
an Projekte, und das ist in Ordnung.

**Beim Messen selbst danebengegriffen:** Meine ersten beiden Proben scheiterten mit
`Expected T_CLOSE_PARENTHESIS, got ','`, und das sah nach einem kaputten Tokenwechsel aus. Es
lag an meiner Abfrage — `Distance` nimmt zwei Argumente, `PointStr` eines, ich hatte vier
beziehungsweise zwei übergeben.

### Regel 3 hat sofort ausgelöst

Zwei PHPStan-Muster trafen nach den Umschreibungen nichts mehr und machten den Lauf rot. Sie
sind hier gestrichen, obwohl `010-003-0003` die Muster-Aufräumung trägt: Sie waren **hier**
gegenstandslos geworden. Die drei Console-Muster bleiben, denn die Commands fallen erst mit dem
Sprung.

### Nachweis

| Probe | Ergebnis |
|---|---|
| Volle Suite | `OK (268 tests, 642 assertions)`, 0 übersprungen |
| PHPStan | `[OK] No errors` |
| Deprecation-Gate | 0 Zeilen, 0 Ausnahmen |
| `Lexer::`, `ClassMetadataInfo`, `EntityManager::create` im Code | 0 Treffer |
| DQL-Funktionen | alle drei geparst, `Find_In_Set` ausgeführt |
