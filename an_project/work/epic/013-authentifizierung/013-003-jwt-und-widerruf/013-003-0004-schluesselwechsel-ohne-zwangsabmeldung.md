---
id: 013-003-0004
title: Schlüsselwechsel ohne Zwangsabmeldung
status: review
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
- [x] Ausgestellte JWT tragen eine Schlüsselkennung im Header.
- [x] Es lassen sich zwei Schlüssel konfigurieren. Signiert wird mit dem aktuellen, angenommen werden beide.
- [x] Ein Wechsel ist durchgespielt: Tokens von vor dem Wechsel bleiben bis zum Ablauf gültig, neue tragen den neuen Schlüssel — **ohne dass jemand neu anmelden muss**.
- [x] Ein Token mit unbekannter Schlüsselkennung wird abgewiesen, ununterscheidbar wie jeder andere Fehlschlag.
- [x] Kein Schlüssel steht in einer Datei im Repo; beide kommen aus der Umgebung.
- [x] Über Tokens ohne `kid` ist entschieden und begründet.

## Verification
Unit-Test über den vollen Wechsel: mit Schlüssel A signieren, Konfiguration auf „B aktuell, A
noch gültig" umstellen, das alte Token muss weiterhin gelten und ein neues mit B kommen; dann A
entfernen und prüfen, dass das alte fällt. Volle Suite.

## Ergebnis

**Ein Schlüsselwechsel ist durchgespielt, und niemand musste sich neu anmelden.** Der Test geht
die drei Zustände in der Reihenfolge durch, in der ein Betreiber sie herstellt.

### Der Ablauf

| Schritt | Konfiguration | Wirkung |
|---|---|---|
| vorher | `SECRET`=alt, `KEY_ID`=`k1` | ein Schlüssel, ein Token |
| Wechsel | `SECRET`=neu/`k2`, `SECRET_PREVIOUS`=alt/`k1` | signiert wird mit neu, angenommen werden beide |
| danach | `SECRET`=neu/`k2` | das alte Token fällt, das neue gilt |

Der dritte Schritt ist frühestens nach `SECURITY_JWT_TTL` sinnvoll — dann ist das längste noch
mit dem alten Schlüssel ausgestellte Access-JWT abgelaufen. Bei der Vorgabe sind das 15 Minuten.

### Vier Felder, und jedes sagt, was es tut

`SECURITY_JWT_KEY_ID` benennt den aktuellen Schlüssel, `SECURITY_JWT_SECRET_PREVIOUS` und
`SECURITY_JWT_KEY_ID_PREVIOUS` tragen den vorherigen durch die Übergangszeit. Die Kennungen sind
**Namen, keine Geheimnisse** — sie stehen im Klartext in jedem Token-Header.

### Ein Token ohne `kid` wird abgewiesen, und das ist entschieden

`JWT::decode()` wählt den Schlüssel nach dem `kid`. Ohne Kennung müsste die Anwendung raten —
und „alle der Reihe nach probieren" hebt den Sinn des Wechsels auf: Ein abgelöster Schlüssel
beglaubigte dann weiter Tokens, die nichts über sich sagen.

**Ausgestellt wurde ein solches Token nie.** Die Ausstellung entstand mit `013-003-0001`, die
Kennung mit `013-003-0004`, und dazwischen lag kein Release. Der Bestand ist leer, die
Entscheidung damit folgenlos — aber sie gehört aufgeschrieben, weil sie es beim nächsten Mal
nicht wäre.

### Eine Fehlkonfiguration schlägt laut durch

**Zwei gleiche Kennungen werden abgewiesen.** Sonst überschriebe die eine die andere im Array,
und die Anwendung akzeptierte stillschweigend nur einen der beiden Schlüssel — mitten in einem
Wechsel der schlechteste Zeitpunkt für eine stille Überraschung. Dasselbe für einen vorherigen
Schlüssel ohne Kennung.

**Und sie darf nicht wie ein ungültiges Token aussehen.** `pruefschluessel()` wird deshalb
**ausserhalb** des `try` im Handler geholt. Fänge man sie mit ab, antwortete die Anwendung auf
jeden Request mit „ungültiger Token", und der Betreiber suchte den Fehler bei seinen Clients.
Ein eigener Test hält genau das fest.

### Nachweis

| Probe | Ergebnis |
|---|---|
| Volle Suite | `OK (404 tests, 1004 assertions)`, 0 übersprungen (vorher 398) |
| PHPStan | `[OK] No errors` |
| Deprecations | 0 protokollierte Zeilen |
| Voller Wechsel | altes Token bleibt gültig, neues trägt `k2`, nach Entfernen des alten Schlüssels fällt das alte Token |
| Unbekannte Kennung | abgewiesen |
| Fehlkonfiguration | schlägt durch, wird nicht als ungültiges Token getarnt |

Kein Schlüssel steht in einer Datei im Repo: Alle vier Felder haben keinen Wert im Baum, die
ausgelieferte `custom/config.php` liest sie auskommentiert aus der Umgebung, und die Pipeline
setzt einen Wegwerf-Wert.
