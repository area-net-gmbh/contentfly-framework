---
id: 014-003-0007
title: Sprachwächter auf das ganze Framework-Paket
status: review
depends_on: [014-003-0006]
---

# Sprachwächter auf das ganze Framework-Paket

## Context
Nach dieser Story ist `lib/contentfly/` vollständig englisch. Der Sprachwächter prüft dann das
ganze Paket statt einzelner Verzeichnisse.

## Acceptance criteria
- [x] `PATHS` in `tests/Unit/EnglishOnlyTest.php` enthält `lib/contentfly` als Ganzes; die bisherigen Einzelpfade darunter sind zusammengefasst.
- [x] Alle Ausnahmen, die `014-003` betreffen, sind gestrichen; übrig sind nur Ausnahmen mit `014-004` oder `014-005`.
- [x] Der Test ist grün. Ein Mutationstest in einer Datei, die bisher nicht geprüft wurde (etwa `Classes/Api.php`), macht ihn rot.

## Verification
Unit-Suite grün, Mutation in `Classes/Api.php` rot mit Datei und Zeile, zurückgesetzt, wieder grün. Volle Suite grün.

## Ergebnis

**Der Sprachwächter prüft `lib/contentfly` als Ganzes.** Die sechs Einzelpfade darunter sind zu
einem zusammengefasst, damit jede neue Datei im Paket automatisch geprüft wird.

**Zwei Reste aus `0001`, die erst die volle Abdeckung gezeigt hat, sind behoben:**
- **Indexname `uniq_user_fremdkennung` → `uniq_user_external_identity`.** Das ist ein
  Datenbankobjekt, aber eines aus dem nie ausgelieferten Schema von Version 2; entschieden wie beim
  Ableitungs-Kontext `contentfly-field`. Nachgeprüft in der Testdatenbank nach der Installation:
  Der Index heisst dort `uniq_user_external_identity`. `LoginManagerApiTest` fragt den neuen Namen
  ab. In `breaking-changes.md` zieht `014-006` nach.
- Platzhalter `<provider>:<kennung>` → `<provider>:<identifier>` im Kommentar von
  `Entity/User.php`.

**Drei neue Wortstämme:** `zugriff`, `verweiger` und `gestattet`, weil „Zugriff verweigert" in
`0004` am Detektor vorbeiging. Der Selbsttest prüft genau diese Meldung.

**Ausnahmen nach der Story:**
- **Eine dauerhafte:** `'Gelöscht'` in `Classes/Api.php`, der Altdaten-Wert aus Contentfly 1.x in
  `pim_log.mode`. Die Begründung und das Entscheidungsdatum stehen in der Ausnahme selbst.
- **Sechs mit `014-005`:** Testklassen- und Testmethodennamen, auf die Kommentare und das
  Manifest verweisen.

Keine Ausnahme nennt mehr `014-003`.

**Mutationstest:** Ein deutscher Kommentar in `Classes/Api.php`, einer Datei, die bisher nicht
geprüft wurde, macht den Test rot: `Api.php:693 [word "fuer"]`. Zurückgesetzt, die Datei ist
unverändert und der Test grün. **Der erste Versuch hat nichts gemeldet, und das lag an der Probe,
nicht am Test:** `sed '0,/…/'` ist GNU-Syntax, macOS-sed hat die Datei gar nicht geändert. `git diff
--stat` hat das gezeigt; die Wiederholung mit Python hat die Datei nachweislich geändert.

Geprüft: volle Suite `OK (528 tests, 1703 assertions)`, eine Assertion mehr für den neuen
Detektor-Fall. PHPStan `[OK] No errors`, Deprecation-Gate grün.
