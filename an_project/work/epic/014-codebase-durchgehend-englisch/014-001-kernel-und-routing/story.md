---
id: 014-001-0000
title: Kernel, Routing, Metadaten und Einstiegspunkte auf Englisch
status: done
depends_on: []
---

# Kernel, Routing, Metadaten und Einstiegspunkte auf Englisch

## Goal
`lib/contentfly/Classes/Kernel/**`, `Classes/Metadaten/`, `lib/contentfly/bootstrap.php`,
`bootstrap-web.php`, `index.php`, `bin/` und `phpunit.xml.dist` sind englisch: Namen nach der
Tabelle im Epic, Meldungen und Kommentare. Das umfasst `CONTENTFLY_PROJECT_DIR` und damit
`custom/config.php` an genau dieser Stelle.

Aufrufer im restlichen Baum und in den Tests werden in derselben Story mitgezogen, damit die
Suite grün bleibt. Deren Kommentare und eigene Namen folgen in ihren Stories.

**Warum zuerst:** Alles andere hängt am Kernel. Wer `Pfade` nach `Security` umbenennt, fasst
dieselben Aufrufer zweimal an.

## Tasks
- [x] 014-001-0001 — Paths und Start samt Einstiegspunkten auf Englisch
- [x] 014-001-0002 — Container, Application, Console und Command auf Englisch
- [x] 014-001-0003 — Routing und Metadaten auf Englisch
- [x] 014-001-0004 — bootstrap.php, bootstrap-web.php und phpunit.xml.dist auf Englisch
- [x] 014-001-0005 — Sprachwächter als Test
