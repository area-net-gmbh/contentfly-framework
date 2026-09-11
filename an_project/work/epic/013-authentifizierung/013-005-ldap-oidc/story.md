---
id: 013-005-0000
title: Active Directory und OIDC anbinden
status: in-progress
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

**SAML** bleibt Bundle-Sache. **Der Satz dazu war überholt und ist richtiggestellt
(2026-09-11):** Er sagte, `onelogin/php-saml` liege „bereits im Baum (heute in
`custom/composer.json`)". Epic `006` hat es gestrichen — `006-004-0002` führt es unter
„entfällt: wird nirgends benutzt" —, und `custom/composer.json` hat heute ein **leeres**
`require`. Wer SAML will, nimmt das Paket neu auf; die Frage Framework- oder Projektsache hat
Epic `006` mit „Projektsache" beantwortet, indem es aus dem Root-Manifest herausblieb.

## Nachgemessen am 2026-09-11

- **`symfony/ldap`, `symfony/http-client`, `web-token/jwt-library` und `symfony/clock` fehlen
  alle** im Lock. Jeder der beiden Wege kostet Pakete, und die Zahlen entscheiden mit.
- **Symfony bringt beide OIDC-Handler mit**, aber zu sehr verschiedenem Preis: die lokale
  Prüfung fünf Pakete (darunter `web-token/jwt-library` und `spomky-labs/pki-framework`), der
  Userinfo-Weg drei leichte. **Entschieden: der Userinfo-Weg.**
- **Das lokale PHP hat `ldap`, das CI-Image nicht** — `tools/ci/install-php-extensions.sh` baut
  nur `pdo_mysql` und `gd`.
- **Entschieden: LDAP wird gegen Doppelgänger geprüft**, nicht gegen ein laufendes Verzeichnis.
  Ein OpenLDAP-Dienst in der Testumgebung wäre der Preis für einen echten Bind, und er steht in
  keinem Verhältnis zu dem, was er zusätzlich belegte. Die Einschränkung gehört benannt, nicht
  verschwiegen.
- **Die Gruppenabbildung braucht keinen eigenen Task:** Sie steht seit `013-004-0003`; beide
  Provider füllen nur `Fremdkennung->gruppen`.

## Fertig, wenn
- Eine Anmeldung gegen ein LDAP/AD läuft durch, mit Gruppenabbildung, und ist dokumentiert.
- Eine Anmeldung über einen OIDC-Provider läuft durch; die Wahl zwischen lokaler Prüfung und
  Userinfo-Abruf ist begründet festgehalten.
- Beide Wege münden in dieselbe Token-Ausstellung wie der lokale Login — ein Client merkt nicht,
  woher der Benutzer kam.
- Was ein Projekt konfigurieren muss, steht im `dev-guide.md`, nicht nur im Code.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
- [ ] 013-005-0001 — Der LDAP-Provider
- [ ] 013-005-0002 — Was mit einem verschwundenen Benutzer passiert
- [ ] 013-005-0003 — Der OIDC-Provider über den Userinfo-Endpunkt
- [ ] 013-005-0004 — Die Buchführung
