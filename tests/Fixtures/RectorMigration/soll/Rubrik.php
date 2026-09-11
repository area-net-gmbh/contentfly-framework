<?php
namespace Tests\Fixtures\RectorMigration\Alt;

use Areanet\PIM\Classes\Annotations as PIM;
use Areanet\PIM\Entity\Base;
use Doctrine\ORM\Mapping as ORM;

/**
 * Der SOLLZUSTAND von `../alt/Rubrik.php` — von Hand geschrieben, siehe `Artikel.php`.
 */
#[ORM\Entity]
#[ORM\Table(name: 'fixture_rubrik')]
#[PIM\Config(excludeFromSync: true)]
class Rubrik extends Base
{
    /**
     * Die verschachtelte Annotation: aus `joinColumns={@ORM\JoinColumn(…)}` werden eigene
     * Attribute. Das ist der Fall, an dem eine Umstellung erfahrungsgemaess scheitert.
     */
    #[ORM\ManyToMany(targetEntity: \Tests\Fixtures\RectorMigration\Alt\Artikel::class)]
    #[ORM\JoinTable(name: 'fixture_rubrik_artikel')]
    #[ORM\JoinColumn(name: 'rubrik_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'artikel_id', referencedColumnName: 'id')]
    protected $artikel;

    /**
     * `Checkbox` behaelt `group` und verliert die beiden Darstellungsfelder.
     */
    #[ORM\Column(type: 'boolean', nullable: true)]
    #[PIM\Checkbox(group: 'Sichtbarkeit')]
    protected $sichtbar;

    /**
     * `Radio` behaelt `group` und verliert drei Felder.
     */
    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    #[PIM\Radio(group: 'Variante')]
    protected $variante;

    /**
     * Die gebliebenen Annotationen, unveraendert in ihren Feldern.
     */
    #[ORM\Column(type: 'string', length: 40, nullable: true)]
    #[PIM\Virtualjoin(targetEntity: \Tests\Fixtures\RectorMigration\Alt\Artikel::class)]
    #[PIM\Permissions]
    #[PIM\I18nPermissions]
    protected $verweise;

    #[ORM\Column(type: 'datetime', nullable: true)]
    protected $stichtag;

    #[ORM\Column(type: 'time', nullable: true)]
    protected $uhrzeit;

    #[ORM\Column(type: 'string', length: 30, nullable: true)]
    protected $matrix;

    /**
     * Alle entfallenen `Config`-Felder weg, `isFilterable` bleibt.
     */
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    #[PIM\Config(isFilterable: true)]
    protected $sammelfeldEntfallen;

    /**
     * Und die Gegenprobe: alle zehn gebliebenen Felder unveraendert.
     */
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    #[PIM\Config(type: 'tree', i18n_universal: true, sortRestrictTo: 'rubrik', sortBy: 'titel', sortOrder: 'ASC')]
    protected $sammelfeldBleibt;
}
