---
id: 011-003-0003
title: dev-guide und technical auf den echten Weg bringen
status: done
depends_on: [011-003-0002]
---

# dev-guide und technical auf den echten Weg bringen

## Context
**Zwei Abschnitte des `dev-guide.md` sind nie ausgefüllt worden** und tragen noch den
Vorlagen-Kommentar:

- *Neue API-Route hinzufügen* spricht von einem **Bundle** und einem `#[Route(...)]`-Attribut.
  So entstehen Routen hier nicht: Sie kommen über `custom/app.php` und den `RouteManager` —
  `an_project/docs/tech-stack.md` sagt das ausdrücklich („Die Bundle-Regel greift erst, wenn ein
  Epic sie einführt").
- *API-Dokumentation* nennt `nelmio/api-doc-bundle` und `compodoc` als Beispiele in
  Kommentaren. Beides ist nicht im Baum; erzeugt wird die Doku über `apidoc.json`.

**Und die Antwortform fehlt ganz.** Seit `011-001` antwortet jeder Endpunkt mit `data` / `errors` /
`meta`. Das steht in `api-envelope.md` — einem **Entscheidungs**dokument. Wer eine Route baut,
liest den `dev-guide`, und dort erfährt er die Form seiner eigenen Antwort nicht.

## Acceptance criteria
- [x] *Neue API-Route hinzufügen* beschreibt den Weg, den dieses Projekt wirklich geht, mit einem Beispiel aus `custom/`.
- [x] *API-Dokumentation* nennt Generator, Befehl und Ablageort — oder hält fest, dass es sie nicht gibt, statt Platzhalter stehen zu lassen.
- [x] Der Envelope ist im `dev-guide` beschrieben: Was ein Endpunkt zurückgeben muss und womit (`renderResponse`, `Classes\Envelope`), Erfolg **und** Fehler.
- [x] `technical.md` nennt die Antwortform als Teil des zugesicherten Vertrags.
- [x] Kein Vorlagen-Kommentar (`<!-- z. B. … -->`) bleibt in beiden Dateien stehen.

## Verification
Jemand baut nach dem `dev-guide` eine Route in `custom/` und bekommt eine Antwort, die
`EnvelopeApiTest`s Leserfunktion auswerten könnte — ohne in `api-envelope.md` nachgesehen zu haben.

## Ergebnis

**Vier Abschnitte waren nie ausgefüllt, nicht zwei.** Beim Aufmachen kamen zwei weitere dazu, die
das Ticket nicht genannt hatte:

| Abschnitt | war | ist |
|---|---|---|
| *Backend-Struktur* | eine Tabelle für **Bundles** (`z. B. CatalogBundle`) | die Grenze Paket/Projekt aus Epic `007`, sieben Orte mit ihrer Verantwortung |
| *Frontend-Struktur* | eine Modul-Map für **Angular** | **es gibt keines** — die Oberfläche ist mit Epic `012` entfallen |
| *Neue API-Route* | `#[Route(...)]` in einem Bundle | `routeManager` und `mount()` in `custom/app.php`, mit `isSecure` erklärt |
| *API-Dokumentation* | `nelmio/api-doc-bundle`, `compodoc` — beides nicht im Baum | apidoc aus den `@api`-Annotationen, mit zwei benannten Einschränkungen |

Die ersten beiden waren nicht nur leer, sondern **irreführend**: Sie beschrieben eine Struktur, die
dieses Projekt ausdrücklich nicht hat. `tech-stack.md` sagt es selbst — „Die Bundle-Regel greift
erst, wenn ein Epic sie einführt" —, aber wer den `dev-guide` liest, liest nicht zuerst den
Stack-Hinweis.

### Die Antwortform steht jetzt dort, wo man sie sucht

Sie stand nur in `api-envelope.md`, einem **Entscheidungs**dokument. Wer eine Route baut, liest den
`dev-guide` — und erfuhr dort die Form seiner eigenen Antwort nicht. Neuer Abschnitt *Die
Antwortform*, mit der Tabelle, welches Werkzeug wo greift (`renderResponse`, `renderError`,
`Envelope`, `ApiResponseService`), und mit dem, was ein Client sich zusichern lassen kann.

`technical.md` bekommt den Vertrag als eigenen Abschnitt — er gehört zu dem, was diese Version
zusichert, nicht nur zu dem, was aufgeräumt wurde.

### Zwei Funde nebenbei

- **`apidoc.json` hiess „Contentfly CMS", Version `1.6.0`.** Wer die API-Doku erzeugt hätte, hätte
  sie unter dem Namen des Vorgängerprodukts und mit einer zwei Hauptversionen alten Nummer
  bekommen. Auf `Contentfly` / `2.0.0` gesetzt.
- **Ein Kommentar sagte „Der Rest dieser Datei ist noch Vorlage."** Er stimmte nicht mehr: Nach ihm
  kam nur noch der ausgefüllte Abschnitt selbst. Ein Hinweis, der nicht mehr gilt, ist schlimmer
  als keiner — er lässt jemanden nach Arbeit suchen, die getan ist.

### Ehrlich benannt statt übergangen

Der Abschnitt zur API-Doku nennt zwei Einschränkungen, die man beim Lesen wissen will: Der
Generator liest **nur** `lib/contentfly/Controller/` — Routen aus `custom/` sind nicht enthalten —,
und die `@api`-Blöcke tragen noch die Antwortbeispiele von **vor** Epic `011`. Beides
nachzuziehen ist offen; es hier zu verschweigen hätte den Abschnitt nur scheinbar fertig gemacht.

**Verifiziert:** Unit-Suite `312 Tests, 1026 Assertions`; kein Vorlagen-Kommentar bleibt in beiden
Dateien stehen ausser den Herkunftsvermerken (`Ausgefüllt mit …`) und dem PURPOSE-Kopf.
