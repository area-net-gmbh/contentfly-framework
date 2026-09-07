---
id: 000-000-0014
title: Die Antwort-Envelopes der API vereinheitlichen
status: todo
depends_on: []
---

# Die Antwort-Envelopes der API vereinheitlichen

## Context
Epic `008` hat sieben Endpunkte charakterisiert und dabei **sieben verschiedene Antwortformen**
gefunden. Ein Client kann keine gemeinsame Auswertung schreiben.

| Endpunkt | Envelope |
|---|---|
| `/api/single` | `ts`, `data`, `version`, `hash` |
| `/api/list` | `data`, `totalItems`, `version`, `hash` |
| `/api/insert` | `ts`, `id`, `data`, `version`, `hash` |
| `/api/delete` | `ts`, `id`, `version`, `hash` |
| `/api/all` | `lastModified`, `data`, `version`, `hash` |
| `/api/multiupdate` | `version`, `hash` |
| `/api/config` | `frontend`, `devmode`, `version`, `hash` |

Nur `version` und `hash` sind überall gleich. `ts` fehlt bei `list`, `multiupdate` und
`config`; `data` fehlt bei `delete` und `multiupdate`.

## Zwei verschärfende Befunde

**`/api/list` antwortet auf eine leere Menge mit HTTP 404** und `{"message":"Not found"}` —
und damit in einem achten Format, das mit keinem der obigen etwas zu tun hat. Für einen
Sync-Client sind „keine Treffer" und „diese Route gibt es nicht" **nicht unterscheidbar**
(`008-003-0004`).

**`/api/multiupdate` meldet im Erfolgsfall gar nichts** — kein `data`, keine Liste der
aktualisierten Ids. Der Aufruf ist auch bei Erfolg nicht auswertbar (`008-002-0003`).

## Ein Gegenbeispiel aus dem eigenen Haus
Die Vorlage macht es bereits anders: `POST api/v1/example/bootstrap` antwortet über den
`ApiResponseService` mit `success`, `status`, `i18n`, `data`, `errors`, `meta`, `timestamp` —
einem konsistenten, auswertbaren Format. Ein Projekt ist an die Form des Frameworks also nicht
gebunden; das Framework selbst hält sich nur nicht daran.

## Abgrenzung und Zusammenhang
- Überschneidet sich mit **`000-000-0006`** (Fehlerantworten: 500 statt 401/403/404/409). Beide
  betreffen die Form der Antwort; sie sollten zusammen entschieden werden.
- Berührt **`000-000-0009`** (multiupdate ohne Rollback) — dort geht es um denselben Endpunkt.
- Eine Vereinheitlichung ist ein **Breaking Change für jeden Client**. Der richtige Zeitpunkt
  ist vermutlich der Kernel-Umbau (Epic `009`) oder das Release (`011`), nicht davor.

## Acceptance criteria
- [ ] Es ist entschieden, ob und in welcher Form vereinheitlicht wird — inklusive der Frage,
      ob der `ApiResponseService` der Vorlage das Vorbild ist.
- [ ] Der Zeitpunkt ist festgelegt und mit `009`/`011` abgestimmt.
- [ ] Der Zusammenhang mit `000-000-0006` und `000-000-0009` ist aufgelöst — entweder
      gemeinsam entschieden oder ausdrücklich getrennt.
- [ ] `/api/list` unterscheidet „leere Menge" von „unbekannte Route".
- [ ] `/api/multiupdate` liefert im Erfolgsfall ein auswertbares Ergebnis.
- [ ] Der Bruch ist im Migrationsleitfaden (Epic `007`) vollständig beschrieben — je Endpunkt
      „vorher → nachher".

## Verification
Die Suite aus Epic `008` mit den gedrehten Zusicherungen; jeder der sieben Endpunkte hat einen
Test auf die neue Form.
