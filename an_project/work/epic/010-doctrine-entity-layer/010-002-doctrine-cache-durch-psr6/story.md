---
id: 010-002-0000
title: doctrine/cache durch einen PSR-6-Cache ersetzen
status: todo
depends_on: []
---

# doctrine/cache durch einen PSR-6-Cache ersetzen

## Goal
Der Bootstrap konfiguriert Query- und Metadaten-Cache über einen PSR-6-Cache statt über
`Doctrine\Common\Cache`. Das abandoned `doctrine/cache` ist aus dem Baum.

Auch das läuft **vor** dem Versionssprung und aus demselben Grund: ORM 3 nimmt ausschliesslich
PSR-6, aber ORM 2.20 nimmt es bereits — also lässt sich der Wechsel gegen ein unverändertes ORM
messen, und der Sprung in `010-003` trägt ein Paket weniger.

Acht Stellen, alle im Bootstrap, mit vier Ausprägungen: APC, APCu, Filesystem und Memcached.
Die Namensraum-Trennung zwischen Abfrage- und Metadaten-Cache ist mit `009-003-0002`
hergestellt worden und darf dabei nicht wieder verloren gehen — sie war jahrelang beabsichtigt
und griff nicht.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. -->
