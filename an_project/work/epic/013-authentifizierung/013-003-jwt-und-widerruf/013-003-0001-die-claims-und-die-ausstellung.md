---
id: 013-003-0001
title: Die Claims und die Ausstellung
status: review
depends_on: []
---

# Die Claims und die Ausstellung

## Context
Seit `013-002-0003` **verifiziert** der `Tokenhandler` HS256-JWT: Er liest `sub`, den Ablauf
prüft die Bibliothek. Mehr steht nicht drin, weil es nichts auszustellen gab. Das ändert dieser
Task.

**Der Claim-Satz gehört festgelegt, nicht gewachsen.** Hinein kommt, was sich nicht ändert,
solange der Token gilt: Kennung, Ausgeber, Ausstellungs- und Ablaufzeitpunkt und eine
Token-Kennung (`jti`) für den späteren Widerruf. **Nicht hinein kommen Rollen, Gruppen und
Berechtigungen** — sonst wirkt eine Rechteänderung erst nach Ablauf, und genau das ist der
klassische Fehler beim Umstieg auf zustandslose Tokens.

**Ohne Anforderung ändert sich nichts.** Der Login gibt weiterhin ein opaques Token aus;
Bestandsclients merken nichts. Das JWT kommt nur, wenn der Aufrufer es verlangt.

**Das Refresh-Token ist eine `pim_token`-Zeile — und muss als solche erkennbar sein.** Der
opaque Zweig nimmt heute *jede* Zeile: Wer sein Refresh-Token als `appcms-token` vorzeigt,
bekäme damit die ganze API. Ein Refresh-Token soll aber genau eines dürfen — ein neues
Access-JWT holen. Die Zeile braucht deshalb einen Vermerk über ihren Zweck, und der opaque
Zweig muss sie als Zugangstoken abweisen.

## Acceptance criteria
- [x] Der Claim-Satz steht an **einer** Stelle im Code benannt: `sub`, `iss`, `iat`, `exp`, `jti`. Rollen, Gruppen und Berechtigungen stehen nicht darin, und ein Test hält das fest.
- [x] `POST /auth/login` liefert auf Anforderung ein Access-JWT **und** ein Refresh-Token; ohne Anforderung unverändert ein opaques Token.
- [x] Der Handler prüft `iss` und `exp`. Ein Token mit fremdem Ausgeber wird abgewiesen — ununterscheidbar wie jeder andere Fehlschlag.
- [x] Das Refresh-Token ist in `pim_token` an seinem Zweck erkennbar, und der opaque Zweig weist es als Zugangstoken ab. Ein Test zeigt es vor und erwartet die Abweisung.
- [x] Die Lebensdauer des Access-JWT ist konfigurierbar; die Vorgabe liegt zwischen 5 und 15 Minuten.
- [x] Ohne gesetztes `SECURITY_JWT_SECRET` wird die **Ausstellung** verweigert, mit einer Meldung, die dem Betreiber sagt, was fehlt. Dem Aufrufer sagt sie nichts Zusätzliches.

## Verification
Integrationstests am Login: ohne Schalter ein opaques Token, mit Schalter ein JWT plus
Refresh-Token; das JWT öffnet `/api/schema`, das Refresh-Token nicht. Unit-Tests für den
Claim-Satz und für die `iss`-Prüfung. Volle Suite.

## Ergebnis

**Der Login stellt auf Anforderung ein Access-JWT samt Refresh-Token aus.** Ohne Anforderung
bleibt alles, wie es war.

### Fünf Claims, und die Liste steht an einer Stelle

| Claim | trägt |
|---|---|
| `sub` | die Kennung des Benutzers |
| `iss` | den Ausgeber |
| `iat` | den Ausstellungszeitpunkt |
| `exp` | den Ablauf |
| `jti` | die Kennung dieses **einen** Tokens — die Handhabe für den Widerruf in `013-003-0003` |

**Was bewusst nicht drinsteht: Rollen, Gruppen, Berechtigungen.** Sie können sich ändern,
während der Token gilt; stünden sie darin, wirkte eine Rechteänderung erst nach dessen Ablauf.
Das ist der klassische Fehler beim Umstieg auf zustandslose Tokens, und er fällt erst auf, wenn
jemandem ein Recht entzogen wird und es nicht wirkt. Zwei Tests halten es fest — einer gegen die
Claim-Liste, einer, der die Rumpfnutzlast eines echten Tokens nach `ROLE_`, `group`,
`permissions` und `isAdmin` absucht.

