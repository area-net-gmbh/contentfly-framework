---
id: 011-003-0003
title: dev-guide und technical auf den echten Weg bringen
status: todo
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
- [ ] *Neue API-Route hinzufügen* beschreibt den Weg, den dieses Projekt wirklich geht, mit einem Beispiel aus `custom/`.
- [ ] *API-Dokumentation* nennt Generator, Befehl und Ablageort — oder hält fest, dass es sie nicht gibt, statt Platzhalter stehen zu lassen.
- [ ] Der Envelope ist im `dev-guide` beschrieben: Was ein Endpunkt zurückgeben muss und womit (`renderResponse`, `Classes\Envelope`), Erfolg **und** Fehler.
- [ ] `technical.md` nennt die Antwortform als Teil des zugesicherten Vertrags.
- [ ] Kein Vorlagen-Kommentar (`<!-- z. B. … -->`) bleibt in beiden Dateien stehen.

## Verification
Jemand baut nach dem `dev-guide` eine Route in `custom/` und bekommt eine Antwort, die
`EnvelopeApiTest`s Leserfunktion auswerten könnte — ohne in `api-envelope.md` nachgesehen zu haben.
