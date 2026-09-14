<?php
namespace Tests\Fixtures\RectorMigration\Before;

use Areanet\PIM\Classes\Annotations as Other;
use Areanet\PIM\Entity\Base;
use Doctrine\ORM\Mapping as Mapping;

/**
 * THE ALIAS PROBE (007-002-0003).
 *
 * A project does not have to import `Areanet\PIM\Classes\Annotations` as `PIM`. This file
 * deliberately uses other aliases — and the rule must apply anyway, because it is configured
 * with the FULLY QUALIFIED name and Rector resolves it via the use statements.
 *
 * If the rule were configured on `PIM\Rte`, it would miss this file, and a project with its
 * own alias would consider the run complete.
 *
 * @Mapping\Entity
 * @Mapping\Table(name="fixture_other_alias")
 * @Other\Config(label="Other", excludeFromSync=true)
 */
class OtherAlias extends Base
{
    /**
     * @Mapping\Column(type="text", nullable=true)
     * @Other\Rte(toolbar="full")
     */
    protected $text;

    /**
     * @Mapping\Column(type="string", length=30, nullable=true)
     * @Other\Password()
     */
    protected $password;
}
