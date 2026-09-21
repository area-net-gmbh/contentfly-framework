---
id: 000-000-0053
title: Die API-Dokumentation auf Contentfly 2 bringen
status: review
depends_on: []
---

# Die API-Dokumentation auf Contentfly 2 bringen

## Context
**Benannt in der Bilanz von Epic `011` und in `dev-guide.md` (`011-003-0003`), aber nie ticketiert.**
Die API-Doku wird aus den `@api`-Annotationen erzeugt — und die beschreiben ein Contentfly, das es
nicht mehr gibt.

**Der Befund, gezählt am 2026-09-17:**

| | |
|---|---|
| `@api`-Blöcke | **27**, alle in `lib/contentfly/Controller/` (`ApiController` 21, `AuthController` 3, `FileController` 2, `SystemController` 1) |
| Antwortbeispiele | **18** `@apiSuccessExample` / `@apiErrorExample` |
| Was sie zeigen | `"message"`, `"lastModified"`, `"dataCount"`, `"devmode"` — also die Antwortformen **vor** `011-001` |

Ein Beispiel nennt sogar `"version": "1.4.0"`. Ein anderes schreibt `"data:"` mit dem Doppelpunkt
**innerhalb** der Anführungszeichen — das Beispiel ist kein gültiges JSON und war es nie.

**Warum das mehr ist als Kosmetik:** Seit `011-001` antwortet **jeder** Endpunkt mit
`data`/`errors`/`meta`. Wer die erzeugte Doku liest, baut einen Client gegen ein Format, das das
Framework nicht mehr spricht — und merkt es erst am ersten Aufruf. Eine Dokumentation, die falsch
ist, ist schlechter als keine: Sie wird geglaubt.

**Zwei weitere Einschränkungen**, beide schon in `dev-guide.md` benannt:

- Der Generator liest **nur** `lib/contentfly/Controller/`. Routen eines Projekts aus `custom/`
  sind nicht enthalten — auch der `ExampleController` der Vorlage nicht.
- `dev-guide.md` nennt `ant apidoc` als Befehl. `build.xml` liegt noch im Repo, aber Ant ist der
  Build von Contentfly **1.x**; der README führt ihn ausdrücklich unter *Historie*. Zu klären, ob
  der Aufruf so bleibt oder der direkte `apidoc -i …` die einzige genannte Fassung wird.

## Zu entscheiden
- **Ob `custom/` mitgelesen wird.** Dafür spricht, dass die Vorlage zeigen soll, wie es geht;
  dagegen, dass die erzeugte Doku dann Beispiel-Routen enthält, die kein echtes Projekt hat.
- **Was mit `build.xml` geschieht** — nachziehen oder entfernen. Nicht Teil dieses Tasks, aber die
  Antwort entscheidet, was in `dev-guide.md` als Befehl steht.

## Acceptance criteria
- [x] Alle 18 Antwortbeispiele zeigen den Envelope aus `011-001` — `data` / `errors` / `meta`, Fehler mit den vier festen Schlüsseln.
- [x] Kein Beispiel nennt mehr eine 1.x-Version oder ein Feld, das es nicht mehr gibt (`message`, `devmode`, `lastModified`, `dataCount` auf oberster Ebene).
- [x] Jedes Beispiel ist **gültiges JSON** — maschinell geprüft, nicht gelesen. Das `"data:"` von heute wäre daran gescheitert.
- [x] Die Entscheidung zu `custom/` ist getroffen und in `dev-guide.md` festgehalten; die beiden Einschränkungen dort stimmen danach mit dem Stand überein.
- [x] Die Doku ist einmal erzeugt worden, und der erzeugte Stand ist gegen einen echten Aufruf gehalten.

## Verification
`apidoc -i lib/contentfly/Controller/` läuft durch. Für mindestens drei Endpunkte — einen aus
`ApiController`, `AuthController::login` und einen Fehlerfall — wird die erzeugte Antwort gegen
den echten Aufruf gehalten: Was in der Doku steht, muss der Server auch liefern.

**Ein Gate wäre hier angebracht** und ist zu erwägen: `EnvelopeApiTest` kennt die echte Form
bereits. Ein Test, der jedes `@apiSuccessExample` als JSON parst und auf die drei Schlüssel prüft,
würde verhindern, dass die Doku ein zweites Mal davonläuft — dieselbe Regel wie bei den anderen
Gates dieses Projekts.

