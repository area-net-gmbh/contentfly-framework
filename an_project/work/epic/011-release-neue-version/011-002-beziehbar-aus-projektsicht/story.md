---
id: 011-002-0000
title: Das Framework aus Projektsicht beziehbar machen
status: todo
depends_on: []
---

# Das Framework aus Projektsicht beziehbar machen

## Goal
**Ein Projekt kann `areanet/contentfly` installieren, ohne dass jemand einen Pfad auf dieser
Maschine kennt.** Epic `007` hat den Bezugsweg entschieden (Composer-Paket statt kopiertem
`lib/`-Baum) und ihn an der Vorlage durchgespielt. Was fehlt, ist der Weg von aussen.

**Der Beleg, dass das noch nicht trägt, liegt vor:** Beim Probelauf am Bestandsprojekt UFP
(`007-005-0003`) musste dessen `composer.json` ein `path`-Repository auf
`/contentfly-framework/lib/contentfly` bekommen — ein absoluter Pfad, den es nur im
Probe-Container gibt. Auf einem Entwicklerrechner scheitert `composer install`. Genau deshalb
ist die UFP-Migration bis zu diesem Epic vertagt.

**Zu entscheiden und umzusetzen:**

- **Wie das Paket herauskommt:** eigenes Repository per Subtree-Split, eine Registry (Satis /
  Composer-Repository / Packagist), oder ein VCS-Repository auf dieses Repo mit passendem
  Zuschnitt. Das Paket liegt heute im Unterordner `lib/contentfly`; das Root-Manifest heisst
  `areanet/contentfly-skeleton`.
- **Wie eine Version dort ankommt** — Tag, Branch oder Registry-Eintrag —, damit ein Projekt
  `^2.0` schreiben kann und nicht `dev-master`.
- **Wie ein Projekt startet:** frischer Checkout der Vorlage, `composer install`, Installation,
  lauffähig — einmal von aussen durchgespielt und im Runbook beschrieben.

**Fertig, wenn** ein Projekt ohne Kenntnis dieses Arbeitsverzeichnisses installiert werden kann
und der Weg im Runbook steht.

## Tasks
<!-- Die Tasks dieser Story. Wird von /new-task synchron gehalten. Geschnitten beim Start der Story. -->
