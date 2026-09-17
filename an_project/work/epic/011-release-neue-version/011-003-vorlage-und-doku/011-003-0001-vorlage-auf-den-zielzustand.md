---
id: 011-003-0001
title: Die Vorlage auf den Zielzustand bringen
status: todo
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
- [ ] Die Entscheidung oben ist getroffen und in `api-envelope.md` nachgetragen — dort steht heute nur „als Vorbild ja, wörtlich nein", und was aus dem Vorbild selbst wird, bleibt offen.
- [ ] `ExampleController` und `ApiResponseService` zeigen die Form, die das Framework zusichert.
- [ ] Die tote Twig-Datei ist entfernt, und der Register-Eintrag nennt es, falls ein Projekt sie kopiert hat.
- [ ] Leere, von nichts referenzierte Verzeichnisse sind weg **oder** in Git und begründet — nicht beides halb.
- [ ] `RouteSecurityApiTest` und `TemplateApiTest` messen die neue Form, jede Anpassung begründet.
- [ ] Volle Suite, PHPStan, Deprecation-Gate grün.

## Verification
Der Beispiel-Endpunkt `/api/v1/example/bootstrap` antwortet in derselben Form wie `/api/config` —
geprüft mit **einer** Leserfunktion, wie `EnvelopeApiTest` es für die Framework-Endpunkte tut.
