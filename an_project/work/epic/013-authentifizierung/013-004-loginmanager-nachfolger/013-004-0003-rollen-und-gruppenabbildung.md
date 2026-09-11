---
id: 013-004-0003
title: Rollen- und Gruppenabbildung
status: done
depends_on: [013-004-0001]
---

# Rollen- und Gruppenabbildung

## Context
Heute nimmt `createManagedUser($alias, $group, $isAdmin)` Gruppe und Adminflag als Argumente
entgegen — das heisst, jedes Projekt entscheidet fuer sich, wie es von „das Fremdsystem sagt,
der Benutzer ist in der Gruppe *Redaktion*" zu einer Contentfly-Gruppe kommt. Was dabei
herauskommt, steht in Projektcode, den niemand mehr liest.

**Die Abbildung gehoert an eine Stelle und in die Konfiguration**, nicht in jede Anmeldung neu.
Ein Provider liefert, was das Fremdsystem sagt — Gruppennamen, Attribute —, und die Abbildung
entscheidet daraus.

**Was NICHT dazugehoert:** Das Contentfly-Berechtigungsmodell umzubauen. `Permission`,
`I18nPermission` und `Group` bleiben, wie sie sind; abgebildet wird auf sie, nicht an ihrer
Stelle. Dieselbe Grenze wie in `013-002-0001`.

## Acceptance criteria
- [x] Was ein Provider an Gruppen oder Attributen liefert, wird an einer Stelle auf Contentfly-Gruppen und das Adminflag abgebildet.
- [x] Die Abbildung ist konfigurierbar, ohne Framework-Code zu aendern.
- [x] Liefert das Fremdsystem nichts Passendes, bekommt der Benutzer die Vorgabe — und **nicht** Adminrechte. Ein Test haelt das fest.
- [x] Aendert sich die Zuordnung im Fremdsystem, wirkt sie bei der naechsten Anmeldung; ein Test meldet denselben Benutzer zweimal mit verschiedenen Gruppen an.
- [x] `Permission`, `I18nPermission` und `Group` sind unveraendert.

## Verification
Unit-Tests der Abbildung fuer Treffer, Nichttreffer und Mehrfachtreffer. Ein Integrationstest,
der einen Benutzer zweimal mit verschiedener Zuordnung anmeldet und die Gruppe in `pim_user`
nachsieht. Volle Suite.

## Ergebnis

**Die Abbildung steht an einer Stelle und in der Konfiguration** — `SECURITY_PROVIDER_GRUPPEN`,
je Anbietername ein Eintrag mit drei Schlüsseln.

| Schlüssel | tut |
|---|---|
| `gruppen` | Fremdgruppe → Name einer Contentfly-Gruppe; der **erste** Treffer in dieser Reihenfolge gewinnt |
| `admin` | Liste von Fremdgruppen, die das Adminflag setzen |
| `vorgabe` | Gruppe, wenn nichts passt; fehlt sie, bleibt der Benutzer ohne Gruppe |

### Die Reihenfolge ist eine Entscheidung

Ein Benutzer kann in mehreren Fremdgruppen sein; Contentfly kennt genau **eine** Gruppe je
Benutzer. Welche gewinnt, steht damit in der Konfiguration und nicht in der Laune einer
Hashtabelle. Ein Test schickt die Fremdgruppen in umgekehrter Reihenfolge und erwartet trotzdem
den ersten Konfigurationseintrag.

### Im Zweifel keine Rechte

Ohne passenden Eintrag gibt es keine Gruppe und **kein Adminflag**. Eine Abbildung, die im
Zweifel Rechte vergibt, ist die falsche Richtung: Das Fremdsystem soll Rechte begründen, nicht
ihr Fehlen.

Drei Fälle, die das im Einzelnen ausbuchstabieren:

- **Das Adminflag wird immer gesetzt, auch auf `false`.** Nur zu setzen, wenn ein Treffer
  vorliegt, hiesse: Einmal Administrator, immer Administrator.
- **Ohne Treffer und ohne Vorgabe wird die Gruppe abgeräumt**, nicht stehengelassen. Sonst
  behielte jemand die Rechte einer Gruppe, aus der ihn das Fremdsystem entfernt hat.
- **Ohne Eintrag für den Anbieter passiert gar nichts** — auch kein Abräumen. Wer keine
  Abbildung konfiguriert, verwaltet die Gruppen von Hand, und dann darf eine Anmeldung sie nicht
  wegnehmen.

### Sie wirkt bei jeder Anmeldung

Nicht nur beim Anlegen. Wer im Fremdsystem aus einer Gruppe fällt, fällt beim nächsten Login
auch hier heraus — **und genau deshalb stehen Rollen und Gruppen nicht im JWT** (`013-003-0001`).
Dort wären sie bis zum Ablauf des Tokens eingefroren. Die beiden Entscheidungen greifen
ineinander, und der Test meldet denselben Benutzer zweimal mit verschiedener Zuordnung an.

### Eine Fehlkonfiguration schlägt laut durch

Eine Abbildung auf eine Gruppe, die es in `pim_group` nicht gibt, bricht die Anmeldung ab und
nennt die Gruppe. Sie stillschweigend zu ignorieren hiesse: Der Benutzer kommt herein und hat
andere Rechte als gedacht — und niemand erfährt, warum. Dieselbe Linie wie beim
Schlüsselwechsel in `013-003-0004`.

### Die Grenze zum Berechtigungsmodell

`Permission`, `I18nPermission` und `Group` sind unverändert. Abgebildet wird **auf** sie, nicht
an ihrer Stelle — dieselbe Grenze wie in `013-002-0001`.

### Nachweis

| Probe | Ergebnis |
|---|---|
| Volle Suite | `OK (434 tests, 1079 assertions)`, 0 übersprungen (vorher 425) |
| PHPStan | `[OK] No errors` |
| Deprecations | 0 protokollierte Zeilen |

Neun Tests: drei für Treffer, vier für Nichttreffer und Abräumen, einer für die
Fehlkonfiguration, einer für die geänderte Zuordnung über zwei Anmeldungen.

Der Integrationstest, der die Gruppe nach zwei Anmeldungen in `pim_user` nachsieht, braucht einen
lauffähigen Provider und kommt mit `013-004-0004` — derselbe Grund wie in den beiden Tasks davor.
