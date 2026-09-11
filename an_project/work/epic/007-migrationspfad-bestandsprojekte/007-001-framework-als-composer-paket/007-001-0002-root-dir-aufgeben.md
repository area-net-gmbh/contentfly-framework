---
id: 007-001-0002
title: ROOT_DIR aufgeben — das Projektverzeichnis wird übergeben, nicht geraten
status: done
depends_on: [007-001-0001]
---

# ROOT_DIR aufgeben — das Projektverzeichnis wird übergeben, nicht geraten

## Context
**Das ist die eigentliche Sperre, nicht das Manifest.** In `lib/contentfly/bootstrap.php`, Zeile
2, steht:

```php
const ROOT_DIR = __DIR__ . '/../..';
```

Das Framework rechnet sich das Projektverzeichnis aus **seiner eigenen Lage** aus. Das stimmt
genau so lange, wie es unter `lib/contentfly/` im Projekt liegt. Sobald es unter
`vendor/areanet/…/lib/contentfly/` liegt, zeigt `../..` nach `vendor/areanet/` — und jeder Pfad
darauf ins Leere.

**Der Umfang, nachgezählt am 2026-09-11:** 80 Vorkommen in 18 Dateien.

| Datei | Vorkommen |
|---|---|
| `lib/contentfly/bootstrap.php` | 15 |
| `lib/contentfly/Command/InstallCommand.php` | 8 |
| `lib/contentfly/Classes/Plugin.php` | 6 |
| `lib/contentfly/Classes/File/Backend/FileSystem.php` | 5 |
| übrige in `lib/` | 4 |
| `custom/config.php` | 2 |
| `tests/` | 40 |

Die 40 Vorkommen in `tests/` sind kein Nebenschauplatz: Sie zeigen, dass auch die Suite heute
annimmt, im selben Baum zu liegen.

**Die Konstante ist zugleich der Grund, warum der Fehler leise wäre.** `__DIR__ . '/../..'`
liefert immer einen Pfad — er existiert nur nicht. Ein `file_exists()` darauf gibt `false`
zurück, und die Meldung, die daraus entsteht, handelt von einer fehlenden Datei und nicht von
einer falschen Wurzel. Wer das Paket zum ersten Mal einbindet, sucht an der falschen Stelle.
**Es ist dasselbe Muster wie in `000-000-0029`:** Ein Fehler, der sich als etwas anderes ausgibt.

## Acceptance criteria
- [x] Das Projektverzeichnis kommt von aussen — vom Einstiegspunkt — und wird nirgends mehr aus der Lage einer Frameworkdatei abgeleitet.
- [x] Kein `__DIR__`-relativer Sprung aus `lib/contentfly/` heraus bleibt übrig; ein Test hält das fest, damit der nächste nicht wieder einen einbaut.
- [x] Fehlt die Angabe, bricht der Start mit einer Meldung ab, die **das** benennt — nicht eine Folgedatei, die nicht gefunden wurde.
- [x] Die Suite kommt ohne die Annahme aus, im selben Baum zu liegen.
- [x] Die volle Suite bleibt grün, und `appcms:install` läuft durch.

## Verification
Das Framework in ein Verzeichnis verschieben, das **nicht** zwei Ebenen unter der Projektwurzel
liegt, und von dort starten — vorher und nachher. Vorher: eine Meldung über eine fehlende Datei.
Nachher: entweder es läuft, oder die Meldung nennt die fehlende Angabe. Volle Suite, PHPStan.

## Ergebnis

**`ROOT_DIR` gibt es nicht mehr.** An seiner Stelle steht
`Classes/Kernel/Pfade` — eine Klasse, die das Projektverzeichnis **entgegennimmt** und bis
dahin bei jedem Zugriff wirft. `index.php`, `bin/console.php`, `bin/cli-config.php` und
`tests/bootstrap.php` setzen `CONTENTFLY_PROJEKT`, bevor sie den Bootstrap einbinden.

**Zwei Verzeichnisse, wo vorher eines war — das ist die eigentliche Änderung.** `projekt()`
kommt von aussen und kann nicht abgeleitet werden. `paket()` darf aus `__DIR__` kommen: Eine
Datei darf ihr eigenes Paket finden, sie darf nur nicht daraus schliessen, wo das Projekt liegt.
Genau diese Unterscheidung fehlte, weshalb beides dieselbe Konstante war. Heute fallen die
Werte noch zusammen; die Zusicherung ist, dass sie es nicht müssen.

