<?php
namespace Tests\Unit\Kernel;

use PHPUnit\Framework\TestCase;

/**
 * A failure before the kernel reaches the caller as a response with a body (`000-000-0024`).
 *
 * Until then an exception from the bootstrap ended the request with HTTP 500 and **0 bytes**: the
 * `$app->error()` handler is a kernel listener, and the kernel did not exist yet. The message was in
 * the server log only.
 *
 * **Why a real server and not a call to `Start::web()`.** On the command line `Start` throws on
 * purpose — `StartTest` relies on that. The response only exists in a web SAPI, and the status code
 * and body only exist over HTTP. So each test starts PHP's built-in server on a scratch project.
 *
 * **Why the body is checked and not just the status.** A test that compares 500 with 500 would have
 * been green with the empty body too — the trap `009-002-0004` described.
 *
 * It needs no database: the configured failure happens before any connection is opened. That is why
 * it lives in the `unit` suite.
 */
class StartupFailureResponseTest extends TestCase
{
    private string $scratch = '';

    /** @var resource|null */
    private $server = null;

    private string $baseUrl = '';

    protected function setUp(): void
    {
        $this->scratch = sys_get_temp_dir() . '/contentfly-startup-' . bin2hex(random_bytes(6));
        mkdir($this->scratch . '/custom', 0777, true);
        mkdir($this->scratch . '/data/cache', 0777, true);

        file_put_contents($this->scratch . '/index.php', sprintf(
            "<?php\nrequire %s;\n\\Areanet\\PIM\\Classes\\Kernel\\Start::web(__DIR__);\n",
            var_export(CONTENTFLY_PROJECT_DIR . '/vendor/autoload.php', true)
        ));
        file_put_contents($this->scratch . '/custom/version.php', "<?php\ndefine('CUSTOM_VERSION', '0.0.0');\n");
    }

    protected function tearDown(): void
    {
        if (is_resource($this->server)) {
            proc_terminate($this->server);
            proc_close($this->server);
        }

        $this->cleanUp($this->scratch);
    }

    /**
     * The case from the task: a cache driver that cannot run. `apc` is used instead of `apcu`
     * because it fails on every PHP — whether an extension happens to be loaded does not matter.
     */
    public function testAMisconfigurationAnswersWithAJsonBody(): void
    {
        $this->writeConfiguration("'apc'");
        $this->startServer(false);

        [$status, $contentType, $raw] = $this->request('/api/config');

        $this->assertSame(500, $status);
        $this->assertNotSame('', $raw, 'Until 000-000-0024 the body was empty');
        $this->assertStringStartsWith('application/json', $contentType);

        $body = json_decode($raw, true);
        $this->assertIsArray($body, 'The body is JSON');
        // 011-001-0003: the same envelope as every other error response — and no debug block.
        // `status` is gone from the body; it stands in the HTTP response, asserted above.
        $this->assertSame(array('data', 'errors', 'meta'), array_keys($body));
        $this->assertNull($body['data']);
        $this->assertArrayNotHasKey('debug', $body['meta']);

        $entry = $body['errors'][0];
        $this->assertSame(array('code', 'detail', 'type', 'context'), array_keys($entry));
        $this->assertStringContainsString('APP_CACHE_DRIVER = "apc"', $entry['detail'],
            'The caller learns what is wrong, not just that something is');
        $this->assertSame('RuntimeException', $entry['type']);

        // The versions ARE known here: the cache driver fails late in the boot, after version.php.
        // The hash is not — `$app['schema']` never came into being. The counter-case, a failure
        // before version.php, is in testWithoutDebugTheBodyNamesNoDirectory.
        $this->assertNotNull($body['meta']['version']);
        $this->assertNull($body['meta']['hash']);
    }

