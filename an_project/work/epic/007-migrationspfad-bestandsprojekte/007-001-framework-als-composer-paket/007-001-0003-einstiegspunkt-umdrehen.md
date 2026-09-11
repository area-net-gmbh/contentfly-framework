---
id: 007-001-0003
title: Der Einstiegspunkt lädt den Autoloader, nicht das Framework
status: todo
depends_on: [007-001-0001]
---

# Der Einstiegspunkt lädt den Autoloader, nicht das Framework

## Context
**Die Zuständigkeit ist heute umgekehrt.** `index.php` besteht aus einer Zeile:

```php
require_once __DIR__.'/lib/contentfly/bootstrap-web.php';
```

Und `bootstrap.php` lädt daraufhin selbst, was es zum Laufen braucht:

```php
require_once ROOT_DIR.'/vendor/autoload.php';
if (file_exists(ROOT_DIR.'/custom/vendor/autoload.php')) {
    require_once ROOT_DIR.'/custom/vendor/autoload.php';
}
require_once ROOT_DIR.'/custom/config.php';
require_once ROOT_DIR.'/custom/version.php';
```

**Ein Paket wird vom Autoloader geladen — es lädt ihn nicht.** Solange `bootstrap.php` die erste
Datei ist, die jemand einbindet, kann der Frameworkcode gar nicht in `vendor/` liegen: Um ihn zu
finden, bräuchte man den Autoloader, den er selbst erst lädt.

Dasselbe gilt für die drei folgenden Zeilen. `custom/config.php` und `custom/version.php` werden
**unbedingt** und von einem festen Pfad geladen. Ein Paket darf so etwas nicht voraussetzen; es
muss danach fragen und ohne auskommen oder klar sagen, dass es ohne nicht geht.

**Die Reihenfolge der beiden Autoloader ist eine Zusicherung, kein Zufall.** Der Kommentar
darüber sagt es ausdrücklich: Root zuerst, `custom/` ergänzend — „Framework schlägt Projekt",
seit `006-004-0001`, begründet in `architecture.md` und geprüft von
`tests/Unit/AutoloaderUeberschneidungTest.php`. Was hier geändert wird, ändert sie mit. Wie,
entscheidet `007-001-0001`.

## Acceptance criteria
- [ ] Der Einstiegspunkt lädt den Autoloader und übergibt dem Framework, was es wissen muss; das Framework lädt keinen Autoloader mehr.
- [ ] `custom/config.php` und `custom/version.php` werden nicht mehr unbedingt von einem festen Pfad geladen — fehlt die Konfiguration, sagt die Meldung das.
- [ ] Was aus der Zusicherung „Framework schlägt Projekt" wird, ist umgesetzt **und** in `architecture.md` nachgezogen — `AutoloaderUeberschneidungTest` prüft danach das, was gilt, nicht das, was galt.
- [ ] `index.php`, `bin/console.php` und `bin/cli-config.php` sind gleich gebaut; keiner der drei hat einen eigenen Weg.
- [ ] Die volle Suite bleibt grün, und `appcms:install` läuft durch.

## Verification
Web-Einstieg und Console je einmal gegen eine frische Installation fahren. `git grep` auf
`require_once` in `lib/contentfly/` zeigt keinen Autoloader mehr. Volle Suite, PHPStan.
