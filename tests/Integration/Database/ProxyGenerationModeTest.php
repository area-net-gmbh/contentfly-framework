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

    /**
     * A proxy of an earlier installation is never loaded (000-000-0049).
     *
     * The file is written where every version up to 000-000-0049 wrote — `data/cache/doctrine`. It would
     * be loaded by its name, and it throws when it is; if the request comes back, the directory is no
     * longer read.
     */
    public function testAProxyFromAnEarlierInstallationIsNotLoaded(): void
    {
        $project = self::applicationDir();
        $stale   = $project . '/data/cache/doctrine';

        if (!is_dir($stale) && !mkdir($stale, 0777, true) && !is_dir($stale)) {
            $this->markTestSkipped('Cannot write ' . $stale);
        }

        $file = $stale . '/__CG__AreanetPIMEntityUser.php';
        file_put_contents($file, "<?php\nthrow new \\RuntimeException('a proxy of an earlier installation was loaded');\n");
        touch($file, time() + 3600);

        try {
            $script = 'require ' . var_export($project . '/vendor/autoload.php', true) . '; '
                . '$app = \Areanet\PIM\Classes\Kernel\Start::console(' . var_export($project, true) . '); '
                . '$user = $app["orm.em"]->getReference("Areanet\\PIM\\Entity\\User", "no-such-id"); '
                . 'echo get_class($user), "|", $app["orm.em"]->getConfiguration()->getProxyDir();';

            $output = array();
            $code   = 0;
            exec(sprintf('%s -r %s 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($script)), $output, $code);
            $raw = trim(implode("\n", $output));

            $this->assertSame(0, $code, $raw);
            [$class, $proxyDir] = explode('|', $raw) + array('', '');

            $this->assertStringContainsString('DoctrineProxy', $class, 'A proxy was really built');
            $this->assertMatchesRegularExpression('#/data/cache/proxies/[0-9a-f]{12}$#', $proxyDir);
            $this->assertFileExists($proxyDir . '/__CG__AreanetPIMEntityUser.php', 'and written in the new directory');
        } finally {
            @unlink($file);
        }
    }

}
