---
id: 000-000-0027
title: Der composer.json-Hinweis behauptet ORM ^2.14
status: review
depends_on: []
---

# Der composer.json-Hinweis behauptet ORM ^2.14

## Context
Der `extra.hinweis`-Block in `composer.json` erklaert, warum die Constraints stehen, wie sie
stehen. Punkt 2 lautet:

> doctrine/orm steht auf ^2.14 und damit real auf 2.20. ORM 3 verlangt weitere statt Annotationen
> an jeder Entity — der Umbau ist Epic 010, nicht dieser Bump.

**Das stimmt seit Epic `010` nicht mehr.** `require` sagt `"doctrine/orm": "^3"`, die Entities
tragen PHP-Attribute, und der Umbau ist erledigt. Der Block ist ausdruecklich dafuer da, dass
jemand die Zahlen nicht ohne Grund anfasst — ein falscher Grund ist schlimmer als keiner.

Aufgefallen bei `013-002`, beim Aufnehmen von `symfony/security-http` ins Manifest.

**Mitzupruefen, weil im selben Block:** Der Absatz zu `platform.php` nennt PHP 8.4 als „mit
009-004-0002 ebenfalls gruen gemessen — 267 Tests". Die Zahl ist seit Epic `013` weit
ueberholt; ob die Aussage dort ueberhaupt eine Zahl braucht, ist Teil des Tasks.

## Acceptance criteria
- [x] Punkt 2 des Hinweisblocks beschreibt den tatsaechlichen Stand: ORM 3 ueber DBAL 3.x, Attribute statt Annotationen, Epic `010` erledigt.
- [x] Die uebrigen Punkte sind gegen `require` und `require-dev` gelesen; was nicht mehr stimmt, ist richtiggestellt.
- [x] Zahlen, die veralten, stehen entweder nicht mehr drin oder tragen das Datum ihrer Messung.
- [x] `composer validate` ist gruen, die volle Suite ebenso.

## Verification
`composer validate`, ein Abgleich jedes Punktes gegen die `require`-Bloecke, volle Suite.

## Ergebnis

**Der Hinweisblock beschreibt wieder den Stand, der ist.** Nicht nur Punkt 2 war überholt —
beim Abgleich gegen `require` fielen fünf Stellen auf.

### Was nicht mehr stimmte

| Stelle | stand da | ist |
|---|---|---|
| Punkt 2 | `doctrine/orm: ^2.14`, Umbau offen | `^3`, real 3.7; Attribute seit Epic `010` |
| Punkt 3 | DBAL 3.x sei nach oben erzwungen | ORM 3.7 erlaubt `^3.8.2 \|\| ^4` — 3.x ist eine **Entscheidung** |
| Schlusssatz | „zwischen Symfony 7.4 und ORM 2.20 bleibt DBAL 3.x" | nach oben deckelt **nichts** mehr |
| `platform.php` | „267 Tests" | 471, und die Zahl trägt jetzt ihr Datum |
| PHP-8.5-Satz | `ellumilel/php-excel-writer` sei die offene Frage | mit `012-001-0003` gefallen, nicht mehr im Baum |

### Der interessanteste Fund ist Punkt 3

Er begründete die DBAL-Obergrenze mit ORM 2 — und diese Begründung ist mit Epic `010` weggefallen,
ohne dass jemand die Zeile anfasste. **Nachgemessen:** `doctrine/orm` 3.7 verlangt
`doctrine/dbal: ^3.8.2 || ^4`, `symfony/http-foundation` 7.4 führt `doctrine/dbal: <3.6` in
`conflict`. Erzwungen ist also nur die **Untergrenze**; dass hier 3.x steht und nicht 4, ist seit
Epic `010` eine Wahl, die niemand getroffen hat.

Das steht jetzt so da — samt dem Satz, dass ein Sprung auf DBAL 4 ein eigener Schritt wäre. Ein
Block, der erklären soll, warum man die Zahlen nicht anfasst, taugt nichts, wenn er eine Grenze
als erzwungen ausgibt, die keine ist.

### Zahlen tragen jetzt ihr Datum

Die „267 Tests" waren seit Epic `010` falsch und wurden über vier Epics hinweg nicht bemerkt —
weil eine nackte Zahl nicht verrät, wann sie gemessen wurde. Jetzt steht dabei: *zuletzt gemessen
am 2026-09-11 mit `013-005-0004`: 471 Tests, 0 Deprecations*, mit dem Hinweis, warum das Datum
dabeisteht.

### Zwei Stellen ausserhalb von `composer.json`

**Das Inventar.** Der Block behauptete, `abhaengigkeiten-inventar.md` trage „die Zuordnung jedes
einzelnen Pakets". Das tut es nicht: Es ist eine Momentaufnahme vom 2026-09-08 und beschreibt den
Baum **vor** Epic `006` — mit Silex, DBAL 2.6 und einem ORM-Dev-Pin. Als historischer Beleg ist
es richtig, als Nachschlagewerk für den heutigen Paketstand falsch. Der Satz sagt das jetzt.

**Die PHP-8.5-Tabelle in `technical.md`.** Sie führte `ellumilel/php-excel-writer` und den
ORM-Dev-Pin noch als offene Blocker, obwohl beide erledigt sind. Beide sind durchgestrichen wie
die anderen zwei Zeilen — **alle vier sind es jetzt.** Das gehört dazu, weil der neue
`composer.json`-Text auf diese Datei verweist: Einen Verweis auf eine falsche Aussage zu setzen
wäre schlimmer gewesen als der ursprüngliche Fehler.

### Nachweis

| Probe | Ergebnis |
|---|---|
| `composer validate` | `./composer.json is valid` |
| Volle Suite | `OK (471 tests, 1150 assertions)`, 0 übersprungen |
| PHPStan | `[OK] No errors` |
| `composer audit --locked` | 0 Advisories |

`composer.lock` trägt eine Zeile Änderung: den Content-Hash. Der `extra`-Block zählt zum Hash,
also meldet `composer validate` sonst „lock file is not up to date" — mit `composer update --lock`
ist nur der Hash neu, kein Paket.
