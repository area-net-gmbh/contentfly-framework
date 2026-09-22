---
id: 000-000-0075
title: Die Lesepfade in Api.php testen — Filter, Übersetzungen, Sync
status: done
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
- [x] Je Zeile mindestens ein Test über HTTP; `getAll` mit `filedata` zusätzlich mit einem Grössennamen, der `../` enthält.
- [x] Der Schema-Cache ist einmal mit eingeschaltetem Cache geprüft (Schreiben und Lesen).
- [x] Coverage von `Api.php` neu gemessen.

## Verification
Die neuen Tests und der Coverage-Lauf aus `0056`.

## Ergebnis (2026-09-22)
**`Api.php` steht bei 75,6 % (955 von 1.263 Zeilen), vorher 59 % (`0069`).** Gesamt 74,7 % (vorher
70,5 %). 783 Tests, grün; PHPStan ohne Fehler.

### Umgesetzt
- **`ReadPathApiTest`** (35 Tests) — je Methode ein Abschnitt: `where` auf `join`/`multijoin` inkl.
  `-1`, `fulltext` (Textfelder und exakte id), `mimetypes` (Gruppe, `other`, unbekannte Gruppe),
  `lastModified`, `untranslatedLang`, i18n-Joins in Liste und Einzelabruf (auch partiell über
  `properties`), `loadJoinedLang`, `compareToLang` (beide Zweige), `/api/all` mit `filedata`
  (Original, Thumbnail-Alias, Einzelwert, `../`), `lastModified` und Löschprotokoll (`DEL` und
  Altwert `Gelöscht`), `/api/count` mit `lastModified` global und je Entity, Join-Tabellen und
  `PIM\File`, `doInsert` mit Unique-Verletzung und universellem Feld.
- **`MainLanguageApiTest` und `SchemaCacheApiTest`** laufen gegen einen **zweiten Server**, den die
  Klasse selbst startet (`Tests\Integration\ExtraServer`): gleicher Baum, gleiche Datenbank, nur
  `APP_LANGUAGES=de,en` bzw. `APP_ENABLE_SCHEMA_CACHE=1`. Er trägt zur Coverage bei, und `stop()`
  prüft sein Log auf Deprecations und Warnungen — das Gate der Pipeline liest nur das Log des
  Suite-Servers. Keine Änderung an der Pipeline nötig. Doku in `tests/README.md`.
- **`custom/config.php`**: `APP_ENABLE_SCHEMA_CACHE` und `APP_LANGUAGES` kommen aus der Umgebung,
  der Standard bleibt aus bzw. leer.
- **Vorlage**: `Core\ExampleRelations` hat `owner` (`join`) und `examples` (`multijoin`),
  `Core\ExampleI18n` hat `code` (`i18n_universal`) und `related` (Join auf eine übersetzbare Entity).
- **`getAll()` mit `filedata`**: Ein Grössenname ist ein Name, kein Pfad. Nur `org` und die
  vorhandenen Thumbnail-Aliase werden genommen. Vorher las `../<andere id>/x` eine Datei aus dem
  Verzeichnis eines fremden Datensatzes, und ein einzelner Wert statt einer Liste brach ab.
  **Gegenprobe:** ohne die Änderung sind beide Tests rot.

### Befunde — als Ist-Zustand festgehalten, nicht behoben
Tickets: `0078`–`0083`, in dieser Reihenfolge.

1. (`0078`) **Mit `APP_LANGUAGES` lässt sich keine Übersetzung mit `id` anlegen** — 500, auch ohne Datensatz
   in der Hauptsprache. `getSingle(…, clearEM: true)` ruft `$this->em->clear($entityFullName)`;
   seit ORM 3 (Epic `010`) nimmt `clear()` kein Argument mehr und leert den ganzen EntityManager,
   samt angemeldetem Benutzer. Die Übernahme der `i18n_universal`-Felder aus der Hauptsprache ist
   deshalb nicht erreichbar. **Schwerster Befund:** Betrifft jedes Projekt mit Sprachen.
2. (`0079`) **Fehlertexte von Doctrine/MySQL erreichen den Client ohne Debug.** `doInsert()` verpackt jede
   Ausnahme in eine `ContentflyException` mit ihrem Text (Befund 1: Text in `code` und `detail`)
   und hängt bei einer Unique-Verletzung die SQL-Meldung an `context.value`. Dieselbe Art Leck wie
   in `0073`, an einer Stelle, die dessen Regel nicht erfasst.
3. (`0080`) **Unique-Verletzung, die nur die Datenbank kennt** (`Core\Example.slug`): 500 statt 409, und die
   Meldung nennt das letzte Feld der Schleife (`groups`) statt `slug`.
4. (`0081`) **`loadJoinedLang` findet nie etwas.** Der Verweis auf eine übersetzbare Entity hat zwei Spalten,
   `related_lang` legt die Sprache schon fest; eine andere Sprache im Join trifft keinen Datensatz,
   der Join kommt als `null`.
5. (`0082`) **`untranslatedLang` liefert immer eine leere Liste**, sobald die Entity auf eine übersetzbare
   joint: Die Join-Schleife bindet `:lang` neu. `Core\ExampleI18n` ist die einzige übersetzbare
   Entity der Vorlage und joint über `related` auf sich selbst — der funktionierende Weg ist damit
   nicht mehr zeigbar.
6. (`0083`) **Ein nicht lesbares `lastModified` in `/api/list` endet mit 500**: `getList()` schluckt den
   Fehler beim Parsen und gibt die Zeichenkette an MySQL weiter.

### Nebenbei
- Ein laufender Testserver hält Doctrines Mapping in `data/cache/metadata`. Nach einer neuen
  Entity-Spalte endet eine Abfrage darauf mit 500, bis `flushSchemaCache` läuft. Frische
  Installationen (Pipeline) betrifft das nicht; Hinweis in `tests/README.md`.

