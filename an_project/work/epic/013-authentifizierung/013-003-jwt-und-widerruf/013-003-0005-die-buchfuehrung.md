---
id: 013-003-0005
title: Die Buchführung
status: review
depends_on: [013-003-0001, 013-003-0002, 013-003-0003, 013-003-0004]
---

# Die Buchführung

## Context
Was diese Story an Betriebswissen erzeugt, ist wertlos, solange es nur im Code steht: welche
Claims ein Token trägt und welche bewusst nicht, welche Umgebungsvariablen es braucht, wie ein
Schlüsselwechsel abläuft, und was ein Bestandsprojekt beim Update zu tun hat.

## Acceptance criteria
- [x] Der Claim-Satz ist dokumentiert: was drinsteht, was bewusst **nicht**, und warum.
- [x] Der Betrieb steht im Runbook beziehungsweise in `deployment.md`: welche Umgebungsvariablen nötig sind, welche Werte sie brauchen, und wie ein Schlüsselwechsel Schritt für Schritt abläuft.
- [x] Die Bruchstellen der Story stehen in `an_project/docs/breaking-changes.md`, je mit dem, was ein Projekt zu tun hat.
- [x] Der Authentifizierungs-Abschnitt in `an_project/docs/technical.md` ist nachgezogen.
- [x] Die Gates sind grün: volle Suite auf PHP 8.3 **und** 8.4, PHPStan, `composer audit --locked`, Deprecation-Log.

## Verification
Volle Suite auf beiden PHP-Versionen, PHPStan, `composer audit --locked`. Die Doku wird gegen
den Code gelesen, nicht aus dem Gedächtnis geschrieben — jede genannte Umgebungsvariable und
jeder genannte Claim muss sich im Baum wiederfinden.

## Ergebnis

**Das Betriebswissen dieser Story steht jetzt ausserhalb des Codes.**

### Was wo steht

| Frage | Antwort in |
|---|---|
| Welche Umgebungsvariablen braucht JWT? | `deployment.md`, Abschnitt *JWT* — eine Tabelle mit vier Variablen und `SECURITY_JWT_TTL` |
| Was steht im Token, was bewusst nicht? | ebenda, mit dem Verweis auf `Zugangstoken::CLAIMS` |
| Wie wechselt man einen Schlüssel? | ebenda, fünf Schritte in der Reihenfolge, in der ein Betreiber sie herstellt |
| Wie wirkt ein Widerruf, und was räumt auf? | ebenda |
| Was ändert sich für ein Bestandsprojekt? | `breaking-changes.md`, sechs Einträge |
| Wie ist das Ganze gebaut? | `technical.md`, Abschnitt *Seit `013-003`* |

### Gegen den Code gelesen, nicht aus dem Gedächtnis

Die Verification verlangte das ausdrücklich. Nachgezählt: Alle fünf genannten
`SECURITY_JWT_*`-Variablen kommen im Baum vor, `CLAIMS` trägt genau die fünf dokumentierten
Namen, der Ausgeber heisst `contentfly`, und `pim_revoked_token` ist der Tabellenname aus der
Entity.

Ein Satz, der beim Schreiben dazukam und im Code nicht steht, weil er dort nicht hingehört: Ein
JWT ist **nicht** vertraulich in dem Sinn, dass sein Inhalt geheim wäre — die Nutzlast ist
base64, nicht verschlüsselt. Geschützt ist die Unveränderbarkeit. Wer das nicht weiss, packt
irgendwann etwas hinein, das niemand lesen soll.

### Der Betrieb ist eine Reihenfolge, keine Liste

Beim Schlüsselwechsel steht deshalb, **wann** der alte Schlüssel weg darf: erst nach
`SECURITY_JWT_TTL`, weil dann das längste noch mit ihm ausgestellte Access-JWT abgelaufen ist.
Ohne diesen Satz macht jemand Schritt 5 direkt nach Schritt 2 und meldet alle ab — genau das,
was der ganze Mechanismus verhindern soll.

### Nachweis

| Probe | PHP 8.3 | PHP 8.4 |
|---|---|---|
| Volle Suite | `OK (404 tests, 1004 assertions)` | `OK (404 tests, 1004 assertions)` |
| Deprecations | 0 | 0 |
| Postausgang | 0 Byte | 0 Byte |

Dazu PHPStan `[OK] No errors` und `composer audit --locked` ohne Advisories.

**Der 8.4-Lauf musste zweimal angesetzt werden** — Docker Desktop war zwischendurch ausgestiegen
(`unexpected EOF`, dann kein Socket mehr). Das ist eine Eigenschaft der Werkbank und keine des
Codes; nach einem Neustart lief er durch.
