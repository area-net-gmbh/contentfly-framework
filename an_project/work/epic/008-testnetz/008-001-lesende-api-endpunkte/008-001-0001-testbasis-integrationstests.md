---
id: 008-001-0001
title: Gemeinsame Basis für die Integrationstests
status: todo
depends_on: []
---

# Gemeinsame Basis für die Integrationstests

## Context
`AuthApiTest` und `FileApiTest` erben beide direkt von `TestCase` und bringen ihre HTTP-Helfer je
selbst mit — `login()`, `postJson()`/`httpPostJson()`, `get()`/`httpGet()`, `auswerten()`. Das
sind zwei Kopien derselben Sache. Epic `008` fügt mindestens fünf weitere Testdateien hinzu; ohne
gemeinsame Basis wird daraus die siebte Kopie.

Dieser Task ist der Enabler für das ganze Epic, nicht nur für diese Story.

## Umfang

Eine Basisklasse unter `tests/Integration/` mit:

- **Übersprung-Logik** — fehlt `CONTENTFLY_TEST_BASE_URL`, wird sauber übersprungen. Heute in
  jedem `setUp()` wiederholt.
- **Anmeldung** — `login()` mit dem Admin aus `CONTENTFLY_TEST_ADMIN_PASS`, samt Zwischenspeichern
  des Tokens für die Dauer eines Tests.
- **HTTP-Helfer** — `postJson()`, `get()` und die Auswertung in Status, Rumpf und Kopfzeilen.
  Der Token geht über den Kopf `appcms-token`.
- **Testdaten** — Anlegen und Aufräumen von Objekten für einen Testlauf.

**Zum Weg für die Testdaten:** Der Aufbau läuft **nicht** über die Schreib-Endpunkte, sondern
direkt gegen die Datenbank. Sonst hinge diese Story an `008-002`, was die Story ausdrücklich
ausschließt — und ein Lesetest, dessen Vorbedingung über denselben Stack läuft, den er prüft,
verliert seine Aussagekraft. Die Lese-Endpunkte bleiben allein den Zusicherungen vorbehalten.

## Acceptance criteria
- [ ] Eine Basisklasse unter `tests/Integration/` bündelt Übersprung-Logik, Anmeldung,
      HTTP-Helfer und Testdaten-Auf-/Abbau.
- [ ] `AuthApiTest` und `FileApiTest` erben davon und sind um ihre eigenen Kopien dieser Helfer
      erleichtert.
- [ ] **Keine einzige Zusicherung der beiden bestehenden Dateien ist verändert.** Es werden
      Helfer verschoben, keine Tests umgeschrieben — was heute geprüft wird, wird danach
      identisch geprüft.
- [ ] Testdaten entstehen ohne Beteiligung der Schreib-Endpunkte; nach jedem Test ist die
      Datenbank in dem Zustand, in dem der Test sie vorgefunden hat.
- [ ] `tests/README.md` beschreibt, wie eine neue Integrationstest-Datei auf der Basis aufsetzt.

## Verification
```sh
CONTENTFLY_TEST_BASE_URL=http://127.0.0.1:8145 \
CONTENTFLY_TEST_ADMIN_PASS=dev-only-secret \
  ./custom/vendor/bin/phpunit
```
Erwartet: **26 Tests, 45 Assertions** — exakt die Zahlen von vorher. Eine abweichende Zahl heißt,
dass beim Umzug eine Zusicherung verlorengegangen oder hinzugekommen ist, und stoppt den Task.

Zusätzlich: Ein Lauf **ohne** `CONTENTFLY_TEST_BASE_URL` überspringt die Integrationstests
weiterhin sauber, statt zu scheitern.
