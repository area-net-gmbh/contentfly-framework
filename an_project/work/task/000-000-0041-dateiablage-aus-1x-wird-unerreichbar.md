---
id: 000-000-0041
title: Dateiablage — Dateien aus Contentfly 1.x sind nach der Migration unerreichbar
status: review
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
- [x] Entschieden und begründet: Das Framework liest `path` weiter (Bestand bleibt, wo er ist), **oder** ein Umzug nach `data/files/<id>/` ist ein ausführbarer Schritt (Console-Command mit `--dry-run`) vor dem Schema-Update.
- [x] Der gewählte Weg ist am UFP-Probe-Backend gemessen: eine Bestandsdatei wird ausgeliefert.
- [x] `breaking-changes.md` und Phase 4/9 in `migration.md` nennen den Schritt und die Reihenfolge (vor dem Schema-Update).
- [x] Volle Suite grün.

## Verification
Frische Datenbankkopie und `data/`-Kopie des alten Stands, Weg gehen, `GET /file/get/<id>` für eine
Bestandsdatei.

## Ergebnis

**Entschieden am 2026-09-15: ein Umzugs-Command vor dem Schema-Update**, kein Altlast-Zweig im
Datei-Backend. Ein Layout, einmal erreicht.

**`appcms:files:relocate`** (`Command/RelocateFilesCommand.php`, im Bootstrap registriert): liest
`pim_file.id`/`path` per SQL, verschiebt jeden Ordner `data/files/<path><id>/` nach `data/files/<id>/` —
Thumbnails und Varianten liegen darin und ziehen mit — und entfernt geleerte Datumsordner. `--dry-run`
ändert nichts. Gemeldet und nicht angefasst: fehlende Ordner, Konflikte (Quelle und Ziel existieren),
ein `path`, der kein schlichtes relatives Präfix ist (etwa `../`). Konflikte, abgelehnte Pfade und
gescheiterte Umzüge ergeben Exit-Code 1. Ohne Spalte `path` meldet er, dass nichts zu tun ist. Die
Datenbank wird nur gelesen.

**Test** `tests/Integration/Command/RelocateFilesCommandTest.php` (eigene Tabelle mit der 1.x-Spalte,
eigenes Verzeichnis, wie `ReencryptCommandTest`): Trockenlauf zählt und verschiebt nichts; echter Lauf
verschiebt Original und Thumbnail, auch ohne abschliessenden Schrägstrich, lässt eine Datei ohne `path`
stehen, räumt `2026/06/` weg; zweiter Lauf findet alles am Platz. Fehlender Ordner, Konflikt (das Ziel
wird nicht überschrieben) und ein Pfad nach draussen werden gemeldet. Ohne Spalte nichts zu tun.

**Gemessen an UFP**, mit einer frischen Kopie von `data/` im 1.x-Layout und der alten Datenbankkopie
(nur gelesen), der Command aus dem migrierten Backend:

| Lauf | Ergebnis |
|---|---|
| `--dry-run` | 27 würden verschoben, 14 fehlen schon (je mit Pfad gemeldet), nichts bewegt |
| echter Lauf | 27 verschoben, 14 fehlen, 0 Konflikte, Exit 0 |
| zweiter Lauf | 0 verschoben, 27 am Platz |

Die Dateiliste danach ist identisch mit dem Stand, den das Probe-Skript für `007-005-0003` erzeugt hat.
Das migrierte Backend liefert eine Bestandsdatei (`fussballfeld.jpg`, vorher unter `2026/06/`) mit
`301` auf `/data/files/<id>/fussballfeld.jpg` aus, der Abruf antwortet `200 image/jpeg`, **byte-gleich
mit dem Original**. 12 Ordner unter `2025/` ohne Zeile in `pim_file` bleiben liegen — Waisen, im
Register erwähnt.

**Zwei Nebenbefunde bei der Messung:**

- **Framework, neu:** Die Kopie von `data/` enthielt die Proxy-Dateien des alten ORM 2 unter
  `data/cache/doctrine`. Mit der Vorgabe aus `000-000-0047` (`FILE_NOT_EXISTS_OR_CHANGED`) erzeugt Doctrine
  eine Proxy nur neu, wenn sie älter ist als die Entity-Datei — die alte war jünger, wurde geladen, und
  `/file/get` antwortete `500 Interface "Doctrine\ORM\Proxy\Proxy" not found`. Das Register nennt bisher
  nur `metadata` und `query` als zu leerende Caches. Eigener Task nötig.
- **Projekt UFP:** `data/.htaccess` des Projekts (Commit vom 2026-08-14) setzt `php_admin_flag`, das in
  `.htaccess` nicht erlaubt ist — Apache antwortet für **jede** Datei unter `data/` mit `500`, beim alten
  Backend genauso. Für den Nachweis oben war die Datei in der Probe-Kopie beiseitegelegt.

**Register:** Eintrag unter *Entity-Layer* mit Ablauf, Reihenfolge und Messung. **Leitfaden:** Phase 4
nennt den Command vor dem Schema-Update. `migration.md` auf 114 Einträge; `MigrationGuideTest` grün.

Auf der Testinstallation (Contentfly 2, ohne Spalte) meldet der Command „pim_file has no column path —
nothing to relocate“.

**Verifiziert:** volle Suite `Tests: 604, Assertions: 1927, Skipped: 3`, PHPStan `[OK] No errors`,
Deprecation-Gate 0.
