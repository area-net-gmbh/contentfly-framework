<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * The guardian of the guaranteed `$app[...]` keys (007-003-0002).
 *
 * ## Why it exists
 * The decision of 2026-09-11 guarantees `ArrayAccess` access permanently — and a guarantee
 * nobody checks is no guarantee. The most expensive finding from epic `006` was of this kind:
 * a condition everyone relied on without anyone checking it.
 *
 * ## It checks both directions, and the second one matters more
 * - **Every guaranteed key is present.** If one disappears, that is a break for every
 *   existing project.
 * - **Every registered key is classified** — guaranteed or explicitly internal.
 *   Without this direction the list would drift apart: a new key would be added, nobody
 *   would decide whether it is public, and a project would use it at its own risk without
 *   knowing.
 *
 * ## Why as a subprocess
 * The container only comes into existence once `bootstrap.php` has run — and that defines
 * constants, reads the configuration and builds half the application. Doing that in the
 * PHPUnit process would alter every test running afterwards. The subprocess is the same
 * solution `SystemControllerApiTest` and `AuthApiTest` use for their console calls.
 *
 * ## Why a database is required
 * Three of the guaranteed keys sit inside an `if ($app['is_installed'])`. Without an
 * installation they would consequently be absent, and the test could not make its most
 * important statement. It therefore extends `IntegrationTestCase` and skips cleanly when no
 * environment is available.
 */
class ContainerKeysTest extends IntegrationTestCase
{
    /**
     * Always available — regardless of whether the application is installed and whether
     * anyone is logged in.
     *
     * @var array<int,string>
     */
    private const ALWAYS = array(
        'is_installed', 'debug', 'database', 'mailer', 'routeManager', 'consoleManager',
        'request_stack', 'dispatcher', 'auth.user', 'loginProviders',
        // Always PRESENT, but null as long as the application is not installed — see
        // testEntityManagerIsPresentButNullUntilInstalled().
        'orm.em',
    );

    /**
     * Only once `appcms:install` has run.
     *
     * They sit inside an `if ($app['is_installed'])`, and that is intentional: `appcms:install`
     * itself must be able to run before a database exists.
     *
     * @var array<int,string>
     */
    private const AFTER_INSTALL = array('db', 'dbs');

    /**
     * Only after login — before that it does **not** exist, rather than being `null`.
     *
     * It is set in `BaseControllerProvider` once a request has authenticated.
     * In a console run it never exists, and that is exactly why it is not in `ALWAYS`.
     *
     * @var array<int,string>
     */
    private const AFTER_LOGIN = array('auth.token');

    /**
     * Internal wiring — exists, but is not guaranteed.
     *
     * @var array<int,string>
     */
    private const INTERNAL = array(
        'dbs.options', 'auth', 'console', 'helper', 'loginThrottle', 'tokenAuthenticator',
        'userProvisioning', 'groupMapping', 'tokenHandler', 'thumbnailSettings',
        'schema', 'typeManager', 'pluginManager',
        // The HttpKernel wiring from Classes/Kernel/Application — it belongs to the
        // application, not to the project.
        'kernel', 'resolver', 'argument_resolver',
    );

    public function testEveryGuaranteedKeyIsPresent(): void
    {
        $present = $this->containerKeys();

        foreach (self::ALWAYS as $key) {
            $this->assertContains(
                $key,
                $present,
                sprintf(
                    'The guaranteed key "%s" is missing from the container. That is a break for '
                    .'every existing project — see an_project/docs/dev-guide.md.',
                    $key
                )
            );
        }
    }

    /**
     * And the ones that depend on the installation — in **both** directions.
     *
     * **This came out of a mistake.** My first draft simply claimed that `db`, `dbs` and
     * `orm.em` were present. That only holds as long as `custom/config.php` happens to carry
     * the credentials of a test run — if it holds the shipped template, the application is not
     * installed, and the test turned red without anything being wrong with the framework.
     *
     * Now it asks for the state and checks the matching half. **The second half is the actual
     * guarantee:** on a fresh checkout these keys do *not* exist, and that is intentional —
     * `appcms:install` must be able to run before a database exists.
     */
    public function testTheDatabaseKeysDependOnTheInstallation(): void
    {
        $present   = $this->containerKeys();
        $installed = $this->isInstalled();

        foreach (self::AFTER_INSTALL as $key) {
            if ($installed) {
                $this->assertContains(
                    $key,
                    $present,
                    sprintf('The application is installed, but "%s" is missing.', $key)
                );

                continue;
            }

            $this->assertNotContains(
                $key,
                $present,
                sprintf(
                    'The application is NOT installed, but "%s" is present. Then '
                    .'appcms:install would run against a half-built container.',
                    $key
                )
            );
        }
    }

    /**
     * And the token does **not exist yet** — that is the guarantee, not its opposite.
     *
     * A project that reads `$app['auth.token']` outside an authenticated request gets an
     * exception and not a silent `null` value. That this stays so deserves to be recorded.
     */
    public function testTheTokenOnlyExistsAfterLogin(): void
    {
        $this->assertNotContains(
            'auth.token',
            $this->containerKeys(),
            'auth.token must NOT be in the freshly built container — it is only set '
            .'once a request has authenticated.'
        );
    }

