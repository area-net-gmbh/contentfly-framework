---
id: 013-000-0000
title: Authentifizierung — stateful und stateless nebeneinander
status: done
depends_on: []
---

# Authentifizierung — stateful und stateless nebeneinander

## Goal
Contentfly authentifiziert wahlweise **stateful** (opaques Token in `pim_token`, wie bisher) oder
**stateless** (JWT) — beides über denselben Mechanismus, entschieden pro ausgestelltem Token statt
pro Endpunkt. Dazu ein tragfähiger Nachfolger des `LoginManager`, mit dem ein Projekt Fremdsysteme
wie Active Directory oder einen OIDC-Provider anbindet, ohne dass das Framework wissen muss, wie
dort geprüft wird.

**Warum ein eigenes Epic und kein Anhängsel des Kernel-Wechsels:** Es berührt das Datenmodell
(`pim_token`, `User.pass`), den API-Vertrag (Header, Login-Antwort) und die Migration der
Bestandsprojekte gleichermaßen. Und der erste Teil — die Härtung — ist vom Kernel unabhängig und
sollte nicht auf ihn warten.

## Ausgangslage

Aus dem Review vom 2026-09-04:

- Es gibt heute **zwei** Auth-Wege, und beide sind stateful: die PHP-Session für die Admin-UI
  (fällt mit `012-004`) und das DB-Token `pim_token`.
- **JWT gibt es im Framework nicht.** `firebase/php-jwt` liegt nur in `custom/vendor`; die gesamte
  JWT-Logik lag im Kundenprojekt. Was in `an_project/docs/technical.md` unter „Pro-Tenant-JWT-Secret"
  steht, beschreibt fremden Code, keine vorhandene Funktion.
- Jeder authentifizierte Request schreibt in die Datenbank (`Token.modified` als Sliding
  Expiration) — das ist der eigentliche Preis des heutigen Modells und das Argument für einen
  stateless-Pfad.
- Sechs Sicherheitsbefunde (A-1 bis A-6) und drei funktionale Defekte, siehe Story `013-001`.

## Zielbild

Ein `stateless: true`-Firewall mit dem `access_token`-Authenticator und **einem** TokenHandler,
der nach Tokenform verzweigt — Symfony erlaubt genau einen Handler pro Firewall und bringt keine
Verkettung mit, die schreibt man selbst:

```
getUserBadgeFrom($token)
  ├─ drei punktgetrennte Segmente → JWT:    Signatur + Claims, kein DB-Zugriff  (stateless)
  └─ sonst                        → opaque: pim_token nachschlagen, Timeout      (stateful)
```

Widerruf ist der Haken an stateless: Ein JWT gilt bis zum Ablauf, Logout und Sperrung wirken
nicht. Deshalb **kurzlebiges Access-JWT plus Refresh-Token als opaques DB-Token** — der Widerruf
sitzt dort, wo ohnehin Zustand liegt, und beide Modelle greifen ineinander statt nebeneinanderher
zu laufen.

## Stories
<!-- Die Stories dieses Epics. Wird von /new-story synchron gehalten. -->
- [x] 013-001-0000 — Auth-Härtung: Passwörter, Master-Passwort, Rate-Limiting, Token-Speicherung
- [x] 013-002-0000 — access_token-Authenticator mit verzweigendem TokenHandler
- [x] 013-003-0000 — JWT ausstellen und widerrufen
- [x] 013-004-0000 — Nachfolger des LoginManagers
- [x] 013-005-0000 — Active Directory und OIDC anbinden
