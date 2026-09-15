---
id: 000-000-0045
title: Kein Haken nach dem Login — Projektdaten und tempData
status: todo
depends_on: []
---

# Kein Haken nach dem Login — Projektdaten und tempData

## Context
**Gefunden bei `007-005-0004`** am Bestandsprojekt UFP (Befund F-7).

Der `LoginProvider`-Vertrag aus `013-004` gibt einem Provider **eine** Pflicht: gegen das
Fremdsystem prüfen. Er fasst die Datenbank nicht an, Anlage und Gruppen macht das Framework. Das
deckt einen reinen Anmeldeweg ab — nicht, was die sechs LoginManager von UFP beim Login zusätzlich
tun:

- **Felder am Benutzer setzen:** `loginDate`, `isPublic`, `publicAlias`, Consent-Felder,
  `dynamicFields` (aus Link-Parametern oder dem Community-Profil), `oAuth2Token`, `communityToken`.
- **`tempData` liefern.** Der Login antwortet mit `data` aus `$user->getTempData()` — das tut
  `AuthController` weiterhin. Aber gesetzt wird `tempData` nur vom Manager, und den gibt es nicht
  mehr. **Die App liest bei jedem Login `res.data.role`**; ohne `data` bricht die Anmeldung im Client.
- Auch der **Passwort-Weg** braucht `tempData` (Rolle, Consent-Stand, Erstanmeldung).

Einen Haken dafür gibt es nicht: `AuthController::loginAction()` löst kein Event aus. Der einzige
Umweg wäre ein Listener auf `pim.controller.after.auth.login`, der die fertige JSON-Antwort
umschreibt, mit Werten, die der Provider über Request-Attribute herüberreicht.

**Entschieden am 2026-09-15:** Das Framework löst nach einem erfolgreichen Login ein Event aus.

## Acceptance criteria
- [ ] `AuthController::loginAction()` löst `pim.auth.after.login` aus — auf dem Passwort- und auf dem Provider-Weg, nach Provisionierung, Gruppenzuordnung und Aktiv-Prüfung, **vor** dem Ausstellen des Tokens.
- [ ] Das Event trägt `user`, `request`, `provider` (Name oder `null`) und `identity` (`ExternalIdentity` oder `null`), dazu `app` wie die übrigen `pim.*`-Events.
- [ ] Was ein Listener am Benutzer ändert und als `tempData` setzt, steht in der Antwort (`user`, `data`) — auch mit `tokenType=jwt`.
- [ ] Ein abgelehnter Login löst das Event nicht aus.
- [ ] Tests für beide Wege und für den abgelehnten Fall.
- [ ] `breaking-changes.md` (Abschnitt LoginManager) nennt das Event als Ort für das, was ein alter Manager über die Prüfung hinaus tat; `ExampleProvider` oder Doku zeigt einen Listener.
- [ ] Volle Suite, PHPStan, Deprecation-Gate grün.

## Verification
Integrationstest gegen `/auth/login` mit registriertem Listener. Am UFP-Probe-Backend: Login
eines Probe-Benutzers antwortet mit `data.role`.
