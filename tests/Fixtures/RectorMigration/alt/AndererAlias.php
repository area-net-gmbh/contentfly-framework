<?php
namespace Tests\Fixtures\RectorMigration\Alt;

use Areanet\PIM\Classes\Annotations as Anders;
use Areanet\PIM\Entity\Base;
use Doctrine\ORM\Mapping as Abbildung;

/**
 * DIE ALIAS-PROBE (007-002-0003).
 *
 * Ein Projekt muss `Areanet\PIM\Classes\Annotations` nicht als `PIM` importieren. Diese Datei
 * benutzt bewusst andere Aliase — und die Regel muss trotzdem greifen, weil sie mit dem
 * VOLLQUALIFIZIERTEN Namen konfiguriert ist und Rector ihn über die use-Anweisungen auflöst.
 *
 * Wäre die Regel auf `PIM\Rte` konfiguriert, ginge sie hier vorbei, und ein Projekt mit
 * eigenem Alias hielte den Lauf für vollständig.
 *
 * @Abbildung\Entity
 * @Abbildung\Table(name="fixture_anderer_alias")
 * @Anders\Config(label="Anders", excludeFromSync=true)
 */
class AndererAlias extends Base
{
    /**
     * @Abbildung\Column(type="text", nullable=true)
     * @Anders\Rte(toolbar="full")
     */
    protected $text;

    /**
     * @Abbildung\Column(type="string", length=30, nullable=true)
     * @Anders\Password()
     */
    protected $kennwort;
}