    /**
     * **`orm.em` is always present — but `null` as long as the application is not installed.**
     *
     * The finding of this task, and it corrects the list from `007-003-0002`: there `orm.em`
     * was listed among the three that only exist after installation. That is wrong —
     * `bootstrap.php` has an `else` branch that sets it to `null`.
     *
     * **For a project, the difference is a trap.** "Missing" reports itself with an exception
     * that names the key. `null` reports itself with
     * *Call to a member function createQueryBuilder() on null* — a message about the method,
     * not about nothing being installed.
     *
     * The same applies to `auth.user`: present, but `null` as long as nobody is logged in.
     */
    public function testEntityManagerIsPresentButNullUntilInstalled(): void
    {
        $this->assertContains(
            'orm.em',
            $this->containerKeys(),
            'orm.em must always be present — even without installation, then as null.'
        );

        if ($this->isInstalled()) {
            $this->assertNotSame('NULL', $this->fromContainer('orm.em'),
                'The application is installed — then orm.em must not be null.');

            return;
        }

        $this->assertSame('NULL', $this->fromContainer('orm.em'),
            'Without installation orm.em is null, not absent — bootstrap.php sets it in the else branch.');
    }

    /**
     * Every registered key is classified.
     *
     * **The more important direction.** Without it the list would drift apart: a new key
     * would be added, nobody would decide whether it is public, and a project would use it at
     * its own risk without knowing.
     */
    public function testEveryRegisteredKeyIsClassified(): void
    {
        $classified = array_merge(
            self::ALWAYS,
            self::AFTER_INSTALL,
            self::AFTER_LOGIN,
            self::INTERNAL
        );

        if (!$this->isInstalled()) {
            $this->markTestSkipped(
                'Without installation the framework registers only part of its keys — '
                .'then this direction tells nothing.'
            );
        }

        $unknown = array_values(array_diff($this->registeredKeys(), $classified));

        sort($unknown);

        $this->assertSame(array(), $unknown, implode("\n", array_merge(
            array(
                'The framework registers these keys without anyone having decided',
                'whether they are public (007-003-0002):',
                '',
            ),
            $unknown,
            array(
                '',
                'Either add them to the list in the dev-guide and enter them here under ALWAYS or',
                'AFTER_INSTALL — or mark them as INTERNAL. Both are a decision;',
                'not making one is the only option that goes wrong.',
            )
        )));
    }

