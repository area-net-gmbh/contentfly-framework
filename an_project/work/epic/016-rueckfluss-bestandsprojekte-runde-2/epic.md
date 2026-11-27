---
id: 016-000-0000
title: Rückfluss aus Bestandsprojekten, Runde 2 — die acht gemeldeten Issues aufgreifen
status: todo
depends_on: []
---

# Rückfluss aus Bestandsprojekten, Runde 2 — die acht gemeldeten Issues aufgreifen

## Goal

Die acht Issues, die am 29.09.2026 aus einem Bestandsprojekt gemeldet wurden (`#103`–`#110`),
sind abgearbeitet: jedes entweder umgesetzt, oder mit einer Begründung geschlossen, die der
Melder lesen kann. **Erfolg heisst: das meldende Projekt kann die Patches und Kopien aufgeben,
mit denen es heute lebt.** Jedes Issue nennt diese Behelfslösung selbst unter *„Womit wir heute
leben"* — sie ist das Abnahmekriterium, nicht der Vorschlag im Issue.

Das ist die **zweite** Rückfluss-Runde. Die erste steckt als W-/L-Liste in `007-005-0005`
(Leitfaden, Werkzeuge, Framework). Diese acht Meldungen sind seit dem Eingang nicht aufgegriffen
worden — im Repo gab es bis zum Anlegen dieses Epics kein einziges geschlossenes Issue.

## Die gemeldeten Issues

Jede Zeile ist am 27.11.2026 gegen `master` gegengeprüft; der Stand ist der *bestätigte*, nicht
der behauptete.

| Issue | Thema | Ort | Stand |
|---|---|---|---|
| `#103` | `User`: `alias` unique und `pass` NOT NULL nicht abschaltbar | `lib/contentfly/Entity/User.php` | offen — `unique: true`, `pass` ohne `nullable` |
| `#104` | `driverOptions` je Verbindung nicht ergänzbar (INIT-Command, `time_zone`) | `lib/contentfly/Classes/Database/ConnectionDefaults.php` | offen — eine fest verdrahtete Option, kein Config-Key, kein Opt-out für `ATTR_STRINGIFY_FETCHES` |
| `#105` | `DatetimeType`: Tippfehler `IMESTAMP`, `ISO8601` ohne Doppelpunkt, Offset beim Schreiben | `lib/contentfly/Classes/Types/DatetimeType.php` | offen, **alle drei** |
| `#106` | `Envelope`: `meta.ts` ohne Offset | `lib/contentfly/Classes/Envelope.php` | offen — `format('Y-m-d H:i:s')` |
| `#107` | CORS: Muster für Origins, damit Subdomains je Mandant abbildbar sind | `lib/contentfly/Classes/Security/CorsPolicy.php` | offen — exakter Vergleich, kein Label-Platzhalter |
| `#108` | `BaseI18nTree.treeParent` scheitert auf MySQL 8.4 (Fehler 6125) | — | **halb gelöst** — der Fremdschlüssel läuft seit `000-000-0025` über `(parent_id, parent_lang)`; offen ist der zweite Teil der Meldung: MySQL 8.4 in die CI-Matrix |
| `#109` | `setUpMiddleware()`: Hooks *n*-mal je Request, OPTIONS läuft die Kette durch | `lib/contentfly/Classes/Controller/Provider/BaseControllerProvider.php` | offen — fünf Provider rufen es auf, kein Flag, kein früher Ausstieg |
| `#110` | `record-api.php`: `tokenPrefix`, Header je Szenario, Listen ohne feste Reihenfolge | `tools/migration/record-api.php` | offen — nur `tokenHeader`, kein Prefix, keine Szenario-Header, kein `unordered` |

## Warum drei Stories und nicht acht Tasks

Die acht Meldungen teilen sich **drei** Begründungen. Acht standalone Tasks würden jede davon
dreimal erklären, und die Entscheidungen fielen dann einzeln statt einmal:

1. **Zeitzone und Offset in der API-Antwort** → `016-001`, für `#105` und `#106`.
   Derselbe Mangel an zwei Stellen: ein Zeitpunkt verlässt das Framework ohne Offset, und eine
   JavaScript-Engine liest ihn als Lokalzeit des Betrachters. Hier hängt die eine Entscheidung,
   die das ganze Epic trägt: **schreibt das Framework künftig UTC, und wenn ja, hinter einem
   Schalter oder als Breaking Change?** Zwei Punkte aus `#105` sind davon unabhängig und reine
   Fehlerkorrekturen (`IMESTAMP`, `DATE_ATOM`). `#104` nennt die Zeitzone als **Anlass**, liegt
   aber ganz bei `016-002`: sein Mechanismus ist ein Config-Key, nicht die Zeitsemantik.
2. **Konfigurierbarkeit statt erzwungener Vorgabe** → `016-002`, für `#103`, `#104`, `#107`.
   Dreimal dasselbe Muster: das Framework schreibt fest, was das Projekt konfigurieren müsste —
   Eindeutigkeit eines Kontos, Session-Werte einer Verbindung, erlaubte Origins. Die Frage ist
   einmal zu beantworten: Config-Key, Event-Hook, oder beides.
3. **Middleware und Migrationswerkzeug** → `016-003`, für `#109`, `#110` und den offenen
   zweiten Teil von `#108`. Die drei ohne gemeinsamen Faden: ein Performance- und
   Seiteneffekt-Befund im Request-Pfad, zwei Lücken im Golden-Master-Werkzeug, eine fehlende
   CI-Dimension.

**Was dieses Epic nicht entscheidet:** ob eine Meldung umgesetzt wird. Zwei Vorschläge berühren
Verhalten, auf das Bestandsprojekte sich verlassen — der UTC-Teil von `#105` und das Opt-out für
`ATTR_STRINGIFY_FETCHES` aus `#104`, dessen heutige Vorgabe `000-000-0044` bewusst gesetzt hat.
Beide gehören in der zuständigen Story begründet, nicht hier.

## Stories
<!-- Die Stories dieses Epics. Wird von /new-story synchron gehalten. -->
- [ ] 016-001-0000 — Ein Zeitpunkt verlässt das Framework mit seinem Offset — und wird nicht als Lokalzeit des Betrachters gelesen
- [ ] 016-002-0000 — Was ein Projekt entscheiden muss, schreibt das Framework nicht mehr fest — Konto, Verbindung, Origin
- [ ] 016-003-0000 — Hooks laufen einmal statt fünfmal, das Golden-Master-Werkzeug ist wieder glaubwürdig, und MySQL 8.4 läuft in der CI
