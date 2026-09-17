---
id: 000-000-0057
title: Die Rechtematrix prüfen — Stufe, Operation und Eigentümerschaft
status: todo
depends_on: []
---

# Die Rechtematrix prüfen — Stufe, Operation und Eigentümerschaft

## Context
**Contentfly kennt keine Rollen. Der erste Zuschnitt dieses Tasks war deshalb falsch** („jede
Rolle gegen jeden Endpunkt") und ist nach einem Einwand des Auftraggebers korrigiert worden.

**Was es wirklich gibt** (erhoben am 2026-09-17):

- ein `isAdmin`-Flag am `User` — `Permission::is()` gibt für Admins **sofort `2`** zurück,
- eine `Group` je Benutzer; ohne Gruppe ist das Ergebnis **`0`**,
- pro Gruppe eine `Permission`-Zeile **je Entity**, mit drei Operationen (`readable`, `writable`,
  `deletable`),
- jede auf einer von vier Stufen: `NONE 0` · `OWN 1` · `ALL 2` · `GROUP 3`.

Die zu prüfende Achse ist damit **Stufe × Operation × Eigentümerschaft des Datensatzes**, nicht
Rolle × Endpunkt. Das sind 4 × 3 × 3 = 36 Fälle, und sie sind für **jede** Entity identisch —
**eine Test-Entity genügt**, plus Admin-Bypass und „Benutzer ohne Gruppe".

## Der konkrete Verdacht, dem dieser Task nachgeht
Die Stufe wird an **23 Stellen** geprüft. An **sechs** davon wird nur gegen `0` geprüft und
danach **nicht** auf `OWN`/`GROUP` verengt:

| Stelle | Einschätzung |
|---|---|
| `Api.php:252` (`doInsert`) | **vermutlich korrekt** — ein neuer Datensatz hat noch keinen Eigentümer, es gibt nichts zu verengen |
| `Api.php:1960` (`getTranslations`) | **Verdacht** — liest bestehende Daten. Ein Benutzer mit `readable = OWN` bekäme Übersetzungen fremder Datensätze |
| `MultijoinType.php:185`, `:225` | ungeprüft |
| `FileController.php:46`, `:476` | ungeprüft |

**Das sind Hypothesen, keine Befunde.** Der Task beantwortet sie — jede einzeln, mit einem Test,
der rot wird, wenn der Verdacht stimmt.

## Eine Falle, die beim Lesen auffällt und benannt gehört
Die Konstanten sind **nicht aufsteigend**: `NONE 0`, `OWN 1`, `ALL 2`, `GROUP 3`. Der Code
vergleicht heute überall mit `==`, deshalb schadet es nicht. **Ein einziges `>=` würde es
schlagartig tun** — `GROUP` wäre dann die höchste Stufe statt einer engeren. Genau dieser Fehler
ist schon einmal passiert: `canExport()` las `== 2` und lieferte ausgerechnet für `GROUP`
`false` (dokumentiert in `Classes/Permission.php`). Ein Test, der `GROUP` explizit abdeckt, hält
das fest.

## Acceptance criteria
- [ ] Ein Test deckt alle vier Stufen × drei Operationen ab, jeweils gegen einen **eigenen**, einen **gruppenfremden** und einen **fremden** Datensatz.
- [ ] Admin-Bypass und „Benutzer ohne Gruppe" sind eigene Fälle.
- [ ] `GROUP` ist ausdrücklich abgedeckt — die Stufe, die die nicht-aufsteigenden Konstanten als erste zerlegen würde.
- [ ] Jede der sechs Stellen ohne Verengung hat ein Ergebnis: **korrekt so** (mit Begründung im Test) oder **Ticket**.
- [ ] Die Tests laufen in der `integration`-Suite gegen echte HTTP-Aufrufe, nicht gegen `Permission::is()` direkt — geprüft wird, was ein Client bekommt, nicht was eine Methode zurückgibt.

## Verification
Gegenprobe: Eine Verengung im Code versuchsweise entfernen (`== OWN` durch `true` ersetzen) — der
zugehörige Test muss rot werden und den Fall benennen. Wird er es nicht, prüft er nichts.
