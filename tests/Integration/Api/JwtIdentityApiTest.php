<?php
namespace Tests\Integration\Api;

use Areanet\PIM\Entity\Permission;
use Tests\Integration\IntegrationTestCase;

/**
 * A token names the account by its id (`015-000-0014`).
 *
 * THE FINDING. `JwtAccessToken::issue()` wrote `getUserIdentifier()` — the alias — into `sub`,
 * and the JWT path resolved the account from it on every request:
 * `TokenHandler::fromJwt()` → `UserBadge($claims->sub)` → `UserLoader` →
 * `findOneBy(['alias' => …])`. `RightsManagement` guarded `pass`, `salt`, `loginManager` and
 * `externalId` of a foreign user — not `alias`.
 *
 * So a non-admin with write access to foreign `PIM\User` records renamed accounts until the
 * `sub` of their own still-valid token belonged to an admin. Nothing was forged; the database
 * was moved underneath the token, and the next request ran as the admin.
 *
 * WHAT IS MEASURED IS THE ANSWER OF THE TOKEN AFTER THE RENAME — not that the rename is refused.
 * Both halves are fixed, and either alone would hold only until the next path that writes an
 * alias, so the test forces the rename past the API with SQL and then asks the token who it is.
 *
 * Needs `SECURITY_JWT_SECRET`; the suite's server has one, and without it the class skips like
 * every other integration test.
 */
class JwtIdentityApiTest extends IntegrationTestCase
{
    /**
     * THE ATTACK, END TO END.
     *
     * The rename is done in the database on purpose: it is the state an installation is in once
     * somebody managed it — through the gap this task closes, through a console command, through
     * a project's own code. What must not happen is that a token follows the name.
     */
    public function testATokenDoesNotFollowARenamedAlias(): void
    {
        [, $victim]  = $this->createTestUser($this->mayWriteTags());
        $victimAlias = $this->userColumn($victim, 'alias');
        $victimToken = $this->jwtFor($victimAlias);

        [, $admin] = $this->createTestUser();
        $this->pdo()->prepare('UPDATE pim_user SET isAdmin = 1 WHERE id = :id')->execute(array('id' => $admin));

        $this->assertFalse($this->isAdminByToken($victimToken), 'Precondition: the token is not an admin');

        // The rename: the victim gets out of the way, the admin takes the freed name.
        $this->pdo()->prepare('UPDATE pim_user SET alias = :alias WHERE id = :id')
                    ->execute(array('alias' => 'moved-'.bin2hex(random_bytes(4)), 'id' => $victim));
        $this->pdo()->prepare('UPDATE pim_user SET alias = :alias WHERE id = :id')
                    ->execute(array('alias' => $victimAlias, 'id' => $admin));

        $this->assertFalse($this->isAdminByToken($victimToken),
            'The token still belongs to the account it was issued for — this is the finding');
        $this->assertSame($victim, $this->userIdByToken($victimToken),
            'and it names that account by its id');
    }

    /** A rename of the OWN account does not invalidate the own token either. */
    public function testATokenSurvivesARenameOfItsOwnAccount(): void
    {
        [, $user] = $this->createTestUser($this->mayWriteTags());
        $token    = $this->jwtFor($this->userColumn($user, 'alias'));

        $this->pdo()->prepare('UPDATE pim_user SET alias = :alias WHERE id = :id')
                    ->execute(array('alias' => 'renamed-'.bin2hex(random_bytes(4)), 'id' => $user));

        $this->assertSame($user, $this->userIdByToken($token),
            'The id did not change, so the token did not either');
    }

    /** A deactivated account is refused, whatever its token says. */
    public function testATokenOfADeactivatedAccountIsRefused(): void
    {
        [, $user] = $this->createTestUser($this->mayWriteTags());
        $token    = $this->jwtFor($this->userColumn($user, 'alias'));

        $this->assertSame(200, $this->postJson('/api/list', array('entity' => 'PIM\\Tag'), $token)[0],
            'Precondition: the token works');

        $this->pdo()->prepare('UPDATE pim_user SET isActive = 0 WHERE id = :id')->execute(array('id' => $user));

        $this->assertNotSame(200, $this->postJson('/api/list', array('entity' => 'PIM\\Tag'), $token)[0]);
    }

    // ── the second half: the rename itself ─────────────────────────────────────────────────

