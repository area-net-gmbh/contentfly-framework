---
id: 011-001-0001
title: Bestandsaufnahme der Antwortformen und Geltungsbereich des Envelopes
status: review
depends_on: []
---

# Bestandsaufnahme der Antwortformen und Geltungsbereich des Envelopes

## Context
`api-envelope.md` entscheidet die Zielform (`data` / `errors` / `meta`) und führt eine Tabelle für
zehn `/api/*`-Endpunkte plus den Fehlerfall. Der Baum hat aber mehr Antwortstellen: `/api/*` läuft
über **einen** Trichter (`ApiController::renderResponse()`, 18 Aufrufe), die Fehlerform entsteht im
Handler in `bootstrap-web.php`, und `/auth`, `/file` und `/system` bauen ihre Antworten je selbst
(`new JsonResponse(...)`: AuthController 10, FileController 2, SystemController 1).

**Die offene Frage ist der Geltungsbereich.** Gilt der Envelope nur für `/api/*` — dann hat die API
danach zwei Formen statt sieben, aber eben zwei — oder auch für Anmeldung, Dateien und
Systemaufrufe? Das entscheidet den Umfang von `0002` bis `0004` und den Umfang des Bruchs für
Clients.

Ohne diese Erhebung ist jede Umstellung ein Stochern: Die Endpunkte, die `api-envelope.md` nicht
nennt (`/api/tree`, `/api/tree2`, `/api/translations`, `/api/query`, `/api/replace`, `/api/mail`,
`/api/schema`), fehlen dort nur, weil Epic `008` sie nicht charakterisiert hat.

## Acceptance criteria
- [x] Jede Antwortstelle des Frameworks ist erhoben: Endpunkt, heutige Form, Fundstelle (Datei:Zeile) — aus dem Code, nicht aus der Erinnerung.
- [x] Zu jedem Endpunkt steht die Zielform fest; die Tabelle in `api-envelope.md` ist auf **alle** Endpunkte erweitert.
- [x] Der Geltungsbereich ist entschieden und begründet: `/api/*` allein, oder samt `/auth`, `/file`, `/system`.
- [x] Festgehalten, welche Tests die heutige Form halten (Datei, Testname) — die Liste ist die Arbeitsgrundlage für `0002` und `0003`.
- [x] Kein Code geändert.

## Verification
Ein Abgleich: Jede `JsonResponse` und jeder `renderResponse`-Aufruf in `lib/` taucht in der Tabelle
auf. `git grep -c "new JsonResponse\|renderResponse("` gegen die Zahl der Zeilen im Dokument.

## Ergebnis

**Erhoben, nicht erinnert:** Ein Skript hat jede Stelle gezählt, die eine Antwort baut —
`renderResponse()`, `new JsonResponse(...)` und `$app->json(...)` in `lib/contentfly` —, jede ihrer
Methode zugeordnet und jede Methode ihrer Route aus den Providern.

**32 JSON-Antwortstellen in vier Controllern** — davon ist eine der Trichter selbst
(`renderResponse`, `ApiController:743`), also 31 Aufrufer —, dazu der Fehlerhandler und die
`204`-Antwort auf `OPTIONS`:

| Gruppe | Stellen | Lage |
|---|---|---|
| `/api/*` | 19 (18 Aufrufer + der Trichter) | `ApiController::renderResponse()` (`:740`) |
| `/auth/*` | 10 | je eigenes `JsonResponse` |
| `/file/*` | 2 | je eigenes |
| `/system/do` | 1 | eigenes |
| Fehler | 1 | `bootstrap-web.php:174–190`, drei Zweige je nach Ausnahme |

**Entschieden am 2026-09-16: Der Envelope gilt für jeden JSON-Endpunkt** — `/api/*`, `/auth/*`,
`/file/upload`, `/file/overwrite`, `/system/do`. Zwei Formen wären keine Vereinheitlichung; das
Erfolgskriterium des Epics lautet, dass ein Client jeden Endpunkt mit demselben Code auswertet. Der
Preis ist benannt: Auch der Anmelderumpf ändert sich, und zwölf Tests hängen daran. Nicht betroffen
sind `/file/get/...` (liefert die Datei selbst) und die `204` auf einen Preflight (kein Rumpf).

**Die Zieltabelle steht jetzt vollständig** in `an_project/docs/api-envelope.md`, Abschnitt
*Geltungsbereich und vollständige Zieltabelle*: 25 Zeilen, je mit Antwortstelle, heutiger Form und
Zielform, plus die Zuordnung, welche Tests die heutige Form halten.

**Vier Korrekturen an dem, was das Dokument bisher sagte:**

1. **`/api/mail` ist nicht mehr gemountet** — die Zeile in der alten Tabelle beschreibt einen
   Endpunkt, den es nicht gibt.
2. **`/api/replace` hat keine eigene Antwort.** Es delegiert per Sub-Request an `insert`/`update`
   (`ApiController:852`) und erbt deren Form.
3. **Fünf Endpunkte fehlten** in der alten Tabelle, weil Epic `008` sie nicht charakterisiert hat:
   `/api/tree`, `/api/tree2`, `/api/translations`, `/api/query`, `/api/schema`.
4. **`/api/list` hat drei Formen, nicht eine** — Zählung, Seite, ohne Seite.

**Zwei Befunde nebenbei, beide für `0002` zu entscheiden:**

- **`/api/config` verliert die Projektversion.** Die Aktion übergibt `APP_VERSION.'/'.CUSTOM_VERSION`,
  `renderResponse()` überschreibt den Schlüssel danach mit `APP_VERSION` (`:143` gegen `:741`).
- **`/api/all` antwortet `204` bei leerer Menge**, also ohne Rumpf und ohne Envelope — `/api/list`
  hat genau das mit `000-000-0014` abgelegt.

**Kein Code geändert** (`git status`: nur `api-envelope.md`, die Task-Datei und `CHANGELOG.md`).

## Verification

Gegenprobe der Vollständigkeit: `git grep -c "new JsonResponse\|renderResponse("` über `lib/` ergibt
dieselben 32 Stellen wie die Tabelle; von den 22 Aktionen mit Route haben genau zwei keine eigene
JSON-Antwort — `replaceAction` (delegiert) und `getAction` (liefert die Datei).