    /**
     * The counter-check with `APP_DEBUG`: more may be in it, and it stays JSON.
     *
     * **Not with the cache driver.** In debug mode the bootstrap builds no Doctrine caches, so `apc`
     * does not fail at startup there at all. The missing configuration fails in both modes — and
     * because it fails before the configuration was read, `APP_DEBUG` has to come from the
     * environment. That is checked along the way.
     */
    public function testWithDebugTheBodyCarriesFileLineAndTrace(): void
    {
        $this->startServer(true);

        [$status, , $raw] = $this->request('/api/config');
        $body = json_decode($raw, true);

        $this->assertSame(500, $status);
        $this->assertIsArray($body, 'The body stays JSON in debug mode');
        // 011-001-0003: the trace describes THIS ANSWER, not the fault — so it sits in the meta,
        // beside `ts` and `hash`, and no longer at the top level of the body.
        $this->assertSame(array('data', 'errors', 'meta'), array_keys($body));
        $this->assertSame(array('file', 'line', 'trace'), array_keys($body['meta']['debug']));
        $this->assertStringEndsWith('Start.php', $body['meta']['debug']['file']);
        $this->assertNotEmpty($body['meta']['debug']['trace']);
        $this->assertStringContainsString((string) realpath($this->scratch), $body['errors'][0]['detail'],
            'In debug mode the directory stays in the message');
    }

    /**
     * A failure before the configuration was even read — here `APP_DEBUG` can only come from the
     * environment. And the message names the project directory on purpose, for the log; the body
     * must not.
     */
    public function testWithoutDebugTheBodyNamesNoDirectory(): void
    {
        // No custom/config.php: Start::prepare() fails with a message that contains the path.
        $this->startServer(false);

        [$status, , $raw] = $this->request('/api/config');
        $body = json_decode($raw, true);

        $this->assertSame(500, $status);
        $this->assertIsArray($body);
        // 011-001-0003: message -> errors[0].detail, debug -> meta.debug.
        $this->assertStringContainsString('project configuration is missing', $body['errors'][0]['detail']);
        $this->assertStringContainsString('<project>/custom/config.php', $body['errors'][0]['detail']);
        $this->assertStringNotContainsString($this->scratch, $raw);
        $this->assertStringNotContainsString((string) realpath($this->scratch), $raw);
        $this->assertArrayNotHasKey('debug', $body['meta']);

        /*
         * BOTH VERSIONS ARE NULL HERE, and that is the point of reading them defensively
         * (011-001-0003). This failure happens in Start::prepare(), before bootstrap.php has read
         * `version.php` — the constants do not exist yet. A plain constant lookup in the envelope
         * would be a fatal error inside the error response, which is the failure mode 000-000-0006
         * fixed one floor up. `null` is the honest answer: nobody knows yet which version it was
         * that could not start.
         */
        $this->assertNull($body['meta']['version']);
        $this->assertNull($body['meta']['projectVersion']);
    }

    private function writeConfiguration(string $cacheDriver): void
    {
        // DB_HOST is set so that the bootstrap counts the instance as installed and builds the
        // Doctrine caches. No connection is opened: the cache driver fails first.
        file_put_contents($this->scratch . '/custom/config.php', <<<PHP
<?php
use Areanet\\PIM\\Classes\\Config;
use Areanet\\PIM\\Classes\\Config\\Factory;

\$config = new Config();
\$config->DB_HOST          = '127.0.0.1';
\$config->APP_DEBUG        = filter_var(getenv('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN);
\$config->APP_CACHE_DRIVER = $cacheDriver;

Factory::getInstance()->setConfig(\$config);
PHP);
    }

    private function startServer(bool $debug): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false);
        fclose($socket);

        $this->baseUrl = 'http://' . $address;

        $this->server = proc_open(
            array(PHP_BINARY, '-d', 'display_errors=Off', '-d', 'log_errors=Off', '-S', $address, 'index.php'),
            array(0 => array('pipe', 'r'), 1 => array('file', '/dev/null', 'w'), 2 => array('file', '/dev/null', 'w')),
            $pipes,
            $this->scratch,
            array('APP_ENV' => 'production', 'APP_DEBUG' => $debug ? '1' : '0', 'PATH' => (string) getenv('PATH'))
        );

        for ($i = 0; $i < 100; $i++) {
            $connection = @fsockopen('127.0.0.1', (int) substr($address, strrpos($address, ':') + 1));

            if ($connection) {
                fclose($connection);

                return;
            }

            usleep(50000);
        }

        $this->fail('The built-in server did not start on ' . $address);
    }

    /** @return array{0:int,1:string,2:string} status, content type, raw body */
    private function request(string $path): array
    {
        $ch = curl_init($this->baseUrl . $path);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => array('Content-Type: application/json'),
            CURLOPT_TIMEOUT        => 10,
        ));

        $raw         = (string) curl_exec($ch);
        $status      = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        return array($status, $contentType, $raw);
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
