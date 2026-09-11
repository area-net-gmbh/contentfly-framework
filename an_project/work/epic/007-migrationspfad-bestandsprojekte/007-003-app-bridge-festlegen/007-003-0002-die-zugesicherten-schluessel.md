---
id: 007-003-0002
title: Die zugesicherten Schlüssel festschreiben — und ein Test hält sie
status: done
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
- [x] Die zugesicherten Schlüssel stehen in `an_project/docs/dev-guide.md`, je mit einem Satz, was sie liefern.
- [x] Ein Test hält fest, dass **jeder** zugesicherte Schlüssel im aufgebauten Container vorhanden ist.
- [x] Der Test hält die Gegenrichtung: Ein registrierter Schlüssel, der in der Liste fehlt, fällt auf — oder er steht ausdrücklich als intern vermerkt, mit Grund.
- [x] Der Test läuft ohne Datenbank, oder er sagt klar, warum er sie braucht.
- [x] Die volle Suite bleibt grün.

## Verification
Einen zugesicherten Schlüssel aus `bootstrap.php` entfernen — der Test muss rot werden. Einen
neuen registrieren, ohne die Liste zu ergänzen — der Test muss ebenfalls rot werden. Danach
zurücksetzen.

## Ergebnis

**Die Liste steht in `an_project/docs/dev-guide.md` — und sie hat drei Stufen, nicht eine.**
Das war der Fund beim Vermessen: Eine flache Liste wäre an zwei Stellen falsch gewesen.

| Stufe | Anzahl | Schlüssel |
|---|---|---|
| Immer verfügbar | 10 | `is_installed`, `debug`, `database`, `mailer`, `routeManager`, `consoleManager`, `request_stack`, `dispatcher`, `auth.user`, `anmeldeanbieter` |
| Erst wenn installiert | 3 | `db`, `dbs`, `orm.em` |
| Erst nach der Anmeldung | 1 | `auth.token` |
| Intern, nicht zugesichert | 16 | u. a. `schema`, `typeManager`, `pluginManager`, `kernel`, `resolver` |

**Drei Schlüssel stehen in einem `if ($app['is_installed'])`.** Auf einem frischen Checkout gibt
es sie nicht — wer sie ohne Prüfung liest, bekommt eine Exception. Das ist Absicht:
`appcms:install` muss selbst laufen können, bevor es eine Datenbank gibt. Eine Liste, die das
verschweigt, führte genau den in die Irre, der sein Projekt zum ersten Mal aufsetzt.

**`auth.token` gibt es erst nach der Anmeldung — nicht `null`, sondern gar nicht.** Gemessen:
Im frisch aufgebauten Container fehlt er, als einziger der 27 geprüften. Gesetzt wird er in
`BaseControllerProvider`, nachdem ein Request sich ausgewiesen hat; in einem Console-Lauf gibt
es ihn nie. **Das ist eine Zusicherung und nicht ihr Gegenteil:** Ein Projekt, das ihn
ausserhalb eines angemeldeten Requests liest, bekommt eine Exception und keinen stillen
`null`-Wert — und ein eigener Test hält fest, dass das so bleibt.

## Der Wächter prüft beide Richtungen, aus zwei Quellen

`tests/Integration/ContainerSchluesselTest.php`, fünf Tests.

**Zwei Quellen für zwei Fragen, und das ist kein Umweg.** Der laufende Container kann sagen, ob
ein Schlüssel *da* ist; er kann nicht sagen, ob jemand einen *neuen* angelegt hat — gefragt wird
immer nur nach den bekannten. Für „ist alles eingeordnet" muss die Quelle der Quelltext sein.
Gelesen werden `bootstrap.php`, `Classes/Kernel/Application.php` und
`Classes/Controller/Provider/BaseControllerProvider.php`.

| Frage | Quelle |
|---|---|
| Ist jeder zugesicherte Schlüssel da? | der aufgebaute Container |
| Fehlt `auth.token` vor der Anmeldung? | derselbe |
| Ist jeder registrierte Schlüssel eingeordnet? | der Quelltext |
| Wird jeder eingeordnete auch registriert? | derselbe |
| Stimmen die Listen mit dem `dev-guide`? | die Doku |

**Die dritte Frage ist die wichtigere.** Ohne sie wüchse die Liste auseinander: Ein neuer
Schlüssel käme dazu, niemand entschiede, ob er öffentlich ist, und ein Projekt benutzte ihn auf
eigenes Risiko, ohne es zu wissen.

**Die vierte ist die Regel aus `006-005`,** hier angewandt: Ein Eintrag, der nichts mehr trifft,
macht den Lauf rot. Sonst bliebe ein Schlüssel in der Liste stehen, nachdem er aus dem Framework
verschwunden ist — eine Zusicherung ins Leere.

## Ein eigener Fehlgriff, und er hätte den Test wertlos gemacht

**Mein erster Entwurf fragte den Container nur nach den Schlüsseln, die ich ohnehin schon
kannte.** Damit wäre „ist jeder registrierte Schlüssel eingeordnet" gegenstandslos gewesen — ein
neuer Schlüssel hätte nie auffallen können, weil nie nach ihm gefragt wurde. Der statische
Parser ist die Antwort darauf, und er fand prompt **drei** Schlüssel, die meine Klassifizierung
nicht kannte: `kernel`, `resolver` und `argument_resolver` aus der HttpKernel-Verdrahtung.

**Und ein zweiter, der die Gegenprobe entwertet hätte:** Mein erster Mutationstest lief ohne
laufenden Testserver. Die Tests haben sich sauber übersprungen, die Ausgabe war leer, und das
sah aus wie „keine Meldung, also in Ordnung". Beim zweiten Anlauf mit Server schlug die erste
Mutation gleich dreifach an.

**Zahlen:** Volle Suite `OK (519 tests, 1666 assertions)` (vorher 514), 0 Deprecations bei 0
Ausnahmen, 0 Byte Postausgang. PHPStan `[OK] No errors`. Beide Richtungen durch absichtliche
Verletzung geprüft: ein umbenannter zugesicherter Schlüssel und ein neuer, nicht eingeordneter.

**Zur Datenbank:** Der Test braucht sie, und der Grund steht in der Klasse — drei der
zugesicherten Schlüssel stehen hinter `is_installed`, und ohne Installation könnte er seine
wichtigste Aussage nicht treffen. Er erbt von `IntegrationTestCase` und überspringt sich sauber,
wenn keine Umgebung steht.