Der Preis dafür ist eine Abfrage auf `pim_user` je Request. Die auf `pim_token`, samt
Schreibzugriff bei jedem Aufruf, fällt dafür weg.

### Nur auf Anforderung, ausdrücklich ohne Konfigurationsschalter

Der Story-Umfang lässt „über Konfiguration **oder** einen Request-Parameter" zu. Es ist der
Parameter geworden, und das ist eine Entscheidung: Ein Konfigurationsschalter kippte die Antwort
für **jeden** Client auf einmal — und die Zusage dieser Story lautet, dass ein Bestandsclient
nichts merkt. Wer JWT will, sagt es je Anfrage.

`token` bleibt dabei das Feld, das der Client vorzeigt; für einen umsteigenden Client ändert
sich genau ein Feldwert und kein Feldname. Daneben stehen `refreshToken` und `expiresIn`.

### Das Refresh-Token musste vom Zugangstoken getrennt werden

**Der Fund aus dem Schnitt, und er war nötig.** Das Refresh-Token ist eine gewöhnliche Zeile in
`pim_token`, und der opaque Zweig des `Tokenhandler` nahm bis hierhin **jede** Zeile an. Ein
Refresh-Token gilt länger als ein Access-JWT — das ist sein Zweck —, und ohne Trennung wäre es
damit ein langlebiger Generalschlüssel für die ganze API gewesen, also genau das, was das
Refresh-Modell verhindern soll.

Die Zeile trägt jetzt eine Spalte `purpose`; `null` heisst Zugangstoken wie bisher, `refresh`
heisst Refresh-Token. Der opaque Zweig weist eine Refresh-Zeile ab — ununterscheidbar wie alles
andere: Wer sie an der falschen Tür vorzeigt, erfährt nicht, dass sie an einer anderen passen
würde. Geprüft im Unit-Test und am laufenden System, über beide Quellen.

### Der Ausgeber wird geprüft

`firebase/php-jwt` prüft Signatur und Ablauf, den `iss` nicht. Ohne die eigene Prüfung gälte
hier jedes Token, das mit demselben Geheimnis signiert wurde — auch eines, das eine ganz andere
Anwendung für einen ganz anderen Zweck ausgestellt hat. Geteilte Geheimnisse sind eine schlechte
Idee, aber sie kommen vor, und dann soll die Anwendung nicht das schwächste Glied sein.

### Ohne Geheimnis wird nicht ausgestellt

`Zugangstoken::geheimnis()` wirft, statt mit einem Ersatz weiterzumachen. Der Login fängt das
vorher ab und antwortet mit einer Meldung, die das fehlende Feld nennt: Sie richtet sich an den
Betreiber, sagt aber nichts über Konten oder vorhandene Tokens. Ein Angreifer erfährt nur, dass
diese Installation keine JWT ausstellt.

### Die Testumgebung kennt das Geheimnis jetzt

`SECURITY_JWT_SECRET` kommt aus der Umgebung, wie in Produktion.
`tools/ci/prepare-test-environment.sh` verlangt dafür `CONTENTFLY_TEST_JWT_SECRET` und reicht es
an den Testserver weiter; die Pipeline setzt einen festen Wert. Ein Geheimnis, das eine
Wegwerf-Datenbank schützt, muss nicht geheim sein — und ein variabler Wert machte den Lauf
schwerer nachzuspielen.

Die Namen sind absichtlich verschieden: `CONTENTFLY_TEST_*` ist die Umgebung des Testlaufs,
`SECURITY_JWT_SECRET` die der Anwendung. Sie gleichzusetzen ist eine Entscheidung des
Aufbauskripts und keine, die in der Anwendung steht.

### Nachweis

| Probe | Ergebnis |
|---|---|
| Volle Suite | `OK (379 tests, 955 assertions)`, 0 übersprungen (vorher 362) |
| PHPStan | `[OK] No errors` |
| Deprecations | 0 protokollierte Zeilen |
| Anmeldung ohne `tokenType` | 128 Hex, kein `refreshToken` |
| Anmeldung mit `tokenType: jwt` | JWT plus Refresh-Token, `expiresIn` ≤ 900 |
| Refresh-Token als Zugangstoken | abgewiesen, über beide Quellen |

Die Spalte `pim_token.purpose` ist eine Schemaänderung; sie gehört mit den übrigen Bruchstellen
in `013-003-0005`.
