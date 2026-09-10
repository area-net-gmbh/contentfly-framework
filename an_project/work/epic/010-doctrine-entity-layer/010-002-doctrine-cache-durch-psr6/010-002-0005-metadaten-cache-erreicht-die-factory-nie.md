---
id: 010-002-0005
title: Der Metadaten-Cache erreicht die Factory nie
status: done
depends_on: [010-002-0001]
---

# Der Metadaten-Cache erreicht die Factory nie

## Context
Entstanden aus einem Befund in `010-002-0001`, nicht aus dem Story-Schnitt.

**Der Metadaten-Cache ist konfiguriert und wirkungslos.** `bootstrap.php` setzt ihn auf der
`Configuration`, **nachdem** `$app['orm.em']` den EntityManager gebaut hat — und
`EntityManager::__construct()` ruft `configureMetadataCache()` **einmal**, im Konstruktor
(`vendor/doctrine/orm/src/EntityManager.php:173`). Was danach auf der Konfiguration landet,
sieht die `ClassMetadataFactory` nie.

Gemessen, in beide Richtungen und ohne Datenbank:

| Aufbau | Cache-Dateien nach einer Metadaten-Abfrage |
|---|---|
| Cache **vor** dem EntityManager gesetzt | 2 |
| Cache **nach** dem EntityManager gesetzt | **0** |

Und im Testlauf, `ReadApiTest` gegen den echten Server:

| Stand | `data/cache/query` | `data/cache/metadata` |
|---|---|---|
| vor `010-002-0001` (`doctrine/cache`) | 7 | **0** |
| nach `010-002-0001` (PSR-6) | 7 | **0** |

**Es ist also kein Rückschritt der Umstellung, sondern ein alter Defekt** — der Abfrage-Cache
greift, weil `Configuration::getQueryCache()` bei jeder Abfrage gelesen wird, der
Metadaten-Cache nur ein einziges Mal beim Bau.

**Warum es ein eigener Task ist:** Ihn zu beheben heisst, einen Cache einzuschalten, der seit
Jahren aus war. Das ist eine Verhaltensänderung mit Folgen — Metadaten werden dann über
Requests hinweg wiederverwendet, und ein Deployment muss den Cache räumen. Das gehört nicht
still in eine Umstellung des Cache-Formats.

## Acceptance criteria
- [x] Der Metadaten-Cache wird gesetzt, **bevor** der EntityManager entsteht — also in
      `EntityManagerFactory::erzeugen()`, nicht danach im Bootstrap.
- [x] Nachgewiesen, dass er greift: `data/cache/metadata` trägt nach einem Testlauf Einträge.
- [x] Die Bedingung `!APP_DEBUG && !APPCMS_CONSOLE` bleibt erhalten — im Debug-Modus und auf
      der Konsole darf weiterhin **kein** Cache aktiv sein, sonst arbeitet ein Entwickler
      gegen veraltete Metadaten.
- [x] Was das Einschalten für ein Deployment bedeutet, steht in `an_project/docs/deployment.md`
      und in `an_project/docs/breaking-changes.md`.
- [x] Die Suite bleibt grün, und `appcms:install` läuft auf einer frischen Datenbank durch —
      gerade der Installer ist empfindlich, weil er Metadaten erzeugt, bevor es ein Schema gibt.

## Verification
`data/cache/metadata` vor dem Lauf leeren, volle Suite, danach zählen. Dazu ein zweiter Lauf
ohne Leeren: Er muss dieselben Ergebnisse liefern — ein Cache, der falsche Metadaten
zurückgibt, fällt sonst erst in Produktion auf.

## Ergebnis

**Der Metadaten-Cache greift.** Nach einem Suite-Lauf mit geleertem Verzeichnis stehen
**16 Einträge** unter `data/cache/metadata` — vorher waren es null, mit `doctrine/cache` wie mit
PSR-6.

### Die Caches gehen jetzt in die Factory, nicht auf die Konfiguration danach

`EntityManagerFactory::erzeugen()` nimmt zwei optionale PSR-6-Pools entgegen und setzt sie,
**bevor** `EntityManager::create()` läuft. Der Bootstrap wählt sie in einer eigenen Closure aus,
die die Treiberentscheidung aus `010-002-0002` unverändert übernimmt.

`null` heisst „kein Cache", und der Aufrufer entscheidet das — nicht die Factory. Damit bleibt
die Bedingung `!APP_DEBUG && !APPCMS_CONSOLE` genau eine Stelle, statt sich über zwei Dateien zu
verteilen.

### Warum es überhaupt nicht ging

`EntityManager::__construct()` ruft `configureMetadataCache()` **einmal**. Der Abfrage-Cache
überlebte das, weil `Configuration::getQueryCache()` bei jeder Abfrage gelesen wird — der
Metadaten-Cache nicht.

Gemessen, ohne Datenbank und in beide Richtungen:

| Aufbau | Cache-Dateien nach einer Metadaten-Abfrage |
|---|---|
| vor dem EntityManager gesetzt | 2 |
| nach dem EntityManager gesetzt | **0** |

### Nachweis

| Probe | Ergebnis |
|---|---|
| Suite mit geleertem Cache | `OK (268 tests, 642 assertions)`; danach 34 Abfrage- und **16 Metadaten-Einträge** |
| **Zweiter Lauf, Cache bleibt stehen** | `OK (268 tests, 642 assertions)` — dieselben Zahlen, keine falschen Metadaten |
| `appcms:install` auf frischer Datenbank | läuft durch; `data/cache/metadata` bleibt bei **0** Einträgen, wie vorgesehen |
| PHPStan | `[OK] No errors` |
| Deprecation-Gate | 0 Zeilen, 0 Ausnahmen |

Der zweite Lauf ist der wichtigere: Ein Cache, der falsche Metadaten zurückgibt, wäre beim
ersten Lauf grün und fiele erst in Produktion auf.

### Was das für ein Deployment heisst

Steht in `an_project/docs/deployment.md` unter *Der Cache beim Deployment* und als Bruchstelle
in `breaking-changes.md`. Der Punkt ist unscheinbar und folgenreich: Wer bisher eine Entity
änderte, sah die Änderung sofort — **weil der Cache nicht funktionierte**. Ab jetzt muss ein
Deployment ihn räumen.
