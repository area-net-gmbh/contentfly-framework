<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * The whole path through a LoginProvider, end-to-end (`013-004-0004`).
 *
 * `013-004-0001` to `0003` measured the contract, provisioning and GroupMapping each on their
 * own. **Only a provider that actually runs shows that they fit together** — and until this
 * point there was none: `custom/Classes/` contained two service classes and no provider.
 *
 * The template checks against `CONTENTFLY_EXAMPLE_PROVIDER`. The test server receives the variable
 * from `CONTENTFLY_TEST_PROVIDER` (see `tools/ci/prepare-test-environment.sh`); the same
 * string is available here so the test knows which identifier it may present.
 */
class LoginProviderApiTest extends IntegrationTestCase
{
    private function entry(): array
    {
        $raw = getenv('CONTENTFLY_TEST_PROVIDER') ?: '';

        if ($raw === '') {
            $this->markTestSkipped('CONTENTFLY_TEST_PROVIDER not set — see tests/README.md.');
        }

        $parts = explode(':', explode(',', $raw)[0]);

        return array(
            'identifier' => $parts[0],
            'secret'     => $parts[1] ?? '',
            'groups'     => isset($parts[2]) ? explode('|', $parts[2]) : array(),
        );
    }

    /** Cleans up the user created through the provider after the test. */
    private function loginThroughProvider(?string $identifier = null, ?string $secret = null): array
    {
        $entry = $this->entry();

        [$status, $body] = $this->postJson('/auth/login', array(
            'alias'        => $identifier ?? $entry['identifier'],
            'pass'         => $secret ?? $entry['secret'],
            'loginManager' => 'example',
        ));

        $row = $this->pdo()->prepare('SELECT id FROM pim_user WHERE loginManager = :lm AND externalId = :ext');
        $row->execute(array('lm' => 'example', 'ext' => $identifier ?? $entry['identifier']));

        if ($id = $row->fetchColumn()) {
            $this->deleteAfterTest('pim_user', (string) $id);
        }

        return array($status, $body);
    }

    // ── The full path ──────────────────────────────────────────────────────────────────

    public function testLoginThroughTheProviderReturnsAToken(): void
    {
        [$status, $body] = $this->loginThroughProvider();

        $this->assertSame(200, $status, 'Response: '.json_encode($body));

        $session = $this->assertEnvelope($body); // 011-001-0004
        $this->assertArrayHasKey('token', $session);

        $this->assertSame(200, $this->getWithToken('/api/schema', $session['token']),
            'The token opens a protected route — the same path as with a password login');
    }

    /**
     * **The user is created on the first login, and their password is locked.**
     *
     * Finding A-6 on the running system: Previously `createManagedUser()` set the user name as the
     * password.
     */
    public function testTheUserIsCreatedWithALockedPassword(): void
    {
        $entry = $this->entry();

        $this->loginThroughProvider();

        $row = $this->pdo()->prepare(
            'SELECT alias, pass, externalId, loginManager FROM pim_user
             WHERE loginManager = :lm AND externalId = :ext'
        );
        $row->execute(array('lm' => 'example', 'ext' => $entry['identifier']));
        $found = $row->fetch(\PDO::FETCH_ASSOC);

        $this->assertIsArray($found, 'The row has been created');
        $this->assertSame('*', $found['pass'], 'Locked, not the user name');
        $this->assertSame($entry['identifier'], $found['externalId'], 'The identifier is stored readably');
        $this->assertSame('example:'.$entry['identifier'], $found['alias'], 'No MD5 prefix');
    }

    /**
     * And the check on it: Nobody gets in with the alias or the identifier as the password.
     */
    public function testTheCreatedUserHasNoGuessablePassword(): void
    {
        $entry = $this->entry();
        $this->loginThroughProvider();

        foreach (array($entry['identifier'], 'example:'.$entry['identifier'], '*') as $attempt) {
            [$status] = $this->postJson('/auth/login', array(
                'alias' => 'example:'.$entry['identifier'],
                'pass'  => $attempt,
            ));

            $this->assertSame(401, $status, 'Attempt with "'.$attempt.'"');
        }
    }

