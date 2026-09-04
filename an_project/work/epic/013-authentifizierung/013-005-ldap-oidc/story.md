---
id: 013-005-0000
title: Active Directory und OIDC anbinden
status: todo
depends_on: [013-004-0000]
---

# Active Directory und OIDC anbinden

## Goal
Die beiden Fälle, die in der Praxis gefragt werden, sind an der neuen Schnittstelle nachweislich
angebunden — nicht als Konzept, sondern lauffähig. Damit ist belegt, dass der Vertrag aus
`013-004` trägt.

## Umfang

**Active Directory / LDAP.** Symfony bringt einen `ldap`-User-Provider und den Bind über
`form_login_ldap` bzw. `http_basic_ldap` bereits mit — es ist wenig eigener Code nötig. Zu klären
und zu entscheiden ist stattdessen das Drumherum:

- Wie werden AD-Gruppen auf Contentfly-Gruppen abgebildet, und wo wird diese Abbildung gepflegt?
- Was passiert mit einem Benutzer, den es im AD nicht mehr gibt — sperren, löschen, ignorieren?
- Wie wird gebunden: Dienstkonto plus Suche, oder direkter Bind mit den Benutzerdaten?

**OIDC.** Der `access_token`-Handler deckt das nativ ab, in zwei Ausprägungen: `oidc` prüft das
Token lokal gegen ein JWKS (seit Symfony 7.1 auch RS256), `oidc_user_info` fragt den
Userinfo-Endpunkt des Providers. Die Wahl ist eine Abwägung — lokal ist schnell und übersteht
einen Ausfall des Providers, der Userinfo-Weg merkt einen Widerruf sofort.

**SAML** bleibt Bundle-Sache; `onelogin/php-saml` liegt bereits im Baum (heute in
`custom/composer.json`). Ob es Framework- oder Projektsache wird, entscheidet Epic `006`.

## Fertig, wenn
- Eine Anmeldung gegen ein LDAP/AD läuft durch, mit Gruppenabbildung, und ist dokumentiert.
- Eine Anmeldung über einen OIDC-Provider läuft durch; die Wahl zwischen lokaler Prüfung und
  Userinfo-Abruf ist begründet festgehalten.
- Beide Wege münden in dieselbe Token-Ausstellung wie der lokale Login — ein Client merkt nicht,
  woher der Benutzer kam.
- Was ein Projekt konfigurieren muss, steht im `dev-guide.md`, nicht nur im Code.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
