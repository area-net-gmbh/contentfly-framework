---
id: 011-001-0004
title: Restliche Endpunkte und Abnahme des Envelopes
status: review
depends_on: [011-001-0003]
---

# Restliche Endpunkte und Abnahme des Envelopes

## Context
Was `0001` als Geltungsbereich entschieden hat und in `0002`/`0003` noch nicht umgestellt ist,
kommt hier — je nach Entscheidung `/auth`, `/file`, `/system`, oder nichts davon.

Dann die Abnahme, und die ist der eigentliche Zweck des Epics: **Ein Client muss jeden Endpunkt
mit demselben Code auswerten können**, Erfolg wie Fehler. Solange das nicht an einem Stück
gemessen ist, bleibt die Vereinheitlichung eine Behauptung.

## Acceptance criteria
- [x] Die restlichen Endpunkte aus dem Geltungsbereich antworten in der Zielform — oder es ist festgehalten, dass sie bewusst aussen bleiben, mit Begründung im Register.
- [x] Ein Test wertet **jeden** Endpunkt des Geltungsbereichs mit einer einzigen Auswertung aus: `data`, `errors`, `meta` — je einmal im Erfolgs- und im Fehlerfall.
- [x] `api-envelope.md` beschreibt den erreichten Zustand, nicht mehr den geplanten.
- [x] `breaking-changes.md` und `migration.md` tragen den Bruch vollständig; die Zahl im Kopf des Leitfadens stimmt.
- [x] Volle Suite, PHPStan, Deprecation-Gate grün.

## Verification
Der Abnahmetest über alle Endpunkte. Dazu ein Blick aus Clientsicht: die Tabelle *vorher → nachher*
im Leitfaden gegen die tatsächlichen Antworten.

## Ergebnis

**Der Envelope ist vollständig.** Es gibt keinen JSON-Endpunkt des Frameworks mehr ausserhalb von
`data` / `errors` / `meta` — im Erfolgs- wie im Fehlerfall.

### Der Trichter steht jetzt in der Basisklasse

`renderResponse()` lag seit `0002` in `ApiController` und hatte ihn für sich. Es gibt aber **vier**
Controller, und die anderen drei bauten ihre Antwort selbst: `message` und `token` hier, `message`
und `data` dort, `method`, `datetime` und `message` im dritten. Solange die Methode in einem der
vier wohnte, hatten die anderen drei einen Grund, weiter zu improvisieren. Sie steht darum in
`BaseController`, zusammen mit `renderError()` für die Ablehnungen, die ein Controller selbst
entscheidet.

### Was umgestellt wurde

| Endpunkt | vorher | jetzt |
|---|---|---|
| `POST /auth/login` | `message`, `token`, `user`, optional `data`, `refreshToken`, `expiresIn`, `schema`, `hash` | `data` = `{token, user, …}` |
| `POST /auth/refresh` | `message`, `token`, `refreshToken`, `expiresIn` | `data` = `{token, refreshToken, expiresIn}` |
| `GET /auth/logout` | `message` | `data` = `null` |
| `POST /file/upload` | `message`, `data` | `data` = das Dateiobjekt |
| `POST /file/overwrite` | `message`, `sourceId`, `destId` | `data` = `{sourceId, destId}` |
| `POST /system/do` | `method`, `datetime`, `message` | `data` = `{method, message}` |

`message` entfällt überall — „Login successful", „File uploaded", „File overwritten",
„Logout successful". Sätze, die ein Client nur wörtlich vergleichen konnte, neben einem `200`, das
dasselbe schon sagte.

### Fünf Entscheidungen, die mehr sind als ein Umhängen

- **`/auth/login`: aus `data` wird `tempData`.** Was ein Projekt dem Client bei der Anmeldung
  mitgibt, hiess `data` — unter dem Envelope wäre das `body.data.data` gewesen. Es heisst jetzt wie
  das Entity-Feld, aus dem es kommt. Für einen Client ist das dieselbe eine Zeile wie alles andere
  in dieser Antwort.
- **`/auth/login?withSchema` teilt genau wie `/api/schema` auf.** Beide lieferten den ganzen
  `getExtendedSchema()`-Block am Stück; `/api/schema` ist mit `0002` aufgeteilt worden. Bliebe es
  hier anders, bräuchte ein Client **zwei Leser für dasselbe Schema**, je nachdem, woher es kam —
  genau das, wogegen dieses Epic antritt. Das mitgelieferte `hash` entfällt, weil `meta.hash` es in
  jeder Antwort trägt.
