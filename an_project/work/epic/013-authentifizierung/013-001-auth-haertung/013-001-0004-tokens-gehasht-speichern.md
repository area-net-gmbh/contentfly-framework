---
id: 013-001-0004
title: Tokens nur noch gehasht speichern
status: todo
depends_on: []
---

# Tokens nur noch gehasht speichern

## Context
`pim_token.token` steht im Klartext — 128 Hex aus 64 Zufallsbytes. Ein Lesezugriff auf die
Datenbank (ein Backup, eine SQL-Injection, ein Dump im Ticketsystem) übergibt **sämtliche
laufenden Sitzungen**, sofort verwendbar.

Künftig steht dort nur ein Hash. Beim Prüfen wird der vorgezeigte Token gehasht und der Hash
nachgeschlagen. Der Token selbst wird dem Client genau einmal ausgeliefert, bei der Anmeldung.

**Ein schneller Hash genügt, und zwar begründet.** Der Token ist kein Passwort: 64 zufällige
Bytes lassen sich nicht raten, und ein Arbeitsfaktor würde jeden authentifizierten Request
verteuern. SHA-256 ohne Salt ist hier richtig — deterministisch, damit man danach suchen kann.

**Bestehende Tokens werden ungültig.** Entschieden am 2026-09-10: Wer angemeldet ist, meldet sich
neu an. Sie beim Update zu hashen hiesse, sie noch einmal im Klartext zu lesen; und ein Backup
von gestern enthält sie ohnehin.

## Acceptance criteria
- [ ] `pim_token` enthält keinen verwendbaren Token mehr — nachgewiesen an einer echten Anmeldung und einem Blick in die Tabelle.
- [ ] Die Authentifizierung funktioniert unverändert: anmelden, Token vorzeigen, Timeout, Abmelden.
- [ ] Das Nachschlagen geschieht über den Hash; der Klartext-Token wird nirgends gespeichert oder protokolliert.
- [ ] Die Wahl eines schnellen Hashes ist im Code begründet — ein Token ist kein Passwort.
- [ ] Der Referrer-Token (API-Token ohne Timeout) funktioniert weiterhin.
- [ ] Die Bruchstelle steht in `breaking-changes.md`: Alle Sitzungen enden mit dem Update.

## Verification
Anmelden, den zurückgegebenen Token in der Datenbank suchen — er darf dort **nicht** stehen.
Dann mit demselben Token einen geschützten Endpunkt aufrufen: Er muss funktionieren. Dazu
`AuthApiTest`, `RouteSecurityApiTest` und die volle Suite.
