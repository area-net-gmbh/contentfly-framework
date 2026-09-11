---
id: 007-001-0003
title: Der Einstiegspunkt lädt den Autoloader, nicht das Framework
status: done
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
- [x] Der Einstiegspunkt lädt den Autoloader und übergibt dem Framework, was es wissen muss; das Framework lädt keinen Autoloader mehr.
- [x] `custom/config.php` und `custom/version.php` werden nicht mehr unbedingt von einem festen Pfad geladen — fehlt die Konfiguration, sagt die Meldung das.
- [x] Was aus der Zusicherung „Framework schlägt Projekt" wird, ist umgesetzt **und** in `architecture.md` nachgezogen — `AutoloaderUeberschneidungTest` prüft danach das, was gilt, nicht das, was galt.
- [x] `index.php`, `bin/console.php` und `bin/cli-config.php` sind gleich gebaut; keiner der drei hat einen eigenen Weg.
- [x] Die volle Suite bleibt grün, und `appcms:install` läuft durch.

## Verification
Web-Einstieg und Console je einmal gegen eine frische Installation fahren. `git grep` auf
`require_once` in `lib/contentfly/` zeigt keinen Autoloader mehr. Volle Suite, PHPStan.

## Ergebnis

**`Classes/Kernel/Start` ist die Tür.** Der Einstiegspunkt lädt den Autoloader und ruft eine
Klasse:

```php
require_once __DIR__ . '/vendor/autoload.php';
\Areanet\PIM\Classes\Kernel\Start::web(__DIR__);
```

**Damit steht in keinem Einstiegspunkt mehr ein Pfad in den Frameworkcode** — das ist der
eigentliche Gewinn dieses Tasks. Ob Contentfly unter `lib/` liegt oder unter
`vendor/areanet/contentfly/`, sieht `index.php` nicht mehr. Alle drei Einstiegspunkte sind gleich
gebaut; `APPCMS_CONSOLE` setzt `Start::konsole()` selbst, damit ein Aufrufer es nicht vergessen
kann.

**Drei Vorbedingungen werden geprüft, und alle drei sind durchgespielt.** Nachgemessen gegen
Wegwerf-Verzeichnisse:

| Fall | Meldung |
|---|---|
| Projektverzeichnis gibt es nicht | nennt den Pfad, den es nicht gibt |
| `custom/config.php` fehlt | nennt den erwarteten Pfad, das Projektverzeichnis und `appcms:install` |
| `custom/vendor/` liegt noch da | nennt den Baum, warum er nicht mehr gilt und was zu tun ist |

**Der zweite Fall war vorher PHPs eigene Meldung** („Failed opening required …"). Sie nennt den
Pfad, aber nicht, dass es um die Konfiguration geht.

**Der dritte ist ein Abbruch und keine stille Nichtbeachtung, und das ist die Entscheidung.**
Ein liegengebliebenes `custom/vendor/` sieht aus wie etwas, das benutzt wird. Würde es ab jetzt
einfach nicht mehr geladen, fehlte dem Projekt eine Klasse — und die Meldung handelte von dieser
Klasse, nicht davon, dass ein ganzer Baum nicht mehr gilt.

**Geworfen statt `exit`:** Ein `exit` liesse sich nicht prüfen, und ein Abbruchweg, den kein
Test betritt, ist einer, auf den man sich nicht verlassen kann. `tests/Unit/Kernel/StartTest.php`
betritt alle drei, dazu die Reihenfolge, in der sie melden.

## Ein Defekt aus `007-001-0002`, hier korrigiert

Meine Meldung im Bootstrap benutzte `fwrite(STDERR, …)`. **`STDERR` ist nur in der CLI
definiert.** In der Web-SAPI wäre die Meldung über den Fehler selbst ein Fehler gewesen und
hätte den ersten verdeckt — genau die Sorte Fehler, gegen die dieser Task gebaut ist. Alle
Abbrüche werfen jetzt eine `RuntimeException`; die trägt die Meldung ins Fehlerprotokoll, das
die Umgebung ohnehin führt.

## Der umgedrehte Test — und was er sofort gefunden hat

`AutoloaderUeberschneidungTest` ist **umgedreht, nicht gelöscht**. Er bewachte die Bedingung der
Zwei-Bäume-Entscheidung; die Bedingung ist weggefallen, also bewacht er, dass sie weggefallen
bleibt. Drei Prüfungen: der Baum des Projekts führt das Framework · es gibt keinen zweiten ·
das Framework lädt seinen eigenen Autoloader nicht mehr.

**Die dritte Prüfung hat beim ersten Lauf etwas gefunden, das die Entscheidung aus
`007-001-0001` nicht bedacht hatte:** `Classes/Plugin::initComposer()` lädt den eigenen
`vendor/`-Baum **jedes Plugins**. „Ein Baum je Projekt" gilt also nicht ausnahmslos, und die
Überschneidungsgefahr aus `006-004` ist je Plugin zurück — ungeprüft. Der Test führt es als
benannte Ausnahme mit Begründung; entschieden wird es mit `007-001-0004`, wo ohnehin über
`plugins/` zu befinden ist. Das Kriterium ist dort nachgetragen.

**Und mein Detektor war zuerst zu grob:** Er suchte das Wort `autoload.php` und meldete damit
auch die Zeile in `Start`, die einen zweiten Baum *prüft*. Er sucht jetzt ein `require`/`include`
auf einen Autoloader-Pfad — eine Prüfung lädt nichts, und sie zu melden hiesse, die Prüfung
gegen sich selbst zu richten.

**Ein zweiter Fehlalarm, und auch der war meiner:** Der Pfad-Wächter aus `007-001-0002` schlug
auf `dirname(__DIR__)` an — in einem **Beispieltext** innerhalb einer Fehlermeldung. Ich habe
das Beispiel umformuliert statt den Wächter aufzuweichen. Dabei fiel auf, dass der Text in
doppelten Anführungszeichen steht: `$projektverzeichnis` wäre interpoliert worden. Jetzt
escaped, und die Meldung ist wörtlich nachgemessen.

**Zahlen:** Volle Suite `OK (490 tests, 1194 assertions)` (vorher 484), 0 Deprecations bei 0
Ausnahmen, 0 Byte Postausgang. PHPStan `[OK] No errors`. `appcms:install` läuft durch, und der
Webeinstieg antwortet auf `/api/config` mit HTTP 200. Zwei Bruchstellen stehen in
`an_project/docs/breaking-changes.md` unter *Paketgrenze*.
