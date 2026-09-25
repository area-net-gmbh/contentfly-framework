<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * Characterization tests for token authentication.
 *
 * After the removal of session-based login (story `012-004`) the token is the **only**
 * way in. A bug there would be a security hole, not a convenience problem — which is why
 * this suite pins down what holds today and fails as soon as any of it changes.
 *
 * Prerequisites as in `FileApiTest`: `CONTENTFLY_TEST_BASE_URL` and
 * `CONTENTFLY_TEST_ADMIN_PASS`, otherwise the tests are skipped. See `tests/README.md`.
 */
class AuthApiTest extends IntegrationTestCase
{
    // ── Login ──────────────────────────────────────────────────────────────────────────

    public function testLoginWithCorrectCredentialsReturnsToken(): void
    {
        [, $body] = $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => $this->pass()));

        /*
         * 011-001-0004: `message` is gone. It said "Login successful" — a sentence a client could
         * only compare verbatim, next to the 200 that already said it. What remains is what the
         * caller actually takes away, and it is the payload now.
         */
        $session = $this->assertEnvelope($body);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{128}$/', $session['token'] ?? '', 'Token is 64 bytes as hex');
        $this->assertTrue($session['user']['isAdmin'] ?? false);
    }

    public function testLoginWithWrongPasswordReturnsNoToken(): void
    {
        [$status, $body] = $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => 'wrong'));

        $this->assertSame(401, $status);
        $this->assertArrayNotHasKey('token', $body);
    }

    public function testLoginWithUnknownUserReturnsNoToken(): void
    {
        [$status, $body] = $this->postJson('/auth/login', array('alias' => 'doesnotexist', 'pass' => 'whatever'));

        $this->assertSame(401, $status);
        $this->assertArrayNotHasKey('token', $body);
    }

    /**
     * The error message currently distinguishes between "user name unknown" and
     * "password wrong". That tells an attacker which identifiers exist.
     *
     * Pinned as current behaviour — it gets fixed in story `013-001`
     * (auth hardening). If the message changes there, this test has to follow.
     */
    public function testErrorMessageRevealsWhetherUserExists(): void
    {
        [, $unknown] = $this->postJson('/auth/login', array('alias' => 'doesnotexist', 'pass' => 'whatever'));
        [, $wrong]   = $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => 'wrong'));

        // 011-001-0004: `message` became `errors[0].detail`. What the test records is unchanged —
        // the two answers still differ, and 013-001 is the place where that is decided.
        $this->assertNotSame(
            $this->assertErrorEnvelope($unknown)['detail'],
            $this->assertErrorEnvelope($wrong)['detail'],
            'Different messages today - see 013-001'
        );
    }

    // ── Access protection ──────────────────────────────────────────────────────────────

    public function testProtectedRouteWithValidTokenReturns200(): void
    {
        $token = $this->login();

        [$status] = $this->get('/api/schema', $token);

        $this->assertSame(200, $status);
    }

    public function testProtectedRouteWithoutTokenReturnsNoData(): void
    {
        [$status, $body] = $this->get('/api/schema', null);

        $this->assertNotSame(200, $status);
        // Flipped twice. First the test pinned 500 — the debug exception handler caught the
        // exception before the application's handler. Then 302: the handler redirected to `/`
        // without a JSON content type, and this call does not send one. Since
        // 000-000-0006 that redirect no longer exists — there is no home a browser could be
        // sent to since the UI was dropped — and the exception's code comes through.
        $this->assertSame(401, $status);
        /*
         * INVERTED WITH 011-001-0003. The raw body now DOES contain the string `"data"` — the key
         * is always there, and on an error it holds null. The assertion was about the payload, not
         * about the word, so it now says that: no object, no list, nothing but null.
         */
        $this->assertSame(array('data' => null), array_intersect_key(json_decode($body, true), array('data' => null)));
    }

    public function testProtectedRouteWithFabricatedTokenReturnsNoData(): void
    {
        [$status] = $this->get('/api/schema', str_repeat('a', 128));

        $this->assertNotSame(200, $status);
    }

    public function testLogoutMakesTokenUnusable(): void
    {
        $token = $this->login();

        [$before] = $this->get('/api/schema', $token);
        $this->assertSame(200, $before, 'Before logout the token is valid');

        $this->get('/auth/logout', $token);

        [$after] = $this->get('/api/schema', $token);
        $this->assertNotSame(200, $after, 'After logout the same token must no longer be valid');
    }

    // ── Password hashing (013-001-0001) ────────────────────────────────────────────────

    public function testLegacyPasswordIsRehashedOnLogin(): void
    {
        // `createTestUser()` does create the user with a SHA-256 hash, BUT LOGS THEM IN
        // RIGHT AWAY to return the token — so the hash is already rehashed before this test
        // sees anything. (Incidentally that means: every test using the helper runs through
        // the legacy-format branch. So it is broadly covered, just not asserted.)
        //
        // For the assertion the legacy hash is therefore restored explicitly.
        [, $userId] = $this->createTestUser();
        $this->setLegacyHash($userId);

        $before = $this->readPassHash($userId);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $before,
            'Precondition: the hash is in the legacy SHA-256 format');

        [$status] = $this->postJson('/auth/login', array(
            'alias' => $this->aliasFor($userId),
            'pass'  => self::TEST_PASSWORD,
        ));
        $this->assertSame(200, $status, 'An existing password still logs in');

        $after = $this->readPassHash($userId);
        $this->assertStringStartsWith('$', $after,
            'and the hash is replaced afterwards — password_hash() starts with $');
        $this->assertNotSame($before, $after);
    }

    public function testLoginStillWorksAfterRehashing(): void
    {
        // A rehashed hash must be verified through the NEW branch the next time. If `isPass()`
        // got that wrong, the user would be locked out after exactly one successful login —
        // and the test above would still be green.
        [, $userId] = $this->createTestUser();
        $this->setLegacyHash($userId);
        $alias = $this->aliasFor($userId);

        $this->postJson('/auth/login', array('alias' => $alias, 'pass' => self::TEST_PASSWORD));

        [$status, $body] = $this->postJson('/auth/login', array(
            'alias' => $alias,
            'pass'  => self::TEST_PASSWORD,
        ));

        $this->assertSame(200, $status);
        $this->assertNotEmpty($this->assertEnvelope($body)['token'] ?? null); // 011-001-0004
    }

    public function testWrongPasswordStillFailsAfterRehashing(): void
    {
        [, $userId] = $this->createTestUser();
        $this->setLegacyHash($userId);
        $alias = $this->aliasFor($userId);

        $this->postJson('/auth/login', array('alias' => $alias, 'pass' => self::TEST_PASSWORD));

        [$status] = $this->postJson('/auth/login', array('alias' => $alias, 'pass' => 'wrong'));

        $this->assertSame(401, $status);
    }

    /** Writes back the legacy SHA-256 hash, as an existing project carries it. */
    private function setLegacyHash(string $userId): void
    {
        $s = $this->pdo()->prepare('SELECT salt FROM pim_user WHERE id = :id');
        $s->execute(array('id' => $userId));
        $salt = (string) $s->fetchColumn();

        $this->pdo()->prepare('UPDATE pim_user SET pass = :pass WHERE id = :id')->execute(array(
            'pass' => hash('sha256', self::TEST_PASSWORD.$salt),
            'id'   => $userId,
        ));
    }

    private function readPassHash(string $userId): string
    {
        $s = $this->pdo()->prepare('SELECT pass FROM pim_user WHERE id = :id');
        $s->execute(array('id' => $userId));

        return (string) $s->fetchColumn();
    }

    private function aliasFor(string $userId): string
    {
        $s = $this->pdo()->prepare('SELECT alias FROM pim_user WHERE id = :id');
        $s->execute(array('id' => $userId));

        return (string) $s->fetchColumn();
    }

    public function testEveryLoginReturnsNewToken(): void
    {
        $this->assertNotSame($this->login(), $this->login(), 'Tokens are generated per login, not reused');
    }

    // ── Regression protection for 012-004 ──────────────────────────────────────────────

    /**
     * The actual purpose of this suite: after the removal of session-based login, **no**
     * request may start a PHP session any more.
     *
     * As long as a session was running, PHP held an exclusive lock on its file until the end
     * of the script — and because all tabs of a user share the same PHPSESSID, that user's
     * concurrent API calls ran one after another instead of in parallel. If the session comes
     * back, that behaviour comes back, and nobody would notice.
     */
    public function testNoRequestStartsPhpSession(): void
    {
        $paths = array(
            array('POST', '/auth/login'),
            array('GET',  '/api/schema'),
            array('GET',  '/file/get/00000000-0000-0000-0000-000000000000'),
        );

        foreach ($paths as [$method, $path]) {
            $headers = $method === 'POST'
                ? $this->postJson($path, array('alias' => 'admin', 'pass' => $this->pass()))[2]
                : $this->get($path, null)[2];

            $this->assertStringNotContainsStringIgnoringCase(
                'PHPSESSID',
                $headers,
                $method.' '.$path.' must not set a session cookie'
            );
        }
    }

    // ── The token is only stored hashed in the table (013-001-0004) ────────────────────

    /**
     * The proof that `013-001-0004` is about.
     *
     * Before, `pim_token.token` held 128 hex characters in plain text. A read access to the
     * database — a backup, an SQL injection, a dump in the ticket system — thereby handed over
     * all running sessions, usable immediately.
     */
    public function testIssuedTokenIsNotStoredInTable(): void
    {
        $token = $this->login();

        $count = $this->pdo()->prepare('SELECT COUNT(*) FROM pim_token WHERE token = :t');

        $count->execute(array('t' => $token));
        $this->assertSame('0', (string) $count->fetchColumn(), 'The plain text is stored nowhere');

        $count->execute(array('t' => hash('sha256', $token)));
        $this->assertSame('1', (string) $count->fetchColumn(), 'its SHA-256 exactly once');
    }

    /**
     * And the other half: login works unchanged.
     *
     * A hashed token that nobody can log in with any more would not be progress.
     */
    public function testIssuedTokenStillWorks(): void
    {
        $token = $this->login();

        [$status] = $this->get('/api/schema', $token);
        $this->assertSame(200, $status);

        [$logout] = $this->get('/auth/logout', $token);
        $this->assertSame(200, $logout);

        [$afterwards] = $this->get('/api/schema', $token);
        $this->assertNotSame(200, $afterwards, 'After logout it is gone');
    }

    /**
     * The hash itself is not a token.
     *
     * Whoever copies it from the table or from `listTokens` and presents it does not get in:
     * it would be hashed a second time during verification.
     */
    public function testHashCannotBePresentedAsToken(): void
    {
        $token = $this->login();

        [$status] = $this->get('/api/schema', hash('sha256', $token));

        $this->assertNotSame(200, $status);
    }

    // ── The dropped routes under /api (013-001-0005) ──────────────────────────────────

    /**
     * `POST /api/login` and `POST /api/logout` do not exist.
     *
     * They were registered and pointed to `api.controller:loginAction` and `:logoutAction` —
     * methods that do not exist in `ApiController` and never did. They never reached the router
     * anyway: `RouteCollector` numbers per provider, `/api/login` was called `login_0` and
     * was displaced when `/auth/login` with the same name was mounted.
     *
     * Both are fixed with `013-001-0005` — the names now carry the mount point, and the two
     * dead routes are removed rather than redirected. The test checks that they behave like any
     * other unknown path: the same response, no special case.
     */
    public function testRoutesUnderApiDoNotExist(): void
    {
        [$unknown] = $this->postJson('/api/doesnotexist-'.bin2hex(random_bytes(4)), array());

        foreach (array('/api/login', '/api/logout') as $path) {
            [$status] = $this->postJson($path, array('alias' => 'admin', 'pass' => $this->pass()));

            $this->assertSame($unknown, $status, $path.' responds like any unknown path');
        }
    }

    /**
     * And the cross-check: the routes under `/auth` still work.
     *
     * They are the ones that displaced their namesake — nothing about them had to change during
     * the cleanup.
     */
    public function testRoutesUnderAuthStillWork(): void
    {
        [$status, $body] = $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => $this->pass()));
        $this->assertSame(200, $status);

        [$logout] = $this->get('/auth/logout', $body['data']['token']); // 011-001-0004
        $this->assertSame(200, $logout);
    }

    // ── All five TokenSources on the running system (013-002-0004) ────────────────────

    /**
     * The proof for the switch: every source opens a protected route.
     *
     * Four of them are inherited — `BaseControllerProvider::checkToken()` read them, and existing
     * clients send them. Without them every existing Ionic client would break on update. The
     * fifth, `Authorization: Bearer`, is new and the path everything is heading towards.
     *
     * `TokenSourcesTest` measures the same without HTTP; here the point is that it is **wired**.
     */
    public function testEveryTokenSourceOpensProtectedRoute(): void
    {
        $token = $this->login();

        $viaHeader = array(
            'Authorization: Bearer' => 'Authorization: Bearer '.$token,
            'appcms-token'          => 'appcms-token: '.$token,
            'X-XSRF-TOKEN'          => 'X-XSRF-TOKEN: '.$token,
        );

        foreach ($viaHeader as $name => $headerLine) {
            $this->assertSame(200, $this->getWithHeader('/api/schema', $headerLine), $name);
        }

        $this->assertSame(200, $this->getWithHeader('/api/schema?_token='.$token, null),
            '_token in the query string');

        [$status] = $this->postJson('/api/count', array('entity' => 'PIM\User', '_token' => $token));
        $this->assertSame(200, $status, '_token in the body');
    }

    public function testProtectedRouteStaysClosedWithoutToken(): void
    {
        $this->assertNotSame(200, $this->getWithHeader('/api/schema', null));
    }

    /**
     * A token that does not exist opens nothing — through any source.
     */
    public function testFabricatedTokenOpensNoSource(): void
    {
        $fabricated = bin2hex(random_bytes(64));

        $this->assertNotSame(200, $this->getWithHeader('/api/schema', 'Authorization: Bearer '.$fabricated));
        $this->assertNotSame(200, $this->getWithHeader('/api/schema', 'appcms-token: '.$fabricated));
        $this->assertNotSame(200, $this->getWithHeader('/api/schema?_token='.$fabricated, null));
    }

    /** A GET with exactly one header of our choice — the base class always sends `appcms-token`. */
    private function getWithHeader(string $path, ?string $headerLine): int
    {
        $ch = curl_init(getenv('CONTENTFLY_TEST_BASE_URL').$path);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headerLine === null ? array() : array($headerLine),
        ));
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        return $status;
    }

    // ── Issuing JWT (013-003-0001) ─────────────────────────────────────────────────────

    /**
     * **Without a request nothing changes.**
     *
     * The promise of this story: an existing client notices nothing. That is why the caller
     * decides per request, and not a configuration switch that would flip the response for
     * everyone at once.
     */
    public function testWithoutRequestTokenStaysOpaque(): void
    {
        [, $body] = $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => $this->pass()));

        // 011-001-0004: the session is the payload — the two keys must be absent THERE.
        $session = $this->assertEnvelope($body);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{128}$/', $session['token'] ?? '');
        $this->assertArrayNotHasKey('refreshToken', $session);
        $this->assertArrayNotHasKey('expiresIn', $session);
    }

    public function testOnRequestLoginReturnsJwtAndRefreshToken(): void
    {
        $body = $this->jwtLogin();

        $this->assertCount(3, explode('.', $body['token']), 'Three dot-separated segments');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{128}$/', $body['refreshToken'] ?? '',
            'The refresh token is an ordinary opaque token');
        $this->assertGreaterThan(0, $body['expiresIn'] ?? 0);
        $this->assertLessThanOrEqual(900, $body['expiresIn']);
    }

    public function testIssuedJwtOpensProtectedRoute(): void
    {
        $body = $this->jwtLogin();

        $this->assertSame(200, $this->getWithHeader('/api/schema', 'Authorization: Bearer '.$body['token']));
        $this->assertSame(200, $this->getWithHeader('/api/schema', 'appcms-token: '.$body['token']),
            'Also through the legacy sources — the branching decides by shape, not by source');
    }

    /**
     * **A refresh token is not a JwtAccessToken.**
     *
     * It is an ordinary row in `pim_token`, and until `013-003-0001` the opaque branch accepted
     * every row. A refresh token lives longer than an access JWT — that is its purpose —
     * and without this separation it would be a long-lived master key for the whole API.
     */
    public function testRefreshTokenOpensNoProtectedRoute(): void
    {
        $body = $this->jwtLogin();

        $this->assertNotSame(200, $this->getWithHeader('/api/schema', 'appcms-token: '.$body['refreshToken']));
        $this->assertNotSame(200, $this->getWithHeader('/api/schema', 'Authorization: Bearer '.$body['refreshToken']));
    }

    /**
     * The claim set on the issued token — read, not assumed.
     *
     * Roles, groups and permissions can change while the token is valid. If they were in it,
     * a permission change would only take effect after it expires.
     */
    public function testIssuedJwtCarriesOnlyTheDefinedClaimSet(): void
    {
        $body   = $this->jwtLogin();
        $claims = json_decode(base64_decode(strtr(explode('.', $body['token'])[1], '-_', '+/')), true);

        $names = array_keys($claims);
        sort($names);

        $this->assertSame(array('exp', 'iat', 'iss', 'jti', 'sub'), $names);
        $this->assertSame('admin', $claims['sub']);
        $this->assertSame('contentfly', $claims['iss']);
    }

    /**
     * Logs in with `tokenType: jwt` and returns THE SESSION — since `011-001-0004` the payload of
     * the response, not the whole response. Token, refresh token and lifetime are what a caller
     * takes away from a login; the envelope around them is checked where it is the subject.
     */
    private function jwtLogin(): array
    {
        [$status, $body] = $this->postJson('/auth/login', array(
            'alias'     => 'admin',
            'pass'      => $this->pass(),
            'tokenType' => 'jwt',
        ));

        if ($status !== 200 || !isset($body['data']['token'], $body['data']['refreshToken'])) {
            $this->fail('JWT login failed: '.json_encode($body));
        }

        return $body['data'];
    }

    // ── The refresh path (013-003-0002) ────────────────────────────────────────────────

    public function testRefreshTokenReturnsFreshAccessJwt(): void
    {
        $login = $this->jwtLogin();

        [$status, $body] = $this->postJson('/auth/refresh', array('refreshToken' => $login['refreshToken']));

        $this->assertSame(200, $status);

        // 011-001-0004: refresh answers in the envelope, and `message` is gone for the same reason
        // as at the login.
        $fresh = $this->assertEnvelope($body);

        $this->assertCount(3, explode('.', $fresh['token'] ?? ''));
        $this->assertNotSame($login['token'], $fresh['token'], 'A fresh token, not the same one');
        $this->assertSame(200, $this->getWithHeader('/api/schema', 'Authorization: Bearer '.$fresh['token']));
    }

    /**
     * **Rotation: the presented refresh token is no longer valid afterwards.**
     *
     * A refresh token that is valid multiple times is a long-lived secret — whoever grabs it
     * can fetch fresh access tokens for as long as they like, and nobody sees it. If it is
     * swapped on every use, a second use stands out.
     */
    public function testPresentedRefreshTokenIsReplaced(): void
    {
        $login = $this->jwtLogin();

        [, $first] = $this->postJson('/auth/refresh', array('refreshToken' => $login['refreshToken']));
        $this->assertNotSame($login['refreshToken'], $first['data']['refreshToken'] ?? null, 'A new refresh token'); // 011-001-0004

        [$second] = $this->postJson('/auth/refresh', array('refreshToken' => $login['refreshToken']));
        $this->assertSame(401, $second, 'The old one is no longer valid');

        [$withNew] = $this->postJson('/auth/refresh', array('refreshToken' => $first['data']['refreshToken']));
        $this->assertSame(200, $withNew, 'The new one is');
    }

    /**
     * An access JWT is no good as a refresh token — the reverse direction of
     * `testRefreshTokenOpensNoProtectedRoute`.
     */
    public function testAccessJwtIsNotUsableAsRefreshToken(): void
    {
        $login = $this->jwtLogin();

        [$status] = $this->postJson('/auth/refresh', array('refreshToken' => $login['token']));

        $this->assertSame(401, $status);
    }

    /**
     * Nor is an opaque login token: it is a `pim_token` row without `purpose`.
     */
    public function testOpaqueLoginTokenIsNotUsableAsRefreshToken(): void
    {
        $opaque = $this->login();

        [$status] = $this->postJson('/auth/refresh', array('refreshToken' => $opaque));

        $this->assertSame(401, $status);
    }

    public function testUnknownRefreshTokenIsRejected(): void
    {
        [$status, $body] = $this->postJson('/auth/refresh', array('refreshToken' => bin2hex(random_bytes(64))));

        $this->assertSame(401, $status);
        $this->assertArrayNotHasKey('token', $body);
    }

    public function testMissingRefreshTokenIsRejected(): void
    {
        [$status] = $this->postJson('/auth/refresh', array());

        $this->assertSame(401, $status);
    }

    /**
     * All failures look the same.
     *
     * Whoever distinguishes here tells an attacker which of their attempts was closer.
     */
    public function testEveryRefreshFailureLooksTheSame(): void
    {
        $login = $this->jwtLogin();

        [, $unknown]   = $this->postJson('/auth/refresh', array('refreshToken' => bin2hex(random_bytes(64))));
        [, $wrongKind] = $this->postJson('/auth/refresh', array('refreshToken' => $login['token']));
        [, $without]   = $this->postJson('/auth/refresh', array());

        // 011-001-0004: all three answer in the error envelope — and all three name the same
        // `code`, which is exactly what this test is about: the caller learns nothing about which
        // attempt came closer.
        $this->assertSame(
            $this->assertErrorEnvelope($unknown, 'contentfly_general_invalid_refresh_token'),
            $this->assertErrorEnvelope($wrongKind, 'contentfly_general_invalid_refresh_token')
        );
        $this->assertSame(
            $this->assertErrorEnvelope($unknown)['detail'],
            $this->assertErrorEnvelope($without)['detail']
        );
    }

    /**
     * A deactivated user gets no new access JWT.
     *
     * This is the case the refresh model has to carry: access ends at the latest with the
     * current access token, because afterwards nobody gets a new one.
     */
    public function testDeactivatedUserGetsNoNewAccessJwt(): void
    {
        [, $userId] = $this->createTestUser();

        [$status, $login] = $this->postJson('/auth/login', array(
            'alias'     => $this->aliasFor($userId),
            'pass'      => self::TEST_PASSWORD,
            'tokenType' => 'jwt',
        ));
        $this->assertSame(200, $status);

        $deactivate = $this->pdo()->prepare('UPDATE pim_user SET isActive = 0 WHERE id = :id');
        $deactivate->execute(array('id' => $userId));

        [$afterDeactivation] = $this->postJson('/auth/refresh', array('refreshToken' => $login['refreshToken']));

        $this->assertSame(401, $afterDeactivation);
    }

    // ── Revocation (013-003-0003) ──────────────────────────────────────────────────────

    /**
     * **The core of the story.** After logout the access JWT is no longer valid — even though
     * its `exp` still lies in the future.
     *
     * Without the revocation list that would not be the case: a stateless token cannot be
     * recalled while it is valid. For a token that someone has grabbed, that is exactly the
     * damage.
     */
    public function testAccessJwtIsInvalidAfterLogout(): void
    {
        $login = $this->jwtLogin();
        $jwt   = $login['token'];

        $this->assertSame(200, $this->getWithHeader('/api/schema', 'Authorization: Bearer '.$jwt));
        $this->assertGreaterThan(0, $login['expiresIn'], 'The token is still valid');

        [$logout] = $this->get('/auth/logout', $jwt);
        $this->assertSame(200, $logout);

        $this->assertNotSame(200, $this->getWithHeader('/api/schema', 'Authorization: Bearer '.$jwt),
            'The same, not yet expired token no longer opens anything');

        $this->cleanUpRevocationList();
    }

    /**
     * The refresh token sent along is revoked.
     *
     * Otherwise the holder would just fetch a new access JWT, and the revocation would be in
     * vain. The client has to send it along because the access JWT does not say which refresh
     * row it belongs to — otherwise that link would be in it as a sixth claim, and the claim
     * set is deliberately small.
     */
    public function testLogoutRevokesRefreshTokenSentAlong(): void
    {
        $login = $this->jwtLogin();

        [$logout] = $this->get('/auth/logout?refreshToken='.$login['refreshToken'], $login['token']);
        $this->assertSame(200, $logout);

        [$status] = $this->postJson('/auth/refresh', array('refreshToken' => $login['refreshToken']));
        $this->assertSame(401, $status, 'The refresh token is gone');

        $this->cleanUpRevocationList();
    }

    /**
     * Without a refresh token sent along it remains — and that is the documented situation,
     * not an oversight.
     */
    public function testRefreshTokenRemainsWhenNotSentAlong(): void
    {
        $login = $this->jwtLogin();

        $this->get('/auth/logout', $login['token']);

        [$status] = $this->postJson('/auth/refresh', array('refreshToken' => $login['refreshToken']));
        $this->assertSame(200, $status, 'It expires through its own time limit, not on logout');

        $this->cleanUpRevocationList();
    }

    /**
     * **Only your own.** Without this check `logout` would be an endpoint any logged-in user
     * could use to end other users' sessions.
     */
    public function testForeignRefreshTokenCannotBeLoggedOut(): void
    {
        [, $userId] = $this->createTestUser();

        [, $foreign] = $this->postJson('/auth/login', array(
            'alias'     => $this->aliasFor($userId),
            'pass'      => self::TEST_PASSWORD,
            'tokenType' => 'jwt',
        ));

        $foreign = $foreign['data']; // 011-001-0004: the session is the payload of the login
        $own     = $this->jwtLogin();

        $this->get('/auth/logout?refreshToken='.$foreign['refreshToken'], $own['token']);

        [$status] = $this->postJson('/auth/refresh', array('refreshToken' => $foreign['refreshToken']));
        $this->assertSame(200, $status, 'The foreign refresh token is still valid');

        $this->cleanUpRevocationList();
    }

    /**
     * **Deactivating a user takes effect immediately even without the revocation list — measured.**
     *
     * The story text named deactivation as a use case of the list. It no longer is since
     * `013-002-0001`: the JWT branch returns its `UserBadge` without its own loader, so the
     * `UserLoader` loads the user from `pim_user` and rejects a deactivated one with the same
     * exception as an unknown one.
     *
     * The test is here so the assertion does not stand unproven — and so it is noticed when
     * someone rebuilds the loading path and switches off deactivation along the way.
     */
    public function testUserDeactivationTakesEffectImmediatelyWithoutRevocationList(): void
    {
        [, $userId] = $this->createTestUser();

        [, $login] = $this->postJson('/auth/login', array(
            'alias'     => $this->aliasFor($userId),
            'pass'      => self::TEST_PASSWORD,
            'tokenType' => 'jwt',
        ));

        $login = $login['data']; // 011-001-0004: the session is the payload of the login
        $this->assertSame(200, $this->getWithHeader('/api/schema', 'Authorization: Bearer '.$login['token']));

        $deactivate = $this->pdo()->prepare('UPDATE pim_user SET isActive = 0 WHERE id = :id');
        $deactivate->execute(array('id' => $userId));

        /*
         * INVERTED WITH 000-000-0050: THIS TOKEN'S jti, not the whole table.
         *
         * The assertion used to count `pim_revoked_token` globally and expect zero. That is a
         * claim about the WHOLE SUITE, not about this test — and it broke as soon as any other
         * class logged out a JWT without cleaning up (`EnvelopeApiTest` did, from `011-001-0004`
         * on). With `executionOrder="random"` it depended on the seed: four of twelve were red,
         * and the message pointed at deactivation, which had nothing to do with it.
         *
         * What the test actually claims is narrower and now literal: THIS token was not revoked
         * through the list, so whatever stops it afterwards is the deactivation. The jti is in the
         * JWT; reading it costs one line and makes the test independent of everything else.
         */
        $jti = json_decode(base64_decode(strtr(explode('.', $login['token'])[1], '-_', '+/')), true)['jti'];

        $revoked = $this->pdo()->prepare('SELECT COUNT(*) FROM pim_revoked_token WHERE jti = :jti');
        $revoked->execute(array('jti' => $jti));

        $this->assertSame('0', (string) $revoked->fetchColumn(),
            'No entry in the revocation list for THIS token — deactivation works without it');
        $this->assertNotSame(200, $this->getWithHeader('/api/schema', 'Authorization: Bearer '.$login['token']));
    }

    /**
     * The revocation list stays small: an entry expires with the token it revokes, and
     * `appcms:token:cleanup` clears it away — no second cleanup path.
     */
    public function testObsoleteRevocationEntriesAreCleanedUp(): void
    {
        $jti = 'test-'.bin2hex(random_bytes(8));

        $this->pdo()->prepare(
            'INSERT INTO pim_revoked_token (jti, expiresAt, created) VALUES (:j, :e, :c)'
        )->execute(array(
            'j' => $jti,
            'e' => (new \DateTime('-1 hour'))->format('Y-m-d H:i:s'),
            'c' => (new \DateTime('-2 hours'))->format('Y-m-d H:i:s'),
        ));

        $this->cleanUpRevocationList();

        $count = $this->pdo()->prepare('SELECT COUNT(*) FROM pim_revoked_token WHERE jti = :j');
        $count->execute(array('j' => $jti));

        $this->assertSame('0', (string) $count->fetchColumn());
    }

    /**
     * Empties the revocation list again after a test.
     *
     * **The reason has changed with `000-000-0050`.** It used to read: the entries do not disturb
     * anyone, but `testUserDeactivationTakesEffectImmediatelyWithoutRevocationList` counts them,
     * and an empty list is the only statement that test can make. That was true and it was a trap:
     * it made every test in the suite responsible for a global count, and the trap sprang shut the
     * moment `EnvelopeApiTest` logged out a JWT (`011-001-0004`).
     *
     * That test now asks about its own jti, so nothing depends on an empty table any more. This
     * method stays for the plain reason: a test cleans up what it created.
     */
    private function cleanUpRevocationList(): void
    {
        $this->pdo()->exec('DELETE FROM pim_revoked_token WHERE expiresAt < NOW()');

        exec(sprintf(
            '%s %s appcms:token:cleanup 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(self::console())
        ));

        $this->pdo()->exec('DELETE FROM pim_revoked_token');
    }
}
