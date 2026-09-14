<?php
namespace Tests\Fixtures\RectorMigration\Before;

use Areanet\PIM\Classes\Annotations as Other;
use Areanet\PIM\Entity\Base;
use Doctrine\ORM\Mapping as Mapping;

/**
 * The TARGET STATE of the alias probe.
 *
 * The project's aliases are preserved — Rector writes `#[Mapping\Table]` and not
 * `#[ORM\Table]`. That is correct: the rule migrates annotations, it does not rearrange imports.
 */
#[Mapping\Table(name: 'fixture_other_alias')]
#[Mapping\Entity]
#[Other\Config(excludeFromSync: true)]
class OtherAlias extends Base
{
    #[Mapping\Column(type: 'text', nullable: true)]
    protected $text;

    #[Mapping\Column(type: 'string', length: 30, nullable: true)]
    protected $password;
}
