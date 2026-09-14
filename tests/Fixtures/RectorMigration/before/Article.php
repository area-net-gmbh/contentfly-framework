<?php
namespace Tests\Fixtures\RectorMigration\Before;

use Areanet\PIM\Classes\Annotations as PIM;
use Areanet\PIM\Entity\Base;
use Doctrine\ORM\Mapping as ORM;

/**
 * Reference entity in the LEGACY STATE — annotations as an existing project has them today.
 *
 * Deliberately not meant to be runnable: this file is never loaded as an entity. It is the
 * input material of the Rector rule from story `007-002`, and its counterpart lives under
 * `../after/`.
 *
 * @ORM\Entity
 * @ORM\Table(name="fixture_article")
 * @PIM\Config(label="Article", tab="Content", excludeFromSync=false, showInList=true)
 */
class Article extends Base
{
    /**
     * The case "the first of several fields is dropped".
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     * @PIM\Config(hide=true, isFilterable=true)
     */
    protected $title;

    /**
     * The case "the last of several fields is dropped".
     *
     * @ORM\Column(type="text", nullable=true)
     * @PIM\Config(encoded=true, readonly=true)
     */
    protected $secret;

    /**
     * The case "a single field, and it is dropped" — afterwards the annotation stands without parentheses.
     *
     * @ORM\Column(type="text", nullable=true)
     * @PIM\Config(lines=5)
     */
    protected $description;

    /**
     * The case "multi-line, dropped and remaining field side by side".
     *
     * @ORM\Column(type="string", length=100, nullable=true)
     * @PIM\Config(
     *     viewMode="compact",
     *     labelProperty="title",
     *     sort=3,
     *     unique=true
     * )
     */
    protected $identifier;

    /**
     * A removed annotation, whole line.
     *
     * @ORM\Column(type="text", nullable=true)
     * @PIM\Rte(toolbar="basic", extend=false)
     */
    protected $bodyText;

    /**
     * Two removed annotations on one property.
     *
     * @ORM\Column(type="string", length=60, nullable=true)
     * @PIM\Password()
     * @PIM\Textarea(lines=3)
     */
    protected $access;

    /**
     * A removed annotation next to one that remains.
     *
     * @ORM\Column(type="string", length=20, nullable=true)
     * @PIM\EntitySelector()
     * @PIM\Select(options="one=One,two=Two")
     */
    protected $choice;

    /**
     * @ORM\ManyToOne(targetEntity="Tests\Fixtures\RectorMigration\Before\Category")
     * @ORM\JoinColumn(name="category_id", referencedColumnName="id", onDelete="SET NULL")
     */
    protected $category;
}
