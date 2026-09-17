---
id: 011-003-0002
title: Den README auf Contentfly 2 bringen
status: todo
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
- [ ] Der README beschreibt Contentfly 2: keine Oberfläche, Datenhaltung plus Kernfunktionen, Zugriff über API und Console.
- [ ] Die Systemvoraussetzungen stimmen mit `composer.json` überein — nicht von Hand geschätzt.
- [ ] Die drei Wege hinaus sind verlinkt statt ausgeschrieben.
- [ ] Was historisch ist und bleiben soll (etwa der Hinweis auf Silex bis Epic `009`), steht als Historie gekennzeichnet — nicht als gegenwärtiger Stand.
- [ ] Keine Verweise mehr auf Dinge, die es nicht gibt: Ant-Ablauf, `appcms/`, die CMS-Oberfläche.

## Verification
Jemand, der das Repository nicht kennt, kommt vom README aus in höchstens einem Schritt zu dem
Dokument, das seine Frage beantwortet. Gegenprobe: Jede genannte Voraussetzung und jeder genannte
Pfad existiert.
