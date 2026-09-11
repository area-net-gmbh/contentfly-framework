---
id: 007-004-0003
title: Die Vollständigkeit prüfbar machen
status: review
depends_on: [007-004-0002]
---

# Die Vollständigkeit prüfbar machen

## Context
**Die Abnahme der Story verlangt es ausdrücklich:** „Jeder der Brüche ist im Leitfaden
erreichbar — entweder als eigener Schritt oder als Verweis ins Register, und **keiner fällt
heraus**."

**Eine Sorgfaltsfrage bleibt es nur so lange, bis sie einmal schiefgeht.** Das Register wächst
mit jeder Story; in Epic `007` allein sind vier Einträge dazugekommen. Ohne Prüfung fällt ein
neuer Abschnitt genau dann heraus, wenn niemand mehr daran denkt.

## Was geprüft wird

**Abschnittsweise, entschieden am 2026-09-11.** Jeder der Register-Abschnitte muss im Leitfaden
einer Phase zugeordnet sein.

**Und die Gegenrichtung, wie bei den Gates aus `006-005`:** Eine Zuordnung, die auf einen
Abschnitt zeigt, den es nicht mehr gibt, macht den Lauf rot. Sonst bliebe sie stehen, nachdem
der Abschnitt umbenannt oder aufgelöst wurde — und der Leitfaden führte ins Leere.

## Acceptance criteria
- [x] Ein Test hält fest, dass jeder Abschnitt von `breaking-changes.md` im Leitfaden einer Phase zugeordnet ist.
- [x] Er hält die Gegenrichtung: Eine Zuordnung ohne Abschnitt macht den Lauf rot.
- [x] Beide Richtungen sind durch absichtliche Verletzung geprüft.
- [x] Die Meldung sagt, was zu tun ist — nicht nur, dass etwas fehlt.
- [x] Die volle Suite bleibt grün.

## Verification
Einen neuen Abschnitt in `breaking-changes.md` anlegen, ohne den Leitfaden zu ergänzen — der Test
muss rot werden. Eine Zuordnung auf einen Abschnitt zeigen lassen, den es nicht gibt — ebenso.
Danach zurücksetzen.

## Ergebnis

**`tests/Unit/Migration/MigrationsleitfadenTest.php`, drei Tests**, und sie brauchen keine
Datenbank — gelesen werden zwei Dateien.

| Prüfung | Was sie verhindert |
|---|---|
| Jeder Register-Abschnitt ist einer Phase zugeordnet | dass der Leitfaden an einem Abschnitt vorbeiführt |
| Jede Zuordnung trifft einen Abschnitt | eine Zuordnung ins Leere — sie sieht aus wie ein Weg |
| Die Grössenangabe im Kopf stimmt noch | eine Zahl, die nicht mehr stimmt |

**Die dritte ist dazugekommen, weil der Leitfaden eine Zahl behauptet.** „100 Einträge in 14
Abschnitten" steht in seinem Kopf; ohne Prüfung wäre sie beim nächsten Bruch still falsch. Sie
trägt ihr Datum, damit sichtbar bleibt, worauf sie sich bezieht.

**Beide Richtungen sind durch absichtliche Verletzung geprüft:** ein neuer Abschnitt im Register
ohne Zuordnung, und eine Zuordnung auf einen Abschnitt, den es nicht gibt. Beide melden sich mit
dem, was zu tun ist — nicht nur damit, dass etwas fehlt.

## Ein Fehlschlag im vollen Lauf, und er war keiner von mir

**Der erste volle Lauf meldete einen Fehlschlag in `SyncApiTest`.** Meine Änderungen in diesem
Task betrafen zwei Dokumente und eine neue Testdatei — mit `SyncApiTest` hat davon nichts zu tun.

`testZweiLoeschungenInDerselbenSekundeKommenBeide` schreibt zwei Log-Zeilen und prüft, dass ein
Sync-Client beide bekommt, wenn er sich den Zeitstempel der zweiten merkt. **Der Test heisst „in
derselben Sekunde", stellt das aber nicht sicher:** Beide Zeilen bekommen `NOW()` in zwei
getrennten `INSERT`s. Fällt eine Sekundengrenze dazwischen, trägt die erste Zeile die Sekunde
davor — und fällt aus dem Ergebnis, weil die Abfrage ab der zweiten filtert.

**Gemessen statt vermutet.** Der erste Versuch, es nachzustellen, fand nichts: 2000 Paare
`SELECT NOW()` lagen alle in derselben Sekunde. Mit dem **echten** Ablauf — zwei vorbereiteten
`INSERT`s mit demselben Aufwand drumherum — sieht es anders aus:

```
2 von 1500 Paaren fielen auseinander (0,13 %)
```

Das passt zu dem, was zu sehen war: ein Fehlschlag in vielen Läufen.

**Der zweite volle Lauf war grün** — `OK (524 tests, 1688 assertions)`. Der Fehlschlag ist also
kein Rückschritt, sondern ein Test, dessen Voraussetzung er selbst nicht herstellt. **Ich habe
ihn nicht nebenbei repariert:** Er gehört nicht zu dieser Story, und die Reparatur ist klein,
aber sie ist eine Änderung an einer Zusicherung. Sie bekommt einen eigenen Task.

**Zahlen:** Volle Suite `OK (524 tests, 1688 assertions)` (vorher 521), 0 Deprecations bei 0
Ausnahmen, 0 Byte Postausgang. PHPStan `[OK] No errors`.
