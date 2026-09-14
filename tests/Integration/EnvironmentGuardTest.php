<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * The guard against the real risk of a pipeline: **a green suite that checked nothing.**
 *
 * `IntegrationTestCase::setUp()` skips cleanly when `CONTENTFLY_TEST_BASE_URL` is missing.
 * Locally that is exactly right — whoever wants to run the unit tests quickly should not fail on a
 * missing database. In a pipeline the same behaviour is a trap: if someone forgets a variable, the
 * test server dies or the installation fails, PHPUnit reports `OK, but some tests were skipped` —
 * and the job turns **green**.
 *
 * This class deliberately does **not** extend `IntegrationTestCase`. If it did, it would skip
 * itself under exactly the conditions it is meant to warn about.
 *
 * **The rule lives here and not in `.gitlab-ci.yml`.** Whoever runs the suite elsewhere — in
 * another CI, in a container, in the pipeline after epic `006` — takes it along without
 * reinventing it.
 *
 * ## What the guard does not protect against
 * It checks the **preconditions** of an integration run — environment variables, the test server,
 * the database and the mail trap — not every conceivable skip. If someone adds their own
 * `markTestSkipped()` to a test, that goes unnoticed here — catching it would need
 * `--fail-on-skipped` in the job, which would move the rule out of the suite again. The
 * preconditions cover the realistic failures: missing variable, dead server, failed installation.
 */
class EnvironmentGuardTest extends TestCase
{
    /**
     * The variables an integration run must have.
     *
     * @var array<string,string> name → why a run without it is worthless
     */
    private const REQUIRED = array(
        'CONTENTFLY_TEST_BASE_URL' =>
            'Without it IntegrationTestCase::setUp() skips EVERY integration test. '
            .'The suite then reports "OK, but some tests were skipped" — and the job turns green, '
            .'although nothing was checked.',
        'CONTENTFLY_TEST_MAIL_TRAP' =>
            'Without it MailTrapTest skips itself. The protection against real mail delivery is '
            .'then not proven. The endpoint that made it necessary was removed with '
            .'000-000-0016 — but $app[\'mailer\'] remains available to projects, and '
            .'custom/app.php is the template they build it into.',
        'CONTENTFLY_TEST_ADMIN_PASS' =>
            'Without it the default value "admin" applies, the login fails and every '
            .'integration test turns red — with the message "Login failed", which does not name '
            .'the cause. Required here so that nobody has to hunt for it.',
    );

    /**
     * Is the suite running in a pipeline?
     *
     * `CI` is set by GitLab, GitHub Actions and most others on their own. An explicit
     * `CI=false`, `CI=0` or an empty variable does not count.
     *
     * **This is also the back door:** whoever sets `CI=false` switches the guard off. That is
     * intentional — some local tools set `CI=false`, and a guard that fires on that would be a
     * nuisance rather than useful. In GitLab `CI` cannot be overridden, so the back door does not
     * apply there.
     */
    private function runsInPipeline(): bool
    {
        $value = getenv('CI');

        return $value !== false && !in_array(strtolower(trim((string) $value)), array('', '0', 'false', 'off'), true);
    }

    private function skipUnlessInPipeline(): void
    {
        if (!$this->runsInPipeline()) {
            $this->markTestSkipped(
                'Not a pipeline run (CI is not set) — locally, skipping the integration tests is '
                .'intended and this guard is therefore not responsible.'
            );
        }
    }

    // ── In the pipeline the preconditions are mandatory ────────────────────────────────

    /**
     * @dataProvider requiredVariables
     */
    public function testEnvironmentVariableIsSetInPipeline(string $name, string $reason): void
    {
        $this->skipUnlessInPipeline();

        $value = getenv($name);

        $this->assertNotFalse(
            $value,
            sprintf("%s is not set in this pipeline run.\n\n%s\n\nSetup: tests/README.md.", $name, $reason)
        );
        $this->assertNotSame(
            '',
            trim((string) $value),
            sprintf("%s is set, but empty.\n\n%s", $name, $reason)
        );
    }

    public static function requiredVariables(): array
    {
        $cases = array();

        foreach (self::REQUIRED as $name => $reason) {
            $cases[$name] = array($name, $reason);
        }

        return $cases;
    }

    // ── Being set is not enough: it also has to be right ───────────────────────────────