**Umgestellt: 80 Vorkommen in 18 Dateien** — `bootstrap.php` (15), `InstallCommand` (8),
`Plugin` (6), `FileSystem` (5), `Api` (2), `SystemController` (2), `custom/config.php` (2) und
40 in der Suite. In `lib/` steht keines mehr; was dort noch so heisst, sind Kommentare, die
erklären, was war.

## Nachgemessen: der Unterschied, um den es geht

Beide Stände, dasselbe Ablegen des Frameworks unter `vendor/areanet/contentfly/`.

**Vorher:**

```
Failed opening required '…/vendor/areanet/contentfly/lib/contentfly/../../custom/config.php'
```

Eine Meldung über eine fehlende **Datei**. Dass die **Wurzel** falsch ist, steht dort nicht —
wer das Paket zum ersten Mal einbindet, sucht an der falschen Stelle. Dasselbe Muster wie in
`000-000-0029`: ein Fehler, der sich als etwas anderes ausgibt.

**Nachher, ohne Angabe:**

```
Das Projektverzeichnis ist nicht gesetzt. Der Einstiegspunkt muss es dem Framework
uebergeben, bevor irgendetwas darauf zugreift: Pfade::setzen(__DIR__).
```

**Nachher, mit Angabe:** `paket()` zeigt nach `…/vendor/areanet/contentfly`, `custom()` nach
`…/custom` — zwei verschiedene, je richtige Verzeichnisse, und die Konfiguration ist lesbar.

**Ein Verzeichnis, das es nicht gibt, wird beim Setzen abgewiesen,** nicht erst beim ersten
Zugriff auf eine Datei darunter. Sonst handelte die Meldung wieder von der Datei.

## Der Wächter

`tests/Unit/Kernel/PfadeTest.php`, sechs Tests. Zwei prüfen die Klasse selbst (ohne gesetztes
Verzeichnis wirft jeder Zugriff; ein nicht existierendes wird beim Setzen abgewiesen), einer die
Pfade, einer dass `paket()` **ohne** Projekt auskommt. Die beiden übrigen halten die Regel:
**kein `__DIR__`-Sprung aus `lib/contentfly/` heraus**, gesucht als `__DIR__ . '/..'` und als
`dirname(__DIR__)`, und jede eingetragene Ausnahme muss noch zutreffen.

**Die Ausnahmeliste hat genau einen Eintrag,** `Pfade::paket()` selbst — und er steht dort mit
Begründung, nicht als Muster im Suchausdruck. Der Unterschied ist wichtig: Ein Muster, das
`paket()` allgemein erlaubte, erlaubte auch den nächsten Sprung, der so aussieht.

**Ohne den zweiten Teil wäre der erste in dem Moment wertlos,** in dem jemand daneben wieder
`__DIR__ . '/../..'` schreibt — und das ist die naheliegendste Art, schnell an eine Datei im
Projekt zu kommen.

## Ein eigener Fehlgriff, korrigiert

Mein erster Entwurf benutzte `Pfade::custom()` im Kopf von `bootstrap.php`, während der
`use`-Block dieser Datei weiter unten steht — hinter den ersten `require`s. PHPStan hat es
gemeldet (`Call to static method custom() on an unknown class Pfade`, viermal). Die Aufrufe
stehen jetzt voll qualifiziert und legen ihr Ergebnis in zwei lokale Werte, damit niemand
nachschlagen muss, worauf sich der Kurzname bezieht.

**Zahlen:** Volle Suite `OK (484 tests, 1182 assertions)` (vorher 478), 0 Deprecations bei 0
Ausnahmen, 0 Byte Postausgang. PHPStan `[OK] No errors`. `appcms:install` läuft durch. Die
Bruchstelle steht in `an_project/docs/breaking-changes.md` unter *Paketgrenze (Epic `007`)*.

**Am Rand, und es gehört gesagt:** Der Testlauf hing zwischendurch, weil Docker Desktop einen
Container in `Created` stehen liess. Mein Wegwerf-Laufskript schiebt den Aufbau nach
`/dev/null` und schwieg deshalb — dieselbe Blindheit, die `000-000-0029` in `tools/ci/`
behoben hat. Der Fix dort greift für das Skript hier nicht, weil es nicht im Repo steht.
