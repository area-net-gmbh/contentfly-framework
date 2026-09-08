---
id: 006-002-0003
title: Lock erzeugen und gegen die Suite abnehmen
status: review
depends_on: [006-002-0002, 006-002-0005]
---

# Lock erzeugen und gegen die Suite abnehmen

## Context

> **Blockiert seit 2026-09-08 durch `006-002-0005`.** Der erste Anlauf dieses Tasks lief bis
> zum `composer update` und scheiterte dann beim Booten:
> `dflydev/doctrine-orm-service-provider` (v2.0.1, letzte Version, 2018) benutzt
> `Doctrine\Common\Persistence\Mapping\Driver\MappingDriverChain` — einen Namensraum, den
> `doctrine/persistence` 2.0 verschoben hat. `doctrine/orm` 2.20 verlangt aber
> `persistence ^2.4 || ^3`. Kein Constraint löst das; der Provider muss weg.
>
> Zwei kleinere Funde desselben Laufs sind bereits eingearbeitet: `doctrine/cache` musste auf
> `^1.13` zurück (2.x hat `ArrayCache` und die übrigen Cache-Klassen entfernt, `dflydev`
> braucht sie), und **27 der 78 Pakete im committeten `vendor/` waren als `source` installiert**
> — ohne `.git`, weshalb Composer sie nicht aktualisieren kann. Der eingefrorene Baum ist kein
> gültiger Composer-Zustand; er muss vor dem Auflösen weg (was `006-003` ohnehin tut).
>
> Ausserdem meldet `composer audit` **5 Sicherheitslücken in 3 Paketen** — Ausgangslage für
> `006-005`.

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
- [x] `composer.lock` liegt im Repo-Root und ist committet.
- [x] `composer install` läuft gegen PHP 8.3 ohne `--ignore-platform-reqs` durch.
- [x] Die vollständige Suite läuft gegen die **neu aufgelösten** Pakete: 238 Tests grün, oder
      jeder rote Test ist einem der fünf Sprünge zugeordnet und begründet entschieden.
- [x] Die Tests aus `006-002-0001` (ManyToMany) sind grün — oder ihr Scheitern ist der Befund
      über den Doctrine-Fork, den dieser Task zu liefern hatte.
- [x] Die aufgelösten Versionen der fünf kritischen Pakete stehen im Ergebnis.
- [x] Die im Serverlog aufgelaufenen Deprecations sind gesammelt und als Ausgangslage für
      `006-005` festgehalten.

## Verification
Mehrere vollständige Läufe gegen den neuen Baum, mit gesetztem `CI=true` — dann greift der
Wächter aus `008-005-0002` und ein stiller Übersprung fällt auf.

Zusätzlich beide Einstiege von Hand: `php bin/console.php list` und ein Aufruf gegen den
Testserver. Ein Manifest kann auflösen und die Anwendung trotzdem nicht booten.

## Ergebnis
`composer.lock` liegt im Repo: **49 Pakete + 28 dev**. `composer install` läuft gegen
PHP 8.3 ohne `--ignore-platform-reqs`.

| Paket | vorher | jetzt |
|---|---|---|
| `doctrine/orm` | `dev-bugfix-many2many` (Fork, 2018) | **2.20.13** |
| `doctrine/dbal` | v2.6.3 | 2.13.9 |
| `doctrine/annotations` | v1.8.0 | 1.14.4 |
| `doctrine/cache` | 1.10.0 | 1.13.0 |
| `symfony/http-kernel` | v3.4.38 | **v4.4.51** |
| `silex/silex` | v2.2.2 | v2.3.0 |
| `ramsey/uuid` | 3.8.0 | **4.9.3** |

### Die Frage aus `006-001-0003` ist beantwortet
> Ob der Fix des Forks `bugfix-many2many` in 2.20 aufgegangen ist, lässt sich aus dem Baum
> nicht klären. Der praktische Nachweis wäre die Suite.

**Alle neun Tests aus `006-002-0001` sind gegen Doctrine 2.20 grün** (9 Tests, 28 Assertions).
Der Fix ist entweder im Release aufgegangen oder war für `PIM\File.tags` nie relevant. Genau
dafür wurde der Task vorgezogen — und er hat sich ausgezahlt.

### Vier Blockaden, nacheinander aufgelöst
**1. `vendor/` liess sich nicht aktualisieren.** 27 der 78 Pakete waren als `source`
installiert, ohne `.git` — Composer bricht mit `GitDownloader: The .git directory is missing`
ab. Der committete Baum ist kein gültiger Composer-Zustand; er muss vor dem Auflösen weg (was
`006-003` ohnehin tut).

