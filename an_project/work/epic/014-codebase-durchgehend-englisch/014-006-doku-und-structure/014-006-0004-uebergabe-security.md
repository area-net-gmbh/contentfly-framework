---
id: 014-006-0004
title: Übergabenotiz für die Sicherheitsprüfung auf den neuen Stand
status: done
depends_on: [014-006-0003]
---

# Übergabenotiz für die Sicherheitsprüfung auf den neuen Stand

## Context
`an_project/docs/uebergabe-security.md` (`000-000-0033`) beschreibt den Stand für die
IT-Security und verweist auf den Tag `v2.0.0-pre-security-2026-09-11`. Übergeben wird erst nach
Epic `014`, damit sich Befunde auf die heutigen Namen beziehen. Die Notiz nennt noch alte Namen
(`Tokenhandler::timeoutGilt()`, `ausDatenbank()`, „Anmeldebremse").

## Acceptance criteria
- [x] Die Notiz nennt nur Namen, die es im Code gibt, und verweist auf `STRUCTURE.md` als
  englischen Einstieg in die Codebase.
- [x] Die Notiz nennt den neuen Tag nach dem Muster des bestehenden, datiert auf den Tag des
  Setzens. Sie sagt, dass der alte Tag nicht mehr der Prüfstand ist.
- [x] Die Liste der offenen Punkte ist gegen den heutigen Code gelesen: Was Epic `014` nicht
  geändert hat, bleibt unverändert stehen.
- [x] Gesetzt wird der Tag **nicht** in diesem Task, sondern nach `/done` des Epics und nur nach
  Rückfrage. Der Task hält den vorgesehenen Namen und den Befehl fest.

## Verification
Suche mit der Liste der alten Namen. Code-Verweise auflösen. Die Notiz einmal von oben nach unten
lesen, ob sie ohne Vorwissen aus dem Epic verständlich ist.

## Ergebnis

**Die Übergabenotiz beschreibt den Stand nach Epic `014`.**

- **Stand:** `v2.0.0-pre-security-2026-09-14`. Ein neuer Absatz sagt, dass der Tag vom 11.09. nicht
  mehr der Prüfstand ist und warum: Zwischen beiden Tags liegt die Umstellung auf englische Namen
  bei unverändertem Verhalten. Befunde sollen sich auf die neuen Namen beziehen.
- **Einstieg:** `STRUCTURE.md` ist als englischer Einstieg in die Codebase genannt.
- **Zahlen neu gemessen:** 153 Dateien im Framework (vorher 152), unverändert 18
  Laufzeit-Abhängigkeiten, 60 Testdateien mit 528 Tests (vorher 59 und 524).
- **Offene Punkte gegen den Code gelesen.**
  - **A-5 gilt weiter:** `SystemController::addToken()` nimmt `token` aus dem Request, und
    `TokenHandler::timeoutApplies()` gibt für eine Zeile mit `referrer` `false` zurück. Die Notiz
    nennt die heutigen Methodennamen und zitiert den heute englischen Kommentar. Der Weg über
    `TokenHandler::fromDatabase()` kennt `LoginThrottle` weiterhin nicht.
  - **`000-000-0024`, `-0025`, `-0030` und `-0031`** stehen alle auf `todo` und bleiben
    unverändert in der Liste.
- **Gates:** `composer audit --locked` meldet lokal kein Advisory und kein abandoned Paket. Der
  Schalter `--abandoned=fail` fehlt in der lokalen Composer-Version und läuft nur in der CI. Suite,
  PHPStan und Deprecation-Gate sind grün, gemessen in `014-006-0002`.

**Der Tag ist nicht gesetzt.** Er folgt erst nach `/done 014` und nur nach Rückfrage, auf dem
Merge-Commit des Epics in `master`:

```sh
git tag -a v2.0.0-pre-security-2026-09-14 -m "Contentfly Framework — Stand fuer die Sicherheitspruefung, nach Epic 014" master
```

**Wird der Tag an einem anderen Tag gesetzt**, ändern sich sein Name und die Zeile *Stand* in der
Notiz gemeinsam. Beide müssen dasselbe Datum tragen.

**Nachweis.** Suche nach alten Namen über die Notiz: 0 Treffer. Die übrigen Meldungen des
Auflösungsskripts stehen in der „Vorher"-Spalte der Befundtabelle (`sha256($pass.$salt)`,
`createManagedUser()`). Die Notiz ist einmal von oben nach unten gelesen: Sie setzt kein Wissen
über Epic `014` voraus, weil der neue Absatz unter *Stand* erklärt, was sich geändert hat.
