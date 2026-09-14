<?php
namespace Tests\Fixtures\RectorMigration\Before;

use Areanet\PIM\Classes\Annotations as PIM;
use Areanet\PIM\Entity\Base;
use Doctrine\ORM\Mapping as ORM;

/**
 * The TARGET STATE of `../before/Article.php`.
 *
 * WRITTEN BY HAND, and that is the point: generated from a Rector run, the comparison would
 * check the rule against itself. What is written here is what SHOULD hold — if the rule
 * delivers something else, the rule is wrong, not this file.
 *
 * The namespace is the same as in the legacy state. The rule changes annotations, not names.
 */
#[ORM\Entity]
#[ORM\Table(name: 'fixture_article')]
#[PIM\Config(excludeFromSync: false)]
class Article extends Base
{
    /**
     * The first of several fields is dropped: `hide` gone, `isFilterable` remains.
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[PIM\Config(isFilterable: true)]
    protected $title;

    /**
     * The last of several is dropped: `readonly` gone, `encoded` remains.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    #[PIM\Config(encoded: true)]
    protected $secret;

    /**
     * A single field, and it is dropped — the annotation stays WITHOUT arguments.
     *
     * Removing it entirely would be wrong: `@PIM\Config` without fields is valid, and whether a
     * property carries a Config at all can matter elsewhere.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    #[PIM\Config]
    protected $description;

    /**
     * Multi-line, mixed: `viewMode` and `sort` gone, `labelProperty` and `unique` remain.
     */
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[PIM\Config(labelProperty: 'title', unique: true)]
    protected $identifier;

    /**
     * A removed annotation, whole line — it disappears completely.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    protected $bodyText;

    /**
     * Two removed ones on one property.
     */
    #[ORM\Column(type: 'string', length: 60, nullable: true)]
    protected $access;

    /**
     * A removed one next to one that remains.
     */
    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    #[PIM\Select(options: 'one=One,two=Two')]
    protected $choice;

    #[ORM\ManyToOne(targetEntity: \Tests\Fixtures\RectorMigration\Before\Category::class)]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    protected $category;
}
