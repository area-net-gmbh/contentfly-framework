---
id: 013-002-0003
title: Der verzweigende TokenHandler
status: review
depends_on: [013-002-0001]
---

# Der verzweigende TokenHandler

## Context
Symfony erlaubt genau **einen** `token_handler` je Firewall und bringt keine Verkettung mit. Die
Verzweigung schreibt man selbst: drei punktgetrennte Segmente und ein plausibler Header →
JWT-Zweig, alles andere → opaquer Zweig.

**Der opaque Zweig ist, was `checkToken()` heute tut:** `pim_token` über den SHA-256 des
vorgezeigten Tokens nachschlagen (`013-001-0004`), Benutzer vorhanden und aktiv, Timeout gegen
`APP_TOKEN_TIMEOUT` oder die Gruppe des Benutzers, ein Token mit `referrer` verfällt nicht — und
der Sliding-Expiration-Write auf `modified`.

**Der JWT-Zweig prüft Signatur und Claims und fasst die Datenbank nicht an.** Ausgestellt werden
JWT erst in `013-003`; hier wird nur verifiziert, und der Test signiert sich sein Token selbst.
Die Schlüsselverwaltung bleibt entsprechend minimal — ein Geheimnis aus der Umgebung. Wechsel,
Übergangszeit und Widerruf gehören zu `013-003`.

**Ununterscheidbar scheitern** ist eine Anforderung, keine Geschmacksfrage: Verschiedene
Fehlermeldungen verraten, welche Tokenart erwartet wird, und damit, welche ein Angreifer bauen
muss.

## Acceptance criteria
- [x] Ein opaques Token aus `pim_token` und ein selbst signiertes JWT münden in dasselbe `UserBadge` für denselben Benutzer.
- [x] Der JWT-Zweig setzt **nachweislich** keine Abfrage auf `pim_token` ab.
- [x] Der Sliding-Expiration-Write passiert nur im opaquen Zweig.
- [x] Ungültige, abgelaufene und manipulierte Tokens werden in beiden Zweigen abgewiesen — mit gleicher Ausnahme und gleicher Meldung. Ein Test vergleicht beide Antworten miteinander.
- [x] Das Signaturgeheimnis steht in keiner Datei im Repo. Ohne gesetztes Geheimnis wird der JWT-Zweig abgewiesen statt stillschweigend übersprungen.
- [x] Der alte Weg ist weiterhin unverändert.

## Verification
Unit-Tests für beide Zweige. Für „kein Datenbankzugriff" wird gezählt, nicht behauptet: ein
Zähler auf dem DBAL-Logger oder ein `EntityManager`, der bei jedem Zugriff wirft. Für die
Ununterscheidbarkeit ein Test, der beide Fehlerantworten gegeneinander hält. Volle Suite.

## Ergebnis

**Ein Handler, zwei Zweige, dasselbe `UserBadge`.** Damit ist „stateful oder stateless" keine
Endpunkt-Entscheidung mehr, sondern eine Eigenschaft des ausgestellten Tokens.

### Der JOSE-Header entscheidet mit, nicht nur die zwei Punkte

Der Task nannte als Kriterium „drei punktgetrennte Segmente". Das allein reicht nicht: Ein
opaquer Token, den ein Projekt über `addToken` selbst gewählt hat, darf Punkte enthalten —
`pim_token.token` nimmt jede Zeichenkette. Entschiede allein die Form, landete `projekt.api.token`
im JWT-Zweig und würde abgewiesen, **obwohl er in der Datenbank steht**. Geprüft wird deshalb
zusätzlich, ob das erste Segment sich als JSON mit `alg` lesen lässt. Ein Test schickt genau
diesen Token durch und erwartet ihn im opaquen Zweig.

### Der JWT-Zweig fasst `pim_token` nicht an — gemessen

Der Test gibt dem Handler einen `EntityManager`, der bei **jedem** Zugriff wirft. Greift der
JWT-Zweig doch zu, fliegt eine Ausnahme, die der Handler nicht fängt. Damit entfällt auch der
Sliding-Expiration-Write, den der opaque Zweig bei *jedem* Request macht.

