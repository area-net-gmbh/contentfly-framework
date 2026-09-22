<?php
namespace Tests\Integration;

/**
 * A second test server over the same tree and the same database, with a different environment
 * (000-000-0075).
 *
 * Some paths of the framework only run under a configuration the suite's server does not have:
 * the schema cache (`APP_ENABLE_SCHEMA_CACHE`) and a main language (`APP_LANGUAGES`). Switching
 * them on for the whole suite would change what every other test measures. So the test class that
 * needs one starts its own server, with exactly that switch set, and stops it afterwards.
 *
 * It is started like the suite's server in `tests/README.md`: the router, `APP_ENV=production`,
 * `APP_DEBUG=0`, no errors in the response stream. What the pipeline's deprecation gate does for
 * the suite's server, stop() does for this one — its log is not the one the gate reads.
 *
 * With `CONTENTFLY_COVERAGE_DIR` set and PCOV loaded, it writes its share of the coverage into the
 * same directory as the suite's server (000-000-0056).
 */
final class ExtraServer
{
    private function __construct(
        private $process,
        private readonly string $url,
        private readonly string $log,
    ) {
    }

    /** @param array<string,string> $environment what differs from the suite's server */
    public static function start(array $environment): self
    {
        $root = IntegrationTestCase::applicationDir();
        $port = self::freePort();
        $log  = tempnam(sys_get_temp_dir(), 'contentfly-extra-server-');

        $command = array(PHP_BINARY, '-d', 'display_errors=Off', '-d', 'log_errors=On');

        $coverage = getenv('CONTENTFLY_COVERAGE_DIR') ?: null;
        if ($coverage !== null && extension_loaded('pcov')) {
            if (!self::iniLoadsPcov()) {
                array_push($command, '-d', 'extension=pcov');
            }
            array_push($command, '-d', 'pcov.enabled=1', '-d', 'pcov.directory='.$root);
        }

        array_push($command, '-S', '127.0.0.1:'.$port, 'tests/router.php');

        $process = proc_open(
            $command,
            array(0 => array('file', '/dev/null', 'r'), 1 => array('file', $log, 'a'), 2 => array('file', $log, 'a')),
            $pipes,
            $root,
            array_merge(getenv(), array('APP_ENV' => 'production', 'APP_DEBUG' => '0'), $environment)
        );

        if (!is_resource($process)) {
            throw new \RuntimeException('The extra test server did not start.');
        }

        $server = new self($process, 'http://127.0.0.1:'.$port, $log);
        $server->waitUntilListening($port);

        return $server;
    }

    public function url(): string
    {
        return $this->url;
    }

    /**
     * Stops the server and fails on what it logged.
     *
     * A deprecation, warning or error in this server's log would otherwise go unseen: the
     * pipeline's gate reads the log of the suite's server only.
     */
    public function stop(): void
    {
        proc_terminate($this->process);
        proc_close($this->process);

        $log = (string) file_get_contents($this->log);
        @unlink($this->log);

        preg_match_all('/^.*PHP (Deprecated|Warning|Notice|Fatal error|Parse error):.*$/m', $log, $matches);

        if ($matches[0] !== array()) {
            throw new \RuntimeException("The extra test server logged:\n".implode("\n", $matches[0]));
        }
    }

    private function waitUntilListening(int $port): void
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);

            if ($socket !== false) {
                fclose($socket);

                return;
            }

            usleep(50000);
        }

        $log = (string) file_get_contents($this->log);
        $this->stop();

        throw new \RuntimeException("The extra test server does not answer on port $port:\n".$log);
    }

    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);

        if ($socket === false) {
            throw new \RuntimeException("No free port for the extra test server: $errstr");
        }

        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, strrpos($name, ':') + 1);
    }

    /** Whether the php.ini files already load PCOV — loading it twice is a warning. */
    private static function iniLoadsPcov(): bool
    {
        $files = array_filter(array_merge(
            array(php_ini_loaded_file() ?: ''),
            array_map('trim', explode(',', php_ini_scanned_files() ?: ''))
        ));

        foreach ($files as $file) {
            if (preg_match('/^\s*(zend_)?extension\s*=\s*"?[^"\n]*pcov/m', (string) @file_get_contents($file))) {
                return true;
            }
        }

        return false;
    }
}
