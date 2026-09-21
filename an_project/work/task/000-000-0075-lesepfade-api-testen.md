---
id: 000-000-0075
title: Die Lesepfade in Api.php testen — Filter, Übersetzungen, Sync
status: todo
depends_on: []
---

# Die Lesepfade in Api.php testen — Filter, Übersetzungen, Sync

## Context
**Aus `000-000-0069`.** Die grössten offenen Blöcke ohne Rechtebezug, gebündelt nach Methode.

| Methode | offene Zweige | Zeilen |
|---|---|---|
| `getList` | `where` auf `join`/`multijoin` inkl. `-1` („ohne Verknüpfung"), `fulltext`, `mimetypes`, `untranslatedLang`, `lastModified`, i18n-Joins | ~80 |
| `getSingle` | `compareToLang`, `loadJoinedLang`, i18n-Joins | ~70 |
| `getAll` | `filedata` (Dateiinhalt als Base64 — der Grössen-Parameter geht in einen Pfad), `lastModified`, Löschprotokoll | ~20 |
| `getCount` | `lastModified` (auch je Entity), Zählung über `multifile`/`multijoin`, `PIM\File` | ~40 |
| `doInsert` | Übersetzung erbt `i18n_universal` von der Hauptsprache; `unique`-Verletzung je Feld | ~25 |
| `getSchema` | Schema-Cache (`APP_ENABLE_SCHEMA_CACHE`, in Produktion an, in der Vorlage aus) | 4 |

## Acceptance criteria
- [ ] Je Zeile mindestens ein Test über HTTP; `getAll` mit `filedata` zusätzlich mit einem Grössennamen, der `../` enthält.
- [ ] Der Schema-Cache ist einmal mit eingeschaltetem Cache geprüft (Schreiben und Lesen).
- [ ] Coverage von `Api.php` neu gemessen.

## Verification
Die neuen Tests und der Coverage-Lauf aus `0056`.
