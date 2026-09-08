---
id: 006-002-0003
title: Lock erzeugen und gegen die Suite abnehmen
status: todo
depends_on: [006-002-0002]
---

# Lock erzeugen und gegen die Suite abnehmen

## Context
Hier wird es ernst: Aus dem Manifest entsteht ein `composer.lock`, und die Anwendung läuft zum
ersten Mal gegen **neu aufgelöste** Pakete statt gegen den eingefrorenen Baum von 2018.

Fünf Major-Sprünge auf einmal (`006-001-0003`):

| Paket | von | nach |
|---|---|---|
| `symfony/*` | 3.4 | 4.4 |
| `doctrine/orm` | Fork von 2018 | 2.20.x |
| `doctrine/dbal` | 2.6.3 | 2.13.x |
| `doctrine/annotations` | 1.8.0 | 1.14.x |
| `ramsey/uuid` | 3.8.0 | 4.x |

**Die Suite ist das Abnahmekriterium.** 238 Tests, 575 Assertions — und für den riskantesten
der fünf Sprünge hat `006-002-0001` gezielt nachgelegt.

## Umfang

### Erst im Wegwerf-Baum, dann im Repo
`vendor/` liegt heute committet im Repo. Ein `composer install` überschreibt ihn — und macht
den Rückweg schwer, wenn etwas klemmt.

Deshalb zuerst in einem Klon: installieren, Suite fahren, Ergebnis ansehen. Erst wenn das
trägt, entsteht der Lock im Repo. Der Umbau von `vendor/` selbst ist `006-003`; hier geht es
nur um den Lock und den Nachweis, dass er trägt.

### Was zu erwarten ist
Die riskanten Stellen, nach Wahrscheinlichkeit:

- **`doctrine/dbal` 2.6 → 2.13.** Innerhalb von 2.x, aber sieben Minor-Versionen. Deprecations
  sind wahrscheinlich, Brüche möglich.
- **`ramsey/uuid` 3 → 4.** Die ID-Strategie `UUID` hängt daran (`APPCMS_ID_STRATEGY` in
  `bootstrap.php`). Ein Major-Sprung mit bekannten API-Änderungen.
- **`doctrine/orm` Fork → Release.** Der ManyToMany-Pfad ist seit `006-002-0001` abgedeckt;
  bleibt er grün, ist die Frage beantwortet.
- **Symfony 3.4 → 4.4.** Silex 2.3 ist dafür gebaut; die Framework-Controller benutzen
  HttpFoundation direkt.
- **`doctrine/annotations` 1.8 → 1.14.** Die `@PIM`-Annotationen hängen daran.

**Deprecations gehören protokolliert, nicht unterdrückt.** `008-005-0001` hat den Testserver
auf `display_errors=Off` und `log_errors=On` gestellt — das Serverlog ist die Quelle. Was dort
auftaucht, ist die Vorarbeit für das „0 Deprecations"-Gate aus `006-005`.

### Wenn die Suite rot wird
Ein roter Test ist hier **kein Grund, den Test anzupassen**. Er ist ein Befund über einen der
fünf Sprünge. `an_project/docs/technical.md` ist eindeutig: „Eine Testanpassung ist ein
Verhaltenswechsel und braucht eine Begründung."

Der Weg ist dann: Sprung isolieren (welches Paket?), Ursache benennen, und entscheiden — Code
anpassen, Constraint zurücknehmen, oder als bewusste Verhaltensänderung dokumentieren. Jede
dieser drei Antworten ist gültig; stillschweigend den Test umschreiben ist es nicht.

## Abgrenzung
- `vendor/` bleibt vorerst im Repo — das Entfernen ist `006-003`.
- `custom/composer.json` wird nicht angefasst — das ist `006-004`.
- Die Pipeline wird nicht umgestellt — das ist `006-002-0004`.

## Acceptance criteria
- [ ] `composer.lock` liegt im Repo-Root und ist committet.
- [ ] `composer install` läuft gegen PHP 8.3 ohne `--ignore-platform-reqs` durch.
- [ ] Die vollständige Suite läuft gegen die **neu aufgelösten** Pakete: 238 Tests grün, oder
      jeder rote Test ist einem der fünf Sprünge zugeordnet und begründet entschieden.
- [ ] Die Tests aus `006-002-0001` (ManyToMany) sind grün — oder ihr Scheitern ist der Befund
      über den Doctrine-Fork, den dieser Task zu liefern hatte.
- [ ] Die aufgelösten Versionen der fünf kritischen Pakete stehen im Ergebnis.
- [ ] Die im Serverlog aufgelaufenen Deprecations sind gesammelt und als Ausgangslage für
      `006-005` festgehalten.

## Verification
Mehrere vollständige Läufe gegen den neuen Baum, mit gesetztem `CI=true` — dann greift der
Wächter aus `008-005-0002` und ein stiller Übersprung fällt auf.

Zusätzlich beide Einstiege von Hand: `php bin/console.php list` und ein Aufruf gegen den
Testserver. Ein Manifest kann auflösen und die Anwendung trotzdem nicht booten.
