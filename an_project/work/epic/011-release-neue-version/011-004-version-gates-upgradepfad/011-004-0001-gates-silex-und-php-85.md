---
id: 011-004-0001
title: Die Gates schärfen: Silex-Freiheit und PHP 8.5
status: done
depends_on: []
---

# Die Gates schärfen: Silex-Freiheit und PHP 8.5

## Context
Zwei Aussagen der Story sind heute **gemessen, nicht gehalten** — sie stimmen, aber nichts meldet
sich, wenn sie aufhören zu stimmen. Genau der Unterschied, den dieses Projekt bei seinen drei
anderen Gates schon gezogen hat: „Eine Zusicherung, die niemand prüft, ist keine Zusicherung."

**Silex-Freiheit.** Im Lock stehen null Silex- und Pimple-Pakete. **Ein `grep` genügt dafür
nicht:** Der einzige Treffer auf „silex" in `composer.lock` steht in einem **Kommentar** — dem
`notes`-Block aus `lib/contentfly/composer.json`, den Composer mit einbettet. Ein Gate, das den
Text durchsucht, schlägt darauf an und ist wertlos. Geprüft werden müssen die **Paketnamen**.

**PHP 8.5.** Zielplattform ist 8.5; `config.platform` steht auf 8.3, und die Pipeline fährt 8.3
und 8.4. Gemessen am 2026-09-17: **keines der 55 Pakete** deckelt PHP unter 8.5. Das ist der
heutige Stand und kann sich mit jedem `composer update` ändern.

## Acceptance criteria
- [x] Ein Test hält fest, dass im Lock **kein Paket** aus der Silex-/Pimple-Familie steht — über die Paketnamen, nicht über eine Textsuche. Die Gegenprobe ist Teil des Tests: Ein erfundener Eintrag macht ihn rot.
- [x] Ein Test hält fest, dass kein Paket des Locks PHP unterhalb der Zielplattform deckelt. Die Zielplattform steht an **einer** Stelle, nicht in jedem Test noch einmal.
- [x] Beide Tests nennen im Fehlerfall das Paket und seine Constraint — nicht nur, dass etwas nicht stimmt.
- [x] Volle Suite, PHPStan, Deprecation-Gate grün.

## Verification
Die beiden Tests laufen grün. Gegenprobe je Test: ein von Hand eingefügtes Paket bzw. eine
Constraint `<8.5` macht ihn rot, und die Meldung sagt, welches Paket es war.

## Ergebnis

**`tests/Unit/Kernel/LockGuaranteesTest.php`** — zwei Zusicherungen, die bisher nur gemessen waren.

### Warum nicht `grep`

Der einzige Treffer auf „silex" in `composer.lock` steht **in einem Kommentar**: Composer bettet
den `notes`-Block aus `lib/contentfly/composer.json` wörtlich ein, und darin steht der Satz
*„until epic 009 Silex and knplabs capped them"*. Ein Gate, das den Text durchsucht, meldet also
eine Abhängigkeit, die es nicht gibt — und **ein Gate, das über seine eigene Dokumentation
Alarm schlägt, wird abgeschaltet**. Der Test liest deshalb die Paketnamen.

### Die Zielplattform steht an einer Stelle

`8.5` als Konstante im Test, **ausdrücklich nicht** `config.platform.php` aus dem Manifest: Das ist
`8.3` und sagt, worauf gefahren wird; die Zielplattform sagt, wohin es geht. Die beiden gleichzusetzen
machte das Gate grün — aus dem falschen Grund.

### Der Test hat zuerst sich selbst gefunden

Der erste Lauf meldete `phpstan/phpstan` und `rector/rector` als Blocker für 8.5. **Beide erlauben
8.5**: Ihre Constraint lautet `^7.2|^8.0`, und meine Auswertung trennte nur an `||`, nicht am
einfachen `|`, das Composer ebenfalls zulässt. `^7.2|^8.0` war damit ein einziges Token, wurde als
`^7.2` gelesen und fiel durch.

Das ist genau die Sorte Fehler, gegen die ein Gate schützen soll — und es ist gut, dass er vor dem
Commit auftrat und nicht in einer roten Pipeline mit zwei falsch beschuldigten Paketen.

Die Hilfsmethode zählt deshalb jetzt ausdrücklich: Was sie **nicht versteht**, gilt als erlaubt. Ein
Gate, das aus Unverständnis „verboten" schliesst, ist aus dem falschen Grund rot, und dieses soll
das Paket nennen, das wirklich blockiert.

### Gegenprobe, wie gefordert

Ein von Hand eingefügtes `silex/silex v2.3.0` mit `php ^7.0` macht **beide** Tests rot, jeder mit
dem Paketnamen in der Meldung:

```
These packages are back in composer.lock:
silex/silex v2.3.0

These packages do not allow PHP 8.5:
silex/silex                              php ^7.0
```

Danach wiederhergestellt — `git status composer.lock` ist leer.

**Was der Test nicht behauptet:** dass die Suite auf 8.5 läuft. Das braucht einen Lauf auf 8.5, und
die Pipeline geht bis 8.4. Dies ist die billigere Hälfte — die Constraint-Seite —, und sie
scheitert früh, bevor jemand ein Image für eine Version baut, die die Abhängigkeiten ablehnen.

**Verifiziert:** Unit-Suite `314 Tests, 1030 Assertions`, PHPStan `[OK] No errors`.
