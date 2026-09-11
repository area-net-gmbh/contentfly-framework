---
id: 000-000-0033
title: Übergabe an die IT-Security — Stand markieren und Übergabenotiz
status: todo
depends_on: []
---

# Übergabe an die IT-Security — Stand markieren und Übergabenotiz

## Context
**Ein Bestandsprojekt ist wegen Sicherheitsbefunden im alten Contentfly gestoppt.** Die
IT-Security soll den neuen Stand parallel prüfen, während hier weitergearbeitet wird.

**Der Stand trägt das.** Alle Gates sind grün — 524 Tests, PHPStan `[OK] No errors`,
`composer audit --locked` ohne Advisories, 0 Deprecations —, und fünf der sechs Befunde aus dem
Review vom 2026-09-04 sind behoben.

**Zwei Dinge fehlen für eine saubere Übergabe:**

1. **Ein benannter Stand.** Es gibt keinen einzigen Tag im Repo. Ohne ihn reden beide Seiten
   über „den aktuellen Stand" und meinen nach der nächsten Story etwas anderes.
2. **Eine Notiz, die sagt, was geprüft wird — und was bekannt offen ist.** Ohne die offene
   Liste findet die Security etwas, das wir kennen, und die Übergabe sieht aus wie Verschweigen.

## Was bekannt offen ist und in die Notiz gehört

| | |
|---|---|
| `A-5` (Task `000-000-0030`) | `referrer`-Tokens laufen nie ab, der Token-String kommt beim Anlegen vom Client, und beim Vorzeigen bremst nichts. Die Route verlangt Adminrecht — es ist ein ratbarer Dauerschlüssel, keine offene Tür. |
| Task `000-000-0024` | Ein Fehler im Bootstrap antwortet mit HTTP 500 und **null Byte** Rumpf; die Meldung steht nur im Log. |
| Task `000-000-0025` | Datenmodell (`BaseI18nTree`), nicht sicherheitsrelevant. |
| Task `000-000-0031` | Eine gedeckelte Abhängigkeit, nicht sicherheitsrelevant. |

**Und was noch nicht endgültig ist:** Epic `011` vergibt die Releaseversion und vereinheitlicht
das Antwortformat der API. Was die Security am Envelope findet, kann bis dahin gegenstandslos
werden. Alles andere — Authentifizierung, Token, Rechte, Dateiauslieferung — ist stabil.

## Acceptance criteria
- [ ] Ein annotierter Tag markiert den übergebenen Stand; sein Name sagt, dass es ein Vor-Release-Stand ist.
- [ ] Die Notiz nennt den geprüften Umfang und ausdrücklich, was **nicht** dazugehört.
- [ ] Die vier offenen Punkte stehen darin, `A-5` mit seinen drei Teilen.
- [ ] Es steht darin, wie man den Stand zum Laufen bringt und die Suite fährt — als Verweis, nicht als zweite Anleitung.
- [ ] Es steht darin, wie ein Befund zurückkommt, damit er nicht in einer Mail versandet.
- [ ] Der Tag wird **nicht** gepusht; das Veröffentlichen entscheidet der Auftraggeber.

## Verification
Ein Leser der Notiz, der das Projekt nicht kennt, kann sagen: was er vor sich hat, was er prüfen
soll, was schon bekannt ist, und wie er einen Fund meldet.
