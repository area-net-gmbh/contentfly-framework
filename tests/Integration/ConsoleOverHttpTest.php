<?php
namespace Tests\Integration;

/**
 * The console does not answer a request (`015-000-0004`).
 *
 * THE MEASUREMENT IS REAL, NOT SIMULATED. The suite's server is PHP's built-in one, which runs
 * under the SAPI `cli-server` and executes any `.php` file that exists on disk — `tests/router.php`
 * hands an existing file straight to it. `GET /bin/console.php` therefore really did start the
 * console before this task, with `ArgvInput` taking its command from the query string wherever
 * `register_argc_argv` is on. That is PHP's default whenever no `php.ini` is loaded.
 *
 * So this test needs no `.htaccess`: it measures the layer that protects an installation whose
 * web server is configured differently from the documented one — the entry point refusing the
 * SAPI itself, before the autoloader.
 *
 * `appcms:setup` is the command asked for on purpose. It is the one from `015-000-0002`: before
 * that task it set the admin password back to `admin`, and the two findings together were a full
 * takeover from a single unauthenticated request.
 */
class ConsoleOverHttpTest extends IntegrationTestCase
{
    /** @return array<string, array{0: string}> */
    public static function consoleRequests(): array
    {
        return array(
            'bare'                 => array('/bin/console.php'),
            'list via argv'        => array('/bin/console.php?list'),
            'appcms:setup'         => array('/bin/console.php?appcms:setup'),
            'dbal:run-sql'         => array('/bin/console.php?dbal%3Arun-sql&SELECT+1'),
            'doctrine cli-config'  => array('/bin/cli-config.php'),
        );
    }

    /**
     * @param string $path
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('consoleRequests')]
    public function testTheConsoleRefusesARequest(string $path): void
    {
        [$status, $body] = $this->get($path);

        $this->assertSame(403, $status, "The entry point answers a request with 403:\n".$body);
        $this->assertStringNotContainsString('Usage:', $body, 'and prints no command list');
        $this->assertStringNotContainsString('Available commands', $body);
        $this->assertStringNotContainsString('appcms:', $body, 'and names no command of its own');
    }

    /**
     * The other half of the proof: the admin password is what `appcms:setup` used to reset.
     *
     * Asking for it over HTTP must leave the account exactly as it was — which is only worth
     * stating because before `015-000-0002` and this task, together, it did not.
     */
    public function testARequestForSetupChangesNothing(): void
    {
        $before = $this->pdo()->query("SELECT pass FROM pim_user WHERE alias = 'admin'")->fetchColumn();

        [$status] = $this->get('/bin/console.php?appcms:setup');

        $this->assertSame(403, $status);
        $this->assertSame($before, $this->pdo()->query("SELECT pass FROM pim_user WHERE alias = 'admin'")->fetchColumn(),
            'The admin password is untouched.');
        $this->assertSame(200, $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => $this->pass()))[0],
            'and the account still opens with the password it had.');
    }

    /** The application itself is unaffected — the guard sits in the entry point, not in the kernel's path. */
    public function testTheApiStillAnswers(): void
    {
        $this->assertSame(200, $this->get('/api/config')[0]);
    }
}
