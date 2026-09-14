<?php
declare(strict_types=1);

use Rector\Config\RectorConfig;
use Areanet\PIM\Migration\RemovedAttributeFieldsRector;
use Rector\DeadCode\Rector\ClassLike\RemoveAnnotationRector;
use Rector\Php80\Rector\Class_\AnnotationToAttributeRector;
use Rector\Php80\ValueObject\AnnotationToAttribute;
use Rector\Doctrine\Set\DoctrineSetList;

/*
 * The migration rule for the `Entity/` directory of an existing project (Story 007-002).
 *
 * ── Purpose ───────────────────────────────────────────────────────────────────────────
 *
 * A project that comes from a Contentfly version before Epic 010/012 runs this rule once over
 * its `Entity/` directory and afterwards has
 *
 *   - PHP attributes instead of `@ORM\*` annotations (Epic 010),
 *   - none of the `@PIM\*` annotations removed with Epic 012,
 *   - none of the removed fields in the `@PIM\*` annotations that remained.
 *
 * The complete list the rule is built from is in
 * `an_project/docs/pim-annotationen-migration.md`. It is the source; what is written here is
 * its implementation.
 *
 * ── THE INVOCATION — AND IT MUST RUN TWICE ────────────────────────────────────────────
 *
 *     ./vendor/bin/rector process path/to/Entity --dry-run     # review
 *     ./vendor/bin/rector process path/to/Entity               # first run
 *     ./vendor/bin/rector process path/to/Entity               # second run
 *     ./vendor/bin/rector process path/to/Entity --dry-run     # must say "Rector is done!"
 *
 * **TWO RUNS ARE NOT A CONVENIENCE BUT A NECESSITY.** The rule that removes fields from
 * attributes sees attributes — and those only come into existence once the conversion has
 * rewritten the annotation in the same run. A Rector pass applies the rules to the tree it
 * found; what one rule newly creates only reaches another rule in the next pass.
 *
 * WHOEVER RUNS IT ONLY ONCE HAS A BROKEN TREE — not a half-migrated one. The code then reads
 * `#[PIM\Config(label: 'Article')]`, and `Config::__construct()` has no `$label`:
 *
 *     Unknown named parameter $label
 *
 * A fatal error when loading the entity. The stop condition is therefore not "twice" but
 * **run until a dry run reports nothing more**. Verified against the reference fixture:
 * the second run still changes something, the third nothing more
 * (`tests/Unit/Migration/RectorRuleTest.php`).
 *
 * The path after `process` overrides `withPaths()` below. Without a path, the rule runs over
 * what is configured here.
 *
 * ── WHY THE PATH IS NARROW ────────────────────────────────────────────────────────────
 *
 * Rector's own scaffold (`rector init`) configures `custom`, `lib`, `tests` and `tools` — that
 * is, the whole tree. Here that would be wrong and dangerous: `lib/` is the framework code,
 * which has long been on attributes, and `tests/Fixtures/RectorMigration/before/` is the
 * reference fixture of THIS rule, which must stay in the legacy state. A run over the whole
 * tree would rewrite both.
 *
 * Therefore only `custom/Entity` is configured — the directory a project migrates.
 *
 * ── WHAT THIS RULE CANNOT DO ─────────────────────────────────────────────────────────
 *
 * It runs over `Entity/`. Everything else remains manual work, and it belongs named here so
 * that nobody considers the run complete:
 *
 *   REMOVE THREE TYPE CLASSES FROM THE CONFIGURATION. Along with the annotations, `RteType`,
 *   `PasswordType` and `EntitySelectorType` were dropped. A project that lists one of them in
 *   `APP_SYSTEM_TYPES` or `APP_CUSTOM_TYPES` aborts at startup with
 *   `contentfly_type_class_not_found`. That lives in `custom/config.php`, not in an entity — a
 *   rule over `Entity/` never gets there, and extending it would mean rewriting a project's
 *   configuration.
 *
 *   The same goes for the dropped plugin interface and the `FRONTEND_*` configuration; both
 *   are described in `an_project/docs/pim-annotationen-migration.md`, sections 5 and 6.
 *
 * The complete list of what the rule covers and what it does not is in
 * `an_project/docs/pim-annotationen-migration.md`, section 7 — in one place, so that it does
 * not drift apart.
 *
 * ── Status ────────────────────────────────────────────────────────────────────────────
 *
 * Complete: the ORM part (`007-002-0002`), the seven removed `@PIM\*` annotations and the
 * conversion of the remaining ones to attributes (`007-002-0003`), the removed fields from the
 * annotations that remain (`007-002-0004`).
 */
