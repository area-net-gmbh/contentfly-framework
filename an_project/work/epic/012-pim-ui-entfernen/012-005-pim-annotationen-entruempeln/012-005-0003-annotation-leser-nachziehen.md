---
id: 012-005-0003
title: Leser der Annotationen nachziehen
status: review
depends_on: [012-005-0002]
---

# Leser der Annotationen nachziehen

## Context
`Type.php`, `Api.php`, `ApiController` und die Typ-Klassen lesen die Annotationen aus und bauen daraus das Schema. Was auf gelöschte Felder zugreift, bricht sonst zur Laufzeit.

## Acceptance criteria
- [x] `Classes/Type.php`, `Classes/Api.php`, `Controller/ApiController.php` und `Classes/Types/*` greifen auf kein entferntes Feld mehr zu.
- [x] Das Schema wird ohne Warnungen oder Notices erzeugt.
- [x] `excludeFromSync` (`Api.php`), `encoded` (`StringType`, `TextareaType`) und `isFilterable` (`Type.php`) funktionieren unverändert.

## Was nachgezogen wurde

| Stelle | Änderung |
|---|---|
| `Classes/Type.php` | Schema-Defaults auf die datenrelevanten Schlüssel reduziert; die Config-Auswertung liest nur noch `i18n_universal`, `encoded`, `unique`, `isFilterable`. Die Tab-Mechanik (`addTab()`, `getTab()`, `$tab`) ist entfallen, ebenso das `readonly` für Doctrine-`@Id`. |
| `Classes/Api.php` | `settings`-Defaults und die Klassen-Annotation auf `labelProperty`, `sortBy`, `sortOrder`, `sortRestrictTo`, `excludeFromSync` reduziert; Tab-Einsammlung, Listenaufbau und der Schema-Schlüssel `list` entfernt. |
| `Classes/Api.php` — `getExtendedSchema()` | Der Titel der customNavigation fiel auf `settings['label']` zurück; jetzt auf den Entity-Namen. |
| `Classes/Types/OnejoinType.php` | `$schema['tab']` und der `addTab()`-Aufruf entfernt. |
| `Classes/Types/FileType.php`, `MultifileType.php` | lasen `Config->accept`. Keine Entity hat es je gesetzt; der Schema-Schlüssel `accept` behält seinen Standard `'*'`. |
| `Entity/Serializable.php` | siehe unten. |
| `Controller/ApiController.php` | greift auf kein entferntes Feld zu — nichts zu tun. |

## Zwei Leser, die kein UI waren

`showInList` speiste `schema[…]['list']`, und daran hingen **zwei Datenpfade**:

1. **`Api::getTree2()`** — die Route `POST /api/tree2` baute ihre SQL-Spaltenliste daraus.
   Ohne Ersatz wäre `SELECT t.id, , t.sorting …` entstanden.
2. **`Serializable::toValueObject()`** — verschachtelte Objekte (`$level > 0`) wurden auf die
   Listenspalten beschränkt.

Entschieden am 2026-09-07: **beide liefern jetzt alle Eigenschaften.** Für Clients ist das
additiv — kein Feld verschwindet, es kommen welche hinzu. Nachgewiesen (siehe Verification).
Die Verschachtelungstiefe begrenzt weiterhin `DB_NESTED_LEVELS`.

Dabei ist ein **latenter Fehler** aufgefallen und behoben: `getTree2()` hat Spaltennamen nie
gequotet. Solange die Auswahl aus `showInList` kam, fiel das nicht auf; mit allen Feldern ist
`groups` dabei — in MySQL 8 ein reserviertes Wort. Die Spalten werden jetzt in Backticks gesetzt.

## Verification
Schema-Erzeugung und einen Sync-Abruf durchspielen; Ergebnis mit dem Stand vor dem Umbau vergleichen — die datenrelevanten Felder sind identisch.

Gegen einen zweiten Worktree auf `master` verglichen, beide gegen dieselbe Datenbank:

- [x] **Schema (`GET /api/schema`), 13 Entities auf beiden Seiten, gleiche Menge.** Entfallene
      Property-Schlüssel: `filter`, `format`, `hide`, `isSidebar`, `label`, `lines`,
      `listShorten`, `readonly`, `showInList`, `tab`. Entfallene Settings-Schlüssel: `hide`,
      `label`, `readonly`, `sort`, `tabs`, `viewMode`. Dazu der Top-Level-Schlüssel `list`.
      **Kein einziger neuer Schlüssel, keine verlorene Entity, keine verlorene Eigenschaft.**
- [x] `format` verschwindet ausschließlich bei `type: datetime` — dort hat es nie ein Leser
      benutzt. Bei `type: time` bleibt es, weil `TimeType::fromDatabase()` damit formatiert.
- [x] Nur zwei Wertänderungen im gesamten Schema, beide aus Task `012-005-0001`:
      `NavItem.entity.type` und `User.pass.type`, je von einem eigenen Alias auf `string`.
- [x] `encoded` und `isFilterable` in allen Entities wertgleich; `excludeFromSync`,
      `labelProperty`, `sortBy`, `sortOrder`, `sortRestrictTo`, `type` in allen `settings`
      wertgleich.
- [x] `POST /api/tree2` liefert HTTP 200; alle Felder, die `master` lieferte
      (`id`, `title`, `userCreated`, `parent`, `sorting`, `childs`), sind enthalten.
- [x] `POST /api/list` — verschachteltes `userCreated`: `master` lieferte
      `alias, id, isActive, isAdmin`, der Branch dieselben plus `isIntern, loginManager`.
      Die Top-Level-Antwort hat auf beiden Seiten 13 Felder.
- [x] Der Passwort-Hash wird auf **beiden** Seiten nicht ausgeliefert — `pass` fehlt in der
      User-Antwort hier wie dort. Das Entfernen von `@PIM\Password` hat daran nichts geändert.
- [x] Testsuite grün: 26 Tests, 45 Assertions.
