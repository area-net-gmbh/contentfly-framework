---
id: 013-005-0004
title: Die Buchführung
status: todo
depends_on: [013-005-0001, 013-005-0002, 013-005-0003]
---

# Die Buchführung

## Context
Was ein Projekt konfigurieren muss, steht nach den drei Tasks im Code — und damit nirgends, wo
jemand es sucht. Die Story verlangt es ausdruecklich im `dev-guide.md`.

**Dazu eine ueberholte Aussage im Story-Text:** Der Umfang sagt, `onelogin/php-saml` liege
„bereits im Baum (heute in `custom/composer.json`)". Epic `006` hat es gestrichen —
`006-004-0002` fuehrt es unter „entfaellt: wird nirgends benutzt" —, und `custom/composer.json`
hat heute ein leeres `require`. Der Satz gehoert richtiggestellt, nicht stehengelassen: Er
verspricht einen Baustein, den es nicht gibt.

## Acceptance criteria
- [ ] `an_project/docs/dev-guide.md` sagt, was ein Projekt fuer LDAP und fuer OIDC konfigurieren muss — Variablen, Werte, und wo der Provider eingetragen wird.
- [ ] Die Einschraenkungen stehen dort, wo sie jemand liest: LDAP gegen Doppelgaenger geprueft, OIDC gegen einen HttpClient-Doppelgaenger.
- [ ] Der SAML-Satz im Story-Text ist richtiggestellt.
- [ ] Die Bruchstellen stehen in `an_project/docs/breaking-changes.md`, je mit dem, was ein Projekt zu tun hat.
- [ ] `an_project/docs/technical.md` ist nachgezogen.
- [ ] Die Gates sind gruen: volle Suite auf PHP 8.3 **und** 8.4, PHPStan, `composer audit --locked`, Deprecation-Log.

## Verification
Volle Suite auf beiden PHP-Versionen, PHPStan, `composer audit --locked`. Die Doku wird gegen den
Code gelesen: Jede genannte Variable und jeder genannte Konfigurationsschluessel muss sich im
Baum wiederfinden.
