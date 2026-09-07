---
id: 012-005-0004
title: Entities des Frameworks und der Vorlage bereinigen
status: review
depends_on: [012-005-0003]
---

# Entities des Frameworks und der Vorlage bereinigen

## Context
Die eigenen Entities tragen die gestrichenen Felder selbst. Sie sind zugleich das Beispiel, an dem sich Bestandsprojekte orientieren.

## Acceptance criteria
- [x] `lib/contentfly/Entity/` enthält keine gestrichenen `@PIM`-Felder mehr.
- [x] `custom/Entity/Core/Example.php` zeigt den Zielzustand — es ist die Referenz für neue Projekte.
- [x] Die UI-Konstanten (`APP_CMS_SHOW_ID_IN_LIST` und verwandte) sind aus Konfiguration und Entities entfernt.
- [x] Das Datenbankschema ist unverändert — es wurden nur Metadaten entfernt, keine Spalten.

## Umfang

**195 Felder aus 96 `@PIM\Config`-Annotationen entfernt**, verteilt auf 18 Entities des
Frameworks und `custom/Entity/Core/Example.php`. Übrig bleiben **20 Annotationen**, alle
datenrelevant:

```
8×  @PIM\Config(isFilterable=true)
2×  @PIM\Config(labelProperty="title")      1×  @PIM\Config(labelProperty="alias")
2×  @PIM\Config(labelProperty="name")       1×  @PIM\Config(unique=true)
1×  @PIM\Config(sortRestrictTo="nav")       1×  @PIM\Config(i18n_universal=true)
1×  @PIM\Config(isFilterable=true, i18n_universal=true)
1×  @PIM\Config(labelProperty="value", sortBy="sorting", sortOrder="ASC", sortRestrictTo="group")
1×  @PIM\Config(labelProperty="title", sortBy="title", sortOrder="ASC")
1×  @PIM\Config(labelProperty="name",  sortBy="name",  sortOrder="ASC")
```

Wo von einer Annotation nichts übrig blieb, ist die ganze Zeile entfallen — kein
`@PIM\Config()` ohne Inhalt.

**Ein Fundort lag außerhalb der Entity-Verzeichnisse:** `custom/Traits/User.php` wird in eine
Entity gemischt und trug `@PIM\Config(label="nameExample")`. Der `AnnotationReader` hat das
zuverlässig gemeldet — `orm:validate-schema` brach ab, bis es weg war.

**Entfallene Konfiguration** (nur die von dieser Story verwaisten):
`FRONTEND_SHOW_ID_IN_LIST`, `FRONTEND_SHOW_OWNER_IN_LIST` und `FRONTEND_TAB_GENERAL_NAME` aus
`Classes/Config.php`; die abgeleiteten Konstanten `APP_CMS_SHOW_ID_IN_LIST` und
`APP_CMS_SHOW_OWNER_IN_LIST` aus `lib/contentfly/bootstrap.php` und `tests/bootstrap.php`.

> **Nicht angefasst:** die übrigen `FRONTEND_*`-Einstellungen (`FRONTEND_UI`, `FRONTEND_TITLE`,
> `FRONTEND_WELCOME`, `FRONTEND_CUSTOM_LOGO`, `FRONTEND_ITEMS_PER_PAGE` …). Sie sind Reste der
> in Story `012-001` gelöschten Oberfläche, nicht dieser Story — und ein eigener Task wert.

## Verification
Schema-Diff gegen die bestehende Datenbank ausführen: keine Änderung. Anwendung bootet, API liefert Daten.

- [x] **Datenbankschema unverändert.** `orm:schema-tool:update --dump-sql` gegen dieselbe
      Datenbank, einmal aus einem `master`-Worktree und einmal vom Branch: die Ausgaben sind
      **byte-identisch**. Es wurden nur Metadaten entfernt, keine Spalte.
      (Beide Läufe melden dieselben vorbestehenden `CREATE INDEX modified_index`-Diffs und
      dieselbe `treeParent`-Mapping-Meldung — die gibt es auf `master` genauso.)
- [x] `php bin/console.php list` bootet; `orm:schema-tool` und `orm:validate-schema` laufen
      ohne Annotationsfehler durch.
- [x] API liefert Daten: `POST /api/insert`, `/api/list`, `/api/tree2` und `GET /api/schema`
      je HTTP 200, Payloads gegen `master` verglichen (Details in `012-005-0003`).
- [x] Testsuite grün: 26 Tests, 45 Assertions.
