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
 *
 * ── WHAT `015-000-0017` CHANGED HERE, AND WHAT IT DID NOT ────────────────────────────────
 *
 * The assurance of `000-000-0085` stands unchanged: the chosen block decides `display_errors` and
 * `is_installed`, because it is chosen before either is read. Only the INPUT moved. It used to be
 * `$_SERVER['SERVER_NAME']` — the caller's `Host` header under Apache's default
 * `UseCanonicalName Off` and under the built-in server — and is now `CONTENTFLY_CONFIG`, set by
 * the deployment.
 *
 * The second case therefore turned around, and that is the behaviour change, not an adjustment to
 * make a test pass: "no matching host falls back to the default block" WAS the finding. With host
 * blocks defined, a start that cannot say which block it runs under now aborts. The case that
 * proves the header is out of the decision — `SERVER_NAME` set, `CONTENTFLY_CONFIG` not — is new.
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
     * The case from `000-000-0085`: a server whose own block switches debug mode off and names a
     * real database. Before that fix both answers came from the default block — `display_errors`
     * stayed `1` although `APP_DEBUG` is `false`, and `is_installed` was `false` although
     * `DB_HOST` is set. That assurance is what this case still measures; since `015-000-0017` the
     * block is named by `CONTENTFLY_CONFIG` instead of by the request.
     */
    public function testTheNamedBlockDecidesErrorOutputAndInstalledState(): void
    {
        $result = $this->boot(array('CONTENTFLY_CONFIG' => 'example.test'), '1');

        $this->assertSame('0', $result['display_errors']);
        $this->assertTrue($result['is_installed']);
    }

    /**
     * THE FINDING, as a test: the `Host` header no longer decides anything (`015-000-0017`).
     *
     * `SERVER_NAME` is set to the name of the host block and `CONTENTFLY_CONFIG` is not set. Before
     * the fix this booted straight into the `example.test` block — which is how a caller chose the
     * block, and, the other way round, how an unknown name got the relaxed `default` one. Now the
     * start aborts, because nothing said which block this instance runs under.
     */
    public function testTheServerNameNoLongerChoosesTheBlock(): void
    {
        $this->bootExpectingFailure(array('SERVER_NAME' => 'example.test'));
    }

    /** Host blocks defined and no name given: fail closed rather than fall back to `default`. */
    public function testWithoutANamedBlockTheStartAborts(): void
    {
        $this->bootExpectingFailure(array());
    }

    /** A name that matches no block aborts too — it is not quietly read as `default`. */
    public function testAnUnknownNamedBlockAbortsTheStart(): void
    {
        $this->bootExpectingFailure(array('CONTENTFLY_CONFIG' => 'nope.example.invalid'));
    }

    /**
     * Runs the bootstrap in a child process and returns what it saw.
     *
     * The values are passed as ordinary environment variables; PHP puts them into `$_SERVER` and
     * `getenv()` alike, which is where the bootstrap reads them. Output goes to `/dev/null` and
     * the result travels through a file: with debug mode on the process may print warnings, and
     * they would otherwise end up mixed into the payload.
     *
     * @param array<string,string> $environment
     * @return array{display_errors: string, is_installed: bool}
     */
    private function boot(array $environment, string $displayErrorsAtStart): array
    {
        $status = $this->runProbe($environment, $displayErrorsAtStart);
        $result = $this->scratch . '/result.json';

        $this->assertFileExists($result, sprintf('The bootstrap did not finish (exit code %d).', $status));

        /** @var array{display_errors: string, is_installed: bool} $decoded */
        $decoded = json_decode((string) file_get_contents($result), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /**
     * Runs it and insists that it did NOT come up.
     *
     * Measured on the absence of `result.json`: the probe writes that file as its last statement,
     * so a start that aborted anywhere before it leaves none. A status assertion alone would not
     * do — the point is that the application never reached the state where it answers.
     *
     * @param array<string,string> $environment
     */
    private function bootExpectingFailure(array $environment): void
    {
        $this->runProbe($environment, '0');

        $this->assertFileDoesNotExist(
            $this->scratch . '/result.json',
            'The start must abort instead of falling back to the default block'
        );
    }

    /** @param array<string,string> $environment */
    private function runProbe(array $environment, string $displayErrorsAtStart): int
    {
        $environment = array_merge(array('PATH' => (string) getenv('PATH')), $environment);

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

        return proc_close($process);
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
