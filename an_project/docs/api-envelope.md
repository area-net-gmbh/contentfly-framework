# Der Antwort-Envelope der API

> Entschieden mit `000-000-0014`. Dieses Dokument hält fest, **welche Form** die API künftig
> hat und **wann** sie sie bekommt.
>
> **Stand 2026-09-16:** Die Erfolgsantworten unter `/api/*` sind umgestellt (`011-001-0002`).
> Offen sind die Fehlerantworten (`011-001-0003`) sowie `/auth/*`, `/file/*` und `/system/do`
> (`011-001-0004`). Siehe *Was schon gilt*.

## Der Befund

Epic `008` hat sieben Endpunkte charakterisiert und sieben verschiedene Antwortformen gefunden.

| Endpunkt | Envelope heute |
|---|---|
| `/api/single` | `ts`, `data`, `version`, `hash` |
| `/api/list` | `data`, `totalItems`, `version`, `hash` |
| `/api/insert` | `ts`, `id`, `data`, `version`, `hash` |
| `/api/delete` | `ts`, `id`, `version`, `hash` |
| `/api/all` | `lastModified`, `data`, `version`, `hash` |
| `/api/multiupdate` | `ts`, `data`, `version`, `hash` *(seit `000-000-0009`)* |
| `/api/config` | `frontend`, `devmode`, `version`, `hash` |

Nur `version` und `hash` sind überall gleich. Ein Client kann keine gemeinsame Auswertung
schreiben; er braucht für jeden Endpunkt eine eigene.

## Die Entscheidung

**Vereinheitlicht wird — aber beim Release, in Epic `011`, nicht jetzt und nicht beim
Kernel-Wechsel.**

Der Grund ist die Reihenfolge, nicht der Aufwand. Die Charakterisierungstests aus Epic `008`
sind die Abnahmegrundlage für den Kernel-Wechsel: Sie beschreiben, was die Anwendung heute tut,
damit man nach dem Tausch vergleichen kann. Ändert man den Vertrag **gleichzeitig** mit dem
Kernel, sind beide Seiten des Vergleichs neu — und man weiß hinterher nicht, ob eine Abweichung
vom Umbau kommt oder von der Absicht. `an_project/docs/technical.md` sagt es als Regel: „Eine
Testanpassung ist ein Verhaltenswechsel und braucht eine Begründung."

Also: erst der Kernel unter unverändertem Vertrag (Epic `009`), dann der Vertrag als **eine**
bewusste Bruchstelle mit vollständigem Migrationsleitfaden (Epic `011`). Das passt auch dazu,
dass `011` ohnehin eine neue Hauptversion vergibt — ein Bruch braucht eine Versionsnummer, an
der man ihn festmachen kann.

## Ist der `ApiResponseService` der Vorlage das Vorbild?

**Als Vorbild ja, wörtlich nein.**

`custom/Classes/Service/Core/ApiResponseService.php` antwortet mit `success`, `status`, `i18n`,
`data`, `errors`, `meta`, `timestamp`. Was daran richtig ist und übernommen wird:

- **Erfolg und Fehler haben dieselbe Form.** Ein Client muss nicht zwei Formate können, um zu
  erfahren, dass etwas schiefging.
- **`data` ist immer da**, auch wenn es `null` ist. Ein fehlender Schlüssel zwingt jeden Client
  zu einer Fallunterscheidung.
- **`meta` sammelt, was nicht Nutzlast ist** — `totalItems`, `itemsPerPage`, `lastModified`,
  `version`, `hash`. Diese Dinge stehen heute neben den Daten und sehen aus wie Daten.
- **`errors` ist eine Liste**, nicht eine Zeichenkette. Ein Formular hat mehr als einen Fehler.

Was nicht übernommen wird:

- **`success` und `status`.** Beide wiederholen den HTTP-Statuscode im Rumpf. Damit gibt es zwei
  Quellen für dieselbe Aussage, die auseinanderlaufen können — und irgendwann läuft eine davon
  auseinander. Der Statuscode steht im Statuscode.
