---
id: 014-001-0005
title: Sprachwächter als Test
status: review
depends_on: [014-001-0004]
---

# Sprachwächter als Test

## Context
Eine einmalige Suche nach deutschen Resten schützt nur den Moment. Ein Test hält den Zustand:
`tests/Unit/EnglishOnlyTest.php` scannt eine Liste von Pfaden. Er meldet jede Datei mit einem
deutschen Wort, und zwar mit Datei, Zeile und Fundstelle.

- **Geprüft wird alles**: Bezeichner, Strings und Kommentare. Erkannt werden Umlaute, `ß` und
  eine Liste typischer deutscher Wörter in Wortgrenzen. Wörter, die auch englisch sind (`die`,
  `also`, `will` …), gehören nicht in die Liste.
- **Die Pfadliste wächst mit dem Epic.** Diese Story trägt ihren Bereich ein, jede weitere Story
  ihren, und `014-006` deckt den ganzen Baum ab.
- **Eine Ausnahmeliste für echte Fremdwörter muss sich selbst prüfen.** Eine Ausnahme, die nichts
  mehr trifft, macht den Lauf rot.
- **Mutationstest:** Ein absichtlich eingefügtes deutsches Wort in einer geprüften Datei macht den
  Test rot. Ein Test, der nichts sieht, ist keiner.

## Acceptance criteria
- [x] `tests/Unit/EnglishOnlyTest.php` existiert, läuft in der Unit-Suite und ist grün für alle Pfade von `014-001`.
- [x] Die Pfadliste enthält genau die Pfade dieser Story. Ein Pfad, der nicht existiert, macht den Test rot.
- [x] Die Ausnahmeliste prüft sich selbst: Eine Ausnahme ohne Treffer macht den Test rot.
- [x] Mutationstest belegt: Ein eingefügter Umlaut, ein deutsches Wort im Kommentar und ein deutscher Bezeichner machen den Test jeweils rot, und die Meldung nennt Datei und Zeile.
- [x] Die Epic-Datei beschreibt, dass jede Story ihre Pfade in die Liste einträgt.

## Verification
Unit-Suite grün. Drei Mutationen einzeln einfügen, Test rot mit Datei und Zeile, zurücksetzen,
Test grün. Eine erfundene Ausnahme eintragen: rot.

## Ergebnis

**`tests/Unit/EnglishOnlyTest.php` prüft die Pfade der Story dauerhaft, mit vier Tests:**
- keine deutschen Texte in den Pfaden
- jeder Pfad existiert
- jede Ausnahme wird noch gebraucht
- der Detektor erkennt Deutsch und lässt Englisch durch

Der vierte Test belegt am Detektor selbst, dass jede der drei Regeln greift (Umlaut, Wort, Wortstamm) und englischer Text durchkommt.

**Drei Regeln, weil eine nicht reicht.** Umlaute fangen nur Texte mit Umlaut, und
`ae`/`oe`/`ue`-Schreibungen sind im Baum häufig. Ganze Wörter fangen Prosa und Meldungen.
Zusammengesetzte Bezeichner wie `Anmeldebremse` fängt nur ein Wortstamm, ohne Rücksicht auf
Gross- und Kleinschreibung.

**Der erste Lauf hat den Wächter selbst korrigiert:**
- `dies`, `den` und `hat` sind auch englische Wörter. Sie meldeten „console dies on …". Alle
  drei sind aus der Liste gestrichen, und der Kommentar sagt, warum.
- Die Testdatei prüft sich nicht selbst, weil sie die Wortlisten enthalten muss.
- **Die Selbstprüfung der Ausnahmeliste hat beim ersten Lauf angeschlagen:** `letzterToken`
  wurde gar nicht als Deutsch erkannt. Gestrichen ist nicht die Ausnahme, sondern die Lücke
  geschlossen: mit den Wortstämmen `letzt` und `kette`.

**22 Ausnahmen, jede mit der Story, die sie auflöst.** Es sind die Security-Namen in den
Bootstrap-Dateien (`014-002`), `ProviderAbgleichCommand` und `EntityManagerFactory::erzeugen`
(`014-003`) sowie drei deutsche Testklassennamen, auf die Kommentare verweisen (`014-005`).
Sobald eine Story umbenennt, wird ihre Ausnahme rot, bis sie gestrichen ist.

**Mutationstest, fünfmal einzeln, jedes Mal zurückgesetzt:**
- deutscher Kommentar in `Paths.php`: rot, Meldung `Paths.php:116 [word "des"]`
- Umlaut in einer Meldung: rot, `Paths.php:93 [umlaut]`
- deutscher Methodenname `datenVerzeichnis()`: rot, `Paths.php:111 [stem "verzeichnis"]`
- erfundene Ausnahme: rot
- nicht existierender Pfad: rot

Danach war der Test wieder grün, und `Paths.php` war unverändert.

Das Epic nennt jetzt die Regel, dass jede Story ihre Pfade einträgt.

Geprüft: volle Suite `OK (528 tests, 1699 assertions)`, also genau die vier neuen Tests und 11
Assertions mehr als vorher. PHPStan `[OK] No errors`, Deprecation-Gate grün.