return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/custom/Entity',
    ])
    /*
     * THE ORM PART (007-002-0002).
     *
     * `ANNOTATIONS_TO_ATTRIBUTES` from `rector-doctrine` — specifically this set and not the
     * generic `AnnotationToAttributeRector`, for which every mapping annotation would have to
     * be entered individually. The set knows them all, including the nested forms, and is
     * maintained alongside Doctrine.
     *
     * IT COMES WITHOUT AN ADDITIONAL PACKAGE: `rector/rector` 1.2 ships `rector-doctrine` in
     * its own vendor directory. Checked on 2026-09-11 — a `composer require
     * rector/rector-doctrine` is not necessary and would pull a second copy of the same rules
     * into the tree.
     *
     * THE OTHER SETS ARE DELIBERATELY LEFT OUT. `DOCTRINE_CODE_QUALITY` and
     * `TYPED_COLLECTIONS` change code, not mapping — that belongs to a project and not to a
     * migration rule. Anyone who wants them adds them on their own.
     */
    ->withSets([
        DoctrineSetList::ANNOTATIONS_TO_ATTRIBUTES,
    ])
    /*
     * THE SEVEN REMOVED @PIM ANNOTATIONS (007-002-0003).
     *
     * With Epic 012, the part of the `@PIM` annotations that described input forms was
     * dropped. These seven are to be deleted without replacement; the list is in
     * an_project/docs/pim-annotationen-migration.md, section 1.
     *
     * WHY THIS IS NOT OPTIONAL: a leftover field is not a tolerated relic.
     * `Doctrine\Common\Annotations\Annotation::__get()` throws a BadMethodCallException,
     * and the AnnotationReader already aborts while reading — the project then does not start
     * at all.
     *
     * FULLY QUALIFIED AND NOT `PIM\Rte`, and that is the difference that counts: both
     * spellings work (verified on 2026-09-11), but the short one depends on the alias. A
     * project that writes `use Areanet\PIM\Classes\Annotations as Anders;` would not be
     * caught by `PIM\Rte`. Rector resolves the full name via the use statements and finds it
     * under any alias; `tests/Fixtures/RectorMigration/` has a dedicated file for this.
     *
     * NO CUSTOM CODE NEEDED. The story assumed there was no ready-made Rector set for the @PIM
     * part. For removing an ENTIRE annotation there is one — RemoveAnnotationRector,
     * configurable, and it also applies to properties (`getNodeTypes()` lists Property). The
     * custom part lies with the FIELDS and therefore in 007-002-0004.
     */
    ->withConfiguredRule(RemoveAnnotationRector::class, [
        'Areanet\\PIM\\Classes\\Annotations\\Rte',
        'Areanet\\PIM\\Classes\\Annotations\\Textarea',
        'Areanet\\PIM\\Classes\\Annotations\\Datetime',
        'Areanet\\PIM\\Classes\\Annotations\\Time',
        'Areanet\\PIM\\Classes\\Annotations\\Password',
        'Areanet\\PIM\\Classes\\Annotations\\MatrixChooser',
        'Areanet\\PIM\\Classes\\Annotations\\EntitySelector',
    ])
    /*
     * AND THE REMAINING @PIM ANNOTATIONS BECOME ATTRIBUTES (007-002-0003).
     *
     * THIS WAS NOT PLANNED AND IS THE MORE IMPORTANT PART. The scope of this story assumed
     * that the remaining annotations were not to be touched. They have to be, and the reason
     * is a SILENT FAILURE:
     *
     * Since 010-001-0003, `Classes/Metadata/MetadataReader` reads exclusively PHP attributes
     * via reflection; the AnnotationReader has disappeared from the framework. A project that
     * keeps `@PIM\Config(excludeFromSync=true)` in the docblock after the migration thereby
     * has a configuration that NOBODY READS ANY MORE — and there is no error message. The
     * entity ends up in the sync API again, an `encoded` field is written unencrypted, an
     * `isFilterable` disappears from the filters. All silently.
     *
     * The ORM set above does not touch the @PIM declarations, so this rule is needed.
     * `ManyToMany` is included: the annotation belongs to the PIM side and is explicitly
     * listed in section 2 of the removal list among those that remain.
     */
    ->withConfiguredRule(AnnotationToAttributeRector::class, [
        new AnnotationToAttribute('Areanet\\PIM\\Classes\\Annotations\\Config'),
        new AnnotationToAttribute('Areanet\\PIM\\Classes\\Annotations\\Select'),
        new AnnotationToAttribute('Areanet\\PIM\\Classes\\Annotations\\Virtualjoin'),
        new AnnotationToAttribute('Areanet\\PIM\\Classes\\Annotations\\Permissions'),
        new AnnotationToAttribute('Areanet\\PIM\\Classes\\Annotations\\I18nPermissions'),
        new AnnotationToAttribute('Areanet\\PIM\\Classes\\Annotations\\Checkbox'),
        new AnnotationToAttribute('Areanet\\PIM\\Classes\\Annotations\\Radio'),
        new AnnotationToAttribute('Areanet\\PIM\\Classes\\Annotations\\ManyToMany'),
    ])
    /*
     * THE REMOVED FIELDS FROM THE ANNOTATIONS THAT REMAIN (007-002-0004).
     *
     * The custom part of this rule, and the only one: for removing a FIELD from an attribute
     * that remains, Rector has nothing — RemoveAnnotationRector takes an entire annotation,
     * ArgumentRemoverRector works on method calls. Checked on 2026-09-11 across all
     * configurable rules.
     *
     * WHY THIS IS NOT COSMETIC: after the conversion to attributes, the code would read
     * `#[PIM\Config(label: 'Article')]`, and Config::__construct() no longer has a $label —
     * "Unknown named parameter $label", a fatal error when loading the entity. Without this
     * step the migration would not be incomplete but broken.
     *
     * The lists are in an_project/docs/pim-annotationen-migration.md, sections 2 and 3.
     */
    ->withConfiguredRule(RemovedAttributeFieldsRector::class, [
        'Areanet\\PIM\\Classes\\Annotations\\Config' => [
            'viewMode', 'showInList', 'listShorten', 'hide', 'label', 'tab', 'tabs', 'sort',
            'isDatalist', 'isSidebar', 'lines', 'accept', 'readonly', 'filter',
        ],
        'Areanet\\PIM\\Classes\\Annotations\\Checkbox' => [
            'horizontalAlignment', 'columns',
        ],
        'Areanet\\PIM\\Classes\\Annotations\\Radio' => [
            'horizontalAlignment', 'columns', 'select',
        ],
    ]);
