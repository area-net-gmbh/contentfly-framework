<?php
namespace Tests\Fixtures\RectorMigration\Alt;

use Areanet\PIM\Classes\Annotations as Anders;
use Areanet\PIM\Entity\Base;
use Doctrine\ORM\Mapping as Abbildung;

/**
 * Der SOLLZUSTAND der Alias-Probe.
 *
 * Die Aliase des Projekts bleiben erhalten — Rector schreibt `#[Abbildung\Table]` und nicht
 * `#[ORM\Table]`. Das ist richtig: Die Regel migriert Annotationen, sie raeumt keine Importe um.
 */
#[Abbildung\Table(name: 'fixture_anderer_alias')]
#[Abbildung\Entity]
#[Anders\Config(excludeFromSync: true)]
class AndererAlias extends Base
{
    #[Abbildung\Column(type: 'text', nullable: true)]
    protected $text;

    #[Abbildung\Column(type: 'string', length: 30, nullable: true)]
    protected $kennwort;
}