    /**
     * And the other way round: every classified key is actually registered.
     *
     * **The rule the gates from `006-005` rely on**, applied here: an entry that no longer
     * matches anything turns the run red. Without this direction a key would remain in the
     * list after it has disappeared from the framework — and a project would rely on a
     * guarantee pointing into the void.
     */
    public function testEveryClassifiedKeyIsActuallyRegistered(): void
    {
        if (!$this->isInstalled()) {
            $this->markTestSkipped(
                'Without installation the keys behind is_installed are missing, and the test '
                .'would wrongly report them as obsolete.'
            );
        }

        $registered = $this->registeredKeys();

        $dead = array();

        foreach (array(
            'ALWAYS'        => self::ALWAYS,
            'AFTER_INSTALL' => self::AFTER_INSTALL,
            'AFTER_LOGIN'   => self::AFTER_LOGIN,
            'INTERNAL'      => self::INTERNAL,
        ) as $list => $keys) {
            foreach ($keys as $key) {
                if (!in_array($key, $registered, true)) {
                    $dead[] = sprintf('%s: %s', $list, $key);
                }
            }
        }

        sort($dead);

        $this->assertSame(array(), $dead, implode("
", array_merge(
            array(
                'These keys are in a list but are no longer registered anywhere.',
                'A guarantee pointing into the void is worse than none (007-003-0002):',
                '',
            ),
            $dead
        )));
    }

    /**
     * And the lists here match the `dev-guide`.
     *
     * They live in two places, and two places drift apart. The same rule as for the gates
     * from `006-005`: an entry that no longer matches anything turns the run red.
     */
    public function testTheListsMatchTheDevGuide(): void
    {
        $docs = (string) file_get_contents(
            dirname(__DIR__, 2) . '/an_project/docs/dev-guide.md'
        );

        foreach (array_merge(self::ALWAYS, self::AFTER_INSTALL, self::AFTER_LOGIN) as $key) {
            $this->assertStringContainsString(
                '`' . $key . '`',
                $docs,
                sprintf('The guaranteed key "%s" is not in the dev-guide.', $key)
            );
        }

        foreach (self::INTERNAL as $key) {
            $this->assertStringContainsString(
                '`' . $key . '`',
                $docs,
                sprintf('The internal key "%s" is not named as such in the dev-guide.', $key)
            );
        }
    }

    /**
     * Is the application installed?
     *
     * The container answers this itself — `is_installed` is one of the guaranteed keys and
     * always present.
     */
    private function isInstalled(): bool
    {
        // var_export(true, true) is 'true' — not '1'. My first comparison was against '1'
        // and thus considered EVERY state "not installed" (007-003-0003).
        return $this->fromContainer('is_installed') === 'true';
    }

    /**
     * All keys the framework **registers**.
     *
     * **Two sources, and each answers what the other cannot.**
     *
     * `Container::keys()` returns what was actually registered during the build — the more
     * reliable answer, because it sees the executed code and needs no search pattern.
     * *(Added with `007-003-0003`: in `0002` I had parsed the source code instead, without
     * noticing that the container can enumerate itself. `tests/Unit/Kernel/ContainerTest.php`
     * has tested the method since `008-004`.)*
     *
     * The source code stays as a second source, because `keys()` only sees what is created
     * **during the build**. `auth.token` is only set once a request has authenticated — it
     * does not exist in the built container, and without the second source it would fall
     * out of the classification.
     *
     * @return array<int,string>
     */
    private function registeredKeys(): array
    {
        $root  = dirname(__DIR__, 2);
        $found = array();

        // What is created during the build — the reliable source.
        foreach ($this->containerKeys(true) as $key) {
            $found[$key] = true;
        }

        // And what is added later: auth.token is only set after login.
        $sources = array(
            $root . '/lib/contentfly/Classes/Controller/Provider/BaseControllerProvider.php',
        );

        foreach ($sources as $path) {
            $this->assertFileExists($path);

            $content = (string) file_get_contents($path);

            // `$app['x'] = …` in the bootstrap, `$this['x'] = …` in the application constructor.
            preg_match_all(
                '/(?:\$app|\$this)\[\x27([a-zA-Z0-9_.]+)\x27\]\s*=[^=]/',
                $content,
                $matches
            );

            foreach ($matches[1] as $key) {
                $found[$key] = true;
            }
        }

        $this->assertNotEmpty($found, 'Not a single key was found — then this test checks nothing.');

        /*
         * ROUTE CONTROLLERS ARE NOT KEYS TO CLASSIFY (007-003-0003).
         *
         * Every mounted controller gets an entry `<prefix>.controller` — the framework
         * creates `api.controller`, `auth.controller`, `file.controller` and
         * `system.controller`, and a project creates another one for each of its own routes.
         * The template thus produces `api/v1/example/.controller`.
         *
         * They are wiring of the ControllerResolver, not a service anyone reads, and their
         * names depend on the project's routes. A list could not keep track of them.
         *
         * keys() FOUND THEM: the source parser from 007-003-0002 did not see them, because
         * they are created at runtime. That is why this source is the better one.
         */
        $keys = array_filter(
            array_keys($found),
            static fn (string $name): bool => !str_ends_with($name, '.controller')
        );

        return array_values($keys);
    }

    /**
     * Fetch a single value from the built container, as a string.
     *
     * `var_export` form, so that `null` can be told apart from `''` and from `false` — which
     * is exactly what matters for `orm.em` and `auth.user`.
     */
    private function fromContainer(string $key): string
    {
        $project = self::applicationDir();

        $script = <<<'PHP'
$app = \Areanet\PIM\Classes\Kernel\Start::console($argv[1]);
echo isset($app[$argv[2]]) ? var_export($app[$argv[2]], true) : '__MISSING__';
PHP;

        $command = sprintf(
            '%s -r %s %s %s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg('require ' . var_export($project . '/vendor/autoload.php', true) . '; ' . $script),
            escapeshellarg($project),
            escapeshellarg($key)
        );

        $output = array();
        $code   = 0;
        exec($command, $output, $code);

        $raw = trim(implode("\n", $output));

        $this->assertSame(0, $code, "The container could not be built:\n" . $raw);

        return $raw;
    }

    /**
     * The keys of the built container — queried in a separate process.
     *
     * @return array<int,string>
     */
    private function containerKeys(bool $all = false): array
    {
        $project = self::applicationDir();

        $script = <<<'PHP'
$app = \Areanet\PIM\Classes\Kernel\Start::console($argv[1]);

if ($argv[2] === '*') {
    echo implode(',', $app->keys());
    return;
}

$found = [];
foreach ($argv[2] === '' ? [] : explode(',', $argv[2]) as $key) {
    if (isset($app[$key])) {
        $found[] = $key;
    }
}
echo implode(',', $found);
PHP;

        $candidates = $all ? array('*') : array_merge(
            self::ALWAYS,
            self::AFTER_INSTALL,
            self::AFTER_LOGIN,
            self::INTERNAL
        );

        $command = sprintf(
            '%s -r %s %s %s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg('require ' . var_export($project . '/vendor/autoload.php', true) . '; ' . $script),
            escapeshellarg($project),
            escapeshellarg(implode(',', $candidates))
        );

        $output = array();
        $code   = 0;
        exec($command, $output, $code);

        $raw = trim(implode("\n", $output));

        $this->assertSame(
            0,
            $code,
            "The container could not be built:\n" . $raw
        );

        return $raw === '' ? array() : explode(',', $raw);
    }
}
