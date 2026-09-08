---
id: 006-001-0001
title: Die tote SCSS-Spur entfernen
status: review
depends_on: []
---

# Die tote SCSS-Spur entfernen

## Context
Der einzige Codeeingriff dieser Story. Solange er aussteht, sieht `scssphp` wie eine echte
Abhängigkeit aus und blockiert die Zuordnung in `006-001-0004`.

> **Korrektur am Story-Text.** `006-001` behauptet, es handle sich um „nur `use`-Zeilen ohne
> Verwendung". Das stimmt nicht: `Compiler` und `OutputStyle` werden in `bootstrap.php`
> tatsächlich benutzt (Zeilen 318, 321, 329). Der Code ist trotzdem tot — nur aus einem
> anderen Grund, und der Eingriff ist entsprechend grösser.

## Umfang

### Was wirklich dasteht
`lib/contentfly/bootstrap.php` enthält einen vollständigen **SCSS-Compiler**: 54 Zeilen
(283–336), die aus `custom/Frontend/scss/` ein CSS samt Source-Map bauen, mit
Hash-basiertem Caching über die `@import`-Kette.

Tot ist er aus vier Gründen zugleich:

| | |
|---|---|
| Auslöser | `Adapter::getConfig()->USE_SCSS_COMPILER` — Framework-Default **`false`** |
| Eingabeverzeichnis | `custom/Frontend/scss/` — **existiert nicht**, Epic `012` hat es entfernt |
| Zweck | ein Frontend, das es nicht mehr gibt |
| Abhängigkeit | `scssphp` steht in **keinem** `composer.json`, nur im Autoloader (`vendor/composer/autoload_psr4.php:42`) |

`an_project/docs/tech-stack.md` ist an dieser Stelle eindeutig: „**Kein Frontend.** Contentfly
ist reine Datenhaltung plus Core-Funktionen." Ein SCSS-Compiler im Bootstrap eines
Frameworks ohne Frontend ist ein Rest, den Epic `012` übersehen hat.

### Was entfernt wird
**Entschieden am 2026-09-08: ganz entfernen** — Block, Konfigurationsfelder und Geisterpaket.

1. Die 54 Zeilen des `if (… USE_SCSS_COMPILER)`-Blocks in `bootstrap.php`.
2. Die beiden `use`-Zeilen (38, 39), die danach niemand mehr braucht.
3. Die Konfigurationsfelder `USE_SCSS_COMPILER` und `BASE_SCSS_FILE` in
   `lib/contentfly/Classes/Config.php` — sonst bleiben genau die wirkungslosen Schalter
   stehen, die `000-000-0010` ohnehin aufräumen soll.
4. Das Verzeichnis `vendor/scssphp/` und seine Autoloader-Registrierung. **Nur, wenn das
   ohne einen Composer-Lauf sauber geht** — der Root-Baum hat kein Manifest, und ein von
   Hand editierter `autoload_psr4.php` wäre schlimmer als der Rest. Lässt es sich nicht
   sauber lösen, bleibt das Verzeichnis stehen und wird in `006-003` mit dem gesamten
   `vendor/` entfernt; hier wird es dann nur als „entfällt" vermerkt.

### Die Konsequenz, die über dieses Repo hinausgeht
Ein Bestandsprojekt mit `USE_SCSS_COMPILER = true` verliert das Feature. Der Standardwert ist
`false`, und die Vorlage hat kein `custom/Frontend/` — für sie ändert sich nichts. Für ein
migrierendes Projekt ist es ein **Breaking Change**, und der gehört als Migrationshinweis
notiert, damit Epic `007` ihn aufnehmen kann.

## Abgrenzung
Kein Aufräumen anderer wirkungsloser Konfigurationsfelder — das ist `000-000-0010`. Hier
fallen nur die beiden, die unmittelbar zu diesem Block gehören.

