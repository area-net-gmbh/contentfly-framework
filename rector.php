<?php
declare(strict_types=1);

use Rector\Config\RectorConfig;

/*
 * Die Migrationsregel für das `Entity/`-Verzeichnis eines Bestandsprojekts (Story 007-002).
 *
 * ── Wofür ─────────────────────────────────────────────────────────────────────────────
 *
 * Ein Projekt, das von einem Contentfly-Stand vor Epic 010/012 kommt, lässt diese Regel
 * einmal über sein `Entity/`-Verzeichnis laufen und hat danach
 *
 *   - PHP-Attribute statt `@ORM\*`-Annotationen (Epic 010),
 *   - keine der mit Epic 012 gestrichenen `@PIM\*`-Annotationen,
 *   - keines der gestrichenen Felder in den `@PIM\*`-Annotationen, die geblieben sind.
 *
 * Die vollständige Liste, aus der die Regel gebaut ist, steht in
 * `an_project/docs/pim-annotationen-migration.md`. Sie ist die Quelle; was hier steht, ist
 * ihre Umsetzung.
 *
 * ── DER AUFRUF, und der erste Schritt ist immer ein Trockenlauf ───────────────────────
 *
 *     ./vendor/bin/rector process pfad/zu/Entity --dry-run
 *     ./vendor/bin/rector process pfad/zu/Entity
 *
 * Der Pfad hinter `process` übersteuert `withPaths()` unten. Ohne Pfad läuft die Regel über
 * das, was hier eingetragen ist.
 *
 * ── WARUM DER PFAD ENG IST ────────────────────────────────────────────────────────────
 *
 * Rectors eigenes Gerüst (`rector init`) trägt `custom`, `lib`, `tests` und `tools` ein — also
 * den ganzen Baum. Hier wäre das falsch und gefährlich: `lib/` ist der Frameworkcode, der
 * längst auf Attributen steht, und `tests/Fixtures/RectorMigration/alt/` ist der Prüfstein
 * DIESER Regel, der im Altstand bleiben muss. Ein Lauf über den ganzen Baum schriebe beide um.
 *
 * Eingetragen ist deshalb nur `custom/Entity` — das Verzeichnis, das ein Projekt migriert.
 *
 * ── Stand ─────────────────────────────────────────────────────────────────────────────
 *
 * Mit `007-002-0001` steht hier der Rahmen und noch keine Regel: Der Lauf geht durch und
 * ändert nichts. Die Regeln kommen mit `007-002-0002` (ORM-Attribute), `007-002-0003` (die
 * sieben gestrichenen Annotationen) und `007-002-0004` (die gestrichenen Felder).
 */
return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/custom/Entity',
    ]);
