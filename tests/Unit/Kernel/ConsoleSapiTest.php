<?php
namespace Tests\Unit\Kernel;

use Areanet\PIM\Classes\Kernel\Start;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The console runs on the command line and nowhere else (`015-000-0004`).
 *
 * WHAT THE FINDING WAS. In the documented layout `bin/` sits next to `index.php` in the document
 * root, and nothing kept the web off it. Symfony's `ArgvInput` takes the command from
 * `$_SERVER['argv']`, which `register_argc_argv` fills from the query string under a web SAPI —
 * and that setting is PHP's own default whenever no `php.ini` is loaded, as in the official
 * Docker images. Every registered command was therefore reachable without any authentication:
 * `appcms:setup`, `dbal:run-sql`, `orm:run-dql`, `orm:schema-tool:drop`.
 *
 * THE SAPI IS PASSED IN, not read from `PHP_SAPI`. A constant cannot be changed from a test, and
 * a rule nobody can measure is one nobody notices the loss of. That `console()` passes the real
 * constant is one line, right at its top.
 *
 * The web answer itself — 403 from `bin/console.php` before the autoloader — is measured in
 * `Tests\Integration\ConsoleOverHttpTest` against the running server.
 */
class ConsoleSapiTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function consoleSapis(): array
    {
        return array(
            'cli'    => array('cli'),
            'phpdbg' => array('phpdbg'),
        );
    }

    /** @return array<string, array{0: string}> */
    public static function webSapis(): array
    {
        return array(
            'cli-server'      => array('cli-server'),
            'apache2handler'  => array('apache2handler'),
            'fpm-fcgi'        => array('fpm-fcgi'),
            'cgi-fcgi'        => array('cgi-fcgi'),
            'litespeed'       => array('litespeed'),
            'empty'           => array(''),
        );
    }

    #[DataProvider('consoleSapis')]
    public function testATerminalSapiIsAccepted(string $sapi): void
    {
        $this->assertTrue(Start::isConsoleSapi($sapi));

        Start::assertConsoleSapi($sapi);
        $this->addToAssertionCount(1);
    }

    #[DataProvider('webSapis')]
    public function testAWebSapiIsRefused(string $sapi): void
    {
        $this->assertFalse(Start::isConsoleSapi($sapi));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/command line only/');

        Start::assertConsoleSapi($sapi);
    }

    /** `cli-server` is the SAPI the integration test meets — it must not be mistaken for `cli`. */
    public function testTheBuiltInServerIsNotTakenForATerminal(): void
    {
        $this->assertFalse(Start::isConsoleSapi('cli-server'),
            'php -S runs under cli-server, and that is a request like any other');
    }

    /** The suite itself runs under `cli` — otherwise the guard would break every test run. */
    public function testTheSuiteRunsUnderAnAcceptedSapi(): void
    {
        $this->assertTrue(Start::isConsoleSapi(PHP_SAPI),
            'PHPUnit runs under '.PHP_SAPI.', and Start::console() must stay usable from it');
    }
}
