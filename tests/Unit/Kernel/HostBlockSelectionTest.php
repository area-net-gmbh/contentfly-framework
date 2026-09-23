<?php
namespace Tests\Unit\Kernel;

use PHPUnit\Framework\TestCase;

/**
 * The host block is chosen before anything reads the configuration (`000-000-0085`).
 *
 * `bootstrap.php` loaded `custom/config.php`, defined `HOST` — and then selected the host block
 * only much later, with `Adapter::setHostname(HOST)`. Until that line `Adapter::$host` was
 * `'default'`, and `Factory::getConfig()` falls back to the default block for an unknown host. Two
 * decisions were taken in that window and therefore read the **wrong block**:
 *
 * - `display_errors`, from `APP_DEBUG`. A default block with `APP_DEBUG = true` — the normal case
 *   for local development — switched the error output on for **every** server, including the ones
 *   whose own block says `false`. PHP then writes deprecations and warnings, with absolute file
 *   paths, into the response body; where output buffering is on, the headers go out early and an
 *   API answer leaves as `200 text/html` without CORS headers.
 * - `is_installed`, from `DB_HOST`. It followed the default block's placeholder instead of the
 *   host's real credentials.
 *
 * ── Why a separate process per case ──────────────────────────────────────────────────────
 *
 * The bootstrap defines constants (`HOST`, `APP_CMS_MAIN_LANG`, `APPCMS_ID_TYPE`) and the config
 * factory is a singleton. Neither can be undone inside one process, so two cases cannot share one.
 * `StartupFailureResponseTest` starts a child process for the same reason.
 *
 * It needs no database: with `is_installed` true the bootstrap builds the EntityManager, but
 * `$app['dbs']` is a lazy factory and nothing opens a connection. That is why this is a unit test.
 *
 * ── Why each case starts from the opposite value ─────────────────────────────────────────
 *
 * `display_errors` is passed in as the reverse of what is expected. Otherwise a case could pass
 * without the bootstrap having decided anything — the assertion would only repeat the value the
 * process started with.
 */
class HostBlockSelectionTest extends TestCase
{
    private string $scratch = '';

    protected function setUp(): void
    {
        $this->scratch = sys_get_temp_dir() . '/contentfly-hostblock-' . bin2hex(random_bytes(6));
        mkdir($this->scratch . '/custom', 0777, true);
        mkdir($this->scratch . '/data/cache', 0777, true);

        file_put_contents($this->scratch . '/custom/version.php', "<?php\ndefine('CUSTOM_VERSION', '0.0.0');\n");

        // The project file the bootstrap requires last. Empty is enough — this test ends before
        // any route matters.
        file_put_contents($this->scratch . '/custom/app.php', "<?php\n");

        /*
         * The two blocks differ in exactly the two values under test. The default block carries
         * the state of a fresh checkout — the `$SET_DB_HOST` placeholder and debug mode on; the
         * host block carries what a staging or live server sets.
         */
        file_put_contents($this->scratch . '/custom/config.php', <<<'PHP'
<?php
use Areanet\PIM\Classes\Config;
use Areanet\PIM\Classes\Config\Factory;

$factory = Factory::getInstance();

$default = new Config();
$default->DB_HOST   = '$SET_DB_HOST';
$default->APP_DEBUG = true;
$factory->setConfig($default);

$host = new Config('example.test', $default);
$host->DB_HOST   = 'db';
$host->APP_DEBUG = false;
$factory->setConfig($host);
PHP);

        file_put_contents($this->scratch . '/probe.php', sprintf(
            "<?php\nrequire %s;\n"
            . "\$app = \\Areanet\\PIM\\Classes\\Kernel\\Start::console(__DIR__);\n"
            . "file_put_contents(__DIR__ . '/result.json', json_encode(array(\n"
            . "    'display_errors' => ini_get('display_errors'),\n"
            . "    'is_installed'   => \$app['is_installed'],\n"
            . ")));\n",
            var_export(CONTENTFLY_PROJECT_DIR . '/vendor/autoload.php', true)
        ));
    }

    protected function tearDown(): void
    {
        $this->cleanUp($this->scratch);
    }

    /**
     * The case from the task: a server whose own block switches debug mode off and names a real
     * database. Before the fix both answers came from the default block — `display_errors` stayed
     * `1` although `APP_DEBUG` is `false`, and `is_installed` was `false` although `DB_HOST` is set.
     */
    public function testTheHostBlockDecidesErrorOutputAndInstalledState(): void
    {
        $result = $this->boot('example.test', '1');

        $this->assertSame('0', $result['display_errors']);
        $this->assertTrue($result['is_installed']);
    }

    /**
     * The other direction: no `SERVER_NAME` matches a block, so the default block applies — which
     * is what `Factory::getConfig()` falls back to. Choosing the host earlier must not change this.
     */
    public function testWithoutAMatchingHostTheDefaultBlockDecides(): void
    {
        $result = $this->boot(null, '0');

        $this->assertSame('1', $result['display_errors']);
        $this->assertFalse($result['is_installed']);
    }

    /**
     * Runs the bootstrap in a child process and returns what it saw.
     *
     * `SERVER_NAME` is an ordinary environment variable on the command line, and PHP puts it into
     * `$_SERVER` — the same key the bootstrap reads. Output goes to `/dev/null` and the result
     * travels through a file: with debug mode on the process may print warnings, and they would
     * otherwise end up mixed into the payload.
     *
     * @return array{display_errors: string, is_installed: bool}
     */
    private function boot(?string $serverName, string $displayErrorsAtStart): array
    {
        $environment = array('PATH' => (string) getenv('PATH'));

        if ($serverName !== null) {
            $environment['SERVER_NAME'] = $serverName;
        }

        $process = proc_open(
            array(PHP_BINARY, '-d', 'display_errors=' . $displayErrorsAtStart, '-d', 'log_errors=Off', 'probe.php'),
            array(0 => array('pipe', 'r'), 1 => array('file', '/dev/null', 'w'), 2 => array('file', '/dev/null', 'w')),
            $pipes,
            $this->scratch,
            $environment
        );

        if (!is_resource($process)) {
            $this->fail('The child process did not start.');
        }

        fclose($pipes[0]);
        $status = proc_close($process);

        $result = $this->scratch . '/result.json';

        $this->assertFileExists($result, sprintf('The bootstrap did not finish (exit code %d).', $status));

        /** @var array{display_errors: string, is_installed: bool} $decoded */
        $decoded = json_decode((string) file_get_contents($result), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    private function cleanUp(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $entry) {
            if (!$entry instanceof \SplFileInfo) {
                continue;
            }

            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }

        rmdir($path);
    }
}
