---
id: 014-005-0001
title: Test-Infrastruktur auf Englisch
status: review
depends_on: []
---

# Test-Infrastruktur auf Englisch

## Context
Alle Integrationstests rufen die Helfer aus `IntegrationTestCase`. Die Infrastruktur kommt deshalb
zuerst, und ihre Umbenennungen werden in allen Aufrufern mitgezogen.

Umfang:
- `tests/Integration/IntegrationTestCase.php` mit allen Helfern (`nachTestLoeschen()`,
  `benutzerAnlegen()`, `konsole()`, `datenverzeichnis()`, `anwendungsverzeichnis()` …)
- `tests/router.php`
- `tests/Integration/UmgebungsWaechterTest.php` → englischer Klassenname
- `tests/Integration/VersandfalleTest.php` → englischer Klassenname
- Test-Umgebungsvariable `CONTENTFLY_TEST_PROJEKT` → `CONTENTFLY_TEST_PROJECT_DIR`
- `tests/README.md`

In den übrigen Testdateien werden nur die Aufrufe der umbenannten Helfer umgestellt.

## Acceptance criteria
- [x] Die Dateien des Tasks sind vollständig englisch: Klassen-, Methoden- und Helfernamen, Variablen, Assertion-Meldungen, Strings und Kommentare. Testklassen, deren Name deutsch ist, sind samt Datei umbenannt.
- [x] Die Prüfungen sind unverändert: Je Datei sind Test- und Assertion-Zahl vor und nach dem Task gleich. Die Ergebnis-Notiz enthält die Liste alt → neu aller umbenannten Testmethoden und -klassen.
- [x] Werte, die ein Test gegen Framework-Code oder gegen die Datenbank prüft (Quelltextzeilen, Meldungen, Tabellennamen), stimmen weiterhin mit dem Framework überein.
- [x] Alle Verweise auf umbenannte Tests und Helfer im Baum sind mitgezogen (`lib`, `custom`, `tests`, `tools`, `phpunit.xml.dist`, `rector.php`).
- [x] Volle Suite mit gleicher Gesamtzahl an Tests und Assertions, PHPStan `[OK]`, Deprecation-Gate grün.

## Verification
Vor dem Task: je Datei `phpunit <datei>` (Integrationstests gegen den laufenden Testserver) mit
Test- und Assertion-Zahl notieren. Danach dieselben Läufe, Zahlen vergleichen. Volle Suite, PHPStan
(Result-Cache geleert), Deprecation-Gate, Detektor des Sprachwächters über die Dateien des Tasks.

## Ergebnis

**Die Test-Infrastruktur ist englisch.** `IntegrationTestCase` ist vollständig übersetzt, und die
Helfer heissen jetzt:

| alt | neu |
|---|---|
| `nachTestLoeschen()` | `deleteAfterTest()` |
| `nachTestVerzeichnisLoeschen()` | `deleteDirectoryAfterTest()` |
| `verzeichnisEntfernen()` | `removeDirectory()` |
| `bremsspeicherLeeren()` | `clearThrottleStorage()` |
| `anwendungsverzeichnis()` · `datenverzeichnis()` · `konsole()` | `applicationDir()` · `dataDir()` · `console()` |
| `kopfzeile()` · `dbZugangsdaten()` | `header()` · `dbCredentials()` |
| `testbenutzer()` · `TEST_PASSWORT` | `createTestUser()` · `TEST_PASSWORD` |

Die Aufrufe sind in allen 25 Integrationstests umgestellt. Die Umgebungsvariable heisst
`CONTENTFLY_TEST_PROJECT_DIR`.

**Umbenannte Testklassen und -methoden** (Übersetzung durch einen Agenten, nachgeprüft über die
Zahlen):

| alt | neu |
|---|---|
| `UmgebungsWaechterTest` | `EnvironmentGuardTest` |
| `testInEinerPipelineIstDieUmgebungsvariableGesetzt` | `testEnvironmentVariableIsSetInPipeline` |
| `testEineGesetzteBasisAdresseMussAuchAntworten` | `testConfiguredBaseUrlMustAlsoRespond` |
| `testDieTestdatenbankMussErreichbarSein` | `testTestDatabaseMustBeReachable` |
| `testEineGesetzteVersandfalleMussEinFangskriptEnthalten` | `testConfiguredMailTrapMustContainCatchScript` |
| `VersandfalleTest` | `MailTrapTest` |
| `testDieVersandfalleIstScharf` | `testMailTrapIsArmed` |

`tests/router.php` und `tests/README.md` sind übersetzt; die README nennt die neuen Helfer- und
Klassennamen.

**Die Versandfalle schreibt jetzt `outbox.log` statt `postausgang.log`.** Das ist konsistent in
`MailTrapTest`, `tools/ci/prepare-test-environment.sh` (samt Markierung `--- END MAIL ---`),
`.gitlab-ci.yml` (Verzeichnis `.ci-mailtrap`) und dem lokalen Suite-Skript. Der Anlass: Die
README-Übersetzung hatte den englischen Namen schon verwendet, und Doku und Code wären
auseinandergelaufen.

**Zwei Überschreibungen, die ein Umbenennen leise verloren hätte:**
- **`AnmeldebremseApiTest` überschreibt `clearThrottleStorage()`** mit einer harten Vorbedingung.
  Wäre nur die Elternmethode umbenannt worden, liefe unbemerkt die Elternfassung ohne diese
  Vorbedingung. Die Überschreibung ist mit umbenannt.
- **`PluginManagerTest` hat einen eigenen privaten Helfer `verzeichnisEntfernen()`.** Das Muster
  für die Aufrufe traf auch ihn, die Deklaration nicht. Der erste volle Lauf meldete acht Errors
  `Call to undefined method removeDirectory()`, und PHPStan zwei. Die Deklaration ist nachgezogen.

**Nachweis:** Je Datei sind Test- und Assertion-Zahl identisch mit dem Ausgangsstand. Gemessen
über das JUnit-Log der vollen Suite, 60 Dateien, mit Zuordnung der zwei umbenannten Dateien.
Volle Suite `OK (528 tests, 1703 assertions)` ohne übersprungene Tests, PHPStan `[OK] No errors`,
Deprecation-Gate grün.
