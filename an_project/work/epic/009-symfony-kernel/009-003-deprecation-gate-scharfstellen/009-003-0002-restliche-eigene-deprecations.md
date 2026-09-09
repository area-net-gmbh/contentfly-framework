---
id: 009-003-0002
title: Die restlichen eigenen Deprecations beheben
status: done
depends_on: []
---

# Die restlichen eigenen Deprecations beheben

## Context
Was nach `009-003-0001` an eigenen Deprecations übrig bleibt, ist klein und mechanisch:

| Meldung | Fundstellen |
|---|---|
| `Application::add()` → `addCommand()`, seit Symfony 7.4 | 3 |

Dazu die Level-0-Meldungen, die keine Deprecations sind — PHPStan zählt heute **sieben**
gewöhnliche Fehler mit, darunter `Doctrine\Common\Cache\ApcuCache does not have a constructor
and must be instantiated without any parameters`. Die sind zu prüfen: Ein Level-0-Fehler ist
selten Rauschen, sondern meist ein echter Fund.

## Acceptance criteria
- [x] Die drei `add()`-Aufrufe benutzen `addCommand()`.
- [x] Jede der sieben Level-0-Meldungen ist angesehen und entweder behoben oder mit Begründung
      als Nicht-Fund festgehalten. Keine wird stillschweigend übergangen.
- [x] Die Suite bleibt grün, `php bin/console.php list` unverändert.

## Verification
PHPStan meldet keine eigene Deprecation und keinen Level-0-Fehler mehr, der nicht begründet ist.
Volle Suite grün.

## Ergebnis

**PHPStan von 41 auf 31 Meldungen — und übrig ist ausschliesslich Doctrine.** Keine eigene
Deprecation, kein Level-0-Fehler mehr.

### Die sieben Level-0-Meldungen waren vier Funde, und einer davon ein echter Fehler

**1. Eine Konstante, die es nicht gibt.** `Api.php` warf bei einer `unique`-Verletzung

```php
throw new ContentflyException(Messages::contentfly_general_record_already_exists, …);
```

Die Konstante heisst `…_ressource_already_exists`. Die Zeile war damit kein Fehlerbericht,
sondern ein Fatal: `Undefined constant`. Gemessen über HTTP:

```
{"message":"Undefined constant …Messages::contentfly_general_record_already_exists",
 "type":"Error","status":500}
```

**Das korrigiert eine Zuschreibung aus Epic `008`.** `ConstraintApiTest` hielt fest: „Heute 500
statt 409 — siehe `000-000-0006`". Der Verweis war falsch; es lag nie an der Fehlerkette,
sondern an dieser Zeile. Jetzt dieselbe Konstante und derselbe Statuscode wie in den drei
anderen Fällen in `doUpdate()`, und die Zusicherung steht auf **409** — eine unique-Verletzung
ist ein Konflikt, kein Serverfehler.

**2. Eine Klasse, die sich nicht laden lässt.** `Classes\ApnsPHP\Log\NoLogger` implementiert
`\ApnsPHP_Log_Interface` — eine Schnittstelle, die es im ganzen Baum nicht gibt. Der Rest der
Apple-Push-Anbindung ist nie mitgekommen; die Klasse war das letzte Überbleibsel, wurde nirgends
referenziert und hätte bei der ersten Instanziierung einen Fatal geworfen. Gelöscht, samt der
zwei leeren Verzeichnisse.

**3. `Serializable::getId()` war nicht deklariert.** Die Methode wird in `toValueObject()`
benutzt und kommt aus `Base`, `BaseI18n` und `Log` — also aus jeder Klasse, die tatsächlich
erbt. Statt sie zu erfinden, ist sie jetzt als **abstrakt** deklariert: Damit steht die
Bedingung im Code, unter der diese Klasse funktioniert, und eine Ableitung ohne Id fällt beim
Laden auf statt bei der ersten Auslieferung.

**4. Vier Cache-Instanzen mit einem verworfenen Argument.** `new ApcCache('query')` — die Klasse
hat gar keinen Konstruktor, das Argument wurde stillschweigend verworfen, und Abfrage- und
Metadaten-Cache teilten sich denselben Namensraum. Gemeint war offensichtlich eine Trennung; sie
steht jetzt als `setNamespace('query')` da. **Kein Fehler mit sichtbarer Wirkung, aber eine
Absicht, die seit Jahren nicht griff** — und in einer Installation mit `APP_CACHE_DRIVER=apc`
hätten sich die beiden Caches gegenseitig überschrieben.

### Die drei Deprecations

`Application::add()` ist seit Symfony 7.4 zugunsten von `addCommand()` deprecated. Drei Stellen
im Bootstrap, eine im `ConsoleManager`.

### Nachweis

| | |
|---|---|
| PHPStan | 41 → **31**, und alle 31 kommen aus Doctrine |
| Eigene Deprecations | 0 |
| Level-0-Fehler | 0 |
| Volle Suite | `OK (266 tests, 641 assertions)`, 0 übersprungen |
| `php bin/console.php list` | die drei `appcms:`-Commands unverändert |
| Unique-Verletzung über HTTP | 409 statt 500 |
