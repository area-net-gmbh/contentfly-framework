---
id: 011-003-0002
title: Den README auf Contentfly 2 bringen
status: done
depends_on: [011-003-0001]
---

# Den README auf Contentfly 2 bringen

## Context
**Das Erste, was jemand liest, beschreibt ein Produkt, das es nicht mehr gibt.**

| Im README | Stand |
|---|---|
| „Das Contentfly CMS", Banner von `contentfly-cms.de` | Die Oberfläche ist mit Epic `012` ersatzlos entfallen |
| „Migration 1.5 auf 1.6" | zwei Hauptversionen zurück |
| „PHP 7.1 oder höher" | Minimum ist 8.3, Zielplattform 8.5 |
| Systemumgebung über ein **Ant**-Buildskript | `build.xml` spielt im heutigen Ablauf keine Rolle |
| `appcms/vendor` | die Struktur ist seit Epic `006`/`007` eine andere |

Die Klon-URL ist mit `011-002-0001` bereits korrigiert worden — sie zeigte auf
`area-net-gmbh/contentfly-cms`, also auf 1.x.

**Was der README künftig ist:** die kürzeste wahre Beschreibung dessen, was dieses Repository
enthält, plus die drei Wege hinaus — ein neues Projekt aufsetzen (Runbook), ein Bestandsprojekt
migrieren (`migration.md`), im Framework selbst arbeiten (`dev-guide.md`).

**Was er nicht wird:** eine zweite Fassung des Runbooks. Was dort steht, gehört nicht hierher
kopiert; es liefe auseinander.

## Acceptance criteria
- [x] Der README beschreibt Contentfly 2: keine Oberfläche, Datenhaltung plus Kernfunktionen, Zugriff über API und Console.
- [x] Die Systemvoraussetzungen stimmen mit `composer.json` überein — nicht von Hand geschätzt.
- [x] Die drei Wege hinaus sind verlinkt statt ausgeschrieben.
- [x] Was historisch ist und bleiben soll (etwa der Hinweis auf Silex bis Epic `009`), steht als Historie gekennzeichnet — nicht als gegenwärtiger Stand.
- [x] Keine Verweise mehr auf Dinge, die es nicht gibt: Ant-Ablauf, `appcms/`, die CMS-Oberfläche.

## Verification
Jemand, der das Repository nicht kennt, kommt vom README aus in höchstens einem Schritt zu dem
Dokument, das seine Frage beantwortet. Gegenprobe: Jede genannte Voraussetzung und jeder genannte
Pfad existiert.

## Ergebnis

**Neu geschrieben, nicht nachgezogen.** Entschieden am 2026-09-17: Der README geht komplett auf
Contentfly 2 — das Meiste des alten Textes war nicht veraltet, sondern beschrieb ein **anderes
Produkt**.

Was weg ist und warum:

| Alt | Grund |
|---|---|
| Banner und Links auf `contentfly-cms.de`, „Das Contentfly CMS" | Die Oberfläche ist mit Epic `012` ersatzlos entfallen |
| „Migration 1.5 auf 1.6", `appcms/vendor`, Ordnerstruktur-Tabellen | zwei Hauptversionen zurück; der heutige Weg steht in `migration.md` |
| „PHP 7.1 oder höher" | Minimum ist `^8.3` — **aus dem Manifest gelesen, nicht geschätzt** |
| Ant-Buildskript, Release-Download | spielen im heutigen Ablauf keine Rolle |

Was stattdessen dasteht: was Contentfly **ist**, was es **nicht** ist (keine Oberfläche — und die
Abgrenzung zu 1.x, das denselben Namen trägt), der Stand aus den Manifesten, die **drei Wege
hinaus**, und die Trennung Paket/Skeleton, die Epic `007` gezogen hat.

**Verlinkt statt kopiert.** Der Runbook-Abschnitt aus `011-002-0004` beschreibt den Start eines
Projekts vollständig; ihn hierher zu kopieren hiesse, zwei Fassungen zu haben, die auseinanderlaufen.
Der README nennt nur, wo es steht.

**Die Historie steht als Historie** — eine Fussnote am Ende, die sagt, dass Silex bis Epic `009`
und ORM 2 bis Epic `010` liefen und dass eine Anleitung mit `appcms/`, Ant oder PHP 7 Contentfly
1.x meint. Das ist die nützliche Form: Sie hilft dem, der auf ein altes Dokument stösst, statt
selbst als aktueller Stand gelesen zu werden.

### Jede Aussage gegengeprüft

| Prüfung | Ergebnis |
|---|---|
| Jeder genannte Pfad existiert | 18 geprüft, alle vorhanden |
| PHP, Erweiterungen, Symfony, Doctrine | aus `lib/contentfly/composer.json` gelesen |
| „Beispiel-Controller, -Entity, -Service, -Command und -Provider" | alle fünf liegen in `custom/` |
| „31 MB" der entfallenen Oberfläche | aus `tech-stack.md` |
| „fünf Prüfungen" | die Pipeline hat fünf Jobs |

Nebenbei stimmt damit erstmals, was `architecture.md` in seiner Tabelle behauptet: *„`README.md` —
beide, verschieden: das Paket beschreibt das Paket, das Projekt das Projekt."* Das Paket hat seinen
eigenen seit `011-002-0003`.
