---
id: 009-005-0003
title: Die Doctrine-Console-Commands und der Nachweis
status: todo
depends_on: [009-005-0002]
---

# Die Doctrine-Console-Commands und der Nachweis

## Context
`bin/console.php` registriert **achtzehn** Doctrine-Commands und baut dafür ein `HelperSet` mit
`ConnectionHelper` und `EntityManagerHelper`. In DBAL 3 gibt es `ImportCommand` nicht mehr, und
die Helper-Konstruktion hat sich geändert — `SystemController` importiert den `ConnectionHelper`
ebenfalls.

**Was nicht mehr läuft, wird gestrichen, nicht repariert.** Diese Commands sind Werkzeug von
Doctrine, nicht Funktion von Contentfly; Epic `010` hebt Doctrine ohnehin weiter. Ein
auskommentierter Command ist schlechter als ein entfernter: Er sieht aus wie etwas, das
zurückkommt.

Dazu der Abschluss der Story: der Nachweis, dass der Stand tatsächlich trägt — und dass
`009-002` jetzt auflösbar ist, was der eigentliche Zweck der ganzen Story war.

## Acceptance criteria
- [ ] Für jeden der achtzehn Commands ist gemessen, ob er unter DBAL 3 startet; was nicht
      läuft, ist entfernt, und die Liste des Entfernten steht mit Begründung im Ergebnis.
- [ ] Das `HelperSet` steht auf dem Weg, den DBAL 3 und ORM 2.20 vorsehen.
- [ ] `SystemController` benutzt keinen entfallenen Import mehr.
- [ ] `php bin/console.php list` läuft, `appcms:install` legt ein Schema auf einer leeren
      Datenbank an, `appcms:token:cleanup --dry-run` läuft.
- [ ] **Die Vorbedingung ist erfüllt:** `composer update` mit
      `symfony/http-foundation ^7.4` löst probeweise auf — der Konflikt, der `009-002`
      blockiert hat, besteht nicht mehr. Die Probe wird zurückgenommen, nicht committet.
- [ ] Volle Suite grün, Deprecation-Gate grün, Postausgang 0 Byte.

## Verification
`php bin/console.php list`, `appcms:install` gegen eine frische Wegwerf-Datenbank,
`appcms:token:cleanup --dry-run`, volle Suite, `composer audit --locked` und ein
`composer update --dry-run` mit probeweise auf `^7.4` gehobenem `symfony/http-foundation`.