- **`/system/do`: `datetime` entfällt ersatzlos.** `meta.ts` ist derselbe Wert im selben Format und
  steht in jeder Antwort. Zwei Felder für einen Zeitstempel waren eine der sieben Formen.
- **Die Ablehnungen der Anmeldung tragen endlich einen Code.** `401`, `429` und der `500` bei
  fehlender JWT-Konfiguration entstehen im `AuthController` selbst und erreichten den Fehlerhandler
  **nie** — sie waren die letzte Gruppe mit eigenem Rumpf. Vier neue `Messages`-Schlüssel geben
  ihnen einen: Bisher musste ein Client den Satz erkennen, um „warte" von „falsches Passwort" zu
  unterscheiden, denn beides ist `4xx`.

  `contentfly_general_invalid_credentials` gilt bewusst für **jeden** `401` der Anmeldung —
  unbekannter Benutzer, falsches Passwort, abgelehnter Provider. Die Antwort darf kein Orakel dafür
  sein, welche Konten existieren; ein Code je Grund hätte genau das daraus gemacht.
- **Die allerletzte Antwort ausserhalb des Envelopes ist weg.** Eine `FileNotFoundException`
  beantwortete der Fehlerhandler mit einem **Klartext-404** (`text/html`, im Rumpf nur die Meldung).
  Das traf nicht nur die Dateiauslieferung, sondern auch `/file/overwrite` — einen JSON-Endpunkt.
  Aufgefallen ist es erst am Abnahmetest, weil kein einzelner Endpunkt-Test darauf schaute. Die
  Ausnahme erbt jetzt von `ContentflyException`, geht den normalen Weg und trägt ihren
  `Messages`-Schlüssel als `code`; der `404` kommt aus ihrem Code statt aus einem
  `X-Status-Code`-Header.

### Die Abnahme — der eigentliche Zweck des Epics

`EnvelopeApiTest` läuft die Endpunkte in Schleifen durch und wertet **jede** Antwort mit derselben
Leserfunktion aus, die nichts kennt als `data`, `errors` und `meta`:

| Test | Umfang |
|---|---|
| `…EveryApiEndpointIsReadableWithTheSameCode` | 15 Erfolgsstellen unter `/api/*` |
| `…TheRemainingEndpointsAreReadableWithTheSameCode` | die sechs aus diesem Task |
| `…SuccessAndFailureAreTheSameShapeEverywhere` | 11 Fälle über alle vier Routengruppen, Erfolg **und** Fehler gemischt |
| `ErrorResponseApiTest::…EveryErrorIsReadableWithTheSameCode` | fünf Fehler verschiedener Herkunft |

Zwanzig Einzeltests hätten belegt, dass jeder Endpunkt **eine** Form hat. Nur ein Stück Code, das
alle durchläuft, belegt, dass es **dieselbe** ist — und genau das hat den Klartext-404 gefunden.

### Ein Test ist umgedreht und umbenannt

`SystemControllerApiTest::testSystemEndpointUsesItsOwnResponseShape` hielt seit `000-000-0014` den
Befund fest, dass `/system/do` und `/api/*` sich unterscheiden; mit `0002` hielt er für einen
Commit den halben Stand. Er heisst jetzt `…AnswersLikeEveryOtherEndpoint` und ist die Stelle, die
es meldet, falls ein künftiger Endpunkt wieder eine eigene Form mitbringt.

Im Basistest lesen `login()` und `createTestUser()` den Token aus `data` — daran hingen 262
Testläufe, bis die eine Zeile stimmte.

**Verifiziert:** volle Suite `Tests: 615, Assertions: 2506, Skipped: 3`, PHPStan `[OK] No errors`,
Deprecation-Gate 0.

### Damit ist Story `011-001` inhaltlich fertig

Vier Tasks: Bestandsaufnahme und Geltungsbereich, Erfolgsantworten, Fehlerantworten, Rest und
Abnahme. `api-envelope.md` beschreibt ab jetzt den erreichten Zustand, das Register trägt drei
Einträge dazu (Erfolg, Fehler, restliche Endpunkte), und der Leitfaden nennt in Phase 8 die zwei
Stellen, an denen ein nicht angepasster Client **still** das Falsche tut: `insert` (die `id` ist
weg) und die Anmeldung (`message` ist weg).
