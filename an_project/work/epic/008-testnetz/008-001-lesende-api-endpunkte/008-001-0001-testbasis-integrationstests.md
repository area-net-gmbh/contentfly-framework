---
id: 008-001-0001
title: Gemeinsame Basis für die Integrationstests
status: review
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
- [x] Eine Basisklasse unter `tests/Integration/` bündelt Übersprung-Logik, Anmeldung,
      HTTP-Helfer und Testdaten-Auf-/Abbau.
- [x] `AuthApiTest` und `FileApiTest` erben davon und sind um ihre eigenen Kopien dieser Helfer
      erleichtert.
- [x] **Keine einzige Zusicherung der beiden bestehenden Dateien ist verändert.** Es werden
      Helfer verschoben, keine Tests umgeschrieben — was heute geprüft wird, wird danach
      identisch geprüft.
- [x] Testdaten entstehen ohne Beteiligung der Schreib-Endpunkte; nach jedem Test ist die
      Datenbank in dem Zustand, in dem der Test sie vorgefunden hat.
- [x] `tests/README.md` beschreibt, wie eine neue Integrationstest-Datei auf der Basis aufsetzt.

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

## Ergebnis

`tests/Integration/IntegrationTestCase.php` bündelt Übersprung-Logik, Anmeldung
(`login()` frisch / `token()` zwischengespeichert), HTTP-Helfer (`postJson()`, `get()`,
`kopfzeile()`) und die Testdaten-Werkzeuge (`pdo()`, `nachTestLoeschen()` mit Aufräumen in
`tearDown()`).

| Datei | vorher | nachher |
|---|---|---|
| `AuthApiTest` | 215 Zeilen | 142 |
| `FileApiTest` | 259 Zeilen | 190 |

Aus `FileApiTest` bleibt nur `upload()` als eigener Helfer — der ist dateispezifisch.

**Ein Punkt, der beim Umsetzen auffiel:** Die Basisklasse ist **nicht autoladbar**.
`custom/composer.json` mappt `Custom\Tests\`, die Testklassen liegen aber unter `Tests\`, und
PHPUnit lädt von sich aus nur Dateien, die auf `Test.php` enden. Sie wird deshalb von
`tests/bootstrap.php` per `require_once` geladen — bewusst statt eines `composer dump-autoload`,
das den committeten `custom/vendor`-Baum neu geschrieben hätte, den Epic `006` ohnehin auflöst.
Der Kommentar an beiden Stellen verweist darauf.

`pdo()` und `nachTestLoeschen()` sind neu und werden von keinem Test dieses Tasks benutzt — die
Abnahmezahl verbietet zusätzliche Tests. Ihre Funktion ist stattdessen mit einer Wegwerf-Testdatei
belegt worden (siehe Verification); `008-001-0002` nimmt sie regulär in Gebrauch.
