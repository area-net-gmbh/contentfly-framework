<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * The event after a successful login, end-to-end (000-000-0045).
 *
 * A provider only verifies; what an old LoginManager did beyond that — set fields on the user, give
 * the client extra data — needs a place. `pim.auth.after.login` is that place. Found on the existing
 * project UFP (007-005-0004), whose app reads `data.role` on every login.
 *
 * The listener under test is the one in the template `custom/app.php`: it sets `tempData` to the
 * provider name and the groups the external system reported. Over HTTP against the running instance,
 * like `LoginProviderApiTest` — an application built inside the test process would prove a different
 * construction than the production one.
 */
class LoginEventApiTest extends IntegrationTestCase
{
    public function testThePasswordPathFiresTheEventWithoutProviderOrIdentity(): void
    {
        [$status, $body] = $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => $this->pass()));

        $this->assertSame(200, $status);
        $this->assertSame(array('loginProvider' => null, 'externalGroups' => array()), $body['data'] ?? null,
            'The listener ran, and it saw neither a provider nor an identity');
    }

    public function testTheProviderPathHandsTheListenerNameAndIdentity(): void
    {
        $entry = $this->providerEntry();

        [$status, $body] = $this->postJson('/auth/login', array(
            'alias'        => $entry['identifier'],
            'pass'         => $entry['secret'],
            'loginManager' => 'example',
        ));
        $this->removeProvisionedUser($entry['identifier']);

        $this->assertSame(200, $status, json_encode($body));
        $this->assertSame(array('loginProvider' => 'example', 'externalGroups' => $entry['groups']), $body['data'] ?? null,
            'The listener saw the registered name and the groups of the ExternalIdentity');
    }

    public function testAJwtLoginCarriesTheListenersDataToo(): void
    {
        [$status, $body] = $this->postJson('/auth/login', array(
            'alias'     => 'admin',
            'pass'      => $this->pass(),
            'tokenType' => 'jwt',
        ));

        if ($status === 500) {
            $this->markTestSkipped('JWTs are not configured on the test instance.');
        }

        $this->assertSame(200, $status);
        $this->assertArrayHasKey('refreshToken', $body);
        $this->assertSame(array('loginProvider' => null, 'externalGroups' => array()), $body['data'] ?? null);
    }

    public function testARejectedLoginDoesNotFireTheEvent(): void
    {
        [$status, $body] = $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => 'wrong'));
        $this->assertSame(401, $status);
        $this->assertArrayNotHasKey('data', $body, 'Wrong password: no listener output');

        $entry = $this->providerEntry();
        [$status, $body] = $this->postJson('/auth/login', array(
            'alias'        => $entry['identifier'],
            'pass'         => 'wrong',
            'loginManager' => 'example',
        ));
        $this->removeProvisionedUser($entry['identifier']);

        $this->assertSame(401, $status);
        $this->assertArrayNotHasKey('data', $body, 'Provider rejected: no listener output');
    }

    /** @return array{identifier: string, secret: string, groups: list<string>} */
    private function providerEntry(): array
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

    private function removeProvisionedUser(string $identifier): void
    {
        $row = $this->pdo()->prepare('SELECT id FROM pim_user WHERE loginManager = :lm AND externalId = :ext');
        $row->execute(array('lm' => 'example', 'ext' => $identifier));

        if ($id = $row->fetchColumn()) {
            $this->deleteAfterTest('pim_user', (string) $id);
        }
    }
}
