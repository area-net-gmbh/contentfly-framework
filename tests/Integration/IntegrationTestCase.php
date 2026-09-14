<?php
namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Base for all integration tests: HTTP against a running, installed instance.
 *
 * Bundles what `AuthApiTest` and `FileApiTest` each brought along on their own until story
 * `008-001` — skip logic, login, HTTP helpers. Epic `008` adds more test files; without this base
 * each of them would be the next copy of the same four methods.
 *
 * Preconditions (otherwise the test is skipped):
 * - `CONTENTFLY_TEST_BASE_URL` points to a running, installed instance
 * - `CONTENTFLY_TEST_ADMIN_PASS` is the password of the user `admin`
 *
 * See `tests/README.md`.
 *
 * **This file comes through the autoloader** — `composer.json` maps `Tests\` to `tests/` in
 * `autoload-dev`. Until `006-004-0004` that was different: `tests/bootstrap.php` loaded it via
 * `require_once`, because the mapping lived in the **project** manifest and pointed to
 * `Custom\Tests\`, a namespace no test file ever used. PHPUnit itself only loads files ending in
 * `Test.php` — so not this one.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected static ?string $baseUrl = null;

    /** Logged-in token, once per test class. */
    private static ?string $cachedToken = null;

    private static ?PDO $pdo = null;

    /** @var array<int, array{0:string,1:string}> Table and id that tearDown() removes. */
    private array $cleanup = array();

    /** @var array<int, string> Directories that tearDown() removes including their contents. */
    private array $directories = array();

    public static function setUpBeforeClass(): void
    {
        self::$baseUrl     = getenv('CONTENTFLY_TEST_BASE_URL') ?: null;
        self::$cachedToken = null;
    }

    protected function setUp(): void
    {
        if (self::$baseUrl === null) {
            $this->markTestSkipped('CONTENTFLY_TEST_BASE_URL not set — integration tests skipped.');
        }
    }

    /**
     * Removes what the test registered through deleteAfterTest() — in reverse order, so that
     * dependent rows disappear before their targets.
     */
    protected function tearDown(): void
    {
        $this->clearThrottleStorage();

        foreach (array_reverse($this->cleanup) as [$table, $id]) {
            $stmt = $this->pdo()->prepare("DELETE FROM `$table` WHERE id = :id");
            $stmt->execute(array('id' => $id));
        }

        foreach ($this->directories as $path) {
            $this->removeDirectory($path);
        }

        $this->cleanup     = array();
        $this->directories = array();
    }

    /**
     * Clears the storage of the LoginThrottle (013-003-0003).
     *
     * THE SUITE IS NOT A REALISTIC CLIENT. Within a few seconds it produces more failed attempts than
     * an address may make per minute — wrong passwords, unknown identifiers, invalid refresh tokens,
     * and all of it from 127.0.0.1. Without cleaning up, the order of the test classes decides which
     * one still gets through: on the first run with the refresh tests 87 tests were red, all with
     * "Too many login attempts".
     *
     * THIS WEAKENS NOTHING. `AnmeldebremseApiTest` measures the throttle within ONE test method; what
     * is cleared here between two methods never carried a statement there.
     *
     * Silently, if the directory is not reachable: then the test server runs elsewhere, and that is
     * the case `AnmeldebremseApiTest` catches with a stricter check of its own.
     */
    protected function clearThrottleStorage(): void
    {
        $data = self::dataDir();

        if (is_dir($data.'/cache')) {
            $this->removeDirectory($data.'/cache/login-throttle');
        }
    }

    /**
     * The directory of **the application** this run checks against (007-001-0005).
     *
     * **So far this was always the suite's own tree** — `dirname(__DIR__, 2)`. That is correct exactly
     * as long as test run and application live in the same tree.
     *
     * Since `007-001` they no longer have to: a project pulls in the framework as a package, and the
     * suite can run against an installation that lives elsewhere. On the first such run **127 tests
     * were red** with "Too many login attempts" — the suite cleared the throttle storage of its own
     * tree, the application wrote into its own. **And it went wrong silently:** the own `data/cache`
     * does exist, so the condition applied, the cleanup ran, and it cleaned up the wrong thing. The
     * message was about a login afterwards, not about a directory.
     *
     * After the fix **four** stayed red, with the same assumption elsewhere: tests that call
     * `bin/console.php` called the one of the development repository — whose `custom/config.php` is the
     * template without credentials, so `$app['orm.em']` was null.
     *
     * **That is why there is ONE setting and not two.** `CONTENTFLY_TEST_PROJECT_DIR` says where the
     * application lives; data directory and console depend on it. Two variables could drift apart,
     * and then a run would check two different installations without noticing.
     *
     * Without the variable it stays the suite's own tree — the normal case in which both are the same,
     * and the only one the pipeline knows.
     */
    public static function applicationDir(): string
    {
        $setting = getenv('CONTENTFLY_TEST_PROJECT_DIR');

        if (!is_string($setting) || $setting === '') {
            return dirname(__DIR__, 2);
        }

        $resolved = realpath($setting);

        if ($resolved === false || !is_dir($resolved)) {
            throw new \RuntimeException(sprintf(
                'CONTENTFLY_TEST_PROJECT_DIR points to "%s" — that is not a directory. A wrong value '
                .'would be worse than none: the run would then clean up the wrong thing and call the '
                .'wrong console without reporting it (007-001-0005).',
                $setting
            ));
        }

        return $resolved;
    }

    /** The application's `data/` directory. */
    public static function dataDir(): string
    {
        return self::applicationDir() . '/data';
    }

    /** The application's console — not the one of the development repository. */
    public static function console(): string
    {
        return self::applicationDir() . '/bin/console.php';
    }

    // ── Login ──────────────────────────────────────────────────────────────────────────

    protected function pass(): string
    {
        return getenv('CONTENTFLY_TEST_ADMIN_PASS') ?: 'admin';
    }

    /** Logs in anew and returns a fresh token. */
    protected function login(): string
    {
        [, $body] = $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => $this->pass()));

        if (!isset($body['token'])) {
            $this->fail('Login failed: '.json_encode($body));
        }

        return $body['token'];
    }

    /**
     * Returns a token and keeps it for the test class.
     *
     * For tests that only need *some* valid token. Whoever checks the login itself — or invalidates
     * a token — uses login().
     */
    protected function token(): string
    {
        if (self::$cachedToken === null) {
            self::$cachedToken = $this->login();
        }

        return self::$cachedToken;
    }

    // ── HTTP ───────────────────────────────────────────────────────────────────────────

    /** @return array{0:int,1:array,2:string} status, body as array, headers */
    protected function postJson(string $path, array $data, ?string $token = null): array
    {
        $headers = array('Content-Type: application/json');
        if ($token !== null) {
            $headers[] = 'appcms-token: '.$token;
        }

        $ch = curl_init(self::$baseUrl.$path);
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => $headers,
        ));

        return $this->evaluate($ch, true);
    }

    /** @return array{0:int,1:string,2:string} status, body, headers */
    protected function get(string $path, ?string $token = null): array
    {
        $ch = curl_init(self::$baseUrl.$path);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_HTTPHEADER     => $token !== null ? array('appcms-token: '.$token) : array(),
        ));

        return $this->evaluate($ch, false);
    }

    /** Reads one header from the raw headers of a response. */
    protected function header(string $headers, string $name): ?string
    {
        if (preg_match('/^'.preg_quote($name, '/').':\s*(.+)$/mi', $headers, $match)) {
            return trim($match[1]);
        }

        return null;
    }

    /** @return array{0:int,1:array|string,2:string} */
    private function evaluate($ch, bool $asJson): array
    {
        $response   = (string) curl_exec($ch);
        $status     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $headers = substr($response, 0, $headerSize);
        $body    = substr($response, $headerSize);

        return array($status, $asJson ? (json_decode($body, true) ?: array()) : $body, $headers);
    }

    // ── Test data ──────────────────────────────────────────────────────────────────────

    /**
     * Connection to the test database.
     *
     * Test data is created deliberately **bypassing the API**: a read test whose precondition runs
     * through the write path it does not check itself loses its meaning — and story `008-001` is
     * explicitly not supposed to depend on `008-002`.
     *
     * The credentials come from the environment; the defaults match the `docker-compose.yml` from
     * the runbook.
     */
    protected function pdo(): PDO
    {
        if (self::$pdo === null) {
            $credentials = self::dbCredentials();

            self::$pdo = new PDO(
                $credentials['dsn'],
                $credentials['user'],
                $credentials['pass'],
                array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
            );
        }

        return self::$pdo;
    }

    /**
     * The connection data of the test database — in exactly **one** place.
     *
     * Public and static because `EnvironmentGuardTest` needs it as well: it checks before the actual
     * run whether the database answers at all, but deliberately does not extend this class. If the
     * defaults were written there a second time, they would drift apart — and the guard would
     * eventually check a different database than the tests.
     *
     * The defaults match the `docker-compose.yml` from the runbook.
     *
     * @return array{dsn:string,user:string,pass:string,host:string,port:string,name:string}
     */
    public static function dbCredentials(): array
    {
        $host = getenv('CONTENTFLY_TEST_DB_HOST') ?: '127.0.0.1';
        $port = getenv('CONTENTFLY_TEST_DB_PORT') ?: '3307';
        $name = getenv('CONTENTFLY_TEST_DB_NAME') ?: 'contentfly';
        $user = getenv('CONTENTFLY_TEST_DB_USER') ?: 'contentfly';
        $pass = getenv('CONTENTFLY_TEST_DB_PASSWORD') ?: 'contentfly';

        return array(
            'dsn'  => "mysql:host=$host;port=$port;dbname=$name;charset=utf8",
            'user' => $user,
            'pass' => $pass,
            'host' => $host,
            'port' => $port,
            'name' => $name,
        );
    }

    /**
     * Registers a row for cleanup. tearDown() removes it even if the test fails — otherwise a red
     * test taints the following ones.
     */
    protected function deleteAfterTest(string $table, string $id): void
    {
        $this->cleanup[] = array($table, $id);
    }

    /**
     * Registers a directory for cleanup — including its contents.
     *
     * For tests that leave files on disk. Without this `data/files` grows with every run, and a fresh
     * checkout behaves differently from a grown environment — exactly the difference a test net must
     * not have (`000-000-0008`).
     */
    protected function deleteDirectoryAfterTest(string $path): void
    {
        $this->directories[] = $path;
    }

    /**
     * Removes a directory including its contents; a missing one is not an error.
     *
     * Protected since 013-001-0003: AnmeldebremseApiTest uses it to clear the throttle's storage.
     */
    protected function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: array() as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $path.'/'.$entry;
            is_dir($full) ? $this->removeDirectory($full) : @unlink($full);
        }

        @rmdir($path);
    }

    // ── Test users with permissions ────────────────────────────────────────────────────

    /** Password of all accounts created through createTestUser(). */
    protected const TEST_PASSWORD = 'test-only-for-this-run';

    /**
     * Creates a group, a non-admin user in it and that user's entity permissions, and logs in as
     * this user.
     *
     * `$permissions` maps an entity short name to permissions, for instance:
     *
     *     $this->createTestUser(array(
     *         'PIM\Tag' => array('readable' => Permission::ALL, 'writable' => Permission::OWN),
     *     ));
     *
     * Missing keys are `Permission::NONE`. **Pass the levels as constants, not as numbers:** they are
     * not in ascending order — `OWN` is 1, `ALL` is 2 and `GROUP` is 3. Whoever reads them as a
     * ranking is mistaken.
     *
     * The password is hashed the way `User::setPass()` used to: sha256 of password and salt.
     * Everything created registers for cleanup, in an order that respects the foreign keys on
     * `pim_group`.
     *
     * @param array<string, array<string,int>> $permissions
     * @param array<string,mixed>              $group        Additional group fields,
     *                                                       for instance apiQueryEnabled or languages
     * @return array{0:string,1:string,2:string} token, user id, group id
     */
    protected function createTestUser(array $permissions = array(), array $group = array()): array
    {
        $run     = bin2hex(random_bytes(6));
        $groupId = 'tgrp-'.$run;
        $userId  = 'tusr-'.$run;
        $alias   = 'testuser-'.$run;
        $salt    = bin2hex(random_bytes(16));

        $this->pdo()->prepare(
            'INSERT INTO pim_group (id, name, tokenTimeout, apiQueryEnabled, languages,
                                    created, modified, views, isIntern)
             VALUES (:id, :name, 60, :ape, :languages, NOW(), NOW(), 0, 0)'
        )->execute(array(
            'id'        => $groupId,
            'name'      => 'Test group '.$run,
            'ape'       => $group['apiQueryEnabled'] ?? 'disabled',
            'languages' => $group['languages'] ?? null,
        ));
        $this->deleteAfterTest('pim_group', $groupId);

        $this->pdo()->prepare(
            'INSERT INTO pim_user (id, isAdmin, alias, pass, isActive, salt,
                                   created, modified, views, isIntern, group_id)
             VALUES (:id, 0, :alias, :pass, 1, :salt, NOW(), NOW(), 0, 0, :grp)'
        )->execute(array(
            'id'    => $userId,
            'alias' => $alias,
            'pass'  => hash('sha256', self::TEST_PASSWORD.$salt),
            'salt'  => $salt,
            'grp'   => $groupId,
        ));
        $this->deleteAfterTest('pim_user', $userId);

        $number = 0;
        foreach ($permissions as $entity => $levels) {
            $permissionId = 'tperm-'.$run.'-'.($number++);

            $this->pdo()->prepare(
                'INSERT INTO pim_permission (id, entityName, readable, writable, deletable,
                                             export, extended, created, modified, views,
                                             isIntern, group_id)
                 VALUES (:id, :entity, :readable, :writable, :deletable, :export, :extended,
                         NOW(), NOW(), 0, 0, :grp)'
            )->execute(array(
                'id'        => $permissionId,
                'entity'    => $entity,
                'readable'  => $levels['readable']  ?? 0,
                'writable'  => $levels['writable']  ?? 0,
                'deletable' => $levels['deletable'] ?? 0,
                'export'    => $levels['export']    ?? 0,
                'extended'  => $levels['extended']  ?? null,
                'grp'       => $groupId,
            ));
            $this->deleteAfterTest('pim_permission', $permissionId);
        }

        [, $body] = $this->postJson('/auth/login', array('alias' => $alias, 'pass' => self::TEST_PASSWORD));

        if (!isset($body['token'])) {
            $this->fail('Login of the test user failed: '.json_encode($body));
        }

        return array($body['token'], $userId, $groupId);
    }
}
