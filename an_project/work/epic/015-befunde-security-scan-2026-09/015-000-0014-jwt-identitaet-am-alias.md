---
id: 015-000-0014
title: JWT-Identität hängt am änderbaren Alias
status: done
depends_on: []
---

# JWT-Identität hängt am änderbaren Alias

## Context
**Security-Scan 2026-09, MEDIUM, Konfidenz mittel. Findings F22 und F26.**

`JwtAccessToken::issue` schreibt `getUserIdentifier()` (`Entity/User.php:410`, der Alias) in
`sub`. Bei jedem Request löst der JWT-Pfad das Konto über den Alias auf: `TokenHandler::fromJwt`
→ `UserBadge($claims->sub)` → `UserLoader::loadUserByIdentifier` → `findOneBy(['alias' => ...])`.
`RightsManagement` (`Classes/Security/RightsManagement.php:35`) schützt bei fremden Benutzern
`pass`, `salt`, `loginManager` und `externalId`, **nicht aber `alias`**.

Ein Nicht-Admin mit Schreibrecht auf fremde `PIM\User`-Datensätze kann deshalb Aliase so
umbenennen, dass der `sub` seines noch gültigen JWT zu einem Admin-Konto gehört. Seine folgenden
Requests laufen dann als dieses Konto.

Nur relevant mit gesetztem `SECURITY_JWT_SECRET`. JWTs sind opt-in.

## Acceptance criteria
- [x] `sub` trägt die unveränderliche User-ID, und `UserLoader` lädt über die ID. Bereits ausgestellte Alias-JWTs sind entweder bis zu ihrem Ablauf übergangsweise gültig oder ungültig, die Entscheidung steht im Register `breaking-changes.md`.
- [x] `RightsManagement` verbietet Nicht-Admins zusätzlich, den `alias` fremder Benutzer zu ändern.

## Verification
Integrationstest mit JWT: Benutzer A hält ein JWT. Ein Nicht-Admin benennt A um und gibt einem
Admin-Konto As alten Alias. Vor dem Fix authentifiziert As JWT danach als Admin, nach dem Fix als A
(bzw. der Umbenennungs-Versuch antwortet 403).
