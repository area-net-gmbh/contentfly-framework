---
id: 000-000-0068
title: Die Bildverarbeitung testen — sie verarbeitet hochgeladene Dateien
status: todo
depends_on: []
---

# Die Bildverarbeitung testen — sie verarbeitet hochgeladene Dateien

## Context
**Gefunden bei der ersten Coverage-Messung (`000-000-0056`, 2026-09-18).** `Classes/File` ist zu
**23 %** abgedeckt: `Processing/Image.php` 4 % (198 von 206 Zeilen offen), `Processing/ImageMagick.php`
**0 %**. Die Bildverarbeitung erzeugt Vorschaubilder aus **hochgeladenen** Dateien — sie ist die
Stelle, an der fremde Eingabe einen Bild-Parser erreicht. Genau dort erwartet ein Prüfer Befunde.

## Acceptance criteria
- [ ] Ein Integrationstest lädt ein Bild hoch und prüft die erzeugten Vorschaubilder (Grösse, Format) — für den GD-Weg und, wo die Erweiterung vorhanden ist, für ImageMagick.
- [ ] Ein Test mit einer **manipulierten** Bilddatei (falsche Endung, beschädigter Kopf, übergrosse Abmessungen): Die Verarbeitung scheitert kontrolliert, ohne 500 und ohne liegengebliebene Dateien.
- [ ] Entschieden und begründet, ob `ImageMagick.php` im Umfang bleibt — 0 % heisst auch: Niemand weiss, ob es noch funktioniert.

## Verification
Die neuen Tests, und der Coverage-Lauf aus `0056`.