    public function testConfiguredBaseUrlMustAlsoRespond(): void
    {
        // The point at which a guard would otherwise lull you into a false sense of security: a
        // set variable says nothing about whether anything is running behind it. If the test
        // server dies between its start and the suite, CONTENTFLY_TEST_BASE_URL is still set —
        // and every integration test would turn red, but with a message about a single endpoint
        // instead of about the environment.
        //
        // This test deliberately runs locally TOO, as soon as the variable is set: a dead test
        // server is just as misleading on the development machine.
        $baseUrl = getenv('CONTENTFLY_TEST_BASE_URL');

        if ($baseUrl === false || trim((string) $baseUrl) === '') {
            $this->skipUnlessInPipeline();
            $this->fail('CONTENTFLY_TEST_BASE_URL is missing — see the test above.');
        }

        $raw = @file_get_contents(rtrim((string) $baseUrl, '/').'/api/config');

        $this->assertNotFalse(
            $raw,
            sprintf(
                "Nothing responds at %s.\n\n"
                ."The variable is set, but the test server is not reachable. A run in "
                ."this state turns every integration test red and never names the "
                ."actual cause.\n\nStarting the server: tests/README.md or "
                ."tools/ci/prepare-test-environment.sh.",
                $baseUrl
            )
        );

        $this->assertNotNull(
            json_decode((string) $raw, true),
            sprintf(
                "Something responds at %s/api/config, but it is not JSON.\n\n"
                ."That points to an unfinished installation — the application "
                ."then delivers an error page instead of the configuration.",
                $baseUrl
            )
        );
    }

    public function testTestDatabaseMustBeReachable(): void
    {
        // Added with 008-005-0004, after the case actually happened: while building
        // 008-005-0003 the database container was stopped. The suite then reported
        // **91 errors** — nothing but "PDOException: Connection refused" from individual tests,
        // and none of them said that the database was simply missing.
        //
        // Exactly the problem this guard was built against, just one layer deeper: the HTTP
        // server can be running and responding (/api/config does not need the database) while
        // the tests fail on their own connection.
        //
        // The credentials come from IntegrationTestCase::dbCredentials() — in exactly one
        // place, so that the guard never ends up checking a different database than the tests.
        $credentials = IntegrationTestCase::dbCredentials();

        try {
            $pdo = new \PDO($credentials['dsn'], $credentials['user'], $credentials['pass'],
                array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION));

            // Being connected is not enough — an empty database responds too. So the check is
            // for a table that only exists after the installation has been run.
            $tables = $pdo->query("SHOW TABLES LIKE 'pim_user'")->fetchAll(\PDO::FETCH_COLUMN);

            $this->assertNotEmpty($tables, sprintf(
                "The database %s on %s:%s responds, but contains no table pim_user.\n\n"
                ."That means: connected, but not installed. The integration tests would "
                ."fail one after another on missing tables without naming the cause.\n\n"
                ."Installation: an_project/docs/runbook.md, step 2.",
                $credentials['name'], $credentials['host'], $credentials['port']
            ));
        } catch (\PDOException $e) {
            $this->fail(sprintf(
                "The test database on %s:%s does not respond.\n\n%s\n\n"
                ."Without it every integration test fails on its own connection — each "
                ."with a PDOException that never says the database is missing.\n\n"
                ."Starting it: docker compose up -d (see an_project/docs/runbook.md).",
                $credentials['host'],
                $credentials['port'],
                $e->getMessage()
            ));
        }
    }

    public function testConfiguredMailTrapMustContainCatchScript(): void
    {
        // The same reasoning for the mail trap: the path can be set and point into the void.
        // MailTrapTest checks that as well — but only after it has triggered the catch script.
        // Here it shows up beforehand, and with a message about the environment instead of
        // about a script.
        $directory = getenv('CONTENTFLY_TEST_MAIL_TRAP');

        if ($directory === false || trim((string) $directory) === '') {
            $this->skipUnlessInPipeline();
            $this->fail('CONTENTFLY_TEST_MAIL_TRAP is missing — see the test further above.');
        }

        $script = rtrim((string) $directory, '/').'/sendmail';

        $this->assertFileExists(
            $script,
            sprintf(
                "The mail trap at %s has no catch script.\n\n"
                ."The path is set, the script is missing — then nothing is caught, and the "
                ."guarantee 'no test run sends mail' is not covered.",
                $directory
            )
        );
        $this->assertTrue(is_executable($script), 'The catch script is not executable.');
    }
}
