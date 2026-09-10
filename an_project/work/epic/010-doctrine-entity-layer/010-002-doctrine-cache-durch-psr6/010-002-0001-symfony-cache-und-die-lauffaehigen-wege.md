---
id: 010-002-0001
title: symfony/cache aufnehmen und Filesystem und APCu umstellen
status: review
depends_on: []
---

# symfony/cache aufnehmen und Filesystem und APCu umstellen

## Context
Der Bootstrap konfiguriert Query- und Metadaten-Cache über `Doctrine\Common\Cache`. ORM 2.20
nimmt PSR-6 bereits entgegen (`setQueryCache()`, `setMetadataCache()`), ORM 3 **nur noch** —
also lässt sich der Wechsel hier gegen ein unverändertes ORM messen.

Dieser Task nimmt die beiden Wege, die heute **laufen** können: `filesystem` (die Vorgabe aus
`Config::$APP_CACHE_DRIVER`, im Testnetz durchlaufen — `data/cache/query` füllt sich bei jedem
Lauf) und `apcu`. Die beiden anderen entscheidet `010-002-0002`.

**Die Namensraum-Trennung darf nicht wieder verloren gehen.** `009-003-0002` hat sie
hergestellt, nachdem sie jahrelang beabsichtigt war und nicht griff: `new ApcCache('query')`
verwarf sein Argument stillschweigend, und beide Caches lagen im selben Namensraum. Unter
Symfonys Adaptern ist der Namensraum ein Konstruktor-Argument — die Trennung muss also
ausdrücklich dort stehen, nicht als nachträgliches `setNamespace()`.

`symfony/cache` ist die naheliegende Wahl und liegt **noch nicht** im Baum: Der Stack steht
ohnehin auf Symfony 7.4, und die Adapter bilden alle gebrauchten Ausprägungen ab.

## Acceptance criteria
- [x] `symfony/cache` steht in `composer.json`, und die Auflösung bewegt nur das, was es selbst braucht — gezählt, nicht angenommen.
- [x] Der `filesystem`-Zweig benutzt `FilesystemAdapter` mit getrennten Verzeichnissen für Query und Metadaten, wie bisher `data/cache/query` und `data/cache/metadata`.
- [x] Der `apcu`-Zweig benutzt `ApcuAdapter` mit den Namensräumen `query` und `metadata`.
- [x] `setQueryCache()`/`setMetadataCache()` statt der `…Impl()`-Fassungen; die beiden PHPStan-Muster für `doctrine/cache` sind entsprechend nachgezogen oder gestrichen.
- [x] Die Suite bleibt grün, und der **Abfrage-Cache** wird dabei nachweislich benutzt — nicht
      nur nicht kaputt.

> **Richtiggestellt, 2026-09-10.** Das Kriterium nannte „der Cache" und meinte beide. Der
> **Metadaten**-Cache schreibt nichts — und zwar vor der Umstellung genauso wie danach.
> Ursache ist eine Reihenfolge im Bootstrap, nicht das Cache-Format; aufgenommen als
> `010-002-0005`. Hier wird deshalb nur der Abfrage-Cache belegt.

## Verification
Volle Suite. Zusätzlich der Nachweis, dass der Cache greift: `data/cache/query` und
`data/cache/metadata` vor dem Lauf leeren, danach zählen — beide müssen Einträge tragen, und
zwar getrennte. Ein Cache, der stillschweigend nichts tut, sähe sonst aus wie ein
funktionierender.

## Ergebnis

**`symfony/cache` ist im Baum, und die beiden lauffähigen Wege stehen auf PSR-6.** Die
Auflösung hat genau drei Pakete gebracht — `symfony/cache`, `symfony/cache-contracts`,
`symfony/var-exporter`, alle 7.4 beziehungsweise 3.7; 41 auf 44 Pakete. Nichts anderes hat sich
bewegt.

`apc` und `memcached` stehen weiterhin auf `doctrine/cache`; sie entscheidet `010-002-0002`.

### Die Namensraum-Trennung ist jetzt strukturell gesichert

Beim `filesystem`-Weg liegt sie wie bisher in den Pfaden
(`data/cache/query`, `data/cache/metadata`), beim `apcu`-Weg im **Konstruktor-Argument** des
Adapters. Das ist der Unterschied zu vorher: `new ApcCache('query')` verwarf sein Argument
stillschweigend, weil die Klasse gar keinen Konstruktor hat — die Trennung war jahrelang
gemeint und griff nicht, bis `009-003-0002` sie mit `setNamespace()` nachrüstete. Ein
Konstruktor-Argument kann nicht ins Leere laufen.

### Der Befund: der Metadaten-Cache hat nie gegriffen

Das Kriterium verlangte den Nachweis, dass der Cache **benutzt** wird — und genau daran ist er
aufgefallen.

`EntityManager::__construct()` ruft `configureMetadataCache()` **einmal**, im Konstruktor.
`bootstrap.php` setzt den Cache aber, nachdem `$app['orm.em']` den EntityManager gebaut hat.
Was danach auf der Konfiguration landet, sieht die `ClassMetadataFactory` nie.

Gemessen, in beide Richtungen und ohne Datenbank:

| Aufbau | Cache-Dateien nach einer Metadaten-Abfrage |
|---|---|
| Cache **vor** dem EntityManager gesetzt | 2 |
| Cache **nach** dem EntityManager gesetzt | **0** |

Und gegen den echten Server, `ReadApiTest`, mit geleertem Verzeichnis:

| Stand | `data/cache/query` | `data/cache/metadata` |
|---|---|---|
| vor der Umstellung (`doctrine/cache`) | 7 | **0** |
| nach der Umstellung (PSR-6) | 7 | **0** |

**Kein Rückschritt, ein alter Defekt.** Der Abfrage-Cache greift, weil
`Configuration::getQueryCache()` bei jeder Abfrage gelesen wird; der Metadaten-Cache nur einmal
beim Bau. Aufgenommen als `010-002-0005` statt hier miterledigt: Ihn zu beheben heisst, einen
Cache einzuschalten, der seit Jahren aus war — eine Verhaltensänderung, die nicht still in eine
Formatumstellung gehört.

### Eine Zahl, die zuerst falsch aussah

Nach dem ersten vollen Lauf standen 36 Dateien im Abfrage-Cache und **eine** im
Metadaten-Cache. Die eine kam nicht vom Metadaten-Cache: `SystemControllerApiTest` ruft
`flushSchemaCache`, und das leert beide Caches mitten im Lauf. Mit `--filter ReadApiTest`
gemessen sind es 7 und 0.

### Was `010-002-0004` noch braucht

`getQueryCacheImpl()` liefert weiterhin ein Objekt — ORM 2.20 verpackt den PSR-6-Pool in
`Doctrine\Common\Cache\Psr6\DoctrineProvider`. **Diese Brückenklasse liegt in
`doctrine/cache`.** `SystemController::flushSchemaCache()` läuft deshalb heute noch, wird aber
brechen, sobald das Paket geht — genau dafür gibt es `010-002-0003`.

### Nachweis

| Probe | Ergebnis |
|---|---|
| Volle Suite | `OK (267 tests, 640 assertions)`, 0 übersprungen |
| PHPStan | `[OK] No errors` |
| Deprecation-Gate | 0 Zeilen, 0 Ausnahmen |
| Abfrage-Cache nach `ReadApiTest` | 7 Dateien unter `data/cache/query`, 0 unter `metadata` |
| Neue Pakete | 3, alle aus dem Symfony-Baum |