    public function testANonAdminCannotRenameAnotherUser(): void
    {
        [$token]    = $this->createTestUser(array('PIM\\User' => array(
            'readable' => Permission::ALL, 'writable' => Permission::ALL,
        )));
        [, $victim] = $this->createTestUser();
        $before     = $this->userColumn($victim, 'alias');

        [$status, $body] = $this->postJson('/api/update', array(
            'entity' => 'PIM\\User', 'id' => $victim, 'data' => array('alias' => 'taken-'.bin2hex(random_bytes(4))),
        ), $token);

        $this->assertSame(403, $status, json_encode($body['errors'] ?? $body));
        $this->assertErrorEnvelope($body, 'contentfly_general_permission_denied');
        $this->assertSame($before, $this->userColumn($victim, 'alias'), 'and the alias is untouched');
    }

    /** Posting a foreign record back with its own alias is not a change and stays allowed. */
    public function testPostingAForeignAliasBackUnchangedStillWorks(): void
    {
        [$token]    = $this->createTestUser(array('PIM\\User' => array(
            'readable' => Permission::ALL, 'writable' => Permission::ALL,
        )));
        [, $victim] = $this->createTestUser();

        [$status, $body] = $this->postJson('/api/update', array(
            'entity' => 'PIM\\User', 'id' => $victim,
            'data'   => array('alias' => $this->userColumn($victim, 'alias')),
        ), $token);

        $this->assertSame(200, $status, json_encode($body['errors'] ?? $body));
    }

    /** Renaming the OWN account stays allowed — it was never the attack. */
    public function testAUserMayStillRenameHisOwnAccount(): void
    {
        [$token, $user] = $this->createTestUser(array('PIM\\User' => array(
            'readable' => Permission::OWN, 'writable' => Permission::OWN,
        )));
        $alias = 'own-'.bin2hex(random_bytes(4));

        [$status, $body] = $this->postJson('/api/update', array(
            'entity' => 'PIM\\User', 'id' => $user, 'data' => array('alias' => $alias),
        ), $token);

        $this->assertSame(200, $status, json_encode($body['errors'] ?? $body));
        $this->assertSame($alias, $this->userColumn($user, 'alias'));
    }

    // ── helpers ────────────────────────────────────────────────────────────────────────────

    /**
     * An ACCESS JWT for that account.
     *
     * `tokenType: 'jwt'` is the opt-in — without it `/auth/login` hands out an opaque token from
     * `pim_token`, and that branch brings its own loader and never resolved anything by alias.
     * The first version of this class used `createTestUser()`'s token and was therefore green
     * without the fix: it measured the branch the finding is not in.
     */
    private function jwtFor(string $alias): string
    {
        [$status, $body] = $this->postJson('/auth/login', array(
            'alias'     => $alias,
            'pass'      => self::TEST_PASSWORD,
            'tokenType' => 'jwt',
        ));

        $this->assertSame(200, $status, 'The JWT login succeeded — '.json_encode($body));
        $this->assertArrayHasKey('refreshToken', $body['data'], 'and it really is the JWT branch');
        $this->assertStringContainsString('.', $body['data']['token'], 'and the token is a JWT');

        return $body['data']['token'];
    }

    /** @return array<string, array<string, int>> rights that let a token write a probe record */
    private function mayWriteTags(): array
    {
        return array('PIM\\Tag' => array('readable' => Permission::ALL, 'writable' => Permission::ALL));
    }

    /** Whether the account behind this token is an admin — read from what the API answers. */
    private function isAdminByToken(string $token): bool
    {
        [$status, $body] = $this->postJson('/api/list', array('entity' => 'PIM\\Permission'), $token);

        // Only an admin may read PIM\Permission (000-000-0090).
        return $status === 200;
    }

    /**
     * The id of the account a token authenticates as — read from what a write leaves behind.
     *
     * NO ENDPOINT ANSWERS "WHO AM I": `/api/config` is GET-only and `/auth/refresh` wants a
     * refresh token, not an access token. What does answer it is `userCreated`: the API stamps
     * every insert with the account the request ran as, so a record written with this token
     * names it. That is the same column every `OWN` rule is decided by, which makes it the right
     * measurement rather than merely an available one.
     */
    private function userIdByToken(string $token): string
    {
        [$status, $body] = $this->postJson('/api/insert', array(
            'entity' => 'PIM\\Tag', 'data' => array('title' => 'whoami-'.bin2hex(random_bytes(6))),
        ), $token);

        $this->assertSame(200, $status, 'The token may write — '.json_encode($body['errors'] ?? $body));

        $id = $body['data']['id'];
        $this->deleteAfterTest('pim_tag', $id);

        $owner = $this->pdo()->query('SELECT usercreated_id FROM pim_tag WHERE id = '.$this->pdo()->quote($id))->fetchColumn();

        $this->assertIsString($owner, 'and the record names its creator');

        return $owner;
    }

    private function userColumn(string $id, string $column): string
    {
        return (string) $this->pdo()->query("SELECT `$column` FROM pim_user WHERE id = ".$this->pdo()->quote($id))->fetchColumn();
    }
}
