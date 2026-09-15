---
id: 000-000-0025
title: BaseI18nTree — die Beziehung passt nicht zum zusammengesetzten Schlüssel
status: done
depends_on: []
---

# BaseI18nTree — die Beziehung passt nicht zum zusammengesetzten Schlüssel

## Context
Übergeben aus Epic `010` (`010-005-0003`), mit Begründung statt stillschweigend.

`orm:validate-schema` meldet:

```
The join columns of the association 'treeParent' have to match to ALL identifier
columns of the target entity 'Areanet\PIM\Entity\BaseI18nTree', however 'id, lang' are missing.
```

`BaseI18nTree` hat einen **zusammengesetzten** Schlüssel — `id` aus `Entity\Base` und `lang` aus
`BaseI18n`. Die Beziehung `treeParent` trägt aber nur **eine** Join-Spalte (`parent_id`).
Doctrine verlangt eine je Schlüsselspalte.

**Warum das in Epic `010` nicht behoben wurde.** Der zweite Fehler derselben Meldung — die
falsche Zielklasse von `treeChilds` — war ein Kopierfehler und ist dort behoben. Dieser hier ist
keiner:

- **Niemand erbt von `BaseI18nTree`.** Gemessen über `lib/` und `custom/`. Die Absicht lässt
  sich an keinem Nutzer prüfen.
- **Die Frage ist fachlich, nicht technisch:** Soll ein Kindknoten auf einen Elternknoten
  **derselben Sprache** zeigen? Das ist die naheliegende Lesart, aber sie steht nirgends.
- **Die Antwort ändert das Schema.** Eine zweite Join-Spalte bedeutet eine neue Spalte in
  `pim_i18n_tree` — für ein Projekt, das die Klasse doch benutzt, eine Migration.

Semantik für unbenutzten Code zu erfinden und dabei ein Tabellenschema zu ändern, ist die
falsche Reihenfolge. Erst die Frage beantworten, dann bauen.

## Acceptance criteria
- [x] Es ist entschieden und begründet, ob ein Elternknoten dieselbe Sprache trägt — oder ob
      `BaseI18nTree` und `BaseI18nSortable` ersatzlos entfallen.
- [x] Bei „behalten": Die Beziehung trägt eine Join-Spalte je Schlüsselspalte, und
      `orm:validate-schema` meldet keinen Mapping-Fehler mehr.
- [x] ~~Bei „entfallen": Die Klassen und die Tabelle `pim_i18n_tree` sind weg, und der Wegfall
      steht als Bruchstelle in `an_project/docs/breaking-changes.md`.~~ Entfällt, gewählt ist „behalten".
- [x] Die Entscheidung steht in `an_project/docs/architecture.md` unter *Key decisions* — sie
      betrifft das Datenmodell, nicht nur eine Datei.
- [x] Die Suite bleibt grün, und ein Datenbankvergleich zeigt genau die beabsichtigte Änderung.

## Verification
`php bin/console.php orm:validate-schema` — der Mapping-Teil muss grün sein. Dazu eine frische
Installation und ein Vergleich der erzeugten Tabellen gegen den Stand davor.

## Ergebnis

**Entschieden: behalten, und ein Elternknoten hat dieselbe Sprache.** Die Entscheidung stammt vom
Auftraggeber (2026-09-15), auf Grundlage des Befunds unten. Begründung und verworfene Alternative
stehen in `an_project/docs/architecture.md`, *Key decisions*, 2026-09-15.

**Der Befund, der die Frage beantwortet hat:** Das Framework liest den i18n-Baum an drei Stellen
schon immer in einer Sprache. `JoinType::toDatabase()` referenziert i18n-Ziele über `id` und die
Sprache des geschriebenen Objekts, `Api::getTree()` verlangt `parent.lang = :lang`, und
`Api::getTree2()` verbindet über `t.lang = e.lang`. Die Entscheidung schreibt also die vorhandene
Semantik ins Mapping, statt eine zu erfinden.

**Umgesetzt:**

- `BaseI18nTree::$treeParent` trägt zwei Join-Spalten, `parent_id → id` und
  `parent_lang → lang`. `lang` selbst kann nicht doppelt dienen, es ist ein Identifier-Feld.
- **Ein Pfad wich ab und ist mitgezogen:** Beim Anlegen einer Übersetzung kopierte `Api` die
  universellen Felder vom Hauptsprachen-Objekt, also auch dessen Elternknoten **in der
  Hauptsprache**. Ein kopierter Join auf eine i18n-Entity wird jetzt an die geschriebene Sprache
  gebunden, wie `JoinType` es auf allen anderen Wegen tut. **Dieser Pfad ist nicht durch einen
  Test belegt:** Keine Entity im Baum erbt von `BaseI18nTree`, und für eine eigene Test-Entity
  hätte die Suite ein Schema ausserhalb der Installation gebraucht.
- Neuer Integrationstest `tests/Integration/Command/SchemaValidationTest.php`: fährt
  `orm:validate-schema` und verlangt beide Hälften grün. Gegenprobe mit der Entity von master: rot,
  mit genau der Meldung aus dem Context.

**Gemessen:**

- `orm:validate-schema`: `[OK] The mapping files are correct.` und
  `[OK] The database schema is in sync with the mapping files.`
- **Datenbankvergleich** einer frischen Installation gegen master: genau eine Tabelle,
  `pim_i18n_tree`. Spalte `parent_lang` neu, Index und Fremdschlüssel über
  `(parent_id, parent_lang)` statt über `parent_id`. Sonst byte-gleich.
- **Migrationsweg durchgespielt** an einer Datenbank im master-Schema mit Testzeilen (zwei
  Sprachen plus eine Waise in `fr`, deren Elternknoten es in `fr` nicht gibt).
  `schema-tool:update --dump-sql` erzeugt fünf Statements, alle an `pim_i18n_tree`. Die
  Waisen-Abfrage findet genau die `fr`-Zeile. `parent_lang = lang` lässt sich für alle anderen
  setzen, die Waise lehnt der Fremdschlüssel ab. Danach ist `orm:validate-schema` grün. Der Weg
  steht in `breaking-changes.md`.
- Volle Suite `Tests: 529, Assertions: 1700, Skipped: 3`, PHPStan `[OK] No errors`,
  Deprecation-Gate 0. Der Kopf von `migration.md` ist auf 101 Einträge gezogen.

**Was die Entscheidung kostet:** Eine Übersetzung kann nur noch unter einen Elternknoten, dessen
Übersetzung existiert; der Fremdschlüssel erzwingt, was die Lesewege schon voraussetzten.
