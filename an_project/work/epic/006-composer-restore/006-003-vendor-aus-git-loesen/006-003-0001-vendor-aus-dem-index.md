---
id: 006-003-0001
title: Beide Vendor-Bäume aus dem Index lösen
status: todo
depends_on: []
---

# Beide Vendor-Bäume aus dem Index lösen

## Context
Der eigentliche Eingriff der Story: **11 208 committete Dateien** verlassen die
Versionskontrolle.

| Baum | Dateien im Index |
|---|---|
| `vendor/` | 5 239 |
| `custom/vendor/` | 5 969 |

Ab hier ist ein frischer Checkout **ohne `composer install` nicht lauffähig**. Das ist der
Punkt der Story, kein Nebeneffekt.

Und es ist der Schritt, der `master` wieder grün macht: Seit `006-002-0006` erwarten die Tests
die Statuscodes von Symfony 4.4, während auf `master` noch der alte Baum liegt. Erst wenn er
weg ist und `composer install` greift, passen Erwartung und Wirklichkeit wieder zusammen.

## Umfang

### Aus dem Index, nicht von der Platte
```sh
git rm -r --cached vendor custom/vendor
```

`--cached` ist wichtig: Die Dateien bleiben lokal liegen, damit eine laufende Umgebung nicht
mitten im Umbau zerfällt. Erst der nächste `composer install` überschreibt sie.

### Die `.gitignore` — mit Vorsicht
Punkt B des Story-Texts ist **bereits erledigt**: Die Lock-Regel wurde in `006-002-0003`
umgestellt, als der Root-Lock committet werden musste. Sie lautet jetzt:

```
composer.lock
!custom/composer.lock
!/composer.lock
```

Hier kommen nur die Vendor-Regeln dazu. **Die Reihenfolge entscheidet:** Eine Regel `vendor/`
darf die beiden Lock-Ausnahmen nicht aushebeln, und `custom/vendor/` muss getrennt genannt
werden — ein unverankertes `vendor/` greift auf jeder Ebene und würde auch
`custom/vendor/` erfassen, was hier zufällig richtig wäre, aber aus dem falschen Grund.

Nach dem Ändern ist **beides** zu prüfen:
- `git ls-files composer.lock` liefert einen Treffer,
- `git ls-files custom/composer.lock` ebenso,
- `git status` meldet die Vendor-Bäume nicht mehr.

### Was dabei nicht verlorengehen darf
`custom/composer.json` und `custom/composer.lock` liegen **innerhalb** von `custom/`, aber
nicht in `custom/vendor/` — sie bleiben. Ebenso `vendor/`-fremde Dateien, falls es welche
gibt; ein Blick in `git status` nach dem Entfernen zeigt es.

## Abgrenzung
Kein Nachweis, dass der Build funktioniert — das ist `006-003-0002`. Keine Dokumentation —
das ist `006-003-0003`. Dieser Task macht den Schnitt, nicht mehr.

## Acceptance criteria
- [ ] `vendor/` und `custom/vendor/` sind aus dem Git-Index entfernt; die Dateien liegen
      lokal noch.
- [ ] Beide Bäume werden von `.gitignore` erfasst.
- [ ] `git ls-files composer.lock` und `git ls-files custom/composer.lock` liefern je einen
      Treffer — die Lock-Ausnahmen aus `006-002-0003` sind unversehrt.
- [ ] `git status` ist nach dem Commit sauber; keine 11 208 Dateien als untracked.
- [ ] Kein anderes Verzeichnis ist versehentlich mitgegangen.

## Verification
`git ls-files | wc -l` vorher und nachher — die Differenz muss **11 208** betragen, nicht mehr
und nicht weniger.

Zusätzlich `git ls-files vendor custom/vendor` → leer, und die beiden Lock-Prüfungen oben.

**Die Anwendung wird in diesem Task nicht geprüft.** Sie läuft lokal weiter, weil die Dateien
noch da sind; ob sie aus dem committeten Stand entsteht, beantwortet erst `006-003-0002`.
