---
id: 007-003-0002
title: Die zugesicherten Schlüssel festschreiben — und ein Test hält sie
status: todo
depends_on: [007-003-0001]
---

# Die zugesicherten Schlüssel festschreiben — und ein Test hält sie

## Context
**Eine Zusicherung ohne Liste ist keine.** Die Entscheidung aus `007-003-0001` sagt, dass der
Zugriff bleibt; dieser Task sagt, **worauf** man sich verlassen darf. Heute steht das nirgends:
`bootstrap.php` registriert 24 Schlüssel, und ob sie alle öffentlich gemeint sind oder manche
nur interne Verdrahtung, entscheidet bisher niemand.

**Und eine Liste, die niemand prüft, ist auch keine.** Der teuerste Befund aus Epic `006` war
von dieser Art — eine Bedingung, auf die man sich verliess, ohne dass sie jemand nachsah. Zu
diesem Task gehört deshalb ein Test, der die Liste gegen einen aufgebauten Container hält.

**Der Test kann mehr als prüfen, dass die Schlüssel da sind.** Er kann auch die Gegenrichtung
halten: Ein Schlüssel, der zugesichert ist, aber nicht mehr registriert wird, ist ein Bruch —
und einer, der registriert wird und in der Liste fehlt, ist entweder vergessen oder absichtlich
intern. Beides muss auffallen, sonst wächst die Liste auseinander.

## Acceptance criteria
- [ ] Die zugesicherten Schlüssel stehen in `an_project/docs/dev-guide.md`, je mit einem Satz, was sie liefern.
- [ ] Ein Test hält fest, dass **jeder** zugesicherte Schlüssel im aufgebauten Container vorhanden ist.
- [ ] Der Test hält die Gegenrichtung: Ein registrierter Schlüssel, der in der Liste fehlt, fällt auf — oder er steht ausdrücklich als intern vermerkt, mit Grund.
- [ ] Der Test läuft ohne Datenbank, oder er sagt klar, warum er sie braucht.
- [ ] Die volle Suite bleibt grün.

## Verification
Einen zugesicherten Schlüssel aus `bootstrap.php` entfernen — der Test muss rot werden. Einen
neuen registrieren, ohne die Liste zu ergänzen — der Test muss ebenfalls rot werden. Danach
zurücksetzen.
