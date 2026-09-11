---
id: 000-000-0027
title: Der composer.json-Hinweis behauptet ORM ^2.14
status: todo
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
- [ ] Punkt 2 des Hinweisblocks beschreibt den tatsaechlichen Stand: ORM 3 ueber DBAL 3.x, Attribute statt Annotationen, Epic `010` erledigt.
- [ ] Die uebrigen Punkte sind gegen `require` und `require-dev` gelesen; was nicht mehr stimmt, ist richtiggestellt.
- [ ] Zahlen, die veralten, stehen entweder nicht mehr drin oder tragen das Datum ihrer Messung.
- [ ] `composer validate` ist gruen, die volle Suite ebenso.

## Verification
`composer validate`, ein Abgleich jedes Punktes gegen die `require`-Bloecke, volle Suite.
