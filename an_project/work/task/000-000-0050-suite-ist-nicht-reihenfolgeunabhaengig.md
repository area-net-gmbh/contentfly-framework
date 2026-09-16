---
id: 000-000-0050
title: Die Suite ist nicht reihenfolgeunabhängig — ein Lauf von vier war rot
status: todo
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

**Der wahrscheinliche Weg:** Die Anmeldung in diesem Test lief auf die Login-Bremse
(`013-003-0003`) und bekam `429` statt eines Tokens. `data` ist dann `null`, das Token leer, und
der Aufruf mit `Authorization: Bearer ` antwortet erwartungsgemäss nicht mit `200` — die Meldung
zeigt also die Folge, nicht die Ursache.

`IntegrationTestCase::clearThrottleStorage()` räumt zwischen zwei Testmethoden auf; offenbar reicht
das nicht in jeder Reihenfolge. **Zu belegen, nicht zu vermuten:** Welcher Vorgänger füllt den
Zähler so, dass der Aufräumer ihn nicht erwischt?

## Warum das jetzt wichtig ist
Bis hierher lief die Suite an einer Stelle. **Ab `011-002-0002` läuft sie bei jedem Push und jedem
Pull Request**, und beide PHP-Versionen sind blockierend. Ein Lauf, der in einem von vier Fällen
ohne Zutun rot wird, ist damit kein Schönheitsfehler mehr: Er kostet jedes Mal eine Untersuchung,
und nach der dritten grundlosen roten Pipeline glaubt niemand mehr der vierten — dann ist das
Testnetz weniger wert als vorher.

## Acceptance criteria
- [ ] Die Ursache ist **belegt**, nicht vermutet: Der Lauf ist mit festem Seed nachgestellt, und die Stelle, die den Zähler füllt, ist benannt.
- [ ] Die Abhängigkeit ist beseitigt — nicht durch Abschalten der Bremse für Tests, denn sie ist eine Sicherheitsfunktion und `LoginThrottleApiTest` misst sie.
- [ ] Die Suite läuft zwanzigmal hintereinander mit unterschiedlichen Seeds grün.
- [ ] Sollten weitere reihenfolgeabhängige Paare auftauchen, sind sie im selben Zug benannt.

## Verification
`for i in $(seq 20); do ./vendor/bin/phpunit; done` gegen eine Instanz, zwanzigmal grün.
Gegenprobe: Der ursprünglich rote Seed (`1789570101`, aus dem Lauf vom 2026-09-16) läuft grün.
