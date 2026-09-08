---
id: 006-003-0002
title: Den Build aus dem committeten Stand nachweisen
status: todo
depends_on: [006-003-0001]
---

# Den Build aus dem committeten Stand nachweisen

## Context
`006-003-0001` hat die Vendor-Bäume aus dem Index gelöst. Dieser Task beantwortet die Frage,
die damit offen ist: **Entsteht aus dem committeten Stand plus `composer install` dasselbe
Ergebnis wie vorher?**

Das ist nicht selbstverständlich. Der alte Baum war eingefroren und enthielt Pakete, die
Composer nie so aufgelöst hätte — `006-002-0003` hat gezeigt, dass 27 von 78 Paketen als
`source` ohne `.git` dalagen und der Baum kein gültiger Composer-Zustand war.

## Umfang

### Der Nachweis: frischer Klon
Nicht im Arbeitsverzeichnis, wo die alten Dateien noch liegen. Ein `git clone` in ein
Wegwerf-Verzeichnis hat genau das, was ein neuer Entwickler bekommt — und nichts sonst.

Dort:
```sh
composer install
```
und dann die Suite. **Das ist der Moment, in dem sich zeigt, ob `master` wieder grün ist.**

Erwartet werden die 7 bekannten Failures aus `000-000-0019` (Upload-Pfad) und `000-000-0020`
(`POST /api/schema`) — mehr nicht. Alles darüber hinaus ist ein Befund über den Ausbau.

### Die Deployment-Variante
`an_project/docs/deployment.md` nennt den Befehl, der den Baum im Artefakt erzeugt:

```sh
composer install --no-dev --optimize-autoloader
```

Auch der gehört geprüft. **Er verhält sich anders** als der normale Lauf: `--no-dev` lässt
PHPUnit weg, `--optimize-autoloader` erzeugt eine Classmap statt der PSR-4-Auflösung. Ob die
Anwendung damit bootet, ist eine eigene Frage — insbesondere für die Plugin-Annotationen, die
über `AnnotationRegistry::registerFile()` geladen werden und nicht über den Autoloader
(`006-002-0003`).

### Was zu messen ist
- Bootet die Anwendung? Console **und** HTTP.
- Läuft die Suite mit dem erwarteten Ergebnis?
- Wie lange dauert `composer install` aus dem Nichts? Das ist die Zahl, die jeder CI-Lauf ab
  jetzt zahlt — sie gehört ins Ergebnis, damit `006-005` weiss, worüber es redet.

## Abgrenzung
Keine Dokumentation — das ist `006-003-0003`. Keine Reparatur der 7 bekannten Brüche; die
haben eigene Tickets.

Stellt sich heraus, dass der Ausbau etwas kaputt macht, ist das ein **Befund** und
gegebenenfalls ein Grund, `006-003-0001` zurückzunehmen — nicht etwas, das hier nebenbei
repariert wird.

## Acceptance criteria
- [ ] Ein frischer Klon plus `composer install` erzeugt einen vollständigen Baum.
- [ ] Console und HTTP booten daraus.
- [ ] Die Suite liefert genau die 7 bekannten Failures — keine weiteren.
- [ ] `composer install --no-dev --optimize-autoloader` ist geprüft; ob die Anwendung damit
      bootet, steht im Ergebnis.
- [ ] Die Dauer eines `composer install` aus dem Nichts ist gemessen und festgehalten.

## Verification
Der Klon wird **nach** dem Lauf gelöscht; was bleibt, ist das Protokoll. Es gehört wörtlich
ins Ergebnis, nicht zusammengefasst — `006-005` und Epic `009` bauen darauf auf.

Die Suite läuft mit `CI=true`, damit der Wächter aus `008-005-0002` greift und ein stiller
Übersprung auffällt.