## Ergebnis (2026-09-21)
**Jedes Beispiel zeigt jetzt, was der Server liefert — abgegriffen vom laufenden Testserver, nicht
aus dem Gedächtnis geschrieben.** Ein Block je Endpunkt, 21 statt 27, alle mit `@apiVersion 2.0.0`.

### Entschieden
| Frage | Antwort | Grund |
|---|---|---|
| `custom/` mitlesen? | **nein** | Die Vorlage hat dort keinen `@api`-Block, und Beispielrouten gehören nicht in die Doku des Frameworks. Ein Projekt ergänzt `-i custom/Controller/` — steht in `dev-guide.md`. |
| `ant apidoc` oder direkter Aufruf? | **nur `npx apidoc@1 -i lib/contentfly/Controller/ -o build/apidoc`** | Ant ist der Build von 1.x; `npx` erspart die globale Installation. Was aus `build.xml` wird, bleibt offen. |
| Die 1.x-Fassungen je Endpunkt (`1.4.2` neben `1.5.x`)? | **entfernt** | Sechs Blöcke, die nur als Versionshistorie existierten, mit Antwortformen, die es nicht mehr gibt. |

### Umgesetzt
- **Antwortbeispiele:** 18 alte, jetzt 22 Erfolgs- und 3 Fehlerbeispiele. Die Fehlerform war vorher
  gar nicht dokumentiert. Die Statuszeile steht vor dem Rumpf, und jedes Beispiel ist mit `{json}`
  typisiert.
- **Anfragebeispiele:** 10 von 23 waren kein gültiges JSON — fehlende schliessende Klammern,
  `"subtitle: "`, `//`-Kommentare, einfache Anführungszeichen, abschliessende Kommata. Die
  Kommentare stehen jetzt in der Beschreibung.
- **Befund über den Auftrag hinaus, gleich mit behoben:** Die Doku nannte als Token-Header
  `APPMS-TOKEN` (`/api/*`) und `X-Token` (`/auth/*`, `/file/*`, `/system/do`). **Der Server
  beantwortet beide mit `401`** — gemessen, `appcms-token` und `Authorization: Bearer` liefern `200`.
  Wer nach der Doku baute, kam nicht einmal bis zur falschen Antwortform.
- **Drei falsche Beschreibungen:** `/api/update` hiess „adding a new object", `/api/replace`
  beschrieb Einfügen und Ändern vertauscht, `/api/list` nannte `404` für „keine Einträge" — seit
  `000-000-0014` ist das `200` mit leerer Liste; `404` heisst unbekannte Entity.
- **`/api/deleted`** liefert `model_name`/`model_id`, nicht `entity_name`/`id`, wie das alte
  Beispiel sagte.

### Das Gate
`tests/Unit/ApiDocExamplesTest.php`, ohne Datenbank, also in jedem Lauf dabei: jedes
`{json}`-Beispiel wird geparst, jedes Antwortbeispiel muss der Envelope sein, keine 1.x-Version,
nur Header, die `TokenSources` kennt. **Gegenprobe:** auf den Controllern von `master` 4 von 5
Tests rot.

### Verifiziert
- `npx apidoc@1 -i lib/contentfly/Controller/ -o build/apidoc` läuft durch. Die erzeugte Doku
  enthält kein `"data:"`, kein `1.4.0`, keinen der beiden falschen Header.
- **Gegen echte Aufrufe gehalten — 14 Beispiele statt der verlangten drei**, darunter
  `AuthController::login` (opak, JWT, `401`), `/api/list` (Seite, Zählung, `404`) und `/api/single`
  (`404`). Verglichen wurden Statuscode, Schlüssel auf oberster Ebene, `meta`-Schlüssel,
  `data`-Schlüssel und der Fehlercode. 13 identisch; `/api/query` weicht nur ab, weil das Beispiel
  eine Entity `Product` zeigt, die die Vorlage nicht hat — die Form ist dieselbe.
- Volle Suite 704 grün (3 übersprungen), PHPStan `[OK] No errors`.

### Offen, nicht Teil dieses Tasks
- apidoc 1.x warnt, dass `@apiParam` bei `POST` nicht in der URL steht, und erwartet `@apiBody`.
  Das ist eine Formsache, die Doku entsteht vollständig.
- `POST /api/translations` antwortet für eine Entity ohne Übersetzung mit **`500`**
  (`contentfly_general_invalid_base_entity`) — eine falsche Eingabe des Clients, eher ein `400`.
  Beim Abgreifen der Beispiele aufgefallen, nicht untersucht.
