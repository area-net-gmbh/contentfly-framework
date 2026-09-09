# Der Antwort-Envelope der API

> Entschieden mit `000-000-0014`. Dieses Dokument hält fest, **welche Form** die API künftig
> hat und **wann** sie sie bekommt. Umgesetzt ist bisher nur der eine Teil, der ohne den Rest
> Sinn ergibt — siehe *Was schon gilt*.

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

## Was schon gilt

Zwei Punkte aus `000-000-0014` liessen sich nicht sinnvoll aufschieben, weil sie keine
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
