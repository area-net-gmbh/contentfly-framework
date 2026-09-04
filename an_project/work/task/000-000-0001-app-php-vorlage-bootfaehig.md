---
id: 000-000-0001
title: custom/app.php bootfähig machen — Vorlage referenziert fehlende Klassen
status: review
depends_on: []
---

# custom/app.php bootfähig machen — Vorlage referenziert fehlende Klassen

## Context
Das Repo lässt sich nicht starten: `custom/app.php:9` instanziiert
`Custom\Classes\Service\Bootstrap\SecretsCheck` — eine Klasse, die es hier nicht gibt. `custom/`
wurde aus einem größeren Kundenprojekt kopiert, ausgedünnt und auf `Example` umgestellt
(siehe `an_project/docs/technical.md`), aber `app.php` blieb die vollständige Kundendatei mit
1358 Zeilen und 82 `mount()`-Aufrufen.

Aufgefallen bei der Umsetzung von `012-001-0000`: Jede Verifikation in Epic 012 muss deshalb
statisch bleiben (`grep` + `php -l`), weil kein Aufruf den Bootvorgang erreicht. Das trifft auch
008 (Testnetz), 009 (Kernel) und 010 (Entity-Layer) — dort ist ein laufendes System keine
Bequemlichkeit, sondern die Abnahmegrundlage.

Der Defekt ist vorbestehend und stammt nicht aus Epic 012.

## Acceptance criteria
- [x] Entschieden und festgehalten, was `custom/app.php` sein soll: eine **schlanke Vorlage**
      analog zu `Example`-Controller und -Entity, oder die **vollständige Kundendatei** mitsamt
      den Klassen, die sie braucht. Beides ist vertretbar — der heutige Mischzustand nicht.
- [x] `php bin/console.php list` läuft ohne Fatal Error durch.
- [x] Die HTTP-Seite bootet mindestens so weit, dass eine Route antwortet.
- [x] Was aus der Kundendatei entfernt oder ergänzt wurde, ist in
      `an_project/docs/technical.md` beschrieben — die Vorlage ist Referenz für alle
      Folgeprojekte und darf nicht wieder still auseinanderlaufen.
- [x] Falls Konfiguration oder eine Datenbank für den Boot nötig sind: Der Weg dahin steht im
      Runbook (`an_project/docs/runbook.md`).

## Verification
`php bin/console.php list` ausführen — die Command-Liste erscheint, kein Fatal Error.
Anschließend einen HTTP-Aufruf gegen eine bekannte Route absetzen und eine Antwort erhalten.
