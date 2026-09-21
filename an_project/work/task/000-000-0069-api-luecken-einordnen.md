---
id: 000-000-0069
title: Die ungetesteten Zweige in Api.php einordnen
status: review
depends_on: []
---

# Die ungetesteten Zweige in Api.php einordnen

## Context
**Gefunden bei der ersten Coverage-Messung (`000-000-0056`, 2026-09-18).** `Api.php` ist mit
**59 %** nicht die schwächste Datei, aber die mit der **grössten absoluten Lücke: 514 von 1.258
Zeilen offen.** Sie ist das Herz der Datenschnittstelle — jeder Lese- und Schreibzugriff läuft
hindurch.

Eine Prozentzahl sagt hier wenig. Die offenen Zeilen verteilen sich auf viele Zweige; manche sind
vermutlich tot, manche Sonderfälle ohne Test, manche Rechte-Verengungen.

## Acceptance criteria
- [x] Die offenen Zeilen sind **nach Methode** aufgeschlüsselt (Coverage-Bericht zeilengenau).
- [x] Jeder offene Block ist eingeordnet: **Test nachziehen** (eigenes Ticket, gebündelt nach Methode), **toter Code** (entfernen, eigenes Ticket) oder **begründet nicht abdecken**.
- [x] Besonders geprüft: jede Stelle mit `Permission::` oder `I18nPermission::`, die kein Test erreicht.

## Verification
Die Einordnung als Tabelle im Task. Ein Leser kann für jeden offenen Block sagen, was mit ihm
geschieht.

## Ergebnis (2026-09-21)
**514 offene Zeilen in 16 Methoden — eingeordnet in fünf Tickets, ein bisschen toten Code und eine
Handvoll begründeter Ausnahmen.** Grundlage: der zeilengenaue Clover-Bericht des Coverage-Laufs aus
`000-000-0068` (`Api.php` dort identisch mit `master`), 744 von 1.258 Zeilen abgedeckt (59 %).

**Das Wichtigste zuerst: Die Rechte-Prüfung hat zwei echte Fehler gefunden**, beide über die API
bestätigt:
- **`000-000-0072`** — `/api/count` und `/api/query` enden für jeden Benutzer mit Rechtestufe
  `GROUP` mit **500**: `groups` ist in MySQL 8 reserviert und steht dort ohne Backticks.
- **`000-000-0073`** — die 500er-Antwort trägt den **SQL-Fehlertext** in `errors[0].detail`, auch
  mit `APP_DEBUG=0`. Dieselbe Art Leck, die TeamViewer 2025 bei UFP gemeldet hat.

### Nach Methode
| Methode | offen | Einordnung |
|---|---|---|
| `getQuery` + `isIndexedArray` | 109 von 134 | **Test oder streichen** → `0076` (Array-Syntax, 84 Zeilen); Rechte-Zweige `OWN`/`GROUP` → `0072` (Fehler) und `0074` |
| `getList` | 91 von 193 | **Test** → `0075` (Filter auf `join`/`multijoin`, `fulltext`, `mimetypes`, i18n-Joins); `GROUP` ohne Gruppe → `0074` |
| `getSingle` | 71 von 117 | **Test** → `0075` (`compareToLang`, `loadJoinedLang`, i18n-Joins); `missing_params` begründet nicht |
| `getCount` | 52 von 82 | **Fehler** → `0072` (`GROUP`); **Test** → `0074` (`OWN`) und `0075` (`lastModified`, Zählung über Verknüpfungen, Dateien) |
| `doInsert` | 39 von 108 | **Test** → `0074` (i18n-Schreibrecht) und `0075` (Erbe der Hauptsprache, `unique` je Feld); `unknown_property`/`unknown_type_object` begründet nicht |
| `getExtendedSchema` | 34 von 48 | **Toter Code** → `0077` (`customNavigation` aus der gestrichenen Oberfläche) |
| `doUpdate` | 33 von 95 | **Test** → `0074` (i18n-Schreibrecht, Passwortprüfung bei `PIM\User`); `unique`-Zweig → `0075`; `catch`-Rümpfe begründet nicht |
| `getAll` | 30 von 71 | **Test** → `0074` (`OWN`/`GROUP` — gegen `0072` geprüft: funktioniert) und `0075` (`filedata`, `lastModified`); `'Gelöscht'` → `0077` |
| `doDelete` | 17 von 62 | **Test** → `0074` (i18n-Schreibrecht); Baum-Kaskade → `0075`; `unknown_entity` begründet nicht |
| `getSchema` | 14 von 129 | **Test** → `0075` (Schema-Cache, in Produktion an, in der Vorlage aus); übrige Einzeiler begründet nicht |
| `getTree` | 8 von 55 | **Test** → `0074` (`OWN`, `GROUP` ohne Gruppe); i18n-Zweig → `0075` |
| `getDeleted` | 6 von 23 | **Test** → `0074` (Entity ohne Leserecht); `lastModified` je Entity → `0075` |
| `getTranslations` | 4 von 31 | **Test** → `0074` (ohne Leserecht, `GROUP`); Parameterfehler begründet nicht |
| `getTree2` | 4 von 59 | **Test** → `0074` (`GROUP`); `unknown_entity`, `break` begründet nicht |
| `getTableName`, `treeSort` | je 1 | begründet nicht |

