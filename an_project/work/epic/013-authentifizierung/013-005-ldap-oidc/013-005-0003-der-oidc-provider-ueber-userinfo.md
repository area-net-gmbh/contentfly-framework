---
id: 013-005-0003
title: Der OIDC-Provider über den Userinfo-Endpunkt
status: todo
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
- [ ] `symfony/http-client` steht im Root-Manifest; `composer audit --locked` bleibt ohne Advisories.
- [ ] Ein `OidcProvider` erfuellt `Anmeldeprovider`: Er nimmt den Token aus dem Request, fragt den Userinfo-Endpunkt und macht aus der Antwort eine `Fremdkennung`.
- [ ] Endpunkt und die Namen der gelesenen Felder kommen aus der Konfiguration; kein Geheimnis steht in einer Datei im Repo.
- [ ] **Die Anmeldung muendet in dieselbe Token-Ausstellung wie der lokale Login** — ein Client merkt nicht, woher der Benutzer kam. Ein Test zeigt es.
- [ ] Ein abgelehnter Token, eine Antwort ohne die erwartete Kennung und ein nicht erreichbarer Provider enden alle in `null` — ununterscheidbar.
- [ ] Die Wahl gegen die lokale Pruefung steht mit ihren Zahlen im Ergebnis, nicht nur das Ergebnis.

## Verification
Unit-Tests gegen einen `HttpClientInterface`-Doppelgaenger: 200 mit Kennung, 401, 200 ohne
Kennung, Transportfehler. Ein Integrationstest, der ueber den Provider anmeldet und mit dem
zurueckgegebenen Token eine geschuetzte Route oeffnet. Volle Suite.
