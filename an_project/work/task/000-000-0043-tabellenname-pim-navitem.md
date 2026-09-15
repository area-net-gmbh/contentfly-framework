---
id: 000-000-0043
title: Tabellenname pim_navItem — CamelCase gegen lower_case_table_names
status: done
depends_on: []
---

# Tabellenname `pim_navItem` — CamelCase gegen `lower_case_table_names`

## Context
**Gefunden bei `007-005-0003`** (Befund F-4). `Entity\NavItem` mappt auf `pim_navItem`. UFP hatte die
Tabelle als Patch in `pim_nav_item` umbenannt — offenbar wegen MySQL auf macOS
(`lower_case_table_names=2`) gegen Linux (`0`). Das Schema-Update von Contentfly 2 löscht
`pim_nav_item` und legt `pim_navItem` neu an; bei UFP ohne Folgen (0 Zeilen), bei einem Projekt mit
Navigationsdaten ein Verlust.

Es ist die einzige CamelCase-Tabelle des Frameworks.

## Acceptance criteria
- [x] Entschieden und begründet: umbenennen (`pim_nav_item`, mit Migrationsschritt) oder beibehalten (mit Hinweis für Projekte, die umbenannt haben).
- [x] `breaking-changes.md` nennt den Schritt vor dem Schema-Update.
- [x] Ein Datenbankvergleich zeigt genau die beabsichtigte Änderung.

## Verification
Frische Installation und eine Kopie mit `pim_nav_item`; Schema-Update lesen, dann anwenden.

## Ergebnis

**Entschieden am 2026-09-15: `pim_navItem` bleibt.** Ein Umbenennen zwänge jedes Bestandsprojekt mit
Navigationsdaten zu einem Migrationsschritt, damit die wenigen, deren Tabelle anders heisst, keinen mehr
brauchen. Getroffen sind nur diese — und für sie gibt es jetzt den Schritt.

**Gemessen** (Testinstallation, MySQL 8.0):

| Fall | `orm:schema-tool:update --dump-sql` |
|---|---|
| frische Installation, `lower_case_table_names=0` | `Nothing to update` |
| frische Installation, `lower_case_table_names=1` (eigener Container, Tabelle als `pim_navitem` gespeichert) | `Nothing to update`, `orm:validate-schema` `[OK]` für Mapping und Datenbank |
| Tabelle wie bei UFP in `pim_nav_item` umbenannt, eine Zeile Navigation | `CREATE TABLE pim_navItem`, drei `ADD CONSTRAINT`, drei `DROP FOREIGN KEY`, **`DROP TABLE pim_nav_item`** |
| dieselbe Datenbank nach `RENAME TABLE pim_nav_item TO pim_navItem` | `Nothing to update`; die Zeile ist erhalten |

**Der Name ist also auf keinem Server das Problem**, sondern eine Tabelle, die anders heisst — selbst
umbenannt oder über einen Dump von einem Server mit `lower_case_table_names≠0` auf Linux gewandert.

**Register:** neuer Eintrag unter *Doctrine ORM 3*, mit beiden Ursachen, dem gemessenen Update und dem
`RENAME TABLE` vor dem Schema-Update. **Leitfaden** Phase 4 verweist darauf statt auf den offenen Task.
Kopf von `migration.md` auf 112 Einträge; `MigrationGuideTest` und `EnglishOnlyTest` grün.

Keine Codeänderung, deshalb keine volle Suite.
