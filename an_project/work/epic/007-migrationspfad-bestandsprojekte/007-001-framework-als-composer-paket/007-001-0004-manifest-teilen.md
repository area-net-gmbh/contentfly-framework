---
id: 007-001-0004
title: Das Manifest teilen — Bibliothekspaket und Projekt getrennt
status: done
depends_on: [007-001-0002, 007-001-0003]
---

# Das Manifest teilen — Bibliothekspaket und Projekt getrennt

## Context
**Ein Manifest trägt heute drei Namensräume.** Aus `composer.json`, Stand 2026-09-11:

```json
"name": "areanet/contentfly-framework",
"type": "project",
"autoload": { "psr-4": {
    "Areanet\\PIM\\": "lib/contentfly/",
    "Custom\\":       "custom/",
    "Plugins\\":      "plugins/"
}}
```

`type: project` beschreibt eine Anwendung, die man klont — kein Paket, das man einbindet. Und
Framework, Projektcode und Plugins hängen an **einer** Datei. Das ist genau die Vermischung, die
das Update unmöglich macht: Wer eine neue Frameworkversion will, bekommt sie nur, indem er den
Baum überschreibt, in dem auch sein eigener Code liegt.

**Was mit umzieht, ist mehr als die drei Zeilen.** Am Manifest hängen der `extra.hinweis`-Block
mit der Begründung jedes Constraints, `config.platform.php`, `config.audit.ignore`, der
`suggest`-Eintrag für `symfony/ldap`, `require-dev` samt Werkzeugen, und `autoload-dev` für
`Tests\`. Jeder Posten gehört zugeordnet: Wer eine Bibliothek einbindet, erbt ihre
`require`-Angaben, aber nicht ihre Werkzeuge.

**`plugins/` steht im Autoload und ist leer** — versioniert liegt dort keine Datei. Ein
PSR-4-Präfix auf ein leeres Verzeichnis ist kein Fehler, aber auch keine Entscheidung; hier wird
es eine.

**Die zweite Hälfte, `custom/composer.json`,** hat heute ein leeres `require` und einen eigenen
`vendor/`-Baum. Was daraus wird, hat `007-001-0001` entschieden; hier wird es umgesetzt.

## Acceptance criteria
- [x] Das Bibliothekspaket trägt `type: library`, einen eigenen Namen und nur den Namensraum des Frameworks.
- [x] Projektcode, Plugins und Tests hängen nicht mehr am Manifest des Frameworks.
- [x] Jeder Posten des alten Manifests ist zugeordnet — `extra.hinweis`, `platform`, `audit.ignore`, `suggest`, `require-dev`, `autoload-dev`; keiner geht verloren, keiner liegt doppelt.
- [x] Die Entscheidung zu `plugins/` ist umgesetzt und begründet — **einschliesslich der Frage, die `007-001-0003` aufgeworfen hat:** `Classes/Plugin::initComposer()` laedt den eigenen `vendor/`-Baum jedes Plugins. Die Entscheidung aus `007-001-0001` sprach von „einem Baum je Projekt" und hatte die Plugins nicht bedacht. Damit ist die Überschneidungsgefahr aus `006-004` je Plugin zurück — ungeprüft.
- [x] Die beiden toten Importe auf Projektklassen sind weg (`OnejoinType` → `Custom\Entity\TestMeta`, `SystemController` → `Custom\Entity\Ansprechpartner`; beide Klassen existieren nicht). Nachgetragen mit `007-001-0001`, weil erst die Trennlinie sie zu einem Befund macht.
- [x] Die Gates laufen weiter: `composer audit --locked` ohne Advisories, PHPStan `[OK] No errors`, 0 Deprecations.
- [x] Die volle Suite bleibt grün.

## Verification
`composer validate` auf jedem entstandenen Manifest. `composer install` von Null gegen den neuen
Zuschnitt, danach die volle Suite und die drei Gates. Der Beweis, dass das Paket wirklich
beziehbar ist, folgt in `007-001-0005`.

## Ergebnis

**Zwei Manifeste.** `lib/contentfly/composer.json` beschreibt `areanet/contentfly`,
`type: library`, mit **einem** Namensraum. Das Manifest im Wurzelverzeichnis beschreibt das
Projekt, heisst jetzt `areanet/contentfly-skeleton` und führt `areanet/contentfly` als
Abhängigkeit über eine `path`-Quelle.

**Die `path`-Quelle ist der Punkt, an dem der Paketweg geprüft und nicht nur beschrieben wird.**
`composer install` legt das Framework nach `vendor/areanet/contentfly`, und von dort lädt der
Autoloader es. Was noch stillschweigend annimmt, im selben Baum zu liegen, fällt dabei auf.
Nachgemessen im Wegwerf-Checkout, `php:8.3-cli`, `composer install` von Null:

```
vendor/areanet/contentfly -> ../../lib/contentfly/
Pfade::paket() = /app/lib/contentfly
```

**`Pfade::paket()` hat dabei seinen Bezugspunkt gewechselt** — von vier Ebenen aufwärts (Wurzel
des Repos) auf zwei (Wurzel des Pakets). Das ist die Folge davon, dass `lib/contentfly/` jetzt
selbst das Paket ist: Es trägt sein eigenes `composer.json`. Der Gewinn ist, dass die Zahl nicht
mehr davon abhängt, wie tief das Paket im Projekt liegt.

## Die Zuordnung, Posten für Posten

| Posten | wohin | warum |
|---|---|---|
| `require` (Doctrine, Symfony, JWT, Mailer, UUID) | Paket | benutzt `lib/` |
| `vlucas/phpdotenv` | **Projekt** | benutzt nur `custom/config.php` — 0 Treffer in `lib/`, 1 in `custom/` |
| `suggest: symfony/ldap` | Paket | gehört zum `LdapProvider` des Frameworks |
| `require-dev` | Projekt | wer eine Bibliothek einbindet, erbt ihre Abhängigkeiten, nicht ihre Werkzeuge |
| `config.platform` | Projekt | eine Bibliothek darf dem Konsumenten keine Plattformversion vorschreiben |
| `config.audit.ignore` | Projekt | das Gate läuft gegen den Lock des Projekts |
| `autoload: Areanet\PIM\` | Paket | |
| `autoload: Custom\`, `Plugins\` | Projekt | die beiden Slots |
| `autoload-dev: Tests\` | Projekt | die Suite gehört zur Entwicklung, nicht in den Lieferumfang |
| `extra.hinweis` | beide, geteilt | die Constraint-Begründung zum Paket, Plattform und Inventar zum Projekt |

**`vlucas/phpdotenv` wechselt die Seite, und das ist kein Detail:** Die Einordnungsregel in
`tools/dependency-assignment.json` sagte „benutzt die ausgelieferte Vorlage es? → root (sie
gehört zum Framework)". Das galt, solange Vorlage und Framework dasselbe Manifest teilten. Die
Vorlage **ist** das Projekt — Schritt 2 der Regel führt jetzt zum Projekt, und die Regel ist
nachgezogen. Eine Regel, die nicht mehr stimmt, ist ein Defekt.

**`custom/composer.json` und `custom/composer.lock` sind entfallen,** mitsamt der
`.gitignore`-Ausnahme, die den Lock offenhielt.

## `plugins/`: bleibt, mit einer benannten Ausnahme

`Plugins\` steht jetzt im Manifest des Projekts. **Der eigene Composer-Baum je Plugin bleibt** —
`Plugin::initComposer()` lädt ihn weiter. Ein Plugin ist kein Bestandteil des Projekts im Sinne
von Composer: Es wird nicht aufgelöst, sondern als Verzeichnis abgelegt. Ihm seine Abhängigkeiten
zu nehmen hiesse, jedes Plugin in das Manifest des Projekts zu zwingen — und damit dieselbe
Vermischung wiederherzustellen, die dieser Task auflöst, nur an anderer Stelle.

**Der Preis steht ausgesprochen in `architecture.md`:** Kollidiert ein Plugin-Paket mit dem des
Projekts, gewinnt der zuerst geladene Baum, und das ist der des Projekts. **Ungeprüft, und das
wird gesagt:** `plugins/` ist leer, es gibt hier nichts, wogegen sich das messen liesse.

## Ein ungefragter Major-Sprung, gedeckelt

`composer update` löste neu auf und hob dabei **`doctrine/persistence` von 3.4.5 auf 4.2.0** —
ungefragt, weil das Paket transitiv ist und bis dahin nur der Lock es festhielt. Persistence 4.2
markiert `AbstractClassMetadataFactory::setMetadataFor()` als deprecated, und
`Classes/Events/LoadMetadata` ruft genau die Methode. **PHPStan wurde davon rot.**

**Gedeckelt auf `^3.4`, nicht mitgenommen.** Der Sprung verlangt eine eigene
`ClassMetadataFactory` und ist damit eine eigene Aufgabe — nicht der Nebeneffekt einer
Manifest-Teilung. Der Deckel steht im `extra.hinweis` des Pakets mit Datum und Grund, damit
niemand ihn für eine vergessene Zeile hält. **Dafür fehlt noch ein Task**; er gehört angelegt.

## Der Wächter

`tests/Unit/Kernel/PaketmanifestTest.php`, fünf Tests: Die Version in `composer.json` stimmt mit
`APP_VERSION` überein · das Paket trägt nur `Areanet\PIM\` · das Projekt trägt ihn **nicht** ·
das Paket liefert weder `require-dev` noch `config.platform` mit · das Manifest liegt dort, wo
`Pfade::paket()` hinzeigt.

**Der erste Test löst eine Zusage ein, die das Manifest selbst macht.** Das `version`-Feld ist
bei einer `path`-Quelle nötig — ohne es leitet Composer die Version aus dem Git-Zweig ab, und
die Auflösung hinge daran, wie der Branch gerade heisst. Damit steht die Version an zwei Stellen,
und zwei Stellen sind eine zu viel: Der Test macht daraus eine geprüfte Bedingung.

**Zahlen:** Volle Suite `OK (495 tests, 1214 assertions)` (vorher 490), 0 Deprecations bei 0
Ausnahmen, 0 Byte Postausgang. PHPStan `[OK] No errors`. `composer validate` auf beiden
Manifesten. `composer install` von Null im `php:8.3-cli` gegen einen Wegwerf-Checkout.
`appcms:install` läuft, `/api/config` antwortet mit 200.

**Die beiden Audit-Gates liefen nicht lokal:** Mein Composer ist 2.6.6, das Gate verlangt ≥ 2.8.0
für `--abandoned`. Das Gate sagt das selbst und wird korrekt rot statt still durchzuwinken.
Nachgeholt im `php:8.3-cli` mit Composer 2.10.3: `composer audit --locked --abandoned=fail` ohne
Advisories, und keine Ausnahme, die nichts mehr trifft.
