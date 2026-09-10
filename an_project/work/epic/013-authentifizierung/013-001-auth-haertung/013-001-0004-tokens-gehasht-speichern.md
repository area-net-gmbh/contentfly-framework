---
id: 013-001-0004
title: Tokens nur noch gehasht speichern
status: done
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
- [x] `pim_token` enthält keinen verwendbaren Token mehr — nachgewiesen an einer echten Anmeldung und einem Blick in die Tabelle.
- [x] Die Authentifizierung funktioniert unverändert: anmelden, Token vorzeigen, Timeout, Abmelden.
- [x] Das Nachschlagen geschieht über den Hash; der Klartext-Token wird nirgends gespeichert oder protokolliert.
- [x] Die Wahl eines schnellen Hashes ist im Code begründet — ein Token ist kein Passwort.
- [x] Der Referrer-Token (API-Token ohne Timeout) funktioniert weiterhin.
- [x] Die Bruchstelle steht in `breaking-changes.md`: Alle Sitzungen enden mit dem Update.

## Verification
Anmelden, den zurückgegebenen Token in der Datenbank suchen — er darf dort **nicht** stehen.
Dann mit demselben Token einen geschützten Endpunkt aufrufen: Er muss funktionieren. Dazu
`AuthApiTest`, `RouteSecurityApiTest` und die volle Suite.

## Ergebnis

**In `pim_token.token` steht ein SHA-256, sonst nichts.** Beim Prüfen wird der vorgezeigte
Token gehasht und der Hash nachgeschlagen. Nachgemessen an einer echten Anmeldung: Der
ausgelieferte Token findet sich **null** mal in der Tabelle, sein Hash genau einmal — und er
funktioniert weiterhin.

### Ein schneller Hash, und zwar begründet

Ein Token ist kein Passwort. 64 zufällige Bytes lassen sich nicht raten, es gibt also nichts,
wogegen ein Arbeitsfaktor schützen würde — er verteuerte nur jeden authentifizierten Request.
Kein Salt, weil das Nachschlagen sonst nicht ginge: Ohne Salt ist der Hash deterministisch, die
Spalte bleibt durchsuchbar und `unique`, und es bleibt bei **einem** Zugriff statt einem Scan.

Die Begründung steht im Code an der Spalte, nicht nur hier.

### Die Spalte bleibt 128 Zeichen breit

Ein SHA-256 als Hex braucht 64. Sie zu kürzen hätte einen Bestand aus 128 Zeichen beim `ALTER`
abgeschnitten — auf einer `UNIQUE`-Spalte ein Fehlschlag mitten in der Migration. Und Platz zu
haben heisst, ein späterer Wechsel des Verfahrens braucht keine Schemaänderung.

### Drei Stellen, an denen der Klartext noch stand

| Stelle | vorher | jetzt |
|---|---|---|
| `pim_token.token` | 128 Hex im Klartext | SHA-256 |
| `pim_log.model_label` bei `addToken`/`deleteToken` | der Token | sein Hash |
| Antwort von `listTokens` | der Token | sein Hash |

**Das Protokoll war der Fund.** Ein Charakterisierungstest hielt fest: „Der Token steht im
Klartext im Protokoll — das gehört zu `013-003`." Es gehört hierher: `pim_log` lebt länger als
die Sitzung, die es beschreibt, und ein Dump des Protokolls übergab dieselben Sitzungen wie ein
Dump der Tokentabelle. Als Kennzeichen taugt der Hash genauso — verwenden kann ihn niemand.

`getKlartext()` liefert den Token, und nur in dem Request, in dem er entstand. Er verlässt das
System an genau zwei Stellen: in der Antwort auf `/auth/login` und in der auf `addToken`.

### `setToken()` hat die Signatur behalten

Es nimmt weiterhin den **Klartext** und legt dessen Hash ab. `SystemController::addToken()`
übergibt einen selbstgewählten Token-String, und Bestandsprojekte tun es womöglich auch; der
soll gehasht werden, ohne dass jede Aufrufstelle daran denken muss. `getToken()` liefert
dagegen den Hash — asymmetrisch, und deshalb an beiden Methoden ausgeschrieben.

Der Referrer-Token war dabei der Fall, den man übersieht: Sein Wert kommt vom Aufrufer und
nicht aus dem Konstruktor. Ein eigener Test legt einen an und ruft damit `/api/schema` auf.

### Nebenher: `random_bytes()` statt `openssl_random_pseudo_bytes()`

An beiden Stellen, die Token erzeugen. Die zweite meldet über einen Ausgabeparameter, ob das
Ergebnis kryptographisch stark ist — niemand hat ihn je gelesen. `random_bytes()` liefert starke
Bytes oder wirft.

### Nachweis

| Probe | Ergebnis |
|---|---|
| Volle Suite | `OK (314 tests, 846 assertions)`, 0 übersprungen (vorher 310) |
| PHPStan | `[OK] No errors` |
| Deprecations | 0 protokollierte Zeilen |
| Postausgang | 0 Byte |
| A-4 in `technical.md` | durchgestrichen |
| Bruchstellen | drei Einträge in `breaking-changes.md` |

Vier Charakterisierungstests umgedreht (zwei davon hielten den Klartext ausdrücklich fest),
vier neue dazu.
