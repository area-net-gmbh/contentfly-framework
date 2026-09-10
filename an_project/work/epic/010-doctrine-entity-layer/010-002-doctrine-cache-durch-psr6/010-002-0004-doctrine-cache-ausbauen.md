---
id: 010-002-0004
title: doctrine/cache aus dem Baum nehmen
status: review
depends_on: [010-002-0002, 010-002-0003]
---

# doctrine/cache aus dem Baum nehmen

## Context
Nach den drei vorigen Tasks benutzt niemand mehr `Doctrine\Common\Cache`. Was bleibt, ist der
Ausbau — und er ist der eigentliche Ertrag dieser Story: `doctrine/cache` ist das **zweite und
letzte** abandoned Paket, das Epic `009` übergeben hat.

Dazu gehört das PHPStan-Muster für die vier Cache-Klassen. **Regel 3 gilt:** Ein Muster, das
nichts mehr trifft, macht den Lauf rot — es muss also weg, nicht stehenbleiben.

Und `tools/ci/audit.sh` trägt den Vermerk „auf `fail` umstellen, sobald Epic 010 durch ist".
Ob der Schalter hier schon gezogen wird oder erst mit `010-005`, ist zu entscheiden: Nach
diesem Task ist die Liste leer, aber Epic `010` ist es nicht.

## Acceptance criteria
- [x] `doctrine/cache` steht nicht mehr in `composer.json`.

> **Richtiggestellt, 2026-09-10.** Das Kriterium verlangte, `composer.lock` bestätige den
> Ausbau. **Das ist unter ORM 2.20 nicht erreichbar:** `doctrine/orm` 2.20.13 fordert
> `doctrine/cache (^1.12.1 || ^2.1.1)` selbst an. Das Paket bleibt als transitive Abhängigkeit
> im Baum, bis `010-003` auf ORM 3 hebt — ORM 3.7 verlangt `psr/cache` statt `doctrine/cache`,
> nachgesehen im Manifest des Releases.
- [x] `Doctrine\Common\Cache` kommt im eigenen Code nicht mehr vor — ausgenommen Erklärungen, die den abgelösten Weg beschreiben.
- [x] Das PHPStan-Muster für die Cache-Klassen ist gestrichen, und der Lauf ist `[OK] No errors`.
- [x] `composer audit --locked` meldet **ein** abandoned Paket — `doctrine/cache`, und zwar als
      transitive Abhängigkeit des ORM. Die ursprüngliche Fassung des Kriteriums verlangte
      **null**; siehe die Richtigstellung oben.
- [x] Über `--abandoned=fail` ist entschieden: **nicht hier**, sondern mit `010-003`. Der
      Schalter kann erst greifen, wenn die Liste leer ist, und das wird sie mit dem ORM-Sprung.
- [x] Volle Suite grün, Console startet, `appcms:install` läuft auf einer frischen Datenbank durch.

## Verification
`composer audit --locked`, `phpstan analyse --memory-limit=512M`, die volle Suite und ein
vollständiger Durchlauf mit frischer Datenbank. Dazu ein `grep` über `lib`, `custom`, `bin` und
`tests` als Nachweis, dass keine Codestelle übrig ist.

## Ergebnis

**Die direkte Anforderung ist weg, und der abandoned Code ist aus dem Baum — das Paket selbst
bleibt vorerst.** Das ist weniger, als der Task versprach, und mehr, als der bloße Ausbau der
Zeile gebracht hätte.

### Warum das Paket nicht gehen kann

`doctrine/orm` 2.20.13 fordert `doctrine/cache (^1.12.1 || ^2.1.1)` **selbst** an. Nachgemessen
mit `composer why`, und der Ausbau aus unserem `require` hat es erwartungsgemäß nicht entfernt:
44 Pakete vorher, 44 nachher, `0 removals`.

Es geht mit `010-003`: ORM 3.7 fordert `psr/cache` an und `doctrine/cache` nicht mehr — im
Manifest des Releases nachgesehen, nicht vermutet.

### Der Ausbau hat trotzdem etwas bewirkt, und zwar das Wesentliche

Unser `^1.13` hielt das Paket auf **1.13.0**. Ohne diese Klammer wählt Composer im Rahmen des
ORM die **2.2.0** — und das ist eine ganz andere Auslieferung:

| | 1.13.0 | 2.2.0 |
|---|---|---|
| Dateien | rund 40 | **13** |
| `ApcCache`, `ApcuCache`, `FilesystemCache`, `MemcachedCache` | vorhanden | **weg** |
| Inhalt | Implementierungen plus PSR-6-Brücke | nur Schnittstellen und die Brücke |

Alle vier Klassen, deren Instanziierung PHPStan seit `006-005` als deprecated meldete, liegen
nicht mehr im Baum. Was bleibt, sind Schnittstellen und `Psr6\DoctrineProvider`, die das ORM
intern für seine `…Impl()`-Getter braucht.

**Der Sprung von 1.13 auf 2.2 war nicht geplant.** Er ist die Folge davon, dass unsere
Untergrenze fiel, und er ist tragbar: Der eigene Code benutzt keine der entfallenen Klassen
mehr — das war `010-002-0001` bis `-0003` —, und die Suite ist grün.

### Was das für die Gates heißt

`tools/ci/audit.sh` trägt den Vermerk „auf `fail` umstellen, sobald Epic 010 durch ist". Der
Schalter bleibt hier stehen: `composer audit` meldet weiterhin **ein** abandoned Paket, und ein
Gate scharfzustellen, während die Liste nicht leer ist, würde die Pipeline anhalten. Die
Entscheidung gehört an `010-003`, wo das Paket tatsächlich fällt.

Das PHPStan-Muster für die vier Cache-Klassen ist bereits mit `010-002-0002` gestrichen worden —
dort traf es nichts mehr und machte den Lauf rot.

### Nachweis

| Probe | Ergebnis |
|---|---|
| `doctrine/cache` in `composer.json` | 0 Treffer |
| im Lock installiert | ja, **2.2.0** statt 1.13.0, als transitive Abhängigkeit des ORM |
| `composer why doctrine/cache` | `doctrine/orm 2.20.13 requires doctrine/cache` |
| Die vier abandoned Cache-Klassen | **nicht mehr im Baum**, je einzeln über `class_exists` geprüft |
| `Doctrine\Common\Cache` im eigenen Code | 0 Codestellen, nur zwei Erklärungen |
| `composer audit --locked` | keine Advisories, 1 abandoned |
| Volle Suite | `OK (268 tests, 642 assertions)`, 0 übersprungen |
| PHPStan | `[OK] No errors` |
| Console und `appcms:install` | startet, läuft auf frischer Datenbank durch |
