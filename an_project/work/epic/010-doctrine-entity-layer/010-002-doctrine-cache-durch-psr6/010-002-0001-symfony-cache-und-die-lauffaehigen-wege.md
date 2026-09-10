---
id: 010-002-0001
title: symfony/cache aufnehmen und Filesystem und APCu umstellen
status: todo
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
- [ ] `symfony/cache` steht in `composer.json`, und die Auflösung bewegt nur das, was es selbst braucht — gezählt, nicht angenommen.
- [ ] Der `filesystem`-Zweig benutzt `FilesystemAdapter` mit getrennten Verzeichnissen für Query und Metadaten, wie bisher `data/cache/query` und `data/cache/metadata`.
- [ ] Der `apcu`-Zweig benutzt `ApcuAdapter` mit den Namensräumen `query` und `metadata`.
- [ ] `setQueryCache()`/`setMetadataCache()` statt der `…Impl()`-Fassungen; die beiden PHPStan-Muster für `doctrine/cache` sind entsprechend nachgezogen oder gestrichen.
- [ ] Die Suite bleibt grün, und der Cache wird dabei nachweislich benutzt — nicht nur nicht kaputt.

## Verification
Volle Suite. Zusätzlich der Nachweis, dass der Cache greift: `data/cache/query` und
`data/cache/metadata` vor dem Lauf leeren, danach zählen — beide müssen Einträge tragen, und
zwar getrennte. Ein Cache, der stillschweigend nichts tut, sähe sonst aus wie ein
funktionierender.