**Eine Einschränkung, die im Story-Text untergeht:** Dort steht „kein Datenbankzugriff". `pim_user`
wird sehr wohl gelesen — ohne Benutzer gibt es kein `UserBadge`. Das messbare Kriterium der Story
sagt es genauer: *kein Query auf `pim_token`*. Der JWT-Zweig gibt sein Badge deshalb **ohne**
eigenen Lader zurück und überlässt das Laden dem `Benutzerlader`; der opaque Zweig gibt eines
**mit** Lader, weil ihm der Benutzer schon vorliegt und eine zweite Abfrage für dieselbe Zeile
nichts brächte.

### Beide Zweige scheitern ununterscheidbar

Jeder Fehlschlag wirft `CustomUserMessageAuthenticationException` mit derselben Meldung — eine
Konstante, damit niemand versehentlich eine zweite einführt. Der Test hält die beiden Antworten
**gegeneinander**, statt jede einzeln gegen einen erwarteten Text zu prüfen: So fällt er auch
dann um, wenn jemand beide ändert, aber nur eine davon.

### Ein Fund unterwegs: `firebase/php-jwt` unter 7.0 hat ein Advisory

Der erste Versuch nahm `^6.10` — `composer audit` meldete sofort **CVE-2025-45769**
(*php-jwt contains weak encryption*, betroffen `< 7.0.0`). Statt einer Ausnahme im Gate steht
jetzt `^7.0` im Manifest. Das Gate bleibt bei null Ausnahmen; genau dafür ist es da.

Und in Version 7 verlangt die Bibliothek für HS256 **mindestens 32 Byte Schlüssel** — sie wirft
sonst schon beim Signieren. Gefunden beim Schreiben der Tests, wo ein 21-Byte-Fremdgeheimnis
nicht angenommen wurde. Der Handler fängt das mit jedem anderen Fehler ab: Ein Betreiber mit zu
kurzem Geheimnis bekommt kein halb funktionierendes System, sondern gar keines. Das ist die
richtige Richtung, denn HS256 mit einem kurzen Geheimnis ist ratbar. Vermerkt an
`SECURITY_JWT_SECRET` und in der ausgelieferten `custom/config.php`, samt Befehl zum Erzeugen.

### Ohne Geheimnis wird abgewiesen, nicht übersprungen

`SECURITY_JWT_SECRET` hat **keinen Standardwert** — dieselbe Linie wie `SECURITY_CIPHER_KEY`. Ein
im Repository hinterlegtes Geheimnis ist keines. Und ein Zweig, der sich mangels Konfiguration
selbst abschaltet, ist keine Prüfung: Ohne Wert wird jedes JWT abgewiesen, mit derselben Meldung
wie alles andere.

### Der opaque Zweig ist unverändert übernommen

Hash-Nachschlag (`013-001-0004`), Benutzer aktiv, Timeout aus der Gruppe (in Minuten) oder aus
`APP_TOKEN_TIMEOUT`, `referrer`-Token verfallen nicht, abgelaufene werden gelöscht, gültige auf
`modified` zurückgeschrieben. Je ein Test.

`letzterToken()` gibt die aufgelöste Zeile heraus — `$app['auth.token']` braucht sie, und sie ein
zweites Mal zu suchen wäre eine Abfrage für etwas, das gerade in der Hand lag.

### Nachweis

| Probe | Ergebnis |
|---|---|
| Volle Suite | `OK (359 tests, 910 assertions)`, 0 übersprungen (vorher 340) |
| PHPStan | `[OK] No errors` |
| Deprecations | 0 protokollierte Zeilen |
| `composer audit --locked` | 0 Advisories (nach dem Bump auf `^7.0`) |
| `checkToken()`-Aufrufer | weiterhin unverändert, alle fünf |

Neunzehn Tests: acht für den opaquen Zweig, acht für den JWT-Zweig, einer für die Verzweigung
selbst, einer für die Ununterscheidbarkeit, einer für das zu kurze Geheimnis.