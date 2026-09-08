---
id: 008-005-0002
title: Ein übersprungener Integrationstest macht den Lauf rot
status: todo
depends_on: [008-005-0001]
---

# Ein übersprungener Integrationstest macht den Lauf rot

## Context
Das eigentliche Risiko dieser Story: **eine grüne Suite, die nichts geprüft hat.**

`IntegrationTestCase::setUp()` überspringt sauber, wenn `CONTENTFLY_TEST_BASE_URL` fehlt.
Lokal ist das genau richtig — wer nur schnell die Unit-Tests fahren will, soll nicht an einer
fehlenden Datenbank scheitern. In einer Pipeline ist dasselbe Verhalten eine Falle: Vergisst
jemand eine Variable, bricht der Testserver weg oder schlägt die Installation fehl, meldet
PHPUnit `OK, but some tests were skipped` — und der Job wird **grün**.

Dasselbe gilt für `CONTENTFLY_TEST_MAIL_TRAP`: Ohne die Variable überspringen sich drei Tests
aus `MailApiTest` still. Sobald `/api/mail` repariert ist (`000-000-0016`), ist das nicht nur
eine Lücke, sondern ein Lauf, der echte Mail verschicken könnte.

## Umfang

### Der Weg: ein Wächter in der Suite
Die Regel gehört **in die Suite**, nicht in die `.gitlab-ci.yml`. Wer die Suite anderswo
fährt — in einem anderen CI, in einem Container, in einer späteren Pipeline nach Epic `006` —
soll sie mitnehmen, ohne sie neu zu erfinden.

Ein Test prüft: **Läuft die Suite in einer Pipeline, müssen die Umgebungsvariablen gesetzt
sein.** GitLab setzt `CI=true` von sich aus; GitHub Actions und die meisten anderen ebenfalls.
Fehlt eine Variable, wird dieser Test **rot** — mit einer Meldung, die sagt, welche fehlt und
warum das nicht durchgewinkt wird.

Lokal, ohne `CI`, ändert sich nichts: Die Integrationstests überspringen sich wie bisher.

### Was `failOnSkipped` nicht leistet
`phpunit.xml.dist` kennt einen Schalter `failOnSkipped`. Er scheidet aus, und der Grund steht
in derselben Datei: Die Trennung in zwei Suiten ist bewusst, damit die Unit-Suite auf jedem
Checkout grün ist und nicht stillsteht, sobald kein Container läuft. `failOnSkipped="true"`
in der Konfiguration würde genau das zerstören — der lokale Lauf ohne Umgebung wäre rot.

Ein CI-only-Schalter in der YAML (`--fail-on-skipped` nur im Job) wäre möglich, verlegt die
Regel aber aus der Suite heraus. Deshalb der Wächter-Test.

### Was der Wächter abdecken muss
- `CONTENTFLY_TEST_BASE_URL` — ohne sie läuft **kein** Integrationstest.
- `CONTENTFLY_TEST_MAIL_TRAP` — ohne sie überspringen sich die Mail-Tests, und der Schutz
  gegen echten Versand ist nicht nachgewiesen.
- `CONTENTFLY_TEST_ADMIN_PASS` — fehlt sie, greift der Standardwert `admin`; die Anmeldung
  scheitert dann und **jeder** Integrationstest wird rot. Das fällt zwar auf, aber die
  Meldung („Anmeldung fehlgeschlagen") nennt die Ursache nicht. Ein Wächter, der die Variable
  in der Pipeline einfordert, spart die Suche.

Zu prüfen ist ausserdem, ob der Wächter auch dann greift, wenn die Variable *gesetzt, aber
falsch* ist — etwa ein Testserver, der gar nicht antwortet. Ein Wächter, der nur die Existenz
einer Zeichenkette prüft, wiegt in Sicherheit.

## Abgrenzung
Kein Umbau der Suite-Trennung und keine Änderung an `IntegrationTestCase::setUp()`s
Überspring-Verhalten für den lokalen Fall. Das Überspringen ist richtig — es darf nur in
einer Pipeline nicht unbemerkt bleiben.

## Acceptance criteria
- [ ] Ein Wächter in der Suite macht den Lauf rot, wenn `CI` gesetzt ist und eine der
      benötigten Umgebungsvariablen fehlt.
- [ ] Die Fehlermeldung nennt die fehlende Variable **und** warum sie nicht übersprungen wird.
- [ ] Ohne `CI` verhält sich die Suite unverändert: Integrationstests überspringen sich sauber.
- [ ] Der Wächter greift auch, wenn eine Variable gesetzt, der Testserver aber nicht
      erreichbar ist — oder es ist begründet festgehalten, warum das nicht abgedeckt wird.
- [ ] Der Fall ist **beidseitig** nachgewiesen: ein Lauf mit `CI=true` ohne Variablen ist rot,
      ein Lauf ohne `CI` ohne Variablen ist grün mit Übersprüngen.

## Verification
Vier Läufe, die die Matrix aufspannen:

| `CI` | Variablen | Erwartung |
|---|---|---|
| nicht gesetzt | fehlen | grün, Integrationstests übersprungen |
| nicht gesetzt | gesetzt | grün, alles läuft |
| `true` | fehlen | **rot**, Meldung nennt die Variable |
| `true` | gesetzt | grün, alles läuft |

Die Ausgabe der beiden interessanten Fälle (Zeile 1 und 3) gehört ins Ergebnis.
