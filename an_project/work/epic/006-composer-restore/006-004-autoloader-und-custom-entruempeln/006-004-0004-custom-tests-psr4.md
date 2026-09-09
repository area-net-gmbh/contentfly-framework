---
id: 006-004-0004
title: Custom\Tests\ durch ein PSR-4-Mapping im Root ersetzen
status: todo
depends_on: [006-004-0003]
---

# Custom\Tests\ durch ein PSR-4-Mapping im Root ersetzen

## Context
Der letzte Grund, aus dem `custom/composer.json` überhaupt noch gebraucht wird, ist ein
Autoload-Eintrag für Testcode. `tests/bootstrap.php` sagt selbst, dass er auf diesen Task
wartet:

> Basisklasse der Integrationstests. Sie kommt nicht über den Autoloader:
> `custom/composer.json` mappt `Custom\Tests\`, die Testklassen liegen aber unter `Tests\`. Und
> PHPUnit selbst lädt nur Dateien, die auf `Test.php` enden — diese also nicht. **Wenn Epic 006
> die Autoload-Situation aufräumt, kann diese Zeile durch ein PSR-4-Mapping ersetzt werden.**

Der Eintrag ist doppelt falsch: Er liegt im **Projekt**-Manifest, obwohl er Framework-Tests
bedient, und er mappt einen Namensraum, den keine einzige Testdatei benutzt.

## Umfang

### Das Mapping wandert ins Root-Manifest
`composer.json` bekommt einen `autoload-dev`-Block, der `Tests\` auf `tests/` mappt — den
Namensraum, den die Dateien **wirklich** tragen. `Custom\Tests\` fällt ersatzlos weg; es gibt
keinen Konsumenten.

Der Block gehört in `autoload-dev`, nicht in `autoload`: Testcode hat im
Deployment-Artefakt nichts zu suchen, und `composer install --no-dev --optimize-autoloader`
lässt ihn dann weg — nachweisbar an der Classmap.

### Der `require_once` in `tests/bootstrap.php`
Er fällt weg, sobald das Mapping greift. Mit ihm der erklärende Kommentar — er beschreibt dann
einen Zustand, den es nicht mehr gibt, und **das ist die Stelle, an der eine überholte
Erklärung entsteht**, wenn man nur die Codezeile löscht.

Zu prüfen ist, ob er wirklich entbehrlich ist: PHPUnit lädt von sich aus nur Dateien auf
`Test.php`. `IntegrationTestCase.php` endet nicht darauf und kommt künftig über den Autoloader
— das ist die Behauptung, und sie ist zu belegen, nicht anzunehmen.

### `custom/composer.json` danach
Nach `006-004-0002` ohne Pakete, jetzt auch ohne `autoload-dev`. Übrig bleibt ein Manifest, das
nur noch `config.allow-plugins` und einen erklärenden Kommentar trägt — der leere Slot, den ein
Projekt füllt.

Zu entscheiden: bleibt die Datei als **Vorlage** liegen, oder verschwindet sie? Sie muss
bleiben — `custom/` ist die Referenz, an der ein Projekt abliest, wie es seine eigenen Pakete
deklariert, und eine fehlende Datei lehrt nichts. Die Entscheidung gehört trotzdem
ausgesprochen, samt der Frage, ob `allow-plugins` für `php-http/discovery` ohne
`sentry/sentry` noch einen Zweck hat.

### `tests/README.md`
Der Abschnitt *Eine neue Integrationstest-Datei anlegen* erklärt die Sonderbehandlung. Er ist
danach falsch und gehört auf den neuen Stand — mit dem Vermerk, dass die Basisklasse jetzt
ganz normal über den Autoloader kommt.

## Abgrenzung
Keine Änderung an den Testklassen selbst und an keinem Namensraum, den sie tragen. Kein Umbau
der Suite-Struktur in `phpunit.xml.dist`.

## Acceptance criteria
- [ ] `composer.json` mappt `Tests\` auf `tests/`, in `autoload-dev`.
- [ ] `Custom\Tests\` ist aus `custom/composer.json` entfernt; es gibt keinen Konsumenten mehr.
- [ ] Der `require_once` auf `IntegrationTestCase.php` ist aus `tests/bootstrap.php` entfernt,
      samt dem Kommentar, der ihn erklärte.
- [ ] Über den Verbleib von `custom/composer.json` und `allow-plugins` ist entschieden und
      begründet.
- [ ] `tests/README.md` beschreibt den neuen Stand.
- [ ] Der Deployment-Baum enthält den Testcode **nicht** — nachgewiesen an der Classmap aus
      `composer install --no-dev --optimize-autoloader`.

## Verification
Die Suite ist der Beleg, und zwar vollständig — die Basisklasse wird von **jeder**
Integrationstestdatei geerbt, ein fehlgeschlagenes Autoloading fiele also sofort auf:

```sh
composer dump-autoload
./vendor/bin/phpunit --testsuite unit                      # 39 Tests, ohne Datenbank
# danach der volle Ablauf aus an_project/docs/runbook.md
```

Erwartet: 247 Tests, genau die 7 bekannten Failures, 0 übersprungen. **Ein Übersprung wäre hier
das gefährlichste Ergebnis** — er sähe wie Erfolg aus und hiesse, dass die Klassen nicht
gefunden wurden. Der Wächter aus `008-005-0002` läuft deshalb mit `CI=true` mit.

Dazu die Gegenprobe am Deployment-Baum:

```sh
composer install --no-dev --optimize-autoloader
php -r 'echo count(array_filter(array_keys(require "vendor/composer/autoload_classmap.php"),
        fn($k) => str_starts_with($k, "Tests\\\\"))), "\n";'   # erwartet: 0
```
