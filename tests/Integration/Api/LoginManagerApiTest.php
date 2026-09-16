<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * Characterization tests for the observable effect of the `LoginManager`.
 *
 * **Why there is no direct test of `createManagedUser()`:** The method needs an
 * EntityManager. Building it inside the test process would mean constructing the application a
 * **second time and differently** than `lib/contentfly/bootstrap.php` does — with its own
 * annotation registration and its own metadata configuration. Such a test would prove
 * that *this* construction works, not the production one. It could drift away from it and
 * stay green while doing so.
 *
 * Instead, this records what a `LoginManager` does from the outside: A user
 * assigned to one **can no longer log in with a password**. That is the
 * guarantee that matters, and it can be checked over HTTP.
 */
class LoginManagerApiTest extends IntegrationTestCase
{
    private function createUser(string $alias, ?string $loginManager, ?string $externalId = null): string
    {
        $id   = 'lm-'.bin2hex(random_bytes(6));
        $salt = bin2hex(random_bytes(16));

        $this->pdo()->prepare(
            'INSERT INTO pim_user (id, isAdmin, alias, pass, isActive, salt, loginManager,
                                   externalId, created, modified, views, isIntern)
             VALUES (:id, 0, :alias, :pass, 1, :salt, :lm, :ext, NOW(), NOW(), 0, 0)'
        )->execute(array(
            'id'    => $id,
            'alias' => $alias,
            'pass'  => hash('sha256', self::TEST_PASSWORD.$salt),
            'salt'  => $salt,
            'lm'    => $loginManager,
            'ext'   => $externalId,
        ));

        $this->deleteAfterTest('pim_user', $id);

