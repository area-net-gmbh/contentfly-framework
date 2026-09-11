<?php
declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Doctrine\Set\DoctrineSetList;

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
 * Eingetragen: der ORM-Teil (`007-002-0002`). Noch offen: die sieben gestrichenen
 * `@PIM\*`-Annotationen (`007-002-0003`) und die gestrichenen Felder (`007-002-0004`).
 */
return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/custom/Entity',
    ])
    /*
     * DER ORM-TEIL (007-002-0002).
     *
     * `ANNOTATIONS_TO_ATTRIBUTES` aus `rector-doctrine` — und zwar dieses Set und nicht die
     * generische `AnnotationToAttributeRector`, bei der jede Mapping-Annotation einzeln
     * einzutragen wäre. Das Set kennt sie alle, samt der verschachtelten Formen, und wird mit
     * Doctrine gepflegt.
     *
     * ES KOMMT OHNE ZUSAETZLICHES PAKET: `rector/rector` 1.2 liefert `rector-doctrine` in
     * seinem eigenen Vendor mit. Nachgesehen am 2026-09-11 — ein `composer require
     * rector/rector-doctrine` ist nicht nötig und würde eine zweite Fassung derselben Regeln
     * in den Baum holen.
     *
     * DIE ANDEREN SETS BLEIBEN BEWUSST DRAUSSEN. `DOCTRINE_CODE_QUALITY` und
     * `TYPED_COLLECTIONS` ändern Code, nicht Mapping — das gehört einem Projekt und nicht
     * einer Migrationsregel. Wer sie will, trägt sie selbst ein.
     */
    ->withSets([
        DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES,
    ]);
