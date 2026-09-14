<?php
namespace Tests\Integration;

/**
 * Checks the test run's **mail trap** — the safeguard that prevents a test run from sending real
 * mail.
 *
 * `tools/ci/prepare-test-environment.sh` starts the test server with a catch script as
 * `sendmail_path`. This test proves both halves: that the script really catches, and that the
 * server really uses *this* script. Without the second, an empty outbox would merely be
 * circumstantial evidence.
 *
 * **Origin: `MailApiTest`, dissolved with `000-000-0016`.** That file characterised
 * `POST /api/mail`, the only API endpoint with an external effect — and found that it had not
 * sent anything at all since the move to PHP 8: `mailAction()` read `APP_MAILFROM` as a bare
 * constant that never existed. The endpoint was removed with `000-000-0016`, and with it the nine
 * tests that recorded its broken behaviour.
 *
 * **This one remained, and that is intentional.** The guarantee "no test run sends mail" never
 * depended on `/api/mail`: `$app['mailer']` remains as a service, a project can send through it,
 * and `custom/app.php` is the template it builds that into. Removing a safeguard together with the
 * endpoint it happened to protect first would mean giving it up at the very moment nobody is
 * looking any more.
 *
 * `EnvironmentGuardTest` still requires `CONTENTFLY_TEST_MAIL_TRAP` under `CI`.
 * Setup: `tests/README.md`.
 */
class MailTrapTest extends IntegrationTestCase
{
    /**
     * The mail trap's directory, or `null` if none is set up.
     *
     * `CONTENTFLY_TEST_MAIL_TRAP` points to a directory with two files: `sendmail`, which the
     * test server uses as `sendmail_path`, and `outbox.log`, which that script writes to
     * instead of delivering. Without the variable, all tests that call the endpoint with a
     * recipient address are skipped — not waved through.
     *
     * Setup: `tests/README.md`.
     */
    private function trapDirectory(): ?string
    {
        $path = getenv('CONTENTFLY_TEST_MAIL_TRAP') ?: null;

        return $path !== null ? rtrim($path, '/') : null;
    }

    /** Number of bytes in the outbox; a missing file counts as empty. */
    private function outboxSize(string $directory): int
    {
        $log = $directory.'/outbox.log';
        clearstatcache(true, $log);

        return is_file($log) ? (int) filesize($log) : 0;
    }

    /**
     * Bails out if no mail trap is set up, and otherwise returns the directory and the outbox
     * size. Every test that sets `mailto` goes through here.
     */
    private function prepareMailTrap(): array
    {
        $directory = $this->trapDirectory();

        if ($directory === null) {
            $this->markTestSkipped(
                'CONTENTFLY_TEST_MAIL_TRAP not set — without proof that the '
                .'test server delivers nothing, this test is not run. '
                .'See tests/README.md.'
            );
        }

        return array($directory, $this->outboxSize($directory));
    }

    // ── The safeguard checks itself ────────────────────────────────────────────────────

    public function testMailTrapIsArmed(): void
    {
        // The test without which all following ones would be worthless: an outbox that stays
        // empty proves nothing as long as it is not established that anything could land in it
        // at all. That is why this test deliberately triggers the trap once.
        //
        // The call runs in a separate PHP process with the same sendmail_path that the test
        // server uses — the PHPUnit process itself does not have it.
        [$directory, $before] = $this->prepareMailTrap();

        $script = $directory.'/sendmail';
        $this->assertFileExists($script, 'The catch script sits next to the outbox');
        $this->assertTrue(is_executable($script), 'and is executable');

        $command = sprintf(
            '%s -d sendmail_path=%s -r %s 2>/dev/null',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($script),
            escapeshellarg('mail("trap@example.invalid", "Self-test", "Body");')
        );
        exec($command);

        $after = $this->outboxSize($directory);
        $this->assertGreaterThan($before, $after,
            'The trap catches — what the endpoint wanted to deliver landed here');

        // And the second half of the proof: that the **test server** uses this script and not the
        // real MTA. Without it the empty outbox would merely be circumstantial evidence — the
        // server could also have been started without the redirection.
        // The diagnostic path comes from tests/router.php and exists in no installation.
        [$diagnosticStatus, $raw] = $this->get('/__test/sendmail-path');
        $this->assertSame(200, $diagnosticStatus,
            'The test server runs through tests/router.php');

        // What is compared is the FILE, not the spelling: on macOS /tmp is a symlink to
        // /private/tmp, and whoever starts the server with one path and the suite with the other
        // still has the same script. A literal comparison would report an error here where there
        // is none — and one that is easily dismissed as "test broken".
        $reported = json_decode($raw, true)['sendmail_path'];

        $this->assertSame(
            realpath($script),
            realpath((string) $reported),
            sprintf(
                "The test server does not deliver through this catch script.\n"
                ."  expected: %s\n  reported: %s\n\n"
                ."Is an older test server perhaps still running on the same port?",
                $script,
                $reported
            )
        );

        // Cut the self-test back out, so that the following tests start from a clean
        // initial state.
        $log = $directory.'/outbox.log';
        file_put_contents($log, substr((string) file_get_contents($log), 0, $before));

        $this->assertSame($before, $this->outboxSize($directory),
            'and the initial state is restored');
    }
}
