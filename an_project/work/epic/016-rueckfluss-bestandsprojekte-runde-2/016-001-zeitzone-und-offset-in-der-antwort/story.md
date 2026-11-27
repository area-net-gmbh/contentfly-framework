---
id: 016-001-0000
title: Ein Zeitpunkt verlässt das Framework mit seinem Offset — und wird nicht als Lokalzeit des Betrachters gelesen
status: todo
depends_on: []
---

# Ein Zeitpunkt verlässt das Framework mit seinem Offset — und wird nicht als Lokalzeit des Betrachters gelesen

## Goal

Jeder Zeitpunkt, den die API ausgibt, trägt seinen Offset — in der Antwort-Hülle und in jedem
`datetime`-Feld. Erledigt sind damit `#106` und `#105`.

**Der Mangel ist in beiden Issues derselbe.** Ein String wie `2026-07-29 12:00:00` ohne `T` und
ohne Offset wird von jeder JavaScript-Engine als *lokale* Zeit des Betrachters gelesen. Wer in
einer anderen Zone sitzt als der Server, sieht einen falschen Zeitpunkt — und nichts im Wert
deutet darauf hin. `\DateTime::ISO8601` ist dieselbe Falle eine Stufe subtiler: die Konstante
erzeugt `+0200` ohne Doppelpunkt, was die JavaScript-Spezifikation nicht verlangt zu lesen.

Gegengeprüft am 27.11.2026 auf `master`:

| Stelle | Heute |
|---|---|
| `Envelope`, `meta.ts` | `$now->format('Y-m-d H:i:s')` — ohne `T`, ohne Offset |
| `DatetimeType::fromDatabase()`, Schlüssel `ISO8601` | `format(\DateTime::ISO8601)` — Offset ohne Doppelpunkt |
| `DatetimeType::fromDatabase()`, Zweig für ungültige Datumswerte | Schlüssel heisst `IMESTAMP` statt `TIMESTAMP`. **Nicht der Null-Zweig**, wie `#105` schreibt: ein NULL steigt vier Zeilen vorher als blankes `null` aus. Der Zweig greift bei Jahr `0000`/`-0001`. |
| `DatetimeType::toDatabase()` | `new \DateTime($value)` direkt in die Spalte — Doctrines `datetime` schreibt die Wanduhrzeit und verwirft die Zone |

## Der Schnitt: zwei Fehlerkorrekturen, eine Entscheidung

**Drei der vier Zeilen sind folgenlos zu beheben.** Der Tippfehler `IMESTAMP` und
`ISO8601` → `DATE_ATOM` sind Fehlerkorrekturen: ein Client, der heute `TIMESTAMP` bei einem
Null-Wert sucht, findet den Schlüssel nicht, und ein Client, der `ISO8601` parst, bekommt ein
Format, das die Spezifikation nicht deckt. `meta.ts` auf `DATE_ATOM` ist derselbe Griff an der
Stelle, die **jede** Antwort betrifft; `#106` sagt selbst, dass das noch niemanden trifft — es ist
gemeldet, bevor es trifft.

**Die vierte Zeile ist eine Entscheidung, und sie trägt das Epic.** `toDatabase()` übernimmt heute
den Offset des Clients: schickt ein Client `2026-07-29T12:00:00+02:00`, landen `12:00:00` in der
Spalte — zwei Stunden daneben, ohne Hinweis. Das zu normalisieren ist richtig und **ändert
Verhalten für jedes Bestandsprojekt**, das heute Lokalzeit schickt und Lokalzeit zurückliest. Das
Issue schlägt deshalb selbst einen Schalter vor.

Zu entscheiden und als ADR festzuhalten, bevor die Normalisierung geschrieben wird:

- Normalisiert das Framework beim Schreiben auf UTC — immer, hinter einem Schalter, oder nicht?
- Falls Schalter: welche Vorgabe trägt er? Eine Vorgabe, die das heutige Verhalten dreht, ist ein
  Breaking Change und gehört nach `breaking-changes.md` und in `migration.md`.
- Ein Zeitpunkt in der Spalte ist ohne Zone gespeichert. Normalisieren beim Schreiben, ohne beim
  Lesen zu wissen, *welche* Zone die Altdaten tragen, verschiebt Bestandsdaten gegen neue Daten —
  dieser Punkt muss beantwortet sein, nicht nur benannt.

**Die zwei Fehlerkorrekturen hängen nicht an dieser Entscheidung** und sollten nicht auf sie
warten.

## Was diese Story nicht umfasst

`#104` nennt die Zeitzone als Anlass (`SET time_zone = '+00:00'` als INIT-Command, damit MySQLs
`NOW()` und ein von PHP geschriebener Wert dieselbe Uhr haben). Das ist derselbe Faden, aber der
Mechanismus ist ein Config-Key für `driverOptions` — und der gehört ganz zu `016-002`, damit `#104`
genau eine zuständige Story hat. Fällt hier die UTC-Entscheidung, ist sie dort der Kontext, nicht
die Voraussetzung.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
- [ ] 016-001-0001 — Der Schlüssel heisst TIMESTAMP, auch im Zweig für ungültige Datumswerte
- [ ] 016-001-0002 — Der ISO8601-Wert trägt den Offset mit Doppelpunkt, damit JavaScript ihn lesen muss
