---
id: 013-005-0003
title: Der OIDC-Provider über den Userinfo-Endpunkt
status: done
depends_on: [013-005-0001]
---

# Der OIDC-Provider über den Userinfo-Endpunkt

## Context
Der zweite Fall. Symfony bringt beide Auspraegungen mit, und die Wahl ist eine Abwaegung —
gemessen am 2026-09-11, nicht geschaetzt:

| | lokale Pruefung (`OidcTokenHandler`) | Userinfo (`OidcUserInfoTokenHandler`) |
|---|---|---|
| neue Pakete | 5, darunter `web-token/jwt-library` und `spomky-labs/pki-framework` | 3, alle leicht |
| je Anmeldung | Signatur lokal gegen JWKS | ein HTTP-Aufruf zum Provider |
| Widerruf | wirkt erst mit dem Ablauf | wirkt sofort |
| Ausfall des Providers | faellt nicht auf | sperrt alle aus |

**Entschieden: der Userinfo-Weg.** Drei leichte Pakete statt fuenf mit einer Kryptobibliothek
darin, und der Widerruf wirkt sofort. Der Preis ist die Abhaengigkeit vom Provider im
Anmeldeweg.

**Was die Abwaegung relativiert, und es gehoert dazugesagt:** Contentfly stellt nach der
Anmeldung ein EIGENES Token aus. Der OIDC-Token wird genau einmal geprueft, beim Login; danach
zaehlt nur noch das eigene (`013-003`). Der Widerrufsvorteil des Userinfo-Wegs betrifft damit nur
das Fenster zwischen Widerruf und dem einen Anmeldeversuch — und der Ausfall-Nachteil ebenso nur
die Anmeldung, nicht die laufende Sitzung.

**Der Nachweis geht ueber einen HttpClient-Doppelgaenger**, nicht gegen einen echten Provider.
Das ist die Konsequenz der Wahl und gehoert ins Ergebnis: Der lokale Weg waere hier
kryptographisch echt pruefbar gewesen.

## Acceptance criteria
- [x] `symfony/http-client` steht im Root-Manifest; `composer audit --locked` bleibt ohne Advisories.
- [x] Ein `OidcProvider` erfuellt `Anmeldeprovider`: Er nimmt den Token aus dem Request, fragt den Userinfo-Endpunkt und macht aus der Antwort eine `Fremdkennung`.
- [x] Endpunkt und die Namen der gelesenen Felder kommen aus der Konfiguration; kein Geheimnis steht in einer Datei im Repo.
- [x] **Die Anmeldung muendet in dieselbe Token-Ausstellung wie der lokale Login** — ein Client merkt nicht, woher der Benutzer kam. Ein Test zeigt es.
- [x] Ein abgelehnter Token, eine Antwort ohne die erwartete Kennung und ein nicht erreichbarer Provider enden alle in `null` — ununterscheidbar.
- [x] Die Wahl gegen die lokale Pruefung steht mit ihren Zahlen im Ergebnis, nicht nur das Ergebnis.

## Verification
Unit-Tests gegen einen `HttpClientInterface`-Doppelgaenger: 200 mit Kennung, 401, 200 ohne
Kennung, Transportfehler. Ein Integrationstest, der ueber den Provider anmeldet und mit dem
zurueckgegebenen Token eine geschuetzte Route oeffnet. Volle Suite.

## Ergebnis

**`OidcProvider` fragt den Userinfo-Endpunkt und macht aus der Antwort eine `Fremdkennung`.**
Damit ist der zweite der beiden Fälle angebunden, die in der Praxis gefragt werden.

### Die Wahl, mit den Zahlen

| | lokal gegen JWKS | **Userinfo (gewählt)** |
|---|---|---|
| neue Pakete | 5, darunter `web-token/jwt-library`, `spomky-labs/pki-framework` | **3, alle leicht** |
| je Anmeldung | Signatur lokal | eine HTTP-Anfrage |
| Widerruf | erst mit dem Ablauf | **sofort** |
| Ausfall des Providers | fällt nicht auf | sperrt die Anmeldung |