- **`i18n`.** Ein Übersetzungsschlüssel samt Platzhaltern ist eine Anforderung des Projekts, das
  diesen Service mitbringt, nicht des Frameworks. Wer ihn braucht, legt ihn in `errors` ab.

## Die Zielform

```json
{
  "data":   …,
  "errors": null,
  "meta": {
    "ts":      "2026-09-09 14:03:11",
    "version": "…",
    "hash":    "…"
  }
}
```

Je Endpunkt, vorher → nachher:

| Endpunkt | vorher | nachher |
|---|---|---|
| `/api/single` | `ts`, `data`, `version`, `hash` | `data`; `ts`/`version`/`hash` unter `meta` |
| `/api/list` | `data`, `totalItems`, `version`, `hash` | `data`; `totalItems`, `itemsPerPage`, `ts`, `version`, `hash` unter `meta` |
| `/api/insert` | `ts`, `id`, `data`, `version`, `hash` | `data` (enthält die `id`); `ts`/`version`/`hash` unter `meta` |
| `/api/delete` | `ts`, `id`, `version`, `hash` | `data` = `{"id": …}`; Rest unter `meta` |
| `/api/all` | `lastModified`, `data`, `version`, `hash` | `data`; `lastModified` unter `meta` |
| `/api/multiupdate` | `ts`, `data`, `version`, `hash` | unverändert in der Bedeutung, `ts`/`version`/`hash` unter `meta` |
| `/api/deleted` | `ts`, `data`, `version`, `hash` | `data`; Rest unter `meta` |
| `/api/count` | `ts`, `data`, `version`, `hash` | `data`; Rest unter `meta` |
| `/api/config` | `frontend`, `devmode`, `version`, `hash` | `data` = `{"devmode": …}`; Rest unter `meta` |
| Fehlerfall | `message`, `type`, `message_value`, `status` | `data` = `null`, `errors` = `[{code, detail, …}]`, `meta` |

## Geltungsbereich und vollständige Zieltabelle

> Erhoben und entschieden mit `011-001-0001` (2026-09-16). Die Tabelle oben stammt aus `000-000-0014`
> und führt zehn Endpunkte — erhoben wurden **32 JSON-Antwortstellen in vier Controllern** (eine davon
> der Trichter `renderResponse` selbst), dazu der Fehlerhandler und die `204`-Antwort auf `OPTIONS`.

**Entschieden: Der Envelope gilt für jeden JSON-Endpunkt des Frameworks** — `/api/*`, `/auth/*`,
`/file/upload`, `/file/overwrite` und `/system/do`. Zwei Formen wären keine Vereinheitlichung: Das
Erfolgskriterium des Epics lautet, dass ein Client **jeden** Endpunkt mit demselben Code auswertet.
Der Preis ist benannt — auch der Anmelderumpf ändert sich, und zwölf Tests hängen daran.

**Nicht betroffen:** `/file/get/...` liefert die Datei selbst (`RedirectResponse` bzw.
`StreamedResponse`), und die `204`-Antwort auf einen `OPTIONS`-Preflight hat keinen Rumpf.

### Zwei Korrekturen an der Tabelle oben

- **`/api/mail` gibt es nicht mehr** — die Route ist nicht gemountet.
- **`/api/replace` hat keine eigene Antwort.** Es delegiert per Sub-Request an `insert` bzw. `update`
  und erbt deren Form; mit ihnen ist es miterledigt.

### Die Endpunkte, vollständig

