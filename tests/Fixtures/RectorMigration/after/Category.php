<?php
namespace Tests\Fixtures\RectorMigration\Before;

use Areanet\PIM\Classes\Annotations as PIM;
use Areanet\PIM\Entity\Base;
use Doctrine\ORM\Mapping as ORM;

/**
 * The TARGET STATE of `../before/Category.php` — written by hand, see `Article.php`.
 */
#[ORM\Entity]
#[ORM\Table(name: 'fixture_category')]
#[PIM\Config(excludeFromSync: true)]
class Category extends Base
{
    /**
     * The nested annotation: `joinColumns={@ORM\JoinColumn(…)}` becomes separate
     * attributes. This is the case where a conversion typically fails, in our experience.
     */
    #[ORM\ManyToMany(targetEntity: \Tests\Fixtures\RectorMigration\Before\Article::class)]
    #[ORM\JoinTable(name: 'fixture_category_article')]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'article_id', referencedColumnName: 'id')]
    protected $articles;

    /**
     * `Checkbox` keeps `group` and loses the two display fields.
     */
    #[ORM\Column(type: 'boolean', nullable: true)]
    #[PIM\Checkbox(group: 'Visibility')]
    protected $visible;

    /**
     * `Radio` keeps `group` and loses three fields.
     */
    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    #[PIM\Radio(group: 'Variant')]
    protected $variant;

    /**
     * The remaining annotations, unchanged in their fields.
     *
     * `targetEntity` stays a STRING and does not become `::class` — unlike with
     * `@ORM\ManyToMany`, where the Doctrine set knows the field as a class reference. My first
     * draft of this target state had inferred `::class` by analogy; looked up in
     * `Classes/Types/VirtualjoinType`, the value sits directly in the schema as
     * `$schema['accept']`, i.e. as a string. Both forms would yield the same value at runtime;
     * the string is the smaller change, and a migration should change the notation, not the
     * meaning.
     */
    #[ORM\Column(type: 'string', length: 40, nullable: true)]
    #[PIM\Virtualjoin(targetEntity: 'Tests\Fixtures\RectorMigration\Before\Article')]
    #[PIM\Permissions]
    #[PIM\I18nPermissions]
    protected $references;

    #[ORM\Column(type: 'datetime', nullable: true)]
    protected $dueDate;

    #[ORM\Column(type: 'time', nullable: true)]
    protected $timeOfDay;

    #[ORM\Column(type: 'string', length: 30, nullable: true)]
    protected $matrix;

    /**
     * All dropped `Config` fields gone, `isFilterable` remains.
     */
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    #[PIM\Config(isFilterable: true)]
    protected $collectedDroppedFields;

    /**
     * And the counter-check: all ten remaining fields unchanged.
     */
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    #[PIM\Config(type: 'tree', i18n_universal: true, sortRestrictTo: 'category', sortBy: 'title', sortOrder: 'ASC')]
    protected $collectedRemainingFields;
}
