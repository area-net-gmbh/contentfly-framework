---
id: 011-003-0001
title: Die Vorlage auf den Zielzustand bringen
status: done
depends_on: []
---

# Die Vorlage auf den Zielzustand bringen

## Context
**Die Vorlage soll zeigen, wie man es heute macht — und zeigt an der wichtigsten Stelle das
Gegenteil.** `custom/Classes/Service/Core/ApiResponseService.php` antwortet mit `success`,
`status`, `i18n`, `data`, `errors`, `meta`, `timestamp`. `an_project/docs/api-envelope.md` hat
ausdrücklich entschieden, **`success` und `status` nicht zu übernehmen** („beide wiederholen den
HTTP-Statuscode im Rumpf … der Statuscode steht im Statuscode") und `i18n` als Anforderung des
Projekts einzustufen, nicht des Frameworks.

Seit `011-001` antwortet jeder Endpunkt des Frameworks mit `data` / `errors` / `meta`. Ein Projekt,
das die Vorlage kopiert, baut damit eine API, die der des Frameworks widerspricht — genau das, was
Epic `011` beseitigt hat.

**Zwei weitere Reste derselben Art:**

- `custom/Views/partials/_email_layout.twig` ist **tot**: Es bindet `_header.twig`,
  `_workspace_bar.twig` und `_footer.twig` ein — keines davon existiert —, und Twig steht in keinem
  der beiden Manifeste; es fiel mit Epic `012` aus dem Baum. Die Datei könnte nie gerendert werden.
- `custom/Provider/` und `custom/i18n/` sind leer, werden von nichts referenziert und liegen nicht
  in Git. In einem frischen Checkout gibt es sie gar nicht — sie suggerieren eine Struktur, die
  nicht existiert. (Derselbe Befund wie bei `plugins/` in `011-002-0004`, nur mit umgekehrtem
  Ausgang: Dort fehlte ein Verzeichnis, das gebraucht wird.)

## Zu entscheiden, bevor es losgeht
**Benutzt `ApiResponseService` künftig `Classes\Envelope` des Frameworks — oder bleibt er als
Beispiel dafür stehen, dass ein Projekt eine eigene Form haben darf?**

*Empfehlung: die Framework-Klasse benutzen.* Die Vorlage ist ein Startpunkt, kein Katalog der
Möglichkeiten. Dass ein Projekt abweichen **darf**, muss sie nicht vorführen — dass es der API des
Frameworks widerspricht, kostet dagegen jeden, der sie kopiert. Der `i18n`-Schlüssel geht dabei
nicht verloren: Er zieht nach `errors[].context`, und **genau das** zu zeigen ist wertvoller als
ein zweites Format daneben.

## Acceptance criteria
- [x] Die Entscheidung oben ist getroffen und in `api-envelope.md` nachgetragen — dort steht heute nur „als Vorbild ja, wörtlich nein", und was aus dem Vorbild selbst wird, bleibt offen.
- [x] `ExampleController` und `ApiResponseService` zeigen die Form, die das Framework zusichert.
- [x] Die tote Twig-Datei ist entfernt, und der Register-Eintrag nennt es, falls ein Projekt sie kopiert hat.
- [x] Leere, von nichts referenzierte Verzeichnisse sind weg **oder** in Git und begründet — nicht beides halb.
- [x] `RouteSecurityApiTest` und `TemplateApiTest` messen die neue Form, jede Anpassung begründet.
- [x] Volle Suite, PHPStan, Deprecation-Gate grün.

## Verification
Der Beispiel-Endpunkt `/api/v1/example/bootstrap` antwortet in derselben Form wie `/api/config` —
geprüft mit **einer** Leserfunktion, wie `EnvelopeApiTest` es für die Framework-Endpunkte tut.

## Ergebnis

**Entschieden: `ApiResponseService` benutzt `Classes\Envelope`.** Nachgetragen in
`api-envelope.md`, wo der Abschnitt *Ist der `ApiResponseService` der Vorlage das Vorbild?* bisher
offenliess, was aus dem Vorbild selbst wird.

Die Begründung ist nicht Einheitlichkeit um ihrer selbst willen: **Eine Vorlage ist ein Startpunkt,
kein Katalog der Möglichkeiten.** Ein Projekt darf weiterhin antworten, wie es will — aber wer den
Startpunkt kopiert, soll eine API bekommen, die dem Framework nicht widerspricht, auf dem sie läuft.

| | vorher | jetzt |
|---|---|---|
| `success`, `status` | im Rumpf | entfallen — der Statuscode steht in der HTTP-Antwort |
| `i18n` bei Erfolg | vier Felder | entfällt — derselbe Grund wie „Login successful" in `011-001-0004` |
| `i18n` bei Fehler | vier Felder | `errors[].context`, gebaut von `ApiResponseService::fault()` |
| `timestamp` | ISO 8601 mit Millisekunden | entfällt — `meta.ts` trägt ihn |

**Der `i18n`-Schlüssel ist nicht verloren, er ist umgezogen.** Das zu zeigen ist der eigentliche
Gewinn: Ein Client, der nur den Envelope kennt, findet den Fehler; einer, der den Schlüssel kennt,
findet auch ihn. Ein zweites Format daneben hätte beides nicht geleistet.

### Was die Vorlage jetzt vorführt

`ExampleController` hat eine **echte Nutzlast** statt eines leeren Arrays, und sie hat einen Zweck:
`startedAt` zeigt, wofür `ApiDateTimeFormatter` da ist — **Daten** formatieren. Der Zeitstempel des
Envelopes ist `meta.ts` und braucht niemandes Hilfe. Dazu eine zweite Methode `exampleError()`, die
keine Route ist: Die interessantere Hälfte des Envelopes ist die, die eine Vorlage üblicherweise
weglässt.

### Drei Reste, die nichts mehr taten

- `custom/Views/partials/_email_layout.twig` band **drei nicht existierende** Partials ein, und
  Twig steht seit Epic `012` in keinem Manifest. Die Datei konnte nicht gerendert werden.
- `custom/Provider/` und `custom/i18n/` waren leer, von nichts referenziert und lagen nicht in Git —
  in einem frischen Checkout gab es sie ohnehin nicht. Sie suggerierten eine Struktur, die es nicht
  gibt. (Spiegelbild des `plugins/`-Funds aus `011-002-0004`: dort fehlte ein Verzeichnis, das
  gebraucht wird.)

### Zwei Tests umgedreht, einer umbenannt

| Test | vorher | jetzt |
|---|---|---|
| `RouteSecurityApiTest::testUnsecuredRouteRespondsWithoutToken` | hielt fest, dass die Vorlage ihre **eigene** Form bringt | prüft den Envelope |
| `TemplateApiTest::testTheExampleEndpointReturnsTheTemplateContent` | `success`, `status`, `i18n` | Envelope plus echte Nutzlast |
| `…testTheTemplateTimestampIsFinerThanTheFrameworkTimestamp` | Befund: **zwei Formate** in einer Antwortkette | `…FormatsItsOwnDatesFinerThanTheEnvelope` — ein Projekt formatiert die Daten in seiner Nutzlast frei, und der Envelope trägt überall dasselbe Format |

Der dritte ist der lehrreiche: Er hielt eine **Unstimmigkeit** fest, die mit dieser Änderung
verschwindet — zwei Antworten auf dieselbe Frage. Übrig bleibt die nützliche Hälfte, und die ist
jetzt eine Zusicherung statt eines Befunds.

**Register:** zwei Einträge — die Antwortform der Vorlage und die entfernte Twig-Datei, jeweils mit
*Was zu tun ist* für ein Projekt, das den Dienst übernommen hat. `migration.md` auf 119 Einträge.

**Verifiziert:** volle Suite `Tests: 616, Assertions: 2507, Skipped: 3`, PHPStan `[OK] No errors`,
Deprecation-Gate 0.