| Endpunkt | Antwortstelle | heute | Zielform |
|---|---|---|---|
| `POST /api/all` | `ApiController:107` | `lastModified`, `data` (+ `version`, `hash`; `204` bei leerer Menge) | `data`; `lastModified` unter `meta` |
| `GET /api/config` | `:143` | `devmode`, `version`, `hash` | `data` = `{devmode}`; Rest unter `meta` |
| `POST /api/count` | `:204` | `ts`, `data` | `data`; `ts` unter `meta` |
| `POST /api/delete` | `:257` | `ts`, `id` | `data` = `{id}`; Rest unter `meta` |
| `POST /api/deleted` | `:290` | `ts`, `data` | `data`; Rest unter `meta` |
| `POST /api/insert` | `:369` | `ts`, `id`, `data` | `data` (enthält die `id`); Rest unter `meta` |
| `POST /api/list` (Zählung) | `:540` | `data` = Anzahl | `data` = Anzahl; Rest unter `meta` |
| `POST /api/list` (Seite) | `:551` | `data`, `itemsPerPage`, `totalItems`, optional `lastModified` | `data`; `itemsPerPage`, `totalItems`, `lastModified` unter `meta` |
| `POST /api/list` (ohne Seite) | `:559` | `data`, `totalItems`, optional `lastModified` | dito |
| `POST /api/multiupdate` | `:631` | `ts`, `data` | `data`; Rest unter `meta` |
| `POST /api/update` | `:736` | `ts`, `id` | `data` = `{id}`; Rest unter `meta` |
| `GET /api/schema` | `:893` | das Schema **auf oberster Ebene**, plus `version`, `hash` | `data` = Schema; Rest unter `meta` |
| `POST /api/single` | `:1000` | `ts`, `data` | `data`; Rest unter `meta` |
| `POST /api/tree` | `:1093` | `ts`, `data` | `data`; Rest unter `meta` |
| `POST /api/tree2` | `:1143` | `ts`, `data` | `data`; Rest unter `meta` |
| `POST /api/translations` | `:1183` | `data` | `data`; `meta` wie überall |
| `POST /api/query` | `:1240` | `ts`, `params`, `data` | `data`; `params` und `ts` unter `meta` |
| `POST /api/replace` | — | erbt von `insert`/`update` | erbt mit |
| `POST /auth/login` | `AuthController:248` (Erfolg) | `message`, `token`, `user`, optional `data`, `refreshToken`, `expiresIn` | `data` = `{token, user, …}`; `message` entfällt (der Statuscode sagt es) |
| `POST /auth/refresh` | `:446` | `message`, `token`, `refreshToken`, `expiresIn` | `data` = `{token, refreshToken, expiresIn}` |
| `GET /auth/logout` | `:526` | `message` | `data` = `null` |
| `POST /file/upload` | `FileController:276` | `message`, `data` | `data` |
| `POST /file/overwrite` | `:543` | `message`, `sourceId`, `destId` | `data` = `{sourceId, destId}` |
| `POST /system/do` | `SystemController:97` | `method`, `datetime`, `message` | `data` = `{method, message}`; `datetime` unter `meta` |
| Fehlerfall (alle) | `bootstrap-web.php:174–190` | `message`, `type`, `status`, je nach Ausnahme `message_value`, `message_entity`, `message_lang` | `data` = `null`, `errors` = Liste, `meta`; `status` entfällt |

### Was die Erhebung nebenbei gefunden hat

- **`/api/config` verliert die Projektversion.** Die Aktion übergibt `APP_VERSION.'/'.CUSTOM_VERSION`,
  und `renderResponse()` überschreibt den Schlüssel danach mit `APP_VERSION` (`ApiController:143`
  gegen `:741`). Der Client sieht die Projektversion nie. Mit der Umstellung ist zu entscheiden, ob
  `meta.version` beide trägt.
- **`/api/all` antwortet `204` bei leerer Menge** — ohne Rumpf, also auch ohne Envelope. `/api/list`
  hat genau das mit `000-000-0014` abgelegt (leere Menge ist `200` mit leerer Liste). Beim Umstellen
  ist zu entscheiden, ob `/api/all` nachzieht.

### Welche Tests die heutige Form halten

| Endpunktgruppe | Tests |
|---|---|
| `/api/*` | `ReadApiTest`, `WriteApiTest`, `QueryApiTest`, `TreeApiTest`, `SyncApiTest`, `ManyToManyApiTest`, `MultiupdateApiTest`, `UpdateReplaceApiTest`, `ConstraintApiTest`, `ReadPermissionApiTest`, `WritePermissionApiTest`, `UnenforcedPermissionApiTest` |
| `/auth/*` | zwölf Dateien, allen voran `AuthApiTest`, `LoginProviderApiTest`, `LoginThrottleApiTest`, `LoginEventApiTest` |
| `/file/*`, `/system/do` | `FileApiTest`, `SystemControllerApiTest` |
| Fehlerform | `ErrorResponseApiTest`, `RouteSecurityApiTest` |

