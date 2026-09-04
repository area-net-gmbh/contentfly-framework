---
id: 012-005-0004
title: Entities des Frameworks und der Vorlage bereinigen
status: todo
depends_on: [012-005-0003]
---

# Entities des Frameworks und der Vorlage bereinigen

## Context
Die eigenen Entities tragen die gestrichenen Felder selbst. Sie sind zugleich das Beispiel, an dem sich Bestandsprojekte orientieren.

## Acceptance criteria
- [ ] `lib/contentfly/Entity/` enthält keine gestrichenen `@PIM`-Felder mehr.
- [ ] `custom/Entity/Core/Example.php` zeigt den Zielzustand — es ist die Referenz für neue Projekte.
- [ ] Die UI-Konstanten (`APP_CMS_SHOW_ID_IN_LIST` und verwandte) sind aus Konfiguration und Entities entfernt.
- [ ] Das Datenbankschema ist unverändert — es wurden nur Metadaten entfernt, keine Spalten.

## Verification
Schema-Diff gegen die bestehende Datenbank ausführen: keine Änderung. Anwendung bootet, API liefert Daten.
