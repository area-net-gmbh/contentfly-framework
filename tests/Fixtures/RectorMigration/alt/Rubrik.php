<?php
namespace Tests\Fixtures\RectorMigration\Alt;

use Areanet\PIM\Classes\Annotations as PIM;
use Areanet\PIM\Entity\Base;
use Doctrine\ORM\Mapping as ORM;

/**
 * Prüfstein-Entity im ALTSTAND, zweiter Teil.
 *
 * Hier stehen die Fälle, die in `Artikel` fehlen: die verschachtelte ORM-Annotation, die beiden
 * reduzierten Annotationen, und die, die unangetastet bleiben müssen.
 *
 * WELCHE FELDER ES WIRKLICH GAB, steht in den Annotationsklassen unter
 * `lib/contentfly/Classes/Annotations/` — nicht in der Streichliste. Die Liste sagt, was
 * WEGFAELLT; was BLEIBT, sagt der Konstruktor. Mein erster Entwurf dieses Prüfsteins hat
 * `@PIM\Radio(options=…)`, `@PIM\Virtualjoin(entity=…, mappedBy=…)` und
 * `@PIM\Permissions(mode=…)` erfunden — Felder, die keine dieser Klassen je hatte. PHPStan hat
 * es im Sollzustand gemeldet (007-002-0001).
 *
 * @ORM\Entity
 * @ORM\Table(name="fixture_rubrik")
 * @PIM\Config(excludeFromSync=true, tabs="Stamm,Weiteres")
 */
class Rubrik extends Base
{
    /**
     * DER FALL, AN DEM EINE UMSTELLUNG ERFAHRUNGSGEMAESS SCHEITERT: verschachtelt.
     *
     * @ORM\ManyToMany(targetEntity="Tests\Fixtures\RectorMigration\Alt\Artikel")
     * @ORM\JoinTable(
     *     name="fixture_rubrik_artikel",
     *     joinColumns={@ORM\JoinColumn(name="rubrik_id", referencedColumnName="id")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="artikel_id", referencedColumnName="id")}
     * )
     */
    protected $artikel;

    /**
     * `@PIM\Checkbox` BLEIBT, verliert `horizontalAlignment` und `columns`; `group` bleibt.
     *
     * @ORM\Column(type="boolean", nullable=true)
     * @PIM\Checkbox(horizontalAlignment=true, columns=2, group="Sichtbarkeit")
     */
    protected $sichtbar;

    /**
     * `@PIM\Radio` BLEIBT, verliert `horizontalAlignment`, `columns` und `select`.
     *
     * @ORM\Column(type="string", length=20, nullable=true)
     * @PIM\Radio(horizontalAlignment=false, columns=3, select=true, group="Variante")
     */
    protected $variante;

    /**
     * Die gebliebenen Annotationen, mit ihren ECHTEN Feldern — sie müssen den Lauf
     * unverändert überstehen. `Permissions` und `I18nPermissions` haben keinen Konstruktor
     * und nehmen deshalb nichts entgegen; sie stehen bewusst nackt da.
     *
     * @ORM\Column(type="string", length=40, nullable=true)
     * @PIM\Virtualjoin(targetEntity="Tests\Fixtures\RectorMigration\Alt\Artikel")
     * @PIM\Permissions
     * @PIM\I18nPermissions
     */
    protected $verweise;

    /**
     * Die drei restlichen gestrichenen Annotationen.
     *
     * @ORM\Column(type="datetime", nullable=true)
     * @PIM\Datetime(format="d.m.Y H:i")
     */
    protected $stichtag;

    /**
     * @ORM\Column(type="time", nullable=true)
     * @PIM\Time(format="H:i:s")
     */
    protected $uhrzeit;

    /**
     * @ORM\Column(type="string", length=30, nullable=true)
     * @PIM\MatrixChooser(rows=3, cols=3)
     */
    protected $matrix;

    /**
     * Die Sammelstelle für die entfallenen `Config`-Felder, die oben noch fehlten.
     *
     * Sie stehen hier zusammen, damit die Liste aus `pim-annotationen-migration.md`
     * VOLLSTAENDIG abgedeckt ist — alle 14. Eine Regel, die nur an dem gemessen wird, was
     * gerade zur Hand war, lässt den Rest stehen, und der bricht dann beim `AnnotationReader`.
     *
     * @ORM\Column(type="string", length=50, nullable=true)
     * @PIM\Config(
     *     listShorten=20,
     *     isDatalist=true,
     *     isSidebar=true,
     *     accept="image/png",
     *     filter="aktiv",
     *     isFilterable=true
     * )
     */
    protected $sammelfeldEntfallen;

    /**
     * Und die Gegenprobe: die gebliebenen `Config`-Felder, die oben noch fehlten.
     *
     * Alle zehn müssen den Lauf unverändert überstehen. Ohne diese Eigenschaft prüfte
     * `007-002-0004` seine wichtigste Zusicherung — dass kein bleibendes Feld verschwindet —
     * nur an der Hälfte.
     *
     * @ORM\Column(type="string", length=50, nullable=true)
     * @PIM\Config(
     *     type="tree",
     *     i18n_universal=true,
     *     sortRestrictTo="rubrik",
     *     sortBy="titel",
     *     sortOrder="ASC"
     * )
     */
    protected $sammelfeldBleibt;
}