Jede Anpassung dieser Erwartungen ist ein Verhaltenswechsel und trägt ihre Begründung im Test —
`an_project/docs/technical.md`.

## Was schon gilt

**Seit `011-001-0002` (2026-09-16): jede Erfolgsantwort unter `/api/*`.** Siebzehn Antwortstellen,
eine Form. Gebaut wird sie an genau einer Stelle — `ApiController::renderResponse()` nimmt jetzt
Nutzlast, Statuscode und die Meta-Zusätze des Endpunkts entgegen, statt ein fertiges Array
durchzureichen. Eine Aktion kann damit keine achte Form mehr erfinden.

Drei Entscheidungen sind dabei gefallen, die über das Umhängen von Schlüsseln hinausgehen:

- **`meta.version` und `meta.projectVersion` statt einer Zeichenkette.** `/api/config` hat die
  Projektversion bisher **nie ausgeliefert** — die Aktion übergab `APP_VERSION.'/'.CUSTOM_VERSION`,
  der Trichter überschrieb den Schlüssel danach. Zwei Felder, weil ein Client, der Versionen
  vergleicht, keine Zeichenkette zerlegen sollte.
- **`/api/all` antwortet auf eine leere Menge mit `200` statt `204`.** Eine `204` hat keinen Rumpf
  und damit keinen Envelope.
- **`/api/schema`: `data` ist das Schema.** `permissions`, `i18nPermissions`, `frontend` und
  `devmode` beschreiben die Antwort, statt sie zu sein, und liegen in `meta` — dort, wo der
  Schema-Hash immer schon lag. `body.data` bedeutet damit unverändert dasselbe wie vorher.

Im Test steht die Form an einer Stelle: `IntegrationTestCase::assertEnvelope()` prüft `data`,
`errors` und die vier Standard-Meta-Felder bei **jedem** Aufruf; ein Test nennt nur noch, was sein
Endpunkt zusätzlich in `meta` legt. Vorher schrieb jeder Test die Schlüsselliste seines Endpunkts
aus — und schrieb damit genau das fest, was hier abgeschafft wird.

Zwei Punkte aus `000-000-0014` liessen sich schon vorher nicht sinnvoll aufschieben, weil sie keine
Formfragen sind, sondern Defekte:

- **`/api/multiupdate` meldet den Erfolg** — erledigt mit `000-000-0009`. Der Aufruf lieferte
  vorher `version` und `hash` und sonst nichts; jetzt `ts` und eine Liste der geänderten
  Objekte.
- **`/api/list` unterscheidet „leere Menge" von „unbekannte Route"** — erledigt hier. Eine
  bekannte Entity ohne Treffer antwortet mit `200` und einer leeren Liste; vorher kam
  `404 {"message":"Not found"}`, dieselbe Antwort wie für eine Entity, die es nicht gibt.

Die Fehlerantworten selbst sind mit `000-000-0006` in Ordnung gebracht — Statuscodes, die
stimmen, und ein Rumpf, der aus der Anwendung kommt statt aus Symfonys Notfallseite. Ihre
**Form** ändert sich erst mit dem Rest.

## Zusammenhang mit anderen Tasks

- `000-000-0006` — Fehlerantworten. **Getrennt entschieden und zuerst erledigt:** Dort ging es
  darum, dass die richtige Antwort überhaupt ankommt; hier darum, welche Form sie hat. Ein
  falscher Statuscode ist ein Defekt, eine uneinheitliche Form ein Vertrag.
- `000-000-0009` — `/api/multiupdate`. Der Erfolgsfall ist dort schon auswertbar geworden, weil
  die Transaktion sonst nichts gemeldet hätte, was sie getan hat.
- Epic `007` — der Migrationsleitfaden übernimmt die Tabelle *vorher → nachher* aus diesem
  Dokument.
