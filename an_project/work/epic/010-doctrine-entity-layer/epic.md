---
id: 010-000-0000
title: Doctrine und Entity-Layer modernisieren
status: todo
depends_on: [006-000-0000, 012-000-0000]
---

# Doctrine und Entity-Layer modernisieren

## Goal
Der Entity-Layer ist die tiefste Kopplung des Frameworks — jedes Bestandsprojekt erbt von
`Areanet\PIM\Entity\Base` und schreibt `@PIM\*`-Annotationen in seine eigenen Entities. Was hier
entschieden wird, muss jedes migrierende Projekt nachvollziehen. Deshalb ein eigenes Epic und
nicht ein Nebenschauplatz des Kernel-Umbaus.

## Erfolgskriterien
- **Doctrine ORM auf einer releasten, gepflegten Version.** Der Dev-Branch-Pin
  `doctrine/orm dev-bugfix-many2many` ist Geschichte (aufgelöst in 006) — hier folgt der
  eigentliche Versionssprung Richtung ORM 3, samt DBAL. Was der Fork am `dev-bugfix-many2many`
  löste, muss vorher verstanden sein: entweder ist es in ORM 3 behoben, oder es braucht eine
  saubere Lösung im Framework.
- **Annotationen → PHP-Attribute.** ORM 3 kennt keinen Annotation-Driver mehr. Betrifft `@ORM\*`
  **und** den `@PIM\*`-Rest, der 012 überlebt — gelesen über `AnnotationReader` u. a. in
  `Classes/Api.php:1419`, `Classes/Types/JoinBidirectionalType.php:58`,
  `Controller/ApiController.php`. Das abandoned `doctrine/annotations` fällt damit weg.
  Reihenfolge: erst 012 die UI-Anteile streichen, dann hier den Rest umstellen — sonst werden
  Formular-Annotationen auf Attribute portiert, die gleich danach gelöscht werden.
- **`Base` und die eigenen Doctrine-Typen bleiben die öffentliche API** des Frameworks — sie sind
  der Grund, warum Bestandsprojekte überhaupt migrierbar sind. Änderungen daran werden bewusst
  getroffen und in 007 dokumentiert.
- **Krypto: `StringType` von AES-CBC-ohne-MAC auf AEAD** (XChaCha20-Poly1305). CBC ohne MAC ist
  unauthentifiziert und manipulierbar; der Umbau ist die Gelegenheit, das zu beenden.
  Dazu gehört ein **ausführbarer Re-Encrypt-Befehl** mit Trockenlauf und Rollback-Weg — er läuft
  später in jedem Bestandsprojekt auf dessen eigenen Daten (007), nicht hier.
- **Konstanten in Attributen geprüft:** Die heutigen Annotationen nutzen Konstanten
  (`APPCMS_ID_TYPE` …). Der Weg, wie diese Konfigurierbarkeit unter Attributen erhalten bleibt,
  ist Teil der Abnahme. UI-Konstanten wie `APP_CMS_SHOW_ID_IN_LIST` entfallen mit 012.
- Das Testnetz aus 008 bleibt grün; Migration und Schema-Diff sind reproduzierbar.

## Stories
<!-- Die Stories dieses Epics. Wird von /new-story synchron gehalten. -->
