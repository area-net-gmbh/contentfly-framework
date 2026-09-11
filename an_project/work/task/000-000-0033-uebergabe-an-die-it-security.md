---
id: 000-000-0033
title: Übergabe an die IT-Security — Stand markieren und Übergabenotiz
status: done
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
- [x] Ein annotierter Tag markiert den übergebenen Stand; sein Name sagt, dass es ein Vor-Release-Stand ist.
- [x] Die Notiz nennt den geprüften Umfang und ausdrücklich, was **nicht** dazugehört.
- [x] Die vier offenen Punkte stehen darin, `A-5` mit seinen drei Teilen.
- [x] Es steht darin, wie man den Stand zum Laufen bringt und die Suite fährt — als Verweis, nicht als zweite Anleitung.
- [x] Es steht darin, wie ein Befund zurückkommt, damit er nicht in einer Mail versandet.
- [x] Der Tag wird **nicht** gepusht; das Veröffentlichen entscheidet der Auftraggeber.

## Verification
Ein Leser der Notiz, der das Projekt nicht kennt, kann sagen: was er vor sich hat, was er prüfen
soll, was schon bekannt ist, und wie er einen Fund meldet.

## Ergebnis

**`an_project/docs/uebergabe-security.md`, sieben Abschnitte**, in der Reihenfolge, in der ein
Prüfer sie braucht: was er vor sich hat · was behoben ist · was bekannt offen ist · was noch
nicht endgültig ist · wie er es zum Laufen bringt · wie ein Fund zurückkommt · und ein Vorschlag.

**Der Tag heisst `v2.0.0-pre-security-2026-09-11`** und sagt damit selbst, dass er kein Release
ist. Er ist annotiert und trägt den Stand der Gates. **Nicht gepusht** — das Veröffentlichen ist
eine Entscheidung des Auftraggebers, nicht ein Nebeneffekt dieses Tasks.

## Was die Notiz ausdrücklich sagt, obwohl es unangenehm ist

**Die offene Liste steht vor der Anleitung, nicht hinter ihr.** `A-5` ist mit allen drei Teilen
beschrieben — der Schlüssel kommt vom Aufrufer, er läuft nie ab, und beim Vorzeigen bremst
nichts. Dazu, was den Befund begrenzt: Adminrecht davor, nur ein Hash in der Tabelle, und
`generateToken` bietet den richtigen Wert längst an. **Es bietet ihn an, aber es verlangt ihn
nicht** — das ist der Satz, auf den es ankommt.

**Ohne diese Liste sähe die Übergabe aus wie Verschweigen.** Ein Prüfer, der `A-5` findet und
danach erfährt, dass wir ihn kannten, glaubt auch beim nächsten Befund nicht mehr, dass er neu
ist.

**Und es steht darin, was nicht endgültig ist:** Epic `011` vereinheitlicht das Antwortformat,
und ein Fund am Envelope kann bis dahin gegenstandslos werden. Das zu verschweigen hiesse,
Prüfzeit für etwas auszugeben, das wir ohnehin ändern.

## Was nicht hineingehört und deshalb verwiesen ist

Anleitung zum Aufsetzen, Testlauf, Gates — drei Verweise statt drei Abschriften. **Dieselbe
Bauregel wie im Migrationsleitfaden:** Zwei Beschreibungen desselben Laufs laufen auseinander.

## Der Vorschlag am Ende

**Das gestoppte Projekt ist genau das, worauf `007-005` wartet.** Die letzte offene Story des
Migrations-Epics braucht ein reales Bestandsprojekt; nötig wäre ein Abzug — `Entity/`,
Konfiguration, Datenbank —, kein Zugriff auf den Produktivstand. Wenn die Sicherheitsprüfung
ohnehin mit dieser Codebase arbeitet, liesse sich beides aus demselben Anlass erledigen.

**Zahlen, die in der Notiz stehen und gemessen sind:** 152 Dateien Frameworkcode, 18
Laufzeit-Abhängigkeiten, 59 Testdateien, 524 Tests, 380 Commits. Gates grün: PHPStan
`[OK] No errors`, `composer audit --locked` ohne Advisories, 0 Deprecations bei 0 Ausnahmen.