**Was die Abwägung relativiert, und es gehört dazugesagt:** Contentfly stellt nach der Anmeldung
ein **eigenes** Token aus (`013-003`). Der OIDC-Token wird genau einmal geprüft, beim Login;
danach zählt nur noch das eigene. Der Widerrufsvorteil betrifft damit nur das Fenster zwischen
Widerruf und dem einen Anmeldeversuch — und der Ausfall-Nachteil ebenso nur die Anmeldung, nicht
die laufende Sitzung. Die Wahl ist damit weniger folgenschwer, als die Tabelle aussieht.

### Nicht Symfonys `OidcUserInfoTokenHandler`

Der ist ein `AccessTokenHandlerInterface` und liefert ein `UserBadge` — eine Kennung und sonst
nichts. **Die Gruppen wären damit weg**, und genau die braucht der Vertrag aus `013-004`, damit
`Gruppenabbildung` etwas abzubilden hat. Ein Wrapper müsste den Endpunkt ein zweites Mal fragen;
der eigene Aufruf ist kürzer als dieser Umweg.

### Zwei Entscheidungen im Kleinen

**Der Statuscode wird ausdrücklich geprüft.** `toArray()` wirft bei 4xx und 5xx zwar von sich
aus — aber nur, solange niemand `throw: false` setzt. Sich bei einer Anmeldung auf eine Vorgabe
zu verlassen, die eine Option abschalten kann, ist die falsche Art von Sparsamkeit.

**`sub` ist die Vorgabe für die Kennung**, und der Kommentar sagt warum: `email` und
`preferred_username` sind bequemer und **änderbar**. Wer darauf abbildet, bekommt ein neues
Konto, sobald jemand heiratet.

### Jeder Fehlschlag sieht gleich aus

| Fall | Antwort |
|---|---|
| Token abgelehnt (401) | `null` |
| 200 ohne die erwartete Kennung — etwa bei falsch konfiguriertem Claim-Namen | `null` |
| Leere Kennung | `null` |
| Provider nicht erreichbar | `null` |
| Kein Token im Request | `null`, ohne Anfrage |
| Kein Endpunkt konfiguriert | `null`, ohne Anfrage |

Der letzte Fall ist dieselbe Linie wie beim JWT-Geheimnis (`013-002-0003`) und bei der
Provider-Vorlage (`013-004-0004`): Ein Weg, der ohne Konfiguration offensteht, wäre schlimmer als
keiner.

### Einschränkung: gegen `MockHttpClient` geprüft

**Das ist die Konsequenz der gewählten Variante und gehört dazu.** Die lokale Prüfung wäre hier
kryptografisch echt prüfbar gewesen, weil man sich den Schlüssel selbst erzeugen kann; der
Userinfo-Weg braucht ein Gegenüber.

**Was dadurch nicht ungeprüft bleibt:** dass die Anmeldung in dieselbe Token-Ausstellung mündet
wie der lokale Login. Das ist der Weg **hinter** `Anmeldeprovider`, und den misst
`AnmeldeproviderApiTest` end-to-end — ein Provider ist dort austauschbar, weil der Vertrag genau
eine Methode hat. Was OIDC-spezifisch ist, endet bei der `Fremdkennung`, und genau bis dorthin
reichen diese elf Tests.

### Nachweis

| Probe | Ergebnis |
|---|---|
| Volle Suite | `OK (471 tests, 1150 assertions)`, 0 übersprungen (vorher 460) |
| PHPStan | `[OK] No errors` |
| `composer audit --locked` | 0 Advisories nach `symfony/http-client` |
| Deprecations | 0 protokollierte Zeilen |

Der Provider ist **nicht** vorregistriert; die Vorlage zeigt den Eintrag auskommentiert.
