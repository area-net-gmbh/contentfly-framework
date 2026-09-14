<?php
namespace Tests\Fixtures\RectorMigration\Before;

use Areanet\PIM\Classes\Annotations as PIM;
use Areanet\PIM\Entity\Base;
use Doctrine\ORM\Mapping as ORM;

/**
 * Reference entity in the LEGACY STATE, second part.
 *
 * This holds the cases missing from `Article`: the nested ORM annotation, the two reduced
 * annotations, and the ones that must remain untouched.
 *
 * WHICH FIELDS REALLY EXISTED is stated in the annotation classes under
 * `lib/contentfly/Classes/Annotations/` — not in the removal list. The list says what is
 * DROPPED; what REMAINS is stated by the constructor. My first draft of this reference fixture
 * invented `@PIM\Radio(options=…)`, `@PIM\Virtualjoin(entity=…, mappedBy=…)` and
 * `@PIM\Permissions(mode=…)` — fields that none of these classes ever had. PHPStan reported
 * it in the target state (007-002-0001).
 *
 * @ORM\Entity
 * @ORM\Table(name="fixture_category")
 * @PIM\Config(excludeFromSync=true, tabs="Master,Further")
 */
class Category extends Base
{
    /**
     * THE CASE WHERE A CONVERSION TYPICALLY FAILS, IN OUR EXPERIENCE: nested.
     *
     * @ORM\ManyToMany(targetEntity="Tests\Fixtures\RectorMigration\Before\Article")
     * @ORM\JoinTable(
     *     name="fixture_category_article",
     *     joinColumns={@ORM\JoinColumn(name="category_id", referencedColumnName="id")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="article_id", referencedColumnName="id")}
     * )
     */
    protected $articles;

    /**
     * `@PIM\Checkbox` REMAINS, loses `horizontalAlignment` and `columns`; `group` remains.
     *
     * @ORM\Column(type="boolean", nullable=true)
     * @PIM\Checkbox(horizontalAlignment=true, columns=2, group="Visibility")
     */
    protected $visible;

    /**
     * `@PIM\Radio` REMAINS, loses `horizontalAlignment`, `columns` and `select`.
     *
     * @ORM\Column(type="string", length=20, nullable=true)
     * @PIM\Radio(horizontalAlignment=false, columns=3, select=true, group="Variant")
     */
    protected $variant;

    /**
     * The remaining annotations, with their REAL fields — they must survive the run
     * unchanged. `Permissions` and `I18nPermissions` have no constructor and therefore accept
     * nothing; they deliberately stand bare.
     *
     * @ORM\Column(type="string", length=40, nullable=true)
     * @PIM\Virtualjoin(targetEntity="Tests\Fixtures\RectorMigration\Before\Article")
     * @PIM\Permissions
     * @PIM\I18nPermissions
     */
    protected $references;

    /**
     * The three remaining removed annotations.
     *
     * @ORM\Column(type="datetime", nullable=true)
     * @PIM\Datetime(format="d.m.Y H:i")
     */
    protected $dueDate;

    /**
     * @ORM\Column(type="time", nullable=true)
     * @PIM\Time(format="H:i:s")
     */
    protected $timeOfDay;

    /**
     * @ORM\Column(type="string", length=30, nullable=true)
     * @PIM\MatrixChooser(rows=3, cols=3)
     */
    protected $matrix;

    /**
     * The collection point for the dropped `Config` fields that were still missing above.
     *
     * They stand together here so that the list from `pim-annotationen-migration.md` is
     * COMPLETELY covered — all 14. A rule measured only against what happened to be at hand
     * leaves the rest in place, and that then breaks in the `AnnotationReader`.
     *
     * @ORM\Column(type="string", length=50, nullable=true)
     * @PIM\Config(
     *     listShorten=20,
     *     isDatalist=true,
     *     isSidebar=true,
     *     accept="image/png",
     *     filter="active",
     *     isFilterable=true
     * )
     */
    protected $collectedDroppedFields;

    /**
     * And the counter-check: the remaining `Config` fields that were still missing above.
     *
     * All ten must survive the run unchanged. Without this property, `007-002-0004` would
     * check its most important guarantee — that no remaining field disappears — only on
     * half of them.
     *
     * @ORM\Column(type="string", length=50, nullable=true)
     * @PIM\Config(
     *     type="tree",
     *     i18n_universal=true,
     *     sortRestrictTo="category",
     *     sortBy="title",
     *     sortOrder="ASC"
     * )
     */
    protected $collectedRemainingFields;
}
