---
id: 014-006-0005
title: Schlusssuche nach alten Namen über alle Docs
status: done
depends_on: [014-006-0004]
---

# Schlusssuche nach alten Namen über alle Docs

## Context
Das Ziel der Story ist messbar: Eine Suche nach jedem alten Namen findet in keinem Dokument mehr
einen Treffer, ausgenommen `CHANGELOG.md` und abgeschlossene Work-Items. Die Tasks `0001` bis
`0004` räumen Datei für Datei auf. Dieser Task prüft das Ganze, auch dort, wo kein Task hinsah:
`README.md`, `tests/README.md`, `CLAUDE.md`, `tools/` und `.gitlab-ci.yml`.

## Acceptance criteria
- [x] Die Suche nach der vollständigen Liste alter Namen findet ausserhalb von `CHANGELOG.md` und
  `an_project/work/` nichts. Dazu gehören die Tabelle des Epics, die umbenannten Klassen, Methoden,
  Testklassen und Helfer aus `014-001` bis `014-005` und die deutschen Begriffe, die wie alte
  Klassennamen aussehen.
- [x] Jeder Code-Verweis in `STRUCTURE.md`, `README.md`, `tests/README.md` und `an_project/docs/`
  löst sich auf.
- [x] In `tools/` und `.gitlab-ci.yml` stimmen die Verweise auf umbenannte Namen. Skriptnamen und
  Kommentare dort bleiben (Epic, *Nicht Teil des Epics*).
- [x] Die Liste der alten Namen und das Prüfskript liegen der Ergebnis-Notiz bei, damit die Suche
  wiederholbar ist.
- [x] Volle Suite, PHPStan und Deprecation-Gate grün.

## Verification
Suche mit der vollständigen Liste über den ganzen Baum ohne `vendor/`, `.an_framework/`,
`CHANGELOG.md` und `an_project/work/`. Die Suche wird gegen einen absichtlich eingesetzten alten
Namen gegengeprüft. Auflösungsskript über alle Docs. Volle Suite.

## Ergebnis

**Die Suche nach alten Namen findet im Baum nichts mehr, und sie ist wiederholbar.**

```sh
python3 tools/language/find-old-names.py          # ganzer Baum; Exit 0 = kein Treffer
python3 tools/language/find-old-names.py DATEI…   # einzelne Dateien
```

Das Skript liegt unter `tools/`, das nicht zur Übergabe gehört. Es führt **keine Kopie** der alten
Namen mit, sondern leitet sie bei jedem Lauf aus Git ab. Grundlage ist der Vergleich zwischen
`b93e5cf7`, dem letzten Commit vor Epic `014`, und `HEAD`. Drei Regeln:

1. **Alte Namen:** 811 Stück. Das sind Klassen, Methoden, Konstanten, Config-Keys,
   Container-Schlüssel, Commands und Umgebungsvariablen, die es vorher gab und heute nicht mehr,
   dazu eine kurze Handliste. Klassennamen zählen überall. Methoden und deutsche Wörter, die
   zugleich Klassennamen waren (`Pfade`), zählen nur als Code. Sonst träfe die deutsche Prosa der
   Docs.
2. **Alte Meldungen:** 101 deutsche String-Literale aus `lib/`, `custom/`, `bin/` und `index.php`,
   gesucht in Markdown. Im Code prüft das der Sprachwächter.
3. **Zitierte Testnamen:** ein `test…`-Name im Kommentar, der nirgends deklariert ist. Diese Regel
   findet Namen von Tests, die schon **vor** dem Epic ersetzt wurden und deshalb in keiner
   abgeleiteten Liste stehen.

Bewusste Vorkommen stehen mit Begründung in `ALLOWED`: die deutschen Eingaben des
Wächter-Selbsttests, `gruppe` als verbotener Claim, der frühere Ableitungskontext in
`FieldEncryption.php` und zwei Prosastellen in `breaking-changes.md`, die zufällig wie alte
Meldungen lauten.

**Gegengeprüft**
- **Probedatei:** Sie enthält fünf Zeilen mit alten Namen, Meldungen und Testnamen und eine
  Prosazeile. Getroffen werden die fünf Zeilen, die Prosazeile nicht.
- **Echtes Dokument:** Ein in `runbook.md` eingesetztes `Tokenquellen::kette()` meldet der
  Lauf mit Datei und Zeile. Zurückgesetzt: 0 Treffer.
- **Entwicklung des Skripts:** Die erste Fassung fing bei der Meldungssuche Code zwischen zwei
  Strings über Zeilenenden hinweg ein und meldete 119 Scheintreffer. Literale sind jetzt auf eine
  Zeile begrenzt.

**Was die Schlusssuche noch gefunden hat, nachdem `0001` bis `0004` fertig waren:**
- **Zwölf Zitate früherer deutscher Testnamen** in Integrationstests, etwa
  `testDerAliasPraefixVerhindertKollisionenZwischenLoginManagern`. Weder der Sprachwächter noch die
  Namensliste konnte sie sehen: kein deutscher Wortstamm aus seiner Liste, und entfallen vor dem
  Epic. Wie in `014-005-0006` beschreiben die Kommentare jetzt die frühere Zusicherung. Der Code ist
  byte-gleich (Token-Vergleich ohne Kommentare).
- **Deutsche Beispielwerte, die kein Name sind:** der Provider-Wert
  `extern-eins:…:CN=Redaktion` in `tests/README.md` und `.gitlab-ci.yml`, heute
  `external-one:…:CN=Editorial`. Der Test zerlegt den Wert nur und prüft ihn gegen keinen festen
  Text. Dazu `CN=Redaktion` im Kommentar von `GroupMapping.php` und der Beispielalias
  `3f2a…-mmustermann` / `-mueller` in `User.php`, `LoginManagerApiTest` und `breaking-changes.md`,
  jetzt einheitlich `3f2a…-jdoe`.

**`tools/` und `.gitlab-ci.yml`.** Getrennt durchsucht: Kein Verweis auf einen umbenannten Namen
steht dort. Die Treffer in `tools/` sind die eigenen deutschen Variablen der Skripte (`$zeile`,
`$liste`, `WURZEL`); sie bleiben nach dem Epic (*Nicht Teil des Epics*).

**Code-Verweise.** Das Auflösungsskript aus `014-006-0001` lief über alle Docs, die Tasks
`0001`–`0004` sowie `README.md`, `tests/README.md` und `CLAUDE.md`. Was sich nicht auflöst, ist
entweder historisch (entfernte Klassen und Pakete, die „alt"-Seite von Bruchstellen) oder ein
Fehlalarm (`TestCase` aus PHPUnit, der Header `Location`, Platzhalter wie `$app['…']`).

**Nachweis.** `find-old-names.py`: 0 Treffer über 290 Dateien, Exit 0. Volle Suite
`OK (528 tests, 1703 assertions)` mit dem neuen Provider-Wert, PHPStan `[OK] No errors`,
Deprecation-Gate grün, Template-Config unverändert.
