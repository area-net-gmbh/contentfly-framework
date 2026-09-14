---
id: 000-000-0034
title: Kommentare der Vorlage custom/ auf Englisch
status: review
depends_on: []
---

# Kommentare der Vorlage `custom/` auf Englisch

## Context
`custom/` ist die Vorlage, die jedes Projekt kopiert. Die Kommentare darin sind deutsch. Wer die
Vorlage übernimmt, übernimmt diese Kommentare mit, und ein Projekt ist nicht zwingend
deutschsprachig. Der Auftraggeber hat entschieden, dass die Kommentare englisch werden.

Die Sprachregel des Frameworks steht dem nicht entgegen. Deutsch gilt für das, was der Workflow
erzeugt: Work-Items, Changelog, Doku und Commit-Bodies. Code-Kommentare in einer ausgelieferten
Vorlage gehören nicht dazu.

**Umfang, so entschieden am 2026-09-14:** alle kommentierten Dateien in `custom/`, einschließlich
`app.php` und `config.php`. Das sind neun Dateien mit rund 550 Kommentarzeilen:
`app.php`, `config.php`, `Entity/Core/Example.php`, `Classes/Anmeldung/BeispielProvider.php`,
`Classes/Service/Core/ApiDateTimeFormatter.php`, `Classes/Service/Core/ApiResponseService.php`,
`Command/ExampleCommand.php`, `Controller/Core/ExampleController.php` und `Traits/User.php`.

**Bezeichner bleiben deutsch.** Das betrifft `BeispielProvider`, den Namensraum
`Custom\Classes\Anmeldung` und `CONTENTFLY_BEISPIEL_PROVIDER`. Ein Umbenennen wäre ein Bruch für
Projekte, die die Vorlage schon übernommen haben, und ginge über Kommentare hinaus.

**Ein Test hängt an einem Kommentarsatz.** `tests/Integration/Api/VorlageApiTest.php` prüft in
`custom/app.php` den deutschen Satz „die Reihenfolge der Registrierung die
Ausführungsreihenfolge". Er wird in derselben Änderung auf den englischen Satz umgestellt. Die
Zusicherung bleibt: Die Vorlage erklärt die Ausführungsreihenfolge.

## Acceptance criteria
- [x] In den neun Dateien steht kein deutscher Kommentar mehr. Die Prüfung umfasst Docblocks,
      Zeilen- und Blockkommentare.
- [x] Die Aussagen sind übersetzt, nicht gekürzt. Was ein Kommentar erklärt, erklärt er danach
      auf Englisch genauso.
- [x] Code, Bezeichner, String-Literale und Konfigurationswerte sind unverändert. Der Diff
      enthält nur Kommentarzeilen.
- [x] `VorlageApiTest` prüft den englischen Satz, und die Zusicherung ist dieselbe.
- [x] `tools/check-template-config.sh` ist grün, und `custom/config.php` enthält keine Zugangsdaten.
- [ ] PHPStan und die volle Suite sind grün. **PHPStan ist grün, die Unit-Suite ebenfalls. Die
      Integrations-Suite ist nicht gelaufen**, so entschieden vom Auftraggeber am 2026-09-14:
      „nicht testen, sind nur Kommentare". Docker lief nicht, und ohne Testdatenbank werden alle
      Integrationstests übersprungen.

## Verification
- Der Diff wird ohne Kommentarzeilen verglichen: Code-Token vor und nach der Änderung
  (`token_get_all` ohne `T_COMMENT`/`T_DOC_COMMENT`/`T_WHITESPACE`) sind je Datei identisch.
- Nach deutschen Resten wird gesucht: Umlaute, ß und typische Wörter (`der|die|das|und|nicht|wird|ist`)
  in den Kommentar-Token.
- `php -l` je Datei, `sh tools/check-template-config.sh`, PHPStan und die volle Suite laufen.

## Ergebnis

**Neun Dateien, 345 Zeilen geändert, und davon ist keine Code.** Nachgewiesen ist das mit einem
Token-Vergleich: Je Datei sind die PHP-Token ohne Kommentare und Leerraum vor und nach der
Änderung identisch. **Der Vergleich ist scharf.** Eine absichtlich geänderte Command-Bezeichnung
in `ExampleCommand.php` macht ihn rot (Exit 1), nach dem Zurücksetzen ist er wieder grün. Die
Suche nach deutschen Resten in den Kommentar-Token findet einen einzigen Treffer. Es ist das
englische Wort „die" in „the application would die".

**Mitübersetzt sind Beispielnamen, die nur in Kommentaren stehen.** Das betrifft
`$app['schlüssel']`, `meine.service`/`MeinService`, `MeinCommand`, `mein-sso`/`MeinSsoProvider`,
`/unterverzeichnis/` und den JSON-Beispielwert in `Example.php`. Keiner davon ist Code.

**Deutsch geblieben ist, was Code ist.** Dazu gehören die Bezeichner (`BeispielProvider`,
`Anmeldung`, `pruefen()`, `anwendung()`, `eintragen()`, `ausKonfiguration()`), die
Array-Schlüssel im PHPStan-Typ `@return list<array{kennung: …}>` und die beiden Strings in
`ExampleCommand`. Die Strings bleiben nach Entscheidung des Auftraggebers deutsch.

**`VorlageApiTest` prüft jetzt „the order of registration is the order of execution".** Direkt
gegen die Datei geprüft enthält die neue Fassung den Satz und der alte Stand nicht. Die übrigen
Zusicherungen des Tests treffen unverändert, und `php bin/console.php list` zeigt weiterhin
`custom:example:command:run`.

**Nicht angefasst ist `custom/Views/partials/_email_layout.twig`.** Dort liegt eine
uncommittete Leerraum-Änderung, die nicht aus diesem Task stammt.

Geprüft: `php -l` für alle neun Dateien, `sh tools/check-template-config.sh` mit Exit 0,
PHPStan `[OK] No errors` mit `--memory-limit=512M` wie in der CI (mit dem Standardlimit von
128M stürzt der Lauf ab) und die Unit-Suite `OK (251 tests, 855 assertions)`.