        return $id;
    }

    public function testAUserWithoutLoginManagerLogsInWithPassword(): void
    {
        $alias = 'lm-free-'.bin2hex(random_bytes(4));
        $this->createUser($alias, null);

        [$status, $body] = $this->postJson('/auth/login', array('alias' => $alias, 'pass' => self::TEST_PASSWORD));

        $this->assertSame(200, $status);
        $this->assertArrayHasKey('token', $this->assertEnvelope($body)); // 011-001-0004
    }

    public function testAUserWithLoginManagerCannotLogInWithPassword(): void
    {
        // The actual guarantee: If a LoginManager is assigned to a user, logging in
        // only works through that path — not even with the correct password.
        // createManagedUser() sets exactly this field to get_class($this).
        $alias = 'lm-managed-'.bin2hex(random_bytes(4));
        $this->createUser($alias, 'Custom\\Classes\\LoginManager\\Example');

        [$status, $body] = $this->postJson('/auth/login', array('alias' => $alias, 'pass' => self::TEST_PASSWORD));

        $this->assertSame(401, $status);
        // 011-001-0004: one code for every 401 of the login — the answer must not say which of the
        // reasons applied. The wording stays in `detail`, and `data` is null, so no token comes with it.
        $this->assertSame('The user can only be authenticated through their login provider.',
            $this->assertErrorEnvelope($body, 'contentfly_general_invalid_credentials')['detail']);
    }

    /**
     * **Inverted with `013-004-0002`, not deleted.**
     *
     * The test used to record that the alias prefix prevents collisions between login
     * managers: `createManagedUser()` prefixes the alias with `md5(get_class($this))`. The
     * prefix solved a real problem — two external systems that deliver the same user name
     * must not get the same account —, but it solved it by making the answer unreadable:
     * Whoever looked into `pim_user` found `3f2a…-jdoe` and did not know who that was.
     *
     * The same uniqueness now comes from a constraint over `loginManager` **and**
     * `externalId`, and the alias reads as `<provider>:<identifier>`.
     */
    public function testUniquenessComesFromTheColumnConstraintInsteadOfAnMd5Prefix(): void
    {
        $constraint = $this->pdo()->query(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pim_user'
               AND INDEX_NAME = 'uniq_user_external_identity' AND NON_UNIQUE = 0"
        )->fetchColumn();

        $this->assertSame('2', (string) $constraint,
            'The unique constraint spans both columns — loginManager and externalId');

        $ldap = $this->createUser('ldap:miller', 'ldap', 'miller');
        $saml = $this->createUser('saml:miller', 'saml', 'miller');

        $this->assertNotSame($ldap, $saml, 'Two accounts for the same external name');

        $aliases = $this->pdo()->query(
            "SELECT alias FROM pim_user WHERE externalId = 'miller' ORDER BY alias"
        )->fetchAll(\PDO::FETCH_COLUMN);

        $this->assertSame(array('ldap:miller', 'saml:miller'), $aliases,
            'Readable, and the origin comes first');
    }

    /**
     * **Finding A-6, over HTTP.**
     *
     * `createManagedUser()` called `setPass($alias)` — the password was the user name.
     * It was defused solely by the bolt "only authorizable via LoginManager"; every
     * path that bypassed it was a trivial account takeover. Now the password is locked,
     * and the bolt is the **second** safeguard.
     */
    public function testAProvisionedUserHasNoGuessablePassword(): void
    {
        $alias = 'ldap:a6-'.bin2hex(random_bytes(4));
        $this->createUserWithLockedPassword($alias, 'ldap');

        foreach (array($alias, substr($alias, 5), 'ldap', '*', '') as $attempt) {
            [$status, $body] = $this->postJson('/auth/login', array('alias' => $alias, 'pass' => $attempt));

            $this->assertSame(401, $status, 'Attempt with "'.$attempt.'"');
            $this->assertErrorEnvelope($body); // 011-001-0004: `data` is null, so there is no token
        }
    }

    /**
     * And the bolt still holds: Even without a locked password nobody would get through.
     */
    public function testTheBoltIsTheSecondSafeguardAndStillHolds(): void
    {
        $alias = 'lm-bolt-'.bin2hex(random_bytes(4));
        $this->createUser($alias, 'ldap');

        [$status, $body] = $this->postJson('/auth/login', array('alias' => $alias, 'pass' => self::TEST_PASSWORD));

        $this->assertSame(401, $status);
        $this->assertSame('The user can only be authenticated through their login provider.',
            $this->assertErrorEnvelope($body, 'contentfly_general_invalid_credentials')['detail']); // 011-001-0004
    }

    /** Creates a user with a locked password — as provisioning would. */
    private function createUserWithLockedPassword(string $alias, string $provider): string
    {
        $id = 'lm-'.bin2hex(random_bytes(6));

        $this->pdo()->prepare(
            'INSERT INTO pim_user (id, isAdmin, alias, pass, isActive, salt, loginManager,
                                   externalId, created, modified, views, isIntern)
             VALUES (:id, 0, :alias, :pass, 1, :salt, :lm, :ext, NOW(), NOW(), 0, 0)'
        )->execute(array(
            'id'    => $id,
            'alias' => $alias,
            'pass'  => '*',
            'salt'  => bin2hex(random_bytes(16)),
            'lm'    => $provider,
            'ext'   => substr($alias, strlen($provider) + 1),
        ));

        $this->deleteAfterTest('pim_user', $id);

        return $id;
    }

    // ── The selection comes from an allowlist (013-004-0001) ───────────────────────────

    /**
     * **A class name in the request no longer selects a class.**
     *
     * Until `013-004-0001` the parameter `loginManager` was resolved to `Custom\Classes\<Name>`
     * and the class was instantiated. The prefix and an `instanceof` check
     * limited the damage — but the selection was up to the caller. Now the parameter names
     * an entry in the registry, and a class name is not listed there.
     */
    public function testAClassNameNoLongerSelectsAClass(): void
    {
        foreach (array(
            'Custom\\Classes\\LoginManager\\Example',
            'Plugins\\Auth\\Ldap',
            'Areanet\\PIM\\Classes\\Manager\\LoginManager',
        ) as $className) {
            [$status, $body] = $this->postJson('/auth/login', array(
                'alias'        => 'admin',
                'pass'         => $this->pass(),
                'loginManager' => $className,
            ));

            $this->assertSame(401, $status, $className.' must not open anything');
            $this->assertArrayNotHasKey('token', $body);
        }
    }

    /**
     * An unknown name is rejected and **not** routed back to the password
     * check.
     *
     * Otherwise a typo in the provider name would be a silent login through the wrong path —
     * with the correct password even a successful one.
     */
    public function testAnUnknownProviderNameDoesNotFallBackToThePassword(): void
    {
        [$status, $body] = $this->postJson('/auth/login', array(
            'alias'        => 'admin',
            'pass'         => $this->pass(),
            'loginManager' => 'doesnotexist',
        ));

        $this->assertSame(401, $status);
        $this->assertArrayNotHasKey('token', $body);
    }

    /**
     * And the counter-check: Without the parameter, login works as always.
     *
     * The registry is empty as shipped — as long as nothing is registered, there is
     * no way around the password check, but also no additional bolt in front of it.
     */
    public function testWithoutProviderNameLoginWorksAsAlways(): void
    {
        [$status, $body] = $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => $this->pass()));

        $this->assertSame(200, $status);
        $this->assertArrayHasKey('token', $this->assertEnvelope($body)); // 011-001-0004
    }
}
