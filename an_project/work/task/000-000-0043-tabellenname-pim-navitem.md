---
id: 000-000-0043
title: Tabellenname pim_navItem — CamelCase gegen lower_case_table_names
status: todo
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
- [ ] Entschieden und begründet: umbenennen (`pim_nav_item`, mit Migrationsschritt) oder beibehalten (mit Hinweis für Projekte, die umbenannt haben).
- [ ] `breaking-changes.md` nennt den Schritt vor dem Schema-Update.
- [ ] Ein Datenbankvergleich zeigt genau die beabsichtigte Änderung.

## Verification
Frische Installation und eine Kopie mit `pim_nav_item`; Schema-Update lesen, dann anwenden.
