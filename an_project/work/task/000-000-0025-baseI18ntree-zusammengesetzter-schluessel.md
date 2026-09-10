---
id: 000-000-0025
title: BaseI18nTree — die Beziehung passt nicht zum zusammengesetzten Schlüssel
status: todo
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
- [ ] Es ist entschieden und begründet, ob ein Elternknoten dieselbe Sprache trägt — oder ob
      `BaseI18nTree` und `BaseI18nSortable` ersatzlos entfallen.
- [ ] Bei „behalten": Die Beziehung trägt eine Join-Spalte je Schlüsselspalte, und
      `orm:validate-schema` meldet keinen Mapping-Fehler mehr.
- [ ] Bei „entfallen": Die Klassen und die Tabelle `pim_i18n_tree` sind weg, und der Wegfall
      steht als Bruchstelle in `an_project/docs/breaking-changes.md`.
- [ ] Die Entscheidung steht in `an_project/docs/architecture.md` unter *Key decisions* — sie
      betrifft das Datenmodell, nicht nur eine Datei.
- [ ] Die Suite bleibt grün, und ein Datenbankvergleich zeigt genau die beabsichtigte Änderung.

## Verification
`php bin/console.php orm:validate-schema` — der Mapping-Teil muss grün sein. Dazu eine frische
Installation und ein Vergleich der erzeugten Tabellen gegen den Stand davor.
