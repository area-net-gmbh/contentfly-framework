<?php
namespace Tests\Integration\Database;

use Doctrine\ORM\Proxy\ProxyFactory;
use Tests\Integration\IntegrationTestCase;

/**
 * The EntityManager the bootstrap builds carries the proxy mode from the configuration (000-000-0047).
 *
 * In a subprocess, like `RawSqlTypesTest`: building the application defines constants.
 */
class ProxyGenerationModeTest extends IntegrationTestCase
{
    public function testTheBuiltEntityManagerDoesNotRegenerateProxiesOnEveryRequest(): void
    {
        $project = self::applicationDir();
        $script  = 'require ' . var_export($project . '/vendor/autoload.php', true) . '; '
            . '$app = \Areanet\PIM\Classes\Kernel\Start::console(' . var_export($project, true) . '); '
            . 'echo $app["orm.em"]->getConfiguration()->getAutoGenerateProxyClasses();';

        $output = array();
        $code   = 0;
        exec(sprintf('%s -r %s 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($script)), $output, $code);
        $raw = trim(implode("\n", $output));

        $this->assertSame(0, $code, $raw);
        $this->assertSame((string) ProxyFactory::AUTOGENERATE_FILE_NOT_EXISTS_OR_CHANGED, $raw,
            'The template sets nothing, so the framework default applies — not AUTOGENERATE_ALWAYS (1)');
    }
}
