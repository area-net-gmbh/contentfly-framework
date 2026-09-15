---
id: 000-000-0041
title: Dateiablage — Dateien aus Contentfly 1.x sind nach der Migration unerreichbar
status: todo
depends_on: []
---

# Dateiablage — Dateien aus Contentfly 1.x sind nach der Migration unerreichbar

## Context
**Gefunden bei `007-005-0003`** am Bestandsprojekt UFP (Befund F-2).

| | Ablage einer Datei |
|---|---|
| Contentfly 1.x | `data/files/<path><id>/`, `pim_file.path` = z. B. `2026/06/` |
| Contentfly 2 | `data/files/<id>/`; `File` hat kein `$path` |

UFP: 41 Dateien, **alle** mit `path`. Nach einer Migration nach Leitfaden liefert die API keine
davon mehr aus, und `orm:schema-tool:update` löscht `pim_file.path` — danach ist auch die
Information weg, wo sie lagen. Weder `breaking-changes.md` noch `migration.md` erwähnen es.

Für die Probe wurden die Ordner vor dem Update umgezogen (`.ufp-probe/move-files-to-id-folders.php`:
27 verschoben, 14 fehlten schon lokal).

## Acceptance criteria
- [ ] Entschieden und begründet: Das Framework liest `path` weiter (Bestand bleibt, wo er ist), **oder** ein Umzug nach `data/files/<id>/` ist ein ausführbarer Schritt (Console-Command mit `--dry-run`) vor dem Schema-Update.
- [ ] Der gewählte Weg ist am UFP-Probe-Backend gemessen: eine Bestandsdatei wird ausgeliefert.
- [ ] `breaking-changes.md` und Phase 4/9 in `migration.md` nennen den Schritt und die Reihenfolge (vor dem Schema-Update).
- [ ] Volle Suite grün.

## Verification
Frische Datenbankkopie und `data/`-Kopie des alten Stands, Weg gehen, `GET /file/get/<id>` für eine
Bestandsdatei.
