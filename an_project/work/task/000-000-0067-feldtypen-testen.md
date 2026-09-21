---
id: 000-000-0067
title: Feldtypen testen, die Framework und Vorlage selbst nicht benutzen
status: review
depends_on: []
---

# Feldtypen testen, die Framework und Vorlage selbst nicht benutzen

## Context
**Gefunden bei der ersten Coverage-Messung (`000-000-0056`, 2026-09-18).** `Classes/Types` ist der
am schwächsten abgedeckte Bereich: **34 %, 541 Zeilen offen.** `MultifileType` 6 %, `CheckboxType`
4 %, `RadioType` 6 %, `OnejoinType` 7 %, `FileType` 11 %, `PermissionsType` 14 %.

**Die Ursache ist strukturell:** Keine Entity des Frameworks oder der Vorlage hat ein Feld dieser
Typen — also ruft kein Test ihr `toDatabase()` oder `fromDatabase()` auf. Projekte benutzen sie aber;
jeder Wert eines solchen Feldes läuft durch diesen Code, beim Schreiben wie beim Lesen, und darin
stecken auch Rechteprüfungen (`pim_blocked` für nicht lesbare Verknüpfungen).

## Acceptance criteria
- [x] Die Vorlage zeigt je Typ ein Beispielfeld — dieselbe Begründung wie bei `Core\ExampleI18n` (`000-000-0059`): Was die Vorlage nicht zeigt, testet niemand.
- [x] Je Typ ein Integrationstest: schreiben, lesen, und bei Verknüpfungen das Verhalten ohne Leserecht auf das Ziel.
- [x] Die Coverage von `Classes/Types` ist danach neu gemessen und im Task festgehalten — als Zahl, nicht als Ziel.

## Verification
Die neuen Tests über HTTP, und der Coverage-Lauf aus `0056`.

## Ergebnis (2026-09-21)
**Die Vorlage zeigt die fünf Beziehungstypen, und 19 Integrationstests schreiben und lesen sie über
HTTP.** Dabei sind vier Fehler aufgefallen, die bisher niemand bemerkt hatte, weil keine Entity
diese Typen benutzte.

### Umgesetzt
- **`custom/Entity/Core/ExampleRelations.php`** — je ein Feld für `file`, `multifile`, `checkbox`,
  `radio` und `onejoin`; der Kommentar über jedem Feld nennt die Kombination aus Mapping und
  Attribut, die den Typ auswählt. Begründung wie bei `ExampleI18n` (`0059`).
- **`permissions`** hat kein Beispielfeld in der Vorlage: Der Typ gehört zu `PIM\Group`, und nur
  dort ergibt er Sinn. Getestet wird er an `PIM\Group`.
- **`tests/Integration/Api/FieldTypeApiTest.php`** — 19 Tests: je Typ schreiben und lesen, bei
  Verknüpfungen das Verhalten ohne Leserecht auf das Ziel (`pim_blocked` bzw. `null`), bei `radio`
  zusätzlich `OWN` auf das Ziel.

### Vier Fehler, im selben Task behoben
| Aufruf | vorher | Ursache |
|---|---|---|
| `/api/list` auf eine Entity mit `OneToOne` | Feld **immer `null`** | `getList()` joint `onejoin` nicht; unter `HINT_FORCE_PARTIAL_LOAD` bleibt eine nicht gejointe Beziehung leer |
| `/api/list` mit `properties` auf `multifile` | je Datei `{}` | `MultifileType` serialisierte den Elterndatensatz statt der Datei, gefiltert nach dessen Feldliste |
| `permissions` mit `flatten`/`properties` | je Zeile die Id der **Gruppe** | `PermissionsType` benutzte `$object` statt `$objectToLoad` |
| `properties: ["permissions"]` auf `PIM\Group` | **500** | die Sammlung geriet in die Partial-Abfrage |

**Gegenprobe:** Ohne die drei Korrekturen in `lib/` werden genau die vier Tests rot, die die Fehler
beschreiben. Registereintrag in `breaking-changes.md`.

### Ein Wächter hat angeschlagen, wie vorgesehen
`ConstraintApiTest::testThereIsNoOnejoinPropertyForTheDeleteCascade` hielt fest, dass es keine
`onejoin`-Property gibt — mit dem Auftrag: „Taucht eine auf, gehört der Nachweis der Lösch-Kaskade
hierher.“ Mit `Core\ExampleRelations` kam eine. Der Wächter ist jetzt der Nachweis
(`testDeletingARecordAlsoDeletesItsOnejoinRecord`, gegen die Datenbank geprüft).

### Coverage, neu gemessen (Lauf aus `0056`)
**`Classes/Types`: 65,9 % (543 von 824 Zeilen)** — vorher 34 %.

| Typ | vorher | jetzt |
|---|---|---|
| `CheckboxType` | 4 % | 78,4 % |
| `RadioType` | 6 % | 82,4 % |
| `OnejoinType` | 7 % | 83,3 % |
| `FileType` | 11 % | 77,8 % |
| `PermissionsType` | 14 % | 88,1 % |
| `MultifileType` | 6 % | 50,0 % |

`MultifileType` bleibt bei der Hälfte: Der zweite Weg — Dateien über eine eigene, sortierbare
Zwischen-Entity (`OneToMany` plus `#[PIM\ManyToMany]`) — hat kein Beispiel. Gesamt: 66,1 % der Zeilen,
vorher 60,2 %.

Der Lauf brauchte `--order-by=default` (siehe `000-000-0071`); im Runbook vermerkt.

### Zwei Befunde mit eigenem Ticket
- **`000-000-0070`** — `PermissionsType::toDatabase()` gibt jeder Gruppe, deren Rechte geschrieben
  werden, ungefragt volle Rechte auf `PIM\Tag`. Als Charakterisierung festgehalten, nicht geändert.
- **`000-000-0071`** — Rector bringt eine eigene, nicht umbenannte Kopie von `nikic/php-parser` mit;
  je nach zufälliger Testreihenfolge bricht PHPUnit ab. Beim Coverage-Lauf aufgefallen.