### Die `[PERM]`-Stellen, einzeln
Jede Stelle mit `Permission::` oder `I18nPermission::`, die kein Test erreicht (22 Blöcke, 172 Zeilen — davon 84 im Block der Array-Syntax von `getQuery`, der selbst eine Rechteprüfung enthält):

| Stelle | Ergebnis der Prüfung | Ticket |
|---|---|---|
| `getCount` `OWN`/`GROUP` | **`GROUP` → 500**, `OWN` ok | `0072`, `0074` |
| `getQuery` `OWN`/`GROUP`, ohne Leserecht | **`GROUP` → 500**, `OWN` ok | `0072`, `0074` |
| `getAll` `OWN`/`GROUP`, ohne Leserecht | ok (DQL, kein rohes SQL) | `0074` |
| `getDeleted` ohne Leserecht | ungeprüft | `0074` |
| `getList` `GROUP` ohne Gruppe | ungeprüft | `0074` |
| `getTree` `OWN`, `GROUP` ohne Gruppe | ungeprüft | `0074` |
| `getTree2` `GROUP` | ungeprüft; setzt `` `groups` `` richtig | `0074` |
| `getTranslations` ohne Leserecht, `GROUP` | ungeprüft; setzt `` `groups` `` richtig | `0074` |
| `doInsert`/`doUpdate`/`doDelete` `I18nPermission::isWritable` | ungeprüft | `0074` |
| `doInsert`/`doUpdate` `I18nPermission::isOnlyReadable` | ungeprüft | `0074` |
| `getQuery` Array-Syntax (Rechteprüfung im Block) | ungeprüft | `0076` |
| `getExtendedSchema` `PIM\NavItem` | toter Code | `0077` |

### Begründet nicht abgedeckt (rund 30 Zeilen)
- **Leere `catch (Exception)`-Rümpfe** (`doDelete`, `doInsert`, `doUpdate` ×2): Sie verschlucken
  Nebenfehler beim Aufräumen bzw. Protokollieren; einen davon auszulösen hiesse, die Datenbank im Test
  zu beschädigen.
- **Einzeilige Guards mit Fehlerantwort** (`unknown_entity`, `missing_params`, `unknown_property`,
  `unknown_type_object`, `i18n_missing_lang_param`): Die Form jeder Fehlerantwort prüft
  `ErrorResponseApiTest` für alle Ausnahmen gleich; je Guard ein Test brächte Zeilen, keine Aussage.
- **Schleifen-`continue`/`break`** in `getAll`, `getCount`, `getDeleted`, `getTree2`, `treeSort` für
  Entities mit `excludeFromSync` bzw. den Sonderfall `_hash`: kein Beispiel in Framework oder Vorlage.
- **`getSchema()`-Zweige** für benutzerdefinierte Properties und `UntypedColumn::report()`: durch die
  Unit-Tests von `000-000-0017` abgedeckt, nicht über HTTP — der Lauf zählt sie trotzdem.

Tickets: `0072`–`0077`. Nach deren Umsetzung ist `Api.php` neu zu messen.