    public function testASecondLoginDoesNotCreateASecondUser(): void
    {
        $entry = $this->entry();

        $this->loginThroughProvider();
        $this->loginThroughProvider();

        $count = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM pim_user WHERE loginManager = :lm AND externalId = :ext'
        );
        $count->execute(array('lm' => 'example', 'ext' => $entry['identifier']));

        $this->assertSame('1', (string) $count->fetchColumn());
    }

    // ── Rejections ─────────────────────────────────────────────────────────────────────

    public function testAWrongSecretIsRejected(): void
    {
        [$status, $body] = $this->loginThroughProvider(null, 'wrong');

        $this->assertSame(401, $status);
        $this->assertArrayNotHasKey('token', $body);
    }

    public function testAnUnknownIdentifierIsRejected(): void
    {
        [$status, $body] = $this->loginThroughProvider('doesnotexist-'.bin2hex(random_bytes(4)), 'whatever');

        $this->assertSame(401, $status);
        $this->assertArrayNotHasKey('token', $body);
    }

    /**
     * **Without configuration the template lets nobody in.**
     *
     * Measured on the class itself and not over HTTP: The test server has the variable
     * set, and restarting it for this would mean halting half the suite. The class is
     * the same one that runs there, and the list comes from the same line.
     */
    public function testWithoutConfigurationTheTemplateLetsNobodyIn(): void
    {
        $before = getenv(\Custom\Classes\Authentication\ExampleProvider::ENVIRONMENT_VARIABLE);

        putenv(\Custom\Classes\Authentication\ExampleProvider::ENVIRONMENT_VARIABLE);
        unset($_ENV[\Custom\Classes\Authentication\ExampleProvider::ENVIRONMENT_VARIABLE]);

        try {
            $provider = new \Custom\Classes\Authentication\ExampleProvider();
            $entry    = $this->entry();

            $request = new \Symfony\Component\HttpFoundation\Request(
                array(), array('alias' => $entry['identifier'], 'pass' => $entry['secret'])
            );

            $this->assertNull($provider->authenticate($request));
        } finally {
            if ($before !== false) {
                putenv(\Custom\Classes\Authentication\ExampleProvider::ENVIRONMENT_VARIABLE.'='.$before);
                $_ENV[\Custom\Classes\Authentication\ExampleProvider::ENVIRONMENT_VARIABLE] = $before;
            }
        }
    }

    // ── The GroupMapping, end-to-end ───────────────────────────────────────────────────

    /**
     * The groups the provider delivers end up in `pim_user.group_id` via
     * `SECURITY_PROVIDER_GROUPS` — or they don't.
     *
     * **The test installation configures no mapping**, and that is exactly the guarantee
     * here: Without an entry, a user created through an external system gets **no**
     * group and **no** admin rights. A mapping that grants rights when in doubt would be the
     * wrong direction.
     */
    public function testWithoutMappingThereIsNeitherGroupNorAdminRights(): void
    {
        $entry = $this->entry();
        $this->assertNotSame(array(), $entry['groups'], 'The provider delivers groups');

        $this->loginThroughProvider();

        $row = $this->pdo()->prepare(
            'SELECT group_id, isAdmin FROM pim_user WHERE loginManager = :lm AND externalId = :ext'
        );
        $row->execute(array('lm' => 'example', 'ext' => $entry['identifier']));
        $found = $row->fetch(\PDO::FETCH_ASSOC);

        $this->assertNull($found['group_id'], 'Without mapping no group');
        $this->assertSame('0', (string) $found['isAdmin'], 'And certainly no admin rights');
    }

    /** A GET with the token as `appcms-token`, only the status code. */
    private function getWithToken(string $path, string $token): int
    {
        [$status] = $this->get($path, $token);

        return $status;
    }
}