## Acceptance criteria
- [x] Der `USE_SCSS_COMPILER`-Block und die beiden `use`-Zeilen sind aus `bootstrap.php` weg.
- [x] `USE_SCSS_COMPILER` und `BASE_SCSS_FILE` sind aus `Classes/Config.php` entfernt; kein
      Vorkommen bleibt im Baum zurück (`config.sample.php` und `custom/config.php`
      eingeschlossen).
- [x] `scssphp` ist entfernt — oder es steht begründet fest, dass es mit `vendor/` in
      `006-003` fällt.
- [x] Der Breaking Change für Bestandsprojekte ist als Migrationshinweis festgehalten.
- [x] Die Anwendung bootet weiterhin, und die Suite bleibt grün.

## Verification
`custom/vendor/bin/phpunit` läuft vollständig grün — **das ist hier das eigentliche
Sicherheitsnetz**: `bootstrap.php` ist die Datei, über die jeder Request und jeder
Console-Aufruf läuft. Ein Fehler darin bricht alles.

Zusätzlich beide Einstiege von Hand: `php bin/console.php list` und ein Aufruf gegen den
Testserver — der Block sitzt hinter `$app['is_installed']`, wird also nur im installierten
Zustand überhaupt erreicht.

Und die Gegenprobe: `grep -rn "SCSS\|scssphp" lib/ custom/` findet ausserhalb von `vendor/`
nichts mehr.

## Ergebnis
`bootstrap.php` schrumpft von **342 auf 284 Zeilen**; zwei Konfigurationsfelder sind weg.
Suite unverändert grün: 238 Tests / 575 Assertions.

### Entfernt
| Was | Wo |
|---|---|
| Der `USE_SCSS_COMPILER`-Block, 55 Zeilen | `bootstrap.php:283–337` |
| `use ScssPhp\ScssPhp\Compiler` und `…\OutputStyle` | `bootstrap.php:38–39` |
| `$USE_SCSS_COMPILER` und `$BASE_SCSS_FILE` | `Classes/Config.php:15,20` |

Die Blockgrenzen wurden **über die Klammerbilanz** bestimmt, nicht über Zeilennummern — bei
55 Zeilen mit vier Verschachtelungsebenen ist ein Abzählfehler sonst zu leicht. Der Block
schloss auf Zeile 337, eine mehr als der Task erwartet hatte.

### `scssphp` bleibt vorerst stehen — begründet
Das Paket steht in **keiner** `installed.json`, aber in **zwei** generierten Autoloader-Dateien
(`vendor/composer/autoload_psr4.php` und `autoload_static.php`). Ohne Root-Manifest lässt sich
`composer dump-autoload` nicht ausführen; von Hand editierte generierte Dateien wären
schlimmer als das Verzeichnis selbst.

Es fällt deshalb mit dem gesamten `vendor/` in `006-003` und ist für `006-001-0004` als
**„entfällt"** vorgemerkt. Das war im Task als Ausweg vorgesehen und ist der eingetretene Fall.

### Der Migrationshinweis
Neu: `an_project/docs/breaking-changes.md` — eine Sammelstelle, die es bisher nicht gab.
Sie nimmt den SCSS-Eintrag auf und verweist auf die bestehende
`pim-annotationen-migration.md`. Epic `007` baut daraus den Migrationsleitfaden; bis dahin
wird sie **im Moment des Entstehens** gefüllt, wo der Zusammenhang noch bekannt ist.

### Verifikation
- `php bin/console.php list` → Exit 0, Commands vollständig.
- `GET /api/config` gegen den Testserver → HTTP 200 (der Block sass hinter
  `$app['is_installed']`, wurde also nur im installierten Zustand erreicht — genau dieser
  Zustand ist geprüft).
- Vollständige Suite → 238 Tests / 575 Assertions grün.
- `grep -rn "SCSS\|Scss\|scss" lib/ custom/ bin/ index.php` findet ausserhalb von
  `custom/vendor/` **nichts** mehr.
