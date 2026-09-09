---
id: 000-000-0014
title: Die Antwort-Envelopes der API vereinheitlichen
status: done
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
- [x] Es ist entschieden, ob und in welcher Form vereinheitlicht wird — inklusive der Frage,
      ob der `ApiResponseService` der Vorlage das Vorbild ist.
- [x] Der Zeitpunkt ist festgelegt und mit `009`/`011` abgestimmt.
- [x] Der Zusammenhang mit `000-000-0006` und `000-000-0009` ist aufgelöst — entweder
      gemeinsam entschieden oder ausdrücklich getrennt.
- [x] `/api/list` unterscheidet „leere Menge" von „unbekannte Route".
- [x] `/api/multiupdate` liefert im Erfolgsfall ein auswertbares Ergebnis.
- [x] Der Bruch ist im Migrationsleitfaden (Epic `007`) vollständig beschrieben — je Endpunkt
      „vorher → nachher".

## Verification
Die Suite aus Epic `008` mit den gedrehten Zusicherungen; jeder der sieben Endpunkte hat einen
Test auf die neue Form.

## Ergebnis

**Entschieden: vereinheitlichen — aber beim Release in Epic `011`, nicht jetzt und nicht mit
dem Kernel-Wechsel.** Form, Zeitpunkt und die Tabelle *vorher → nachher* je Endpunkt stehen in
`an_project/docs/api-envelope.md`; die Entscheidung selbst als *Key decision* in
`an_project/docs/architecture.md`, und Epic `011` hat sie als Erfolgskriterium bekommen, damit
sie nicht in einem Dokument stehen bleibt.

### Warum nicht jetzt

Der Grund ist die Reihenfolge, nicht der Aufwand. Die Charakterisierungstests aus Epic `008`
sind die **Abnahmegrundlage** für den Kernel-Wechsel: Sie beschreiben, was die Anwendung heute
tut, damit man nach dem Tausch vergleichen kann. Ändert man den Vertrag gleichzeitig mit dem
Kernel, sind **beide Seiten des Vergleichs neu** — und hinterher lässt sich eine Abweichung
nicht mehr dem Umbau oder der Absicht zuordnen. `technical.md` sagt es als Regel: „Eine
Testanpassung ist ein Verhaltenswechsel und braucht eine Begründung."

`011` vergibt ohnehin eine neue Hauptversion. Ein Bruch braucht eine Nummer, an der man ihn
festmachen kann.

### Ist der `ApiResponseService` das Vorbild?

**Als Vorbild ja, wörtlich nein.** Übernommen wird: dieselbe Form für Erfolg und Fehler, `data`
immer vorhanden (auch als `null`), `meta` für alles, was keine Nutzlast ist, `errors` als
Liste. Nicht übernommen:

- **`success` und `status`** wiederholen den HTTP-Statuscode im Rumpf. Zwei Quellen für dieselbe
  Aussage laufen irgendwann auseinander, und dann glaubt der Client der falschen.
- **`i18n`** ist eine Anforderung des Projekts, das diesen Service mitbringt, nicht des
  Frameworks. Wer einen Übersetzungsschlüssel braucht, legt ihn in `errors` ab.

### Was jetzt schon umgesetzt ist

Zwei Punkte liessen sich nicht aufschieben, weil sie keine Formfragen sind, sondern Defekte:

- **`/api/list` unterscheidet „leere Menge" von „unbekannte Route".** Eine bekannte Entity ohne
  Treffer antwortet mit `200`, `data: []`, `totalItems: 0`. Vorher kam
  `404 {"message":"Not found"}` — dieselbe Antwort wie für eine Entity, die es nicht gibt. Die
  unbekannte Entity bleibt ein `404`, jetzt unterscheidbar mit
  `contentfly_general_unknown_entity`. Ein zweiter Test hält diese Gegenprobe fest.
- **`/api/multiupdate` meldet im Erfolgsfall, was es getan hat** — schon mit `000-000-0009`
  erledigt, weil die Transaktion sonst nichts über ihr Ergebnis gesagt hätte.

Der Fehlerfall aus `000-000-0006` ist **getrennt** entschieden und zuerst erledigt worden: Dort
ging es darum, dass die richtige Antwort überhaupt ankommt, hier darum, welche Form sie hat. Ein
falscher Statuscode ist ein Defekt, eine uneinheitliche Form ein Vertrag.

### Ein Fund am Rande

Der `ApiResponseService`, den der Task als Gegenbeispiel nennt, trug noch Kommentare aus dem
fremden Projekt, aus dem er stammt: „for Usabiq", „ngx-translate", „Task 119". Das ist derselbe
Befund, den `000-000-0017` in der Beispiel-Entity behoben hat — diese Datei war dort nicht
dabei. Da sie hier ohnehin als Vorbild bewertet wurde, ist sie mitgereinigt; im ganzen Baum
steht jetzt keine dieser drei Zeichenketten mehr.

### Offen und bewusst nicht getan

Die Umsetzung selbst braucht ein Arbeitspaket unter Epic `011`. Es anzulegen heisst, eine
Story in einem fremden Epic zu schneiden, und das gehört nicht in einen Ad-hoc-Task — das
Erfolgskriterium im Epic hält es fest, bis jemand `011` aufmacht.

### Nachweis

| | |
|---|---|
| Volle Suite | `OK (247 tests, 614 assertions)`, 0 übersprungen |
| Vorher | 246 Tests |
| Umgedreht | `testEineLeereListeKommtAls404()` → `testEineLeereListeKommtAls200()` |
| Neu | `testEineUnbekannteEntityBleibtEin404MitBegruendung()` |

Zwei Einträge in `an_project/docs/breaking-changes.md`, ein neues Dokument
`an_project/docs/api-envelope.md`.
