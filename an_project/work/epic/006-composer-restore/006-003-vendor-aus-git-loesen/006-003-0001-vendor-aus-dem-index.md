---
id: 006-003-0001
title: Beide Vendor-Bäume aus dem Index lösen
status: done
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
- [x] `vendor/` und `custom/vendor/` sind aus dem Git-Index entfernt; die Dateien liegen
      lokal noch.
- [x] Beide Bäume werden von `.gitignore` erfasst.
- [x] `git ls-files composer.lock` und `git ls-files custom/composer.lock` liefern je einen
      Treffer — die Lock-Ausnahmen aus `006-002-0003` sind unversehrt.
- [x] `git status` ist nach dem Commit sauber; keine 11 208 Dateien als untracked.
- [x] Kein anderes Verzeichnis ist versehentlich mitgegangen.

## Verification
`git ls-files | wc -l` vorher und nachher — die Differenz muss **11 208** betragen, nicht mehr
und nicht weniger.

Zusätzlich `git ls-files vendor custom/vendor` → leer, und die beiden Lock-Prüfungen oben.

**Die Anwendung wird in diesem Task nicht geprüft.** Sie läuft lokal weiter, weil die Dateien
noch da sind; ob sie aus dem committeten Stand entsteht, beantwortet erst `006-003-0002`.

## Ergebnis
**11 591 → 383 Dateien im Index.** Differenz **11 208** — exakt die erwartete Zahl, nicht eine
mehr.

| Prüfung | Ergebnis |
|---|---|
| `git ls-files` vorher / nachher | 11 591 / 383 → Differenz **11 208** |
| `git ls-files vendor custom/vendor` | **0 Treffer** |
| Vorgemerkte Löschungen ausserhalb der beiden Bäume | **0 von 11 208** |
| Untracked-Einträge (`??`) | **0** |
| `git ls-files composer.lock` | 1 Treffer |
| `git ls-files custom/composer.lock` | 1 Treffer |
| `git check-ignore vendor/autoload.php` · `custom/vendor/autoload.php` | beide ignoriert |
| `git check-ignore composer.lock` · `custom/composer.lock` | beide **nicht** ignoriert |

Die letzten beiden Zeilen sind die eigentliche Absicherung: Die neuen Regeln fassen die
Vendor-Bäume und lassen die Lock-Ausnahmen aus `006-002-0003` unberührt. Beides einzeln
gemessen, nicht aus der Reihenfolge der Regeln geschlossen.

### Die `.gitignore`-Regeln — verankert, und beide genannt
```
/vendor/
/custom/vendor/
```

Der Task-Text stellte die Wahl: Ein unverankertes `vendor/` hätte beide Bäume erfasst — „was
hier zufällig richtig wäre, aber aus dem falschen Grund". Es greift auf **jeder** Ebene, also
auch auf einem künftigen `plugins/x/vendor`, das vielleicht committet gehört. Die beiden
verankerten Regeln sagen genau, was gemeint ist. Die Begründung steht als Kommentar in der
Datei, damit die nächste Person die Verankerung nicht als Umständlichkeit wegkürzt.

### Ein Fehlgriff beim Nachmessen
Meine erste Kontrolle „ist sonst etwas mitgegangen?" lief über `grep` auf die
`git status --porcelain`-Zeilen und meldete einen vermeintlichen Fremdpfad:

```
D  "custom/vendor/phpunit/phpunit/tests/_files/Go ogle-Sea.rch.wsdl"
```

Der Pfad ist keiner — er liegt in `custom/vendor/`. Git setzt Pfade mit Sonderzeichen in
Anführungszeichen, und daran ist mein Muster vorbeigelaufen. Nachgezählt wurde deshalb mit
einem Skript, das die Quotes entpackt: **0 von 11 208** liegen ausserhalb der beiden Bäume.

### Was dieser Task nicht beantwortet
Ob die Anwendung aus dem committeten Stand entsteht. Lokal läuft sie weiter, weil die Dateien
auf der Platte liegen — das ist kein Beleg, sondern nur der Grund für `--cached`. Den Beleg
führt `006-003-0002` im frischen Klon.
