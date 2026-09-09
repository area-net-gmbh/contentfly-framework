---
id: 009-001-0005
title: Ein Wächter gegen die Rückkehr der Silex-Typen
status: todo
depends_on: [009-001-0002, 009-001-0003, 009-001-0004]
---

# Ein Wächter gegen die Rückkehr der Silex-Typen

## Context
Ohne Prüfung ist die Ablösung eine Momentaufnahme. Derselbe Gedanke wie bei
`tests/Unit/AutoloaderUeberschneidungTest.php` aus `006-004-0003`: Eine Bedingung, die niemand
prüft, ist keine Zusicherung — und genau daran ist der alte Zustand jahrelang vorbeigelaufen.

Der Wächter hält fest, wo `Silex\` und `Pimple\` **noch** stehen dürfen: im Bootstrap und in
`Classes/Kernel/`. Das ist keine Ausnahmeliste zum Wachsen, sondern die Liste dessen, was
`009-002` anfasst — sie schrumpft dort auf null.

## Acceptance criteria
- [ ] Ein Unit-Test schlägt fehl, sobald `Silex\` oder `Pimple\` ausserhalb der erlaubten
      Stellen auftaucht. Er läuft ohne Datenbank.
- [ ] Die erlaubten Stellen stehen als benannte Liste mit Begründung im Test, nicht als Muster.
      Verschwindet eine, meldet der Test auch das — eine Ausnahme, die nicht mehr gebraucht
      wird, ist genauso ein Befund wie eine neue Verwendung (dieselbe Regel wie beim
      Audit- und Deprecation-Gate aus `006-005`).
- [ ] Beide Richtungen sind belegt: mit einer eingebauten Verwendung rot, ohne grün — und grün
      als bestandener Test, nicht als übersprungener.

## Verification
`./vendor/bin/phpunit --testsuite unit` grün. Gegenprobe: eine `use Silex\Application;`-Zeile
in eine beliebige Datei ausserhalb der Liste einsetzen, Test läuft rot, Zeile zurücknehmen.
