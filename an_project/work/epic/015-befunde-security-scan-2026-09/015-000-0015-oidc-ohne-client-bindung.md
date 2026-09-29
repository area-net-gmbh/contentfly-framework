---
id: 015-000-0015
title: OIDC-Login prüft nicht, für welchen Client das Token ausgestellt ist
status: done
depends_on: []
---

# OIDC-Login prüft nicht, für welchen Client das Token ausgestellt ist

## Context
**Security-Scan 2026-09, MEDIUM, Konfidenz mittel. Findings F23 und F24.**

`OidcProvider::authenticate` (`Classes/Security/OidcProvider.php:77`) schickt das Token aus dem
Login-Body (`accessToken` oder `pass`) als Bearer an den Userinfo-Endpunkt und wertet jede
200-Antwort als Identitätsnachweis (Zeile 109). Ob das Token für **diese** Anwendung ausgestellt
wurde, prüft niemand: kein Check von `aud`, `azp` oder `client_id`, keine Introspection. Kennung und
Gruppen aus der Antwort steuern danach `UserProvisioning::findOrCreate` und
`GroupMapping::apply`, also auch `isAdmin`.

Folge: Jeder andere Client desselben IdP, dem Benutzer Tokens geben (etwa eine fremde Seite mit
„Anmelden mit <IdP>“ oder ein kompromittierter Client im selben Realm oder Tenant), kann diese
Tokens hier einlösen und erhält eine Contentfly-Sitzung als der Benutzer, bei passender Gruppe als
Admin.

Nur relevant, wenn ein Projekt `OidcProvider` in `custom/app.php` registriert. Die Vorgabe ist
ohne OIDC.

## Acceptance criteria
- [x] Der Provider kennt die erwartete `client_id` aus der Konfiguration und lehnt Tokens ab, die nicht nachweislich dafür ausgestellt sind: per ID-Token-Prüfung (Signatur, `iss`, `aud`, `exp`, `nonce`) oder per Token-Introspection (`client_id`/`aud`).
- [x] Ohne konfigurierte `client_id` startet der Provider nicht (fail closed).
- [x] Doku (`dev-guide.md`, Migrationsleitfaden) beschreibt die neue Pflichteinstellung.

## Verification
Test mit einem IdP-Double: Ein Token mit `aud` eines anderen Clients, das der Userinfo-Endpunkt mit
200 beantwortet. Vor dem Fix führt es zum Login, nach dem Fix zu 401. Ein Token mit passendem `aud`
meldet an.
