---
id: 011-001-0002
title: Erfolgsantworten auf data/errors/meta umstellen
status: done
depends_on: [011-001-0001]
---

# Erfolgsantworten auf data/errors/meta umstellen

## Context
Der Trichter ist `ApiController::renderResponse()`: Er hängt heute `version` und `hash` an ein
Array, das jeder Aufrufer selbst zusammenbaut — mal mit `ts`, mal mit `lastModified`, mal mit
`id` neben `data`. Genau daraus entstehen die sieben Formen.

Die Umstellung gehört in den Trichter, nicht in 18 Aufrufer: `data` immer vorhanden (auch `null`),
`errors` immer vorhanden (bei Erfolg `null`), und alles, was nicht Nutzlast ist, unter `meta` —
`ts`, `version`, `hash`, `totalItems`, `itemsPerPage`, `lastModified`.

**Die Charakterisierungstests aus Epic `008` werden dabei rot.** Das ist der Zweck der Übung, aber
`technical.md` verlangt: „Eine Testanpassung ist ein Verhaltenswechsel und braucht eine
Begründung." Jede angepasste Erwartung nennt die Zeile der Zieltabelle, aus der sie folgt.

## Acceptance criteria
- [x] Jeder Erfolgsfall der in `0001` festgelegten Endpunkte antwortet in der Zielform; `data` und `errors` sind immer vorhanden.
- [x] Kein Endpunkt trägt mehr Nutzlast und Metadaten auf derselben Ebene.
- [x] Die Statuscodes sind unverändert — mit **einer** entschiedenen Ausnahme: `/api/all` antwortet auf eine leere Menge `200` statt `204` (siehe unten).
- [x] Jede angepasste Testerwartung ist im Test begründet, mit Verweis auf die Zieltabelle.
- [x] `breaking-changes.md` trägt den Bruch für die Erfolgsantworten, `migration.md` den Schritt für Clients; `MigrationGuideTest` grün.
- [x] Volle Suite, PHPStan, Deprecation-Gate grün.

## Verification
Die Aufzeichnung eines Endpunkts vorher/nachher im Test festhalten. Gegenprobe: Ein Testclient
liest `data` und `meta.version` bei jedem umgestellten Endpunkt mit demselben Code.

## Ergebnis

**Der Trichter nimmt jetzt drei Dinge entgegen statt eines fertigen Arrays.**
`renderResponse(mixed $data, int $status, array $meta)` baut `data` / `errors` / `meta` — 17
Antwortstellen, eine Form. Eine Aktion kann damit keine achte erfinden; vorher hat jede ihr
eigenes Array gebaut, und die Methode hat nur noch `version` und `hash` daran gehängt.

`errors` ist im Erfolgsfall `null` und **nicht abwesend**: Ein Client soll auf `body.errors`
prüfen können, ohne vorher zu wissen, ob er eine Erfolgs- oder Fehlerantwort in der Hand hat.
Die Fehlerseite baut dieselbe Hülle mit `011-001-0003`.

### Drei Entscheidungen, die mehr sind als ein Umhängen von Schlüsseln

- **Zwei Versionsfelder.** `meta.version` ist die des Frameworks, `meta.projectVersion` die des
  Projekts. `/api/config` hat die Projektversion **nie ausgeliefert** — die Aktion übergab
  `APP_VERSION.'/'.CUSTOM_VERSION`, der Trichter überschrieb den Schlüssel danach mit
  `APP_VERSION`. Gefunden in `0001`, hier behoben. Zwei Felder statt einer Zeichenkette, damit
  niemand zum Vergleichen zerlegen muss.
- **`/api/all` antwortet auf eine leere Menge `200` statt `204`.** Eine `204` hat keinen Rumpf und
  damit auch keinen Envelope — genau der Fall, für den die Vereinheitlichung da ist. `/api/list`
  hat dasselbe mit `000-000-0014` abgelegt. Das ist die einzige Statuscode-Änderung dieses Tasks.
- **`/api/schema`: `data` ist das Schema.** `permissions`, `i18nPermissions`, `frontend` und
  `devmode` beschreiben die Antwort, statt sie zu sein, und liegen in `meta` — dort, wo der
  Schema-Hash immer schon lag. Damit bedeutet `body.data` bei diesem Endpunkt unverändert
  dasselbe wie vorher, und fünf Tests, die das Schema lesen, brauchten keine Zeile.

Dazu zwei Vereinfachungen, die aus der Umstellung fielen: `/api/insert` liefert die `id` nur noch
einmal (im Objekt statt zusätzlich daneben), und `listAction` hat statt zweier fast gleicher
Zweige für „mit Seite" und „ohne Seite" einen Ausgang — Paginierung ist ein Meta-Schlüssel mehr,
keine andere Form.

### Die Testanpassungen

`technical.md` verlangt für jede geänderte Erwartung eine Begründung. Die wichtigste Änderung ist
aber, **wie** geprüft wird: Bisher schrieb jeder Test die Schlüsselliste seines Endpunkts aus
(`array('ts', 'data', 'version', 'hash')`) — und schrieb damit genau das fest, was diese Story
abschafft. Neu prüft `IntegrationTestCase::assertEnvelope()` die Form an **einer** Stelle, bei
jedem Aufruf; ein Test nennt nur noch, was sein Endpunkt zusätzlich in `meta` legt.

Zwei Tests sind dabei umgedreht und umbenannt worden, weil ihre Aussage sich ins Gegenteil
verkehrt hat:

| vorher | jetzt |
|---|---|
| `ReadApiTest::testListReturnsADifferentEnvelopeThanSingle` | `…ReturnsTheSameEnvelopeAsSingle` |
| `WriteApiTest::testInsertReturnsTheGeneratedIdOnTheTopLevel` | `…ReturnsTheCreatedObjectWithItsId` |

`SystemControllerApiTest::testSystemEndpointUsesItsOwnResponseShape` bleibt stehen und hält jetzt
den **halben** Stand fest: `/api/*` ist umgestellt, `/system/do` nicht. Er ist die Stelle, die es
meldet, falls `011-001-0004` ihn vergisst.

### Abnahme

Neu: `tests/Integration/Api/EnvelopeApiTest.php`. Es ruft **15 Antwortstellen** in einer Schleife
auf und wertet jede mit **derselben Leserfunktion** aus, die nichts kennt als `data`, `errors` und
`meta` — das ist die Zusicherung des Epics, und sie zeigt sich nur, wenn ein Stück Code alle
durchläuft. Dazu die leere Menge (`200` mit leerer Liste statt `204`) und die beiden
Versionsfelder.

**Verifiziert:** volle Suite `Tests: 611, Assertions: 2113, Skipped: 3`, PHPStan `[OK] No errors`,
Deprecation-Gate 0.

### Was offen bleibt

`/auth/*`, `/file/upload`, `/file/overwrite` und `/system/do` folgen mit `011-001-0004`, die
Fehlerform mit `011-001-0003`. Bis dahin antworten sie in ihrer bisherigen Form.
