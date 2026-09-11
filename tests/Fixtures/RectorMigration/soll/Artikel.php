<?php
namespace Tests\Fixtures\RectorMigration\Alt;

use Areanet\PIM\Classes\Annotations as PIM;
use Areanet\PIM\Entity\Base;
use Doctrine\ORM\Mapping as ORM;

/**
 * Der SOLLZUSTAND von `../alt/Artikel.php`.
 *
 * VON HAND GESCHRIEBEN, und das ist der Punkt: Aus einem Rector-Lauf erzeugt, pruefte der
 * Vergleich die Regel gegen sich selbst. Was hier steht, ist das, was gelten SOLL — wenn die
 * Regel etwas anderes liefert, ist die Regel falsch, nicht diese Datei.
 *
 * Der Namensraum ist derselbe wie im Altstand. Die Regel aendert Annotationen, nicht Namen.
 */
#[ORM\Entity]
#[ORM\Table(name: 'fixture_artikel')]
#[PIM\Config(excludeFromSync: false)]
class Artikel extends Base
{
    /**
     * Das erste von mehreren Feldern faellt: `hide` weg, `isFilterable` bleibt.
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[PIM\Config(isFilterable: true)]
    protected $titel;

    /**
     * Das letzte von mehreren faellt: `readonly` weg, `encoded` bleibt.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    #[PIM\Config(encoded: true)]
    protected $geheimnis;

    /**
     * Ein einziges Feld, und es faellt — die Annotation bleibt OHNE Argumente stehen.
     *
     * Sie ganz zu streichen waere falsch: `@PIM\Config` ohne Felder ist gueltig, und ob eine
     * Eigenschaft ueberhaupt eine Config traegt, kann an anderer Stelle Bedeutung haben.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    #[PIM\Config]
    protected $beschreibung;

    /**
     * Mehrzeilig, gemischt: `viewMode` und `sort` weg, `labelProperty` und `unique` bleiben.
     */
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[PIM\Config(labelProperty: 'titel', unique: true)]
    protected $kennung;

    /**
     * Eine gestrichene Annotation, ganze Zeile — sie verschwindet restlos.
     */
    #[ORM\Column(type: 'text', nullable: true)]
    protected $fliesstext;

    /**
     * Zwei gestrichene an einer Eigenschaft.
     */
    #[ORM\Column(type: 'string', length: 60, nullable: true)]
    protected $zugang;

    /**
     * Eine gestrichene neben einer, die bleibt.
     */
    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    #[PIM\Select(options: 'eins=Eins,zwei=Zwei')]
    protected $auswahl;

    #[ORM\ManyToOne(targetEntity: \Tests\Fixtures\RectorMigration\Alt\Rubrik::class)]
    #[ORM\JoinColumn(name: 'rubrik_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    protected $rubrik;
}