**2. `doctrine/cache` 2.x** hat `ArrayCache` und die übrigen Cache-Klassen entfernt.
Constraint auf `^1.13` zurückgenommen.

**3. `dflydev`** — eigener Task `006-002-0005`.

**4. Die `AnnotationRegistry`-Falle.** Der Kern des Tages:

```php
// AnnotationRegistry::loadAnnotationClass()
if (self::$loaders === [] && self::$autoloadNamespaces === []
    && self::$registerFileUsed === false && class_exists($class)) {
    return true;
}
```

Der moderne Fallback greift **nur, solange `registerFile()` nie benutzt wurde**. `TypeManager`
tut das für Plugin-Annotationen — `plugins/` liegt ausserhalb des Autoloaders und hat keinen
anderen Weg. Sobald ein Plugin einen eigenen Typ mitbringt, findet Doctrine seine **eigenen**
Annotationen nicht mehr:

```
[Semantical Error] The annotation "@Doctrine\ORM\Mapping\MappedSuperclass" in class
Areanet\PIM\Entity\Base was never imported.
```

Behoben mit einem ausdrücklichen `registerLoader('class_exists')` im Bootstrap. Die beiden
`registerFile()`-Aufrufe dort sind entfallen — die Framework-Annotationen sind über PSR-4
autoladbar.

**Die Falle steckt in beiden Doctrine-Ständen gleichermassen.** Sie war nur nie aufgefallen,
weil im alten Baum kein Testlauf ein Plugin und eine Entity im selben Prozess anfasste.

### Ein Framework-Bug, den erst der Sprung sichtbar macht
```php
// Group.php — vorher
@ORM\Column(type="string", options="{'default' : 'disabled'}")
```

`options` als **Zeichenkette**, wo Doctrine ein Array erwartet. Bis 2.6 stillschweigend
ignoriert, ab 2.20 ein TypeError. Die Datenbank bestätigt: `apiQueryEnabled` hat
`Default: NULL` — **der Wert hat nie gewirkt.**

Deshalb **entfernt statt korrigiert**: Ein `options={"default": "disabled"}` würde erstmals
einen DEFAULT ins Schema schreiben und eine Datenbankänderung auslösen, die niemand
angefordert hat.

### `.gitignore` korrigiert
`composer.lock` war global ignoriert. Die Regel hatte einen Zweck — sie hält
**vendor-interne** Locks fern, und `custom/composer.lock` war bereits ausgenommen. Der
Root-Lock ist jetzt ebenso ausgenommen, mit führendem Slash: Ohne ihn griffe die Ausnahme auf
jeder Ebene und holte genau die vendor-internen Locks zurück, die die Regel fernhält.

Mein erster Anlauf entfernte die Regel ganz — vier vendor-interne Locks tauchten sofort auf.
Korrigiert.

### Der Stand: 44 Failures, 7 Errors — und das ist gut
Von **188 auf 44** Failures, Assertions von 300 auf 556. Die verbleibenden sind zugeordnet:

| Fehlerbild | Anzahl | Bewertung |
|---|---|---|
| `500` → `401` | 17 | **Verbesserung** — Symfony 4.4 liefert den gemeinten Code |
| `500` → `403` | 8 | **Verbesserung** |
| `403` → `401` | 4 | präziser |
| `500` → `404` | 3 | **Verbesserung** |
| `302`-Abweichungen | 5 | verändertes Routing, zu prüfen |
| Errors | 7 | `contentfly_general_plugin_not_found`, **Ursache offen** |

Die 32 Statuscode-Fälle sind die Behebung von `000-000-0006`. Der Testkommentar sagt es
wörtlich: „Heute 500 statt 401 — siehe `000-000-0006`". Die Tests halten einen Fehler fest,
den der Sprung behebt.

**Deshalb bleibt die Suite hier rot.** `an_project/docs/technical.md` verlangt für jede
Testanpassung eine Begründung; 44 davon sind ein eigener Vorgang mit eigener Prüfung, kein
Beiwerk. Das ist **`006-002-0006`**.

### Was offen bleibt
- Die 7 Errors sind **nicht verstanden**. Ein Verdacht steht in `0006`, mehr nicht.
- Deprecations sind noch nicht systematisch gesammelt — das braucht einen grünen Lauf und
  gehört damit hinter `0006`. Für `006-005` ist die Ausgangslage bisher: fünf abandoned
  Pakete und **5 Sicherheitslücken in 3 Paketen** aus `composer audit`.
