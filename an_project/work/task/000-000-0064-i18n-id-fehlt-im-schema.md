---
id: 000-000-0064
title: Übersetzungen lassen sich nicht anlegen — id fehlt im Schema jeder i18n-Entity
status: todo
depends_on: []
---

# Übersetzungen lassen sich nicht anlegen — id fehlt im Schema jeder i18n-Entity

## Context
**Gefunden bei `000-000-0059`, am 2026-09-18, mit der ersten i18n-Entity, die es in diesem Baum
je gab** (`custom/Entity/Core/ExampleI18n`). Eine Übersetzung entsteht über `/api/insert` mit der
`id` des bestehenden Datensatzes und einer anderen `lang`. Das scheitert **immer**:

```
contentfly_general_unknown_property — Core\ExampleI18n::id
```

**Ursache, gelesen, nicht vermutet:** `BaseI18n` deklariert `$id` neu — mit `#[ORM\Id]` und
`#[ORM\GeneratedValue(strategy: 'NONE')]`, aber **ohne** `#[ORM\Column]`. Die Spalte kommt aus
`Base`; `010-003-0002` hat sie hier entfernt, weil ORM 3 die doppelte Definition ablehnt. Der
Schema-Aufbau in `Api::getSchema()` liest aber nur die Attribute der **Deklaration selbst**: Er
findet keinen `Column`, kein Typ passt, und `id` fällt aus `properties`. `doInsert()` weist jedes
Feld ab, das dort fehlt.

**Also vermutlich eine Regression seit `010-003-0002`.** Bis dahin trug die Neudeklaration den
`Column` mit. Kein Test hat es gemerkt, weil es keine i18n-Entity gab.

**Nebenbefund, derselbe Pfad:** Ohne konfiguriertes `APP_LANGUAGES` wirft `doInsert()` für eine
i18n-Entity die Warnung `Undefined array key 0` (`Config::$APP_LANGUAGES` ist standardmässig
`array()`, `is_array()` ist also wahr, `[0]` fehlt). In Produktion mit `display_errors=On` landet
sie im Antwortstrom.

## Acceptance criteria
- [ ] `id` steht im Schema jeder `BaseI18n`-Entity, mit demselben Typ wie bei `Base` — ohne die doppelte `Column`, die ORM 3 ablehnt.
- [ ] Eine Übersetzung lässt sich per `/api/insert` anlegen (Integrationstest auf `Core\ExampleI18n`).
- [ ] Die erlaubten Fälle stehen im Provider von `PermissionMatrixApiTest::testInsertingATranslationOfARecordOutOfReachIsRejected()` (dann umbenannt), neben den abgelehnten aus `0059`.
- [ ] Ohne `APP_LANGUAGES` entsteht keine Warnung; das Verhalten ist entschieden und begründet (Fehlermeldung oder Rückfall).
- [ ] Geprüft, ob `BaseI18nSortable` und `BaseI18nTree` denselben Mangel haben.

## Verification
Der Integrationstest aus Kriterium 2: vor der Änderung `unknown_property`, danach 200 und die
Zeile in der Datenbank.
