<?php
namespace Tests\Fixtures\RectorMigration\Alt;

use Areanet\PIM\Classes\Annotations as PIM;
use Areanet\PIM\Entity\Base;
use Doctrine\ORM\Mapping as ORM;

/**
 * Prüfstein-Entity im ALTSTAND — Annotationen, wie ein Bestandsprojekt sie heute hat.
 *
 * Bewusst nicht lauffähig gemeint: Diese Datei wird nie als Entity geladen. Sie ist das
 * Eingangsmaterial der Rector-Regel aus Story `007-002`, und ihr Gegenstück liegt unter
 * `../soll/`.
 *
 * @ORM\Entity
 * @ORM\Table(name="fixture_artikel")
 * @PIM\Config(label="Artikel", tab="Inhalt", excludeFromSync=false, showInList=true)
 */
class Artikel extends Base
{
    /**
     * Der Fall „das erste von mehreren Feldern fällt".
     *
     * @ORM\Column(type="string", length=255, nullable=true)
     * @PIM\Config(hide=true, isFilterable=true)
     */
    protected $titel;

    /**
     * Der Fall „das letzte von mehreren Feldern fällt".
     *
     * @ORM\Column(type="text", nullable=true)
     * @PIM\Config(encoded=true, readonly=true)
     */
    protected $geheimnis;

    /**
     * Der Fall „ein einziges Feld, und es fällt" — danach steht die Annotation ohne Klammern.
     *
     * @ORM\Column(type="text", nullable=true)
     * @PIM\Config(lines=5)
     */
    protected $beschreibung;

    /**
     * Der Fall „mehrzeilig, entfallenes und bleibendes Feld nebeneinander".
     *
     * @ORM\Column(type="string", length=100, nullable=true)
     * @PIM\Config(
     *     viewMode="compact",
     *     labelProperty="titel",
     *     sort=3,
     *     unique=true
     * )
     */
    protected $kennung;

    /**
     * Eine gestrichene Annotation, ganze Zeile.
     *
     * @ORM\Column(type="text", nullable=true)
     * @PIM\Rte(toolbar="basic", extend=false)
     */
    protected $fliesstext;

    /**
     * Zwei gestrichene Annotationen an einer Eigenschaft.
     *
     * @ORM\Column(type="string", length=60, nullable=true)
     * @PIM\Password()
     * @PIM\Textarea(lines=3)
     */
    protected $zugang;

    /**
     * Eine gestrichene Annotation neben einer, die bleibt.
     *
     * @ORM\Column(type="string", length=20, nullable=true)
     * @PIM\EntitySelector()
     * @PIM\Select(options="eins=Eins,zwei=Zwei")
     */
    protected $auswahl;

    /**
     * @ORM\ManyToOne(targetEntity="Tests\Fixtures\RectorMigration\Alt\Rubrik")
     * @ORM\JoinColumn(name="rubrik_id", referencedColumnName="id", onDelete="SET NULL")
     */
    protected $rubrik;
}
