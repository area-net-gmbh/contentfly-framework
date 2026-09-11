---
id: 007-002-0001
title: Der Prüfstein — Beispiel-Entities im Altstand und der Lauf-Rahmen
status: review
depends_on: []
---

# Der Prüfstein — Beispiel-Entities im Altstand und der Lauf-Rahmen

## Context
**Ohne diesen Task ist kein folgender verifizierbar.** Eine Rector-Regel lässt sich nicht daran
messen, dass ihr Lauf durchgeht — nur daran, was danach im Code steht.

**Hier ist nichts zu borgen.** Nachgemessen am 2026-09-11: Die Entities des Frameworks tragen
durchgehend Attribute — 20 von 22 mit `#[ORM\…]`, die beiden anderen (`BaseUID`,
`Serializable`) ohne Mapping. Der Altstand, gegen den die Regel laufen soll, existiert im Repo
also nicht mehr. Er muss gebaut werden.

**Was der Prüfstein enthalten muss,** damit die folgenden Tasks etwas zu prüfen haben. Die Liste
steht vollständig in `an_project/docs/pim-annotationen-migration.md`:

| | Anzahl | Beispiel |
|---|---|---|
| entfallene `@PIM\*`-Annotationen | 7 | `@PIM\Rte`, `@PIM\Password`, `@PIM\MatrixChooser` |
| entfallene Felder von `@PIM\Config` | 14 | |
| reduzierte Annotationen | 2 | `@PIM\Checkbox` verliert `horizontalAlignment` und `columns`; `@PIM\Radio` zusätzlich `select` |
| gebliebene Annotationen | 5 | `@PIM\Config`, `@PIM\Select`, `@PIM\Virtualjoin`, `@PIM\Permissions`, `@PIM\I18nPermissions` |
| `@ORM\*`-Annotationen | alle benutzten Formen | `@ORM\Column`, `@ORM\ManyToOne`, `@ORM\JoinColumn`, … |

**Jede Zeile mindestens einmal, und die gebliebenen ausdrücklich mit.** Eine Regel, die nur an
dem gemessen wird, was sie ändern soll, kann alles andere mit abräumen, ohne dass es auffällt.

## Der Lauf-Rahmen

`rector/rector` liegt in 1.2.10 im Baum, eine `rector.php` gibt es nicht. Dieser Task legt sie
an — leer, aber lauffähig, mit dem Pfad, den ein Bestandsprojekt angibt, und `--dry-run` als
dokumentierter Normalfall.

**Die Abnahme liegt in der Suite, nicht in einem Wegwerf-Verzeichnis** (entschieden am
2026-09-11): Ein Test fährt Rector gegen den Prüfstein und vergleicht mit dem Sollzustand. Eine
Regel ohne Test verfällt wie eine Anleitung, der niemand folgt — dasselbe Argument, aus dem
`000-000-0029` einen Test bekam.

## Acceptance criteria
- [x] Der Prüfstein trägt jede der 7 entfallenen Annotationen, jedes der 14 entfallenen `Config`-Felder, die reduzierten `Checkbox`/`Radio`-Felder und die 5 gebliebenen Annotationen — je mindestens einmal.
- [x] Er trägt `@ORM\*`-Annotationen in den Formen, die der Baum vor Epic `010` benutzte.
- [x] Ein Sollzustand liegt daneben; er ist von Hand geschrieben und begründet, nicht aus einem Rector-Lauf erzeugt — sonst prüfte der Vergleich die Regel gegen sich selbst.
- [x] `rector.php` existiert und läuft gegen den Prüfstein durch, ohne etwas zu ändern (noch keine Regel eingetragen).
- [x] Ein Test fährt den Lauf und vergleicht; er ist grün, solange nichts konfiguriert ist, weil dann Ist gleich Alt ist.
- [x] Die volle Suite bleibt grün.

## Verification
`./vendor/bin/rector process --dry-run --config=rector.php` gegen den Prüfstein: kein Vorschlag,
weil keine Regel eingetragen ist. Der Test läuft mit. Volle Suite.

## Ergebnis

**Der Prüfstein liegt unter `tests/Fixtures/RectorMigration/`** — `alt/` mit zwei Entities im
Altstand, `soll/` mit dem von Hand geschriebenen Zielzustand. `rector.php` steht im
Wurzelverzeichnis und läuft gegen den Prüfstein durch, ohne etwas zu ändern; mit
`007-002-0001` ist noch keine Regel eingetragen, und Rector sagt genau das.

