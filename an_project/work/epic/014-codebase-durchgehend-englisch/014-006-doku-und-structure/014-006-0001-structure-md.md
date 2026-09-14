---
id: 014-006-0001
title: STRUCTURE.md auf die englischen Namen
status: review
depends_on: []
---

# STRUCTURE.md auf die englischen Namen

## Context
`STRUCTURE.md` ist die englische Einstiegsdoku für die Übergabe der Codebase (`000-000-0035`). Sie
entstand vor Epic `014` und erklärt deutsche Bezeichner deshalb über ein Glossar in Abschnitt 16.
Diese Bezeichner gibt es nicht mehr. Stehen blieben 54 Treffer auf alte Namen, darunter die
Testnamen in Abschnitt 14, dazu das Glossar und der Verweis darauf in Abschnitt 2.

## Acceptance criteria
- [x] Jeder Name in `STRUCTURE.md` stimmt mit dem Code überein: Klassen, Methoden,
  Container-Schlüssel, Config-Keys, Umgebungsvariablen, Commands, Pfade und Testklassen samt
  Testzahlen.
- [x] Das Glossar der deutschen Bezeichner (Abschnitt 16) und der Verweis darauf in Abschnitt 2
  sind entfernt; die Nummerierung der übrigen Abschnitte bleibt stimmig.
- [x] Jeder Code-Verweis in `STRUCTURE.md` ist gegen den Code geprüft: Klassen und Dateien
  existieren, genannte Methoden existieren in der genannten Klasse.
- [x] `STRUCTURE.md` steht in `PATHS` von `tests/Unit/EnglishOnlyTest.php`. Nötige Ausnahmen, etwa
  Pfade deutscher Docs unter `an_project/docs/`, stehen begründet in `EXCEPTIONS`.
- [x] Die Suche nach den alten Namen (Tabelle im Epic, umbenannte Tests und Helfer aus `014-005`)
  findet in `STRUCTURE.md` nichts.

## Verification
Suche mit der Liste der alten Namen über `STRUCTURE.md`. Skript, das alle Code-Verweise aus der
Datei zieht und gegen `lib/`, `custom/`, `tests/`, `bin/` auflöst. Testzahlen gegen
`phpunit --list-tests`. Sprachwächter grün, Mutation in `STRUCTURE.md` macht ihn rot.

## Ergebnis

**`STRUCTURE.md` nennt nur noch Namen, die es im Code gibt.** Abschnitt 16, das Glossar der deutschen
Bezeichner, ist entfernt. An die Stelle des Hinweises in Abschnitt 2 („Code comments … are written
in German") tritt der Satz, dass der Code durchgehend englisch ist und `EnglishOnlyTest` das hält.
Die Nummerierung 1–15 ist unverändert; alle Querverweise („section 13" usw.) zeigen weiter auf den
richtigen Abschnitt.

**Neben den Namen haben sich drei Fakten geändert; alle drei sind neu gemessen:**
- **Unit-Suite:** 255 statt 251 Tests.
- **Fehlschläge in der Lieferung:** An einer Kopie mit genau den gelieferten Inhalten
  (`git archive` der Pfade aus Abschnitt 2 plus `vendor/`) schlagen weiterhin **18** Unit-Tests
  fehl: 4 in `CiStepsTest`, 3 in `MigrationGuideTest`, 11 von 19 in `RectorRuleTest`.
- **Rückstände im Projektverzeichnis:** Die Unit-Suite hinterlässt dort zwei Dinge. Ohne
  Konfigurationsdatei legt Rector beim Lauf von `RectorRuleTest` eine Standard-`rector.php` an,
  und `PluginManagerTest` hinterlässt ein leeres `plugins/`. Das steht jetzt in Abschnitt 14.

  Eine erste Vermutung, die generierte `rector.php` koste einen weiteren Fehlschlag, hat die
  Nachmessung widerlegt (11 mit und ohne Datei). Sie steht deshalb nicht in der Doku.

**Code-Verweise geprüft.** Ein Skript zieht jeden Verweis in Backticks aus der Datei und löst ihn gegen
den Code auf: Dateien und Verzeichnisse, Klassen, `Klasse::methode()`, Methoden, Konstanten und
Config-Keys, Console-Commands, Container-Schlüssel. Gegengeprüft ist es an einer Datei mit sieben
alten Namen, und es meldet alle sieben. Über `STRUCTURE.md` bleibt eine Meldung, `Authorization`;
das ist der Name eines HTTP-Headers, keine Klasse.

**Sprachwächter.** `STRUCTURE.md` steht in `PATHS`, ohne Ausnahme. Eine deutsche Zeile in der Datei
machte den Test rot (`STRUCTURE.md:568 [word "wird"]`); zurückgesetzt war er grün, die Datei
byte-gleich. **Grenze:** Ein alter Klassenname ohne deutschen Wortstamm, etwa `Tokenhandler`,
bleibt für den Detektor unsichtbar. Dafür gibt es die Suche nach alten Namen, und
`014-006-0005` macht sie vollständig.

**Nachweis.** Suche nach den alten Namen über `STRUCTURE.md`: 0 Treffer. Unit-Suite
`OK (255 tests, 870 assertions)`.
