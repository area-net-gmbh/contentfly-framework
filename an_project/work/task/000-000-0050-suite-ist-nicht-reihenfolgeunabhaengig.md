---
id: 000-000-0050
title: Die Suite ist nicht reihenfolgeunabhängig — ein Lauf von vier war rot
status: review
depends_on: []
---

# Die Suite ist nicht reihenfolgeunabhängig — ein Lauf von vier war rot

## Context
**Gefunden bei `011-002-0002`** am 2026-09-16, beim Umbau der Pipeline.

Vier Läufe derselben Suite gegen dieselbe frisch aufgebaute Instanz, nichts dazwischen geändert:

| Lauf | Ergebnis |
|---|---|
| 1 | `Tests: 615, Assertions: 2505, Failures: 1` |
| 2–4 | `Tests: 615, Assertions: 2506` grün |

Rot war `AuthApiTest::testUserDeactivationTakesEffectImmediatelyWithoutRevocationList` an der
Zeile, die den frisch ausgestellten JWT gegen `/api/schema` hält. Allein aufgerufen ist die Klasse
grün (40/40). **PHPUnit würfelt den Seed** (`Random Seed:` im Kopf jedes Laufs), die Reihenfolge
ist also bei jedem Lauf eine andere.

**Die Vermutung beim Anlegen war falsch, und das gehört hierher.** Sie lautete: Die Anmeldung
laufe auf die Login-Bremse und bekomme `429` statt eines Tokens. Das war plausibel und es war
nicht so — die abgeschnittene Protokollausgabe hatte die Meldung verdeckt. Sie lautet
vollständig:

```
No entry in the revocation list — deactivation works without it
Failed asserting that 1 is identical to 0.
```

Es geht also um `pim_revoked_token`, nicht um die Bremse.

## Warum das jetzt wichtig ist
Bis hierher lief die Suite an einer Stelle. **Ab `011-002-0002` läuft sie bei jedem Push und jedem
Pull Request**, und beide PHP-Versionen sind blockierend. Ein Lauf, der in einem von vier Fällen
ohne Zutun rot wird, ist damit kein Schönheitsfehler mehr: Er kostet jedes Mal eine Untersuchung,
und nach der dritten grundlosen roten Pipeline glaubt niemand mehr der vierten — dann ist das
Testnetz weniger wert als vorher.

## Acceptance criteria
- [x] Die Ursache ist **belegt**, nicht vermutet: Der Lauf ist mit festem Seed nachgestellt, und die Stelle, die den Zähler füllt, ist benannt.
- [x] Die Abhängigkeit ist beseitigt — nicht durch Abschalten der Bremse für Tests, denn sie ist eine Sicherheitsfunktion und `LoginThrottleApiTest` misst sie.
- [x] Die Suite läuft zwanzigmal hintereinander mit unterschiedlichen Seeds grün.
- [x] Sollten weitere reihenfolgeabhängige Paare auftauchen, sind sie im selben Zug benannt.

## Verification
`for i in $(seq 20); do ./vendor/bin/phpunit; done` gegen eine Instanz, zwanzigmal grün.
Gegenprobe: Der ursprünglich rote Seed (`1789570101`, aus dem Lauf vom 2026-09-16) läuft grün.

## Ergebnis

### Reproduziert — mit Seed 4 von zwölf

`./vendor/bin/phpunit --random-order-seed=4` war zuverlässig rot. Damit stand die Grundlage, ohne
die weder Ursache noch Behebung zu belegen gewesen wären.

### Die Ursache, und wer sie eingebaut hat

**`EnvelopeApiTest` — geschrieben in `011-001-0004`, also zwei Commits vorher.** Er meldet sich mit
`tokenType: jwt` an und ruft `/auth/logout`; das schreibt eine Zeile in `pim_revoked_token`. Das
ist der Zweck des Widerrufs, kein Nebeneffekt — aber die Klasse räumte sie nicht weg. Gemessen:

```
vorher: 0
OK (5 tests, 207 assertions)
nach EnvelopeApiTest: 1 Zeile(n)
```

`AuthApiTest::testUserDeactivationTakesEffectImmediatelyWithoutRevocationList` zählte die Tabelle
**global** und erwartete `0`. Lief `EnvelopeApiTest` vorher, war sie `1`.

### Behoben wurden beide Hälften

**Die Falle war älter als ihr Auslöser.** Der Aufräumer in `AuthApiTest` trug seine eigene Warnung
im Kommentar: *„die Einträge stören niemanden — aber `testUserDeactivation…` zählt sie, und eine
leere Liste ist die einzige Aussage, die dieser Test machen kann."* Das war zutreffend und es war
eine Falle: Es machte **jeden Test der Suite** für eine globale Zahl verantwortlich. Hätte ich nur
den Verursacher aufgeräumt, wäre der nächste Test mit einem JWT-Logout wieder hineingelaufen.

| | |
|---|---|
| `EnvelopeApiTest` | räumt in `tearDown()` auf, was er erzeugt — ein Test, der Zustand hinterlässt, bricht andere Tests |
| `AuthApiTest` | fragt nach **seinem eigenen `jti`** statt nach der Tabelle. Das ist ohnehin die Aussage, die der Test meint: *dieses* Token wurde nicht über die Liste widerrufen, also wirkt die Deaktivierung. Der `jti` steht im JWT. |

Der Kommentar am Aufräumer nennt jetzt den einfachen Grund statt des alten: Ein Test räumt weg,
was er angelegt hat.

### Nebenbei: der Sprachwächter hat gegriffen

Der erste Anlauf trug einen **deutschen** Codekommentar. `EnglishOnlyTest` hat ihn zeilenweise
gemeldet, bevor er in einen Commit kam — genau die Aufgabe, für die Epic `014` ihn gebaut hat.

### Verifiziert

| | |
|---|---|
| Seed 4 (der Reproduzierer) | grün |
| Seeds 1–20, je ein voller Lauf | **0 rote von 20**, jeder `Tests: 615, Assertions: 2506, Skipped: 3` |

Vorher war Seed 4 von zwölf zuverlässig rot. Dass die Assertion-Zahl über alle zwanzig Läufe
identisch ist, ist die zweite Aussage: Es wird nicht nur nichts mehr rot, es läuft auch in jeder
Reihenfolge dasselbe.
