---
id: 013-003-0004
title: Schlüsselwechsel ohne Zwangsabmeldung
status: todo
depends_on: [013-003-0001]
---

# Schlüsselwechsel ohne Zwangsabmeldung

## Context
Ein Signaturgeheimnis, das sich nicht wechseln lässt, ohne alle Sitzungen zu beenden, wird nicht
gewechselt. Damit ist ein Leak dauerhaft.

**Der Weg ist eine Kennung im Token-Header (`kid`) und eine Übergangszeit**, in der zwei
Schlüssel akzeptiert werden: Signiert wird immer mit dem aktuellen, angenommen werden aktueller
und vorheriger. Nach Ablauf des längsten Access-Tokens kann der alte weg.

**Der Bestand aus `013-002` trägt kein `kid`.** Zu entscheiden und zu begründen: ob ein Token
ohne Kennung noch angenommen wird und wie lange. Ausgestellt wurden solche Tokens nie — die
Ausstellung entsteht erst mit `013-003-0001` —, was die Entscheidung leicht macht und trotzdem
in den Text gehört.

## Acceptance criteria
- [ ] Ausgestellte JWT tragen eine Schlüsselkennung im Header.
- [ ] Es lassen sich zwei Schlüssel konfigurieren. Signiert wird mit dem aktuellen, angenommen werden beide.
- [ ] Ein Wechsel ist durchgespielt: Tokens von vor dem Wechsel bleiben bis zum Ablauf gültig, neue tragen den neuen Schlüssel — **ohne dass jemand neu anmelden muss**.
- [ ] Ein Token mit unbekannter Schlüsselkennung wird abgewiesen, ununterscheidbar wie jeder andere Fehlschlag.
- [ ] Kein Schlüssel steht in einer Datei im Repo; beide kommen aus der Umgebung.
- [ ] Über Tokens ohne `kid` ist entschieden und begründet.

## Verification
Unit-Test über den vollen Wechsel: mit Schlüssel A signieren, Konfiguration auf „B aktuell, A
noch gültig" umstellen, das alte Token muss weiterhin gelten und ein neues mit B kommen; dann A
entfernen und prüfen, dass das alte fällt. Volle Suite.
