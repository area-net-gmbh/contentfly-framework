---
id: 006-004-0004
title: Custom\Tests\ durch ein PSR-4-Mapping im Root ersetzen
status: done
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
- [x] `composer.json` mappt `Tests\` auf `tests/`, in `autoload-dev`.
- [x] `Custom\Tests\` ist aus `custom/composer.json` entfernt; es gibt keinen Konsumenten mehr.
- [x] Der `require_once` auf `IntegrationTestCase.php` ist aus `tests/bootstrap.php` entfernt,
      samt dem Kommentar, der ihn erklärte.
- [x] Über den Verbleib von `custom/composer.json` und `allow-plugins` ist entschieden und
      begründet.
- [x] `tests/README.md` beschreibt den neuen Stand.
- [x] Der Deployment-Baum enthält den Testcode **nicht** — nachgewiesen an der Classmap aus
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

## Ergebnis
**Das Mapping steht dort, wo es hingehört, und `custom/vendor` registriert jetzt gar nichts
mehr.** Die Suite bleibt bei 249 Tests mit den 7 bekannten Failures — die Basisklasse kommt
über den Autoloader, ohne dass eine einzige Testdatei angefasst wurde.

| | vorher | jetzt |
|---|---|---|
| Mapping für den Testcode | `Custom\Tests\` → `../tests/`, in `custom/composer.json` | `Tests\` → `tests/`, in `composer.json` (`autoload-dev`) |
| Präfixe im zweiten Autoloader | 1 | **0** |
| `IntegrationTestCase` | `require_once` in `tests/bootstrap.php` | über den Autoloader |
| `Tests\`-Klassen im Deployment-Baum | — | **0** |

### Der Eintrag war doppelt falsch — beides belegt
`grep` über den ganzen Baum: **Keine einzige Testdatei trägt `Custom\Tests\`.** Die
Namensräume sind `Tests\Integration`, `Tests\Integration\Api`, `Tests\Unit`,
`Tests\Unit\Entity`, `Tests\Unit\Manager` und `Tests\Unit\Service`. Das Mapping zeigte
also seit jeher ins Leere — und es lag im **Projekt**-Manifest, obwohl es die Tests des
**Frameworks** bedienen sollte.

Übrig geblieben sind nach dem Umbau zwei Erwähnungen von `Custom\Tests\`, beide in der
Vergangenheitsform: in `tests/README.md` und im Klassenkommentar von `IntegrationTestCase`.
Sie erklären, warum es die Sonderbehandlung gab — das ist der Teil, den man beim Löschen einer
Codezeile sonst als überholte Erklärung stehen lässt.

### `autoload-dev`, nicht `autoload`
Testcode gehört nicht ins Deployment-Artefakt. Nachgewiesen, nicht angenommen:

```
composer install --no-dev --optimize-autoloader
→ 0 Klassen aus Tests\ in der Classmap
→ kein Tests\-Präfix in autoload_psr4.php
→ php bin/console.php list: Exit 0
```

Danach zurück auf den Dev-Baum, 77 Pakete.

### `custom/composer.json` bleibt — und verliert `allow-plugins`
Die Datei bleibt liegen. `custom/` ist die Referenz, an der ein Projekt abliest, wie es seine
eigenen Pakete deklariert; eine fehlende Datei lehrt nichts, eine leere mit Begründung lehrt
genau das Richtige.

`config.allow-plugins` mit `php-http/discovery` ist dagegen **entfernt**. Die Freigabe gehörte
zu `sentry/sentry`, das mit `006-004-0002` entfallen ist. Sie ist keine Konfiguration, sondern
eine **stehende Ausführungserlaubnis** für ein Composer-Plugin — und die soll eine Vorlage
nicht für ein Paket erteilen, das sie gar nicht anfordert. Wer Sentry wieder aufnimmt, wird von
Composer gefragt und entscheidet selbst.

Was jetzt nicht mehr in der Datei steht, steht als eigener Abschnitt *im* `extra.hinweis`
darin, samt Grund. Ein Slot, dem man ansieht, was aus ihm entfernt wurde, ist eine bessere
Vorlage als ein leerer.

### Verification
| Prüfung | Ergebnis |
|---|---|
| `Tests\` im Root-Autoloader | `'Tests\\' => array($baseDir . '/tests')` |
| Präfixe in `custom/vendor/composer/autoload_psr4.php` | **keine** |
| Unit-Suite | OK (41 tests, 58 assertions) |
| volle Suite mit `CI=true` | **249 Tests / 598 Assertions, 7 Failures, 0 übersprungen** |
| Postausgang der Versandfalle | 0 Byte |
| Deployment-Baum: `Tests\`-Klassen | 0 |

**Die volle Suite ist hier der eigentliche Beleg, nicht die Unit-Suite.** Kein Unit-Test erbt
von `IntegrationTestCase`; nur der Integrationslauf zeigt, dass die Klasse ohne `require_once`
gefunden wird. Und ein Übersprung wäre das gefährlichste Ergebnis gewesen — er sähe aus wie
Erfolg und hiesse, dass die Klassen nicht gefunden wurden. Der Lauf mit `CI=true` schliesst das
aus: Der Wächter aus `008-005-0002` war scharf, 0 übersprungen.