**Abgedeckt ist die Liste vollständig:** alle 7 entfallenen Annotationen, alle 14 entfallenen
`Config`-Felder, alle 10 gebliebenen, die reduzierten Felder von `Checkbox` und `Radio`, die
gebliebenen Annotationen, und die verschachtelte ORM-Annotation
(`@ORM\JoinTable` mit `joinColumns={@ORM\JoinColumn(…)}`) — der Fall, an dem eine Umstellung
erfahrungsgemäss scheitert.

`tests/Unit/Migration/RectorRegelTest.php`, acht Tests: Der Prüfstein ist wirklich Altstand ·
der Sollzustand ist wirklich Zielzustand · die gebliebenen Felder stehen dort noch · beide
unterscheiden sich · der Lauf geht durch · die Listen stimmen noch mit der Dokumentation
überein · und der Prüfstein erfindet keine Felder.

## Drei Fehlgriffe, und der dritte ist der lehrreichste

**Erstens: Mein Prüfstein hat Felder erfunden.** Ich schrieb `@PIM\Radio(options=…)`,
`@PIM\Virtualjoin(entity=…, mappedBy=…)` und `@PIM\Permissions(mode=…)` — **Felder, die keine
dieser Klassen je hatte.** Ich hatte sie aus der Streichliste abgeleitet, und die sagt nur, was
*wegfällt*. Was *bleibt*, sagt der Konstruktor: `Radio` und `Checkbox` haben genau einen
Parameter (`group`), `Virtualjoin` genau `targetEntity`, und `Permissions` wie
`I18nPermissions` haben **keinen Konstruktor** — sie nehmen nichts entgegen.

**PHPStan hat es gefangen, und nur deshalb.** Im Sollzustand stehen Attribute, und die prüft es
gegen die Konstruktoren: fünf Meldungen. Im Altstand stehen Docblocks, die es nicht ansieht —
dort wäre es durchgelaufen, und jeder folgende Task hätte gegen Fiktion gemessen.

**Zweitens: Rectors eigenes Gerüst ist hier gefährlich.** Ein Lauf ohne `rector.php` fragt, ob
es eine anlegen soll, und legt eine an, die auf `custom`, `lib`, `tests` und `tools` zeigt —
also auf den ganzen Baum. Hier hiesse das: den Frameworkcode umschreiben, der längst auf
Attributen steht, **und den Prüfstein dieser Regel**, der im Altstand bleiben muss. Die
`rector.php` trägt jetzt nur `custom/Entity` und sagt im Kopf, warum sie so eng ist.

**Drittens, und das ist der Fehlgriff, der etwas über Wächter sagt: Der Test, den ich gegen den
ersten Fehler geschrieben habe, war grün, weil er nichts fand.** Dreimal hintereinander:

1. **Der Suchausdruck traf nie.** In einfachen Anführungszeichen wurde aus meinen zwei
   Backslashes `PIM\(` — eine *escapte Klammer* statt eines literalen Backslashs. Es braucht
   vier.
2. **Er las Feldnamen aus Zeichenketten-Werten.** `options="eins=Eins,zwei=Zwei"` trägt
   Gleichheitszeichen im Wert; `eins` und `zwei` wurden als Felder gemeldet. Werte werden jetzt
   vorher entfernt. Dasselbe für `::class`, aus dem `Artikel` als Feldname herausfiel.
3. **Und dann filterte er genau das weg, was er prüfen sollte.** Mein Versuch, die
   Prosa-Beispiele in den Docblocks zu überspringen, entfernte Kommentarzeilen — aber im
   Altstand *sind* die Annotationen Kommentarzeilen. Wieder grün, wieder aus dem falschen Grund.

Gelöst über den Unterschied, auf den es wirklich ankommt: **Wirksam ist eine Annotation nur am
Zeilenanfang.** `* @PIM\Config(…)` im Docblock zählt, ein `@PIM\Radio(options=…)` mitten im
Satz hinter einem Backtick nicht. Mehrzeilige Annotationen werden mitgenommen, solange die
Klammern offen sind. Damit braucht es keine Ausnahmeliste.

**Jeder der drei Zustände wurde durch absichtliche Verletzung geprüft** — ein eingeschmuggeltes
Feld muss gemeldet werden, und bei den ersten beiden Fassungen wurde es nicht.

**Zahlen:** Volle Suite `OK (503 tests, 1305 assertions)` (vorher 495), 0 Deprecations bei 0
Ausnahmen, 0 Byte Postausgang. PHPStan `[OK] No errors`. Der Rector-Lauf gegen den Prüfstein
geht durch und ändert nichts.
