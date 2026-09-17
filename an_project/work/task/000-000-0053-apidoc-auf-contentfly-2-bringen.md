---
id: 000-000-0053
title: Die API-Dokumentation auf Contentfly 2 bringen
status: todo
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
- [ ] Alle 18 Antwortbeispiele zeigen den Envelope aus `011-001` — `data` / `errors` / `meta`, Fehler mit den vier festen Schlüsseln.
- [ ] Kein Beispiel nennt mehr eine 1.x-Version oder ein Feld, das es nicht mehr gibt (`message`, `devmode`, `lastModified`, `dataCount` auf oberster Ebene).
- [ ] Jedes Beispiel ist **gültiges JSON** — maschinell geprüft, nicht gelesen. Das `"data:"` von heute wäre daran gescheitert.
- [ ] Die Entscheidung zu `custom/` ist getroffen und in `dev-guide.md` festgehalten; die beiden Einschränkungen dort stimmen danach mit dem Stand überein.
- [ ] Die Doku ist einmal erzeugt worden, und der erzeugte Stand ist gegen einen echten Aufruf gehalten.

## Verification
`apidoc -i lib/contentfly/Controller/` läuft durch. Für mindestens drei Endpunkte — einen aus
`ApiController`, `AuthController::login` und einen Fehlerfall — wird die erzeugte Antwort gegen
den echten Aufruf gehalten: Was in der Doku steht, muss der Server auch liefern.

**Ein Gate wäre hier angebracht** und ist zu erwägen: `EnvelopeApiTest` kennt die echte Form
bereits. Ein Test, der jedes `@apiSuccessExample` als JSON parst und auf die drei Schlüssel prüft,
würde verhindern, dass die Doku ein zweites Mal davonläuft — dieselbe Regel wie bei den anderen
Gates dieses Projekts.
