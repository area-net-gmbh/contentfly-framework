---
id: 000-000-0034
title: Kommentare der Vorlage custom/ auf Englisch
status: todo
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
- [ ] In den neun Dateien steht kein deutscher Kommentar mehr. Die Prüfung umfasst Docblocks,
      Zeilen- und Blockkommentare.
- [ ] Die Aussagen sind übersetzt, nicht gekürzt. Was ein Kommentar erklärt, erklärt er danach
      auf Englisch genauso.
- [ ] Code, Bezeichner, String-Literale und Konfigurationswerte sind unverändert. Der Diff
      enthält nur Kommentarzeilen.
- [ ] `VorlageApiTest` prüft den englischen Satz, und die Zusicherung ist dieselbe.
- [ ] `tools/check-template-config.sh` ist grün, und `custom/config.php` enthält keine Zugangsdaten.
- [ ] PHPStan und die volle Suite sind grün.

## Verification
- Der Diff wird ohne Kommentarzeilen verglichen: Code-Token vor und nach der Änderung
  (`token_get_all` ohne `T_COMMENT`/`T_DOC_COMMENT`/`T_WHITESPACE`) sind je Datei identisch.
- Nach deutschen Resten wird gesucht: Umlaute, ß und typische Wörter (`der|die|das|und|nicht|wird|ist`)
  in den Kommentar-Token.
- `php -l` je Datei, `sh tools/check-template-config.sh`, PHPStan und die volle Suite laufen.
