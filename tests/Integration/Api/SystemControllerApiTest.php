<?php
namespace Tests\Integration\Api;

use Areanet\PIM\Controller\SystemController;
use Tests\Integration\IntegrationTestCase;

/**
 * Characterization tests for `POST /system/do` — the system endpoint and the token management.
 *
 * The controller has **one** route and dispatches dynamically from there:
 *
 *     $method = $request->get('method');
 *     if(!method_exists($this, $method)){ throw new \Exception(…); }
 *     return new JsonResponse(array('method' => …, 'datetime' => …, 'message' => $this->$method($request)));
 *
 * So the request decides which method runs, and the gate is `method_exists` — not an
 * allowlist. What lies behind it is to be replaced with epic `009`; before that it has to be
 * recorded. The four token methods additionally depend on story `013-003` (JWT and
 * revocation) — what gets replaced there is described here first.
 *
 * **These tests record what is — not what should be.** Five findings from this file are
 * noted as `000-000-0015` and get fixed there; the tests on them are then to be inverted
 * deliberately.
 */
class SystemControllerApiTest extends IntegrationTestCase
{
    /** The id of the administrator — `addToken` needs a valid user. */
    private function adminId(): string
    {
        $id = $this->pdo()->query('SELECT id FROM pim_user WHERE isAdmin = 1 ORDER BY created LIMIT 1')->fetchColumn();

        $this->assertNotFalse($id, 'Precondition: the test database has an administrator');

        return (string) $id;
    }

    /** Calls `/system/do` with the given method. */
    private function systemDo(string $method, array $additional = array(), ?string $token = null): array
    {
        return $this->postJson('/system/do', array_merge(array('method' => $method), $additional), $token ?? $this->token());
    }

    /**
     * Creates an API token via `addToken` and registers the row and the log entry for cleanup.
     *
     * @return array{0:int,1:array} status, body
     */
    private function createToken(string $tokenString, string $referrer = 'https://test.example'): array
    {
        [$status, $body] = $this->systemDo('addToken', array(
            'referrer' => $referrer,
            'token'    => $tokenString,
            'user'     => $this->adminId(),
        ));

        // The lookup uses the HASH: since 013-001-0004 the column holds nothing else.
        $row = $this->pdo()->prepare('SELECT id FROM pim_token WHERE token = :t');
        $row->execute(array('t' => hash('sha256', $tokenString)));

        if ($id = $row->fetchColumn()) {
            $this->deleteAfterTest('pim_token', (string) $id);

            $log = $this->pdo()->prepare('SELECT id FROM pim_log WHERE model_name = :n AND model_id = :i');
            $log->execute(array('n' => 'PIM\\Token', 'i' => (string) $id));

            foreach ($log->fetchAll(\PDO::FETCH_COLUMN) as $logId) {
                $this->deleteAfterTest('pim_log', (string) $logId);
            }
        }

        return array($status, $body);
    }

    // ── A: The protection ──────────────────────────────────────────────────────────────

    public function testRequestWithoutTokenIsRejected(): void
    {
        [$status] = $this->postJson('/system/do', array('method' => 'listTokens'));

        $this->assertSame(401, $status);
    }

    public function testInvalidTokenIsRejected(): void
    {
        [$status] = $this->systemDo('listTokens', array(), 'this-token-does-not-exist');

        $this->assertSame(401, $status);
    }

    public function testNonAdminWithValidTokenIsRejected(): void
    {
        // The before hook requires both: a valid token AND isAdmin. The test user has a fresh
        // token — it fails on the second condition alone.
        [$token] = $this->createTestUser();

        [$statusElsewhere] = $this->get('/api/schema', $token);
        $this->assertSame(200, $statusElsewhere, 'Precondition: the token itself is valid');

        [$status] = $this->systemDo('listTokens', array(), $token);

        $this->assertSame(401, $status, 'Non-admin is rejected — since Symfony 4.4 with 401 instead of 403');
    }

    public function testHookIntentReachesClientSinceStackSwitch(): void
    {
        // **Inverted with 006-002-0006.** Up to Symfony 3.4 this test recorded that the
        // hook's intent does NOT arrive:
        //
        //     new AccessDeniedHttpException('…', null, 401)
        //
        // The third argument is the exception code, not the status code, and
        // AccessDeniedHttpException has 403 hard-wired — the client received 403.
        //
        // Under Symfony 4.4, 401 gets through. The intent of the code is fulfilled; the
        // side finding from 008-004-0003 resolved itself with the stack switch.
        $source = file_get_contents(CONTENTFLY_PROJECT_DIR.'/lib/contentfly/Classes/Controller/Provider/Base/SystemControllerProvider.php');

        $this->assertStringContainsString("AccessDeniedHttpException('Access denied', null, 401)", $source,
            'The intent in the code is 401');

        [$status] = $this->postJson('/system/do', array('method' => 'listTokens'));
        $this->assertSame(401, $status, 'And since Symfony 4.4 it also arrives');
    }

    public function testOnlyPostIsAllowed(): void
    {
        [$status] = $this->get('/system/do?method=listTokens', $this->token());

        // 405 since 000-000-0006. Before that 302: without a JSON content type the error
        // handler redirected to `/`. The MethodNotAllowedHttpException carries its code in
        // getStatusCode(), not in getCode() — which is why it did not get through before
        // even when the redirect did not apply.
        $this->assertSame(405, $status);
    }

    // ── B: The dispatch and its response shape ─────────────────────────────────────────

    public function testResponseCarriesMethodTimestampAndResult(): void
    {
        [$status, $body] = $this->systemDo('flushSchemaCache');

        $this->assertSame(200, $status);
        $this->assertSame(array('method', 'datetime', 'message'), array_keys($body),
            'Exactly three keys, in this order');
        $this->assertSame('flushSchemaCache', $body['method'], 'The requested method is mirrored back');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $body['datetime'],
            'Format Y-m-d H:i:s, without time zone');
    }

    public function testSystemEndpointUsesItsOwnResponseShape(): void
    {
        /*
         * HALF DONE — AND THAT IS WHAT THIS TEST NOW RECORDS (011-001-0002).
         *
         * Both endpoints used to bring their own shape: `/api/*` data/totalItems/version/hash,
         * `/system/do` method/datetime/message. `/api/*` has moved to the one envelope; `/system/do`
         * follows with `011-001-0004`, together with `/auth/*` and `/file/*`.
         *
         * The test stays as long as the difference does. It disappears with `0004` — and it is the
         * place that will report it if `/system/do` is forgotten there.
         */
        [$statusSystem, $system] = $this->systemDo('generateToken');
        [$statusApi, $api]       = $this->postJson('/api/list', array('entity' => 'PIM\\User'), $this->token());

        $this->assertSame(200, $statusSystem);
        $this->assertSame(200, $statusApi, 'Precondition: both calls succeed');

        $this->assertSame(array('method', 'datetime', 'message'), array_keys($system),
            '/system/do still answers in its own shape — 011-001-0004 moves it');
        $this->assertEnvelope($api, array('totalItems'));
    }

    public function testUnknownMethodEndsInHtmlErrorPage(): void
    {
        // doAction throws a bare \Exception. Silex turns it into 500 and delivers the
        // HTML error page — no JSON, although the caller requested JSON.
        // **Updated with 006-002-0006.** Up to Symfony 3.4 Silex delivered the HTML error
        // page here, although the caller had requested JSON. Since 4.4 the error handler
        // from bootstrap-web.php applies and responds with JSON — an improvement, and
        // another piece of 000-000-0006.
        [$status, $body] = $this->systemDo('noSuchMethod');

        $this->assertSame(500, $status);
        // 011-001-0003: a bare \Exception has no Messages key, so `code` is null and the text
        // stands in `detail` — the field meant for a human.
        $this->assertSame('Method noSuchMethod is not available.', $this->assertErrorEnvelope($body, null)['detail'],
            'The exception message now reaches the client as JSON');
    }

    public function testMissingMethodAlsoEndsInError(): void
    {
        // Without 'method', method_exists() receives null as its second argument — under
        // PHP 8.3 a deprecation, from PHP 9 on a TypeError. The result is 500 today as it
        // will be then, but for a different reason. Relevant for the target platform PHP 8.5.
        [$status] = $this->postJson('/system/do', array(), $this->token());

        $this->assertSame(500, $status);
    }

    /**
     * **Inverted with `000-000-0015`, not deleted.**
     *
     * The test used to record that the gate is `method_exists()` and not an allowlist: everything
     * that `method_exists()` affirmed was reachable — including `setEM` and `__construct` from
     * `BaseController`. They were called and only failed on their type check; the boundary
     * was drawn by the signature, not by the endpoint.
     *
     * Now it is drawn by an explicit list.
     */
    public function testGateIsAllowlistNotMethodExists(): void
    {
        $this->assertTrue(method_exists(SystemController::class, 'setEM'),
            'Precondition: the method still exists');

        [$statusSetEm]     = $this->systemDo('setEM');
        [$statusConstruct] = $this->systemDo('__construct');

        $this->assertSame(500, $statusSetEm, 'Rejected at the gate, not by the type');
        $this->assertSame(500, $statusConstruct);

        // The difference to before is in the message: it now comes from doAction, not from
        // a type check deep in the base class.
        [, $body] = $this->postJson('/system/do', array('method' => 'setEM'), $this->token());
        $this->assertSame('Method setEM is not available.', $this->assertErrorEnvelope($body)['detail']); // 011-001-0003
    }

    /**
     * **Inverted with `000-000-0015`, not deleted.**
     *
     * The test used to record that `doAction` calls itself and is therefore not checked for real,
     * and checked the **cause** instead of the effect: `doAction` is public, passed
     * `method_exists()` and sent the controller into endless recursion. With the default
     * limit it ended after ~0.2 s in a fatal error, **without** a limit not at all — the
     * outcome depends on a setting outside the suite, which is why it was never triggered.
     *
     * Now it can be triggered safely: the allowlist does not know `doAction`.
     * `doAction` is still public — it has to be, the router calls it as a route.
     */
    public function testDoActionNoLongerCallsItself(): void
    {
        $doAction = new \ReflectionMethod(SystemController::class, 'doAction');
        $this->assertTrue($doAction->isPublic(),
            'still public — the router calls it as a route');

        [$status, $body] = $this->postJson('/system/do', array('method' => 'doAction'), $this->token());

        $this->assertSame(500, $status);
        $this->assertSame('Method doAction is not available.', $this->assertErrorEnvelope($body)['detail'],
            'Rejected instead of calling itself'); // 011-001-0003
    }

    // ── C: The token management ────────────────────────────────────────────────────────

    public function testGenerateTokenReturnsHexCharactersAndWritesNothing(): void
    {
        // Log in first, then count: /auth/login itself creates a row in pim_token.
        $this->token();

        $before = $this->pdo()->query('SELECT COUNT(*) FROM pim_token')->fetchColumn();

        [$status, $body] = $this->systemDo('generateToken');

        $this->assertSame(200, $status);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{128}$/', $body['message'],
            '64 random bytes as hex — the value is only generated, not stored');
        $this->assertSame($before, $this->pdo()->query('SELECT COUNT(*) FROM pim_token')->fetchColumn(),
            'generateToken creates no row; only addToken does');
    }

    public function testAddTokenCreatesRowAndLogsIt(): void
    {
        $value = 'test-'.bin2hex(random_bytes(16));

        [$status, $body] = $this->createToken($value, 'https://addtoken.example');

        $this->assertSame(200, $status);
        $this->assertSame(
            array('id', 'token', 'referrer', 'user'),
            array_keys($body['message']),
            'The response carries the row including the embedded user'
        );
        $this->assertSame($value, $body['message']['token']);
        $this->assertSame('https://addtoken.example', $body['message']['referrer']);
        $this->assertSame(array('id', 'alias', 'active'), array_keys($body['message']['user']),
            'Three fields of the user are mirrored — no password, no salt');

        $row = $this->pdo()->prepare('SELECT token, referrer, user_id FROM pim_token WHERE id = :id');
        $row->execute(array('id' => $body['message']['id']));
        $found = $row->fetch(\PDO::FETCH_ASSOC);

        /*
         * INVERTED WITH 013-001-0004.
         *
         * This used to say `assertSame($value, $found['token'], 'The token is stored in plain
         * text in the table')` — recorded as what was true, with the note that it belongs to
         * story 013-001. Now a SHA-256 is stored there, and the plain text is stored nowhere.
         */
        $this->assertNotSame($value, $found['token'], 'The plain text is NOT stored in the table');
        $this->assertSame(hash('sha256', $value), $found['token'], 'but its SHA-256');
        $this->assertSame($this->adminId(), $found['user_id']);
    }

    /**
     * **Inverted with `000-000-0015`, not deleted.**
     *
     * The test used to record that `addToken` writes the log entry with a German mode instead of
     * the constant: `addToken` set `'Erstellt'`, `deleteToken` `'Gelöscht'`. `pim_log.mode` thus held two
     * vocabularies side by side, and whoever filtered by `Log::INSERTED` did not find the
     * token operations.
     *
     * **The existing data stays as it is** — deliberately. `pim_log` is a log; rewriting old
     * rows afterwards would mean altering the record. Whoever evaluates history searches for
     * the German values for token operations before this state. The note is in
     * `an_project/docs/breaking-changes.md`.
     */
    public function testAddTokenWritesLogEntryWithConstant(): void
    {
        $value = 'test-'.bin2hex(random_bytes(16));

        [, $body] = $this->createToken($value);

        $log = $this->pdo()->prepare('SELECT mode, model_name, model_label FROM pim_log WHERE model_name = :n AND model_id = :i');
        $log->execute(array('n' => 'PIM\\Token', 'i' => (string) $body['message']['id']));
        $entry = $log->fetch(\PDO::FETCH_ASSOC);

        $this->assertSame('INS', $entry['mode'], "Log::INSERTED, not 'Erstellt'");
        $this->assertSame('PIM\\Token', $entry['model_name']);
        /*
         * ALSO INVERTED WITH 013-001-0004.
         *
         * The test recorded: "The token is stored in plain text in the log — that belongs to
         * 013-003." In truth it belonged here: `pim_log` lives longer than the session it
         * describes, and a dump of the log handed over the same sessions as a dump of the
         * token table. The hash serves just as well as an identifier.
         */
        $this->assertNotSame($value, $entry['model_label'], 'No plain-text token in the log');
        $this->assertSame(hash('sha256', $value), $entry['model_label']);
    }

    /**
     * **Renamed with `000-000-0030`: `token` is no longer required.** It used to be called
     * `…RequiresReferrerTokenAndUser`. Referrer and user still are — what is missing without them
     * cannot be generated.
     */
    public function testAddTokenRequiresReferrerAndUser(): void
    {
        $this->token();

        $before = $this->pdo()->query('SELECT COUNT(*) FROM pim_token')->fetchColumn();

        [$withoutAnything] = $this->systemDo('addToken');
        [$withoutUser]     = $this->systemDo('addToken', array('referrer' => 'https://x.example', 'token' => 'test-'.bin2hex(random_bytes(16))));
        [$withoutReferrer] = $this->systemDo('addToken', array('user' => $this->adminId()));

        $this->assertSame(500, $withoutAnything);
        $this->assertSame(500, $withoutUser);
        $this->assertSame(500, $withoutReferrer);
        $this->assertSame($before, $this->pdo()->query('SELECT COUNT(*) FROM pim_token')->fetchColumn(),
            'An incomplete call leaves nothing behind');
    }

    /**
     * Finding A-5, part 1 (`000-000-0030`): the caller no longer decides alone how strong the key is.
     *
     * Until then `token=test` was accepted — and the table stores an unsalted SHA-256, which for
     * `test` is reversed offline in seconds. A supplied token now has to clear a floor, and a weak
     * one is **rejected**, not silently replaced: a caller that knows its value beforehand must
     * learn that it was not taken.
     */
    public function testAddTokenRejectsAWeakToken(): void
    {
        $this->token();

        $before = $this->pdo()->query('SELECT COUNT(*) FROM pim_token')->fetchColumn();

        $weak = array(
            'test'                  => 'short',
            str_repeat('a', 64)     => 'long, but one character',
            str_repeat('abcd', 16)  => 'long, but four characters',
            'test-'.bin2hex(random_bytes(8)) => 'random, but 21 characters',
        );

        foreach ($weak as $value => $why) {
            [$status, $body] = $this->systemDo('addToken', array(
                'referrer' => 'https://weak.example',
                'token'    => (string) $value,
                'user'     => $this->adminId(),
            ));

            $this->assertSame(400, $status, "Rejected as a client error ($why)");
            $this->assertStringContainsString('too weak', $this->assertErrorEnvelope($body)['detail'], // 011-001-0003
                "The response says why ($why)");
        }

        $this->assertSame($before, $this->pdo()->query('SELECT COUNT(*) FROM pim_token')->fetchColumn(),
            'A rejected token leaves no row behind');
    }

    /**
     * Finding A-5, the safe way (`000-000-0030`): without `token` the framework generates the value.
     *
     * It is the same one `generateToken` returns — 64 random bytes as hex — and the response carries
     * it exactly once. The test presents it afterwards: a value that is only stored but does not
     * open the API would be a key without a lock.
     */
    public function testAddTokenWithoutTokenGeneratesOneThatOpensTheApi(): void
    {
        [$status, $body] = $this->systemDo('addToken', array(
            'referrer' => 'https://generated.example',
            'user'     => $this->adminId(),
        ));

        $this->assertSame(200, $status);

        $id    = (string) $body['message']['id'];
        $value = $body['message']['token'];

        $this->deleteAfterTest('pim_token', $id);
        $log = $this->pdo()->prepare('SELECT id FROM pim_log WHERE model_name = :n AND model_id = :i');
        $log->execute(array('n' => 'PIM\\Token', 'i' => $id));
        foreach ($log->fetchAll(\PDO::FETCH_COLUMN) as $logId) {
            $this->deleteAfterTest('pim_log', (string) $logId);
        }

        $this->assertMatchesRegularExpression('/^[0-9a-f]{128}$/', $value,
            'Generated like generateToken: 64 random bytes as hex');

        $row = $this->pdo()->prepare('SELECT token FROM pim_token WHERE id = :id');
        $row->execute(array('id' => $id));
        $this->assertSame(hash('sha256', $value), $row->fetchColumn(), 'Stored as its hash, as every token');

        [$apiStatus] = $this->get('/api/schema', $value);
        $this->assertSame(200, $apiStatus, 'The generated API token opens the API');
    }

    public function testAddTokenRejectsUnknownUser(): void
    {
        // A token that clears the floor from 000-000-0030 — this test is about the user. With the
        // 21 characters it used to send, the call now ends at the token, before the user lookup.
        [$status] = $this->systemDo('addToken', array(
            'referrer' => 'https://x.example',
            'token'    => 'test-'.bin2hex(random_bytes(16)),
            'user'     => 'this-user-does-not-exist',
        ));

        $this->assertSame(500, $status);
    }

    public function testAlreadyAssignedTokenIsRejected(): void
    {
        $value = 'test-'.bin2hex(random_bytes(16));

        [$first] = $this->createToken($value);
        $this->assertSame(200, $first, 'Precondition');

        [$second] = $this->systemDo('addToken', array(
            'referrer' => 'https://secondattempt.example',
            'token'    => $value,
            'user'     => $this->adminId(),
        ));

        $this->assertSame(500, $second, 'pim_token.token is unique — the second attempt fails');
    }

    public function testListTokensShowsOnlyTokensWithReferrer(): void
    {
        // The query reads "WHERE token.referrer <> ''". Login tokens from /auth/login have
        // no referrer (NULL) and therefore do not show up — which is why this method cannot
        // reveal the token of the running test either. The separation is a side effect of
        // the query, not an explicit rule.
        $value = 'test-'.bin2hex(random_bytes(16));
        $this->createToken($value, 'https://listtokens.example');

        [$status, $body] = $this->systemDo('listTokens');

        $this->assertSame(200, $status);

        $values = array_column($body['message'], 'token');
        /*
         * Since 013-001-0004 `token` is the HASH. The token itself can no longer be looked
         * up — not even by the operator. Whoever holds one hashes it themselves and finds
         * their row that way.
         */
        $this->assertNotContains($value, $values, 'The plain text is not delivered');
        $this->assertContains(hash('sha256', $value), $values, 'its hash identifies the row');
        $this->assertNotContains($this->token(), $values, 'The login token of the test run does not show up');

        foreach ($body['message'] as $entry) {
            $this->assertSame(array('id', 'token', 'referrer', 'user'), array_keys($entry));
            $this->assertNotSame('', $entry['referrer']);
        }
    }

    /**
     * The referrer token still works (013-001-0004).
     *
     * It is the second way into the API — a permanent key without timeout that an operator
     * chooses and stores via `addToken`. When hashing the token table, exactly this is the
     * case that is easy to overlook: its value comes from the caller and not from the
     * constructor, so it is set via `setToken()` instead of being generated on creation.
     */
    public function testReferrerTokenStillOpensApi(): void
    {
        $value = 'test-'.bin2hex(random_bytes(16));
        $this->createToken($value, 'https://referrer.example');

        [$status] = $this->get('/api/schema', $value);

        $this->assertSame(200, $status, 'The self-chosen API token is accepted');
    }

    /**
     * **Inverted with `000-000-0015`, not deleted.**
     *
     * The test used to record that `deleteToken` is unusable because of a wrong namespace. The
     * method looked in `Areanet\Contently\Entity\Token` — "Contently" instead of "PIM", the
     * only place in the whole tree with that name. Doctrine did not know the class, and the
     * method ended before its first functional line: **an API token could not be got rid of
     * via the API.**
     *
     * The old test literally demanded the inversion — "then the row has to disappear and a
     * log entry with `Log::DELETED` has to be created". Exactly that is what is here.
     *
     * The rest of the method **never** ran until then. So the test checks not only the
     * repository call, but what comes after it.
     */
    public function testDeleteTokenRemovesRowAndLogsIt(): void
    {
        $value = 'test-'.bin2hex(random_bytes(16));

        [, $body] = $this->createToken($value);
        $id       = $body['message']['id'];

        [$status] = $this->systemDo('deleteToken', array('id' => $id));
        $this->assertSame(200, $status);

        $row = $this->pdo()->prepare('SELECT COUNT(*) FROM pim_token WHERE id = :id');
        $row->execute(array('id' => $id));
        $this->assertSame('0', (string) $row->fetchColumn(), 'The row is gone');

        $log = $this->pdo()->prepare(
            'SELECT mode, model_label FROM pim_log WHERE model_name = :n AND model_id = :i AND mode = :m'
        );
        $log->execute(array('n' => 'PIM\\Token', 'i' => (string) $id, 'm' => 'DEL'));
        $entry = $log->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($entry, 'and a log entry with Log::DELETED exists');
        $this->assertSame(hash('sha256', $value), $entry['model_label'], 'with the hash as identifier');
    }

    public function testDeleteTokenReportsUnknownToken(): void
    {
        // The second half the task requires: the case "token unknown". Before, it never ran
        // for the same reason — the method did not even get as far as the check.
        [$status, $body] = $this->postJson(
            '/system/do',
            array('method' => 'deleteToken', 'id' => 'doesnotexist-'.bin2hex(random_bytes(4))),
            $this->token()
        );

        $this->assertSame(500, $status);
        $this->assertSame('Invalid token', $this->assertErrorEnvelope($body)['detail']); // 011-001-0003
    }

    public function testLoginTokenOfTestRunSurvivesTokenMethods(): void
    {
        // The guarantee this file gives to the rest of the suite: whatever happens to tokens
        // here does not affect the token of the running test.
        $this->systemDo('generateToken');
        $this->systemDo('listTokens');
        $this->createToken('test-'.bin2hex(random_bytes(16)));

        [$status] = $this->get('/api/schema', $this->token());

        $this->assertSame(200, $status, 'The login token is still valid');
    }

    /**
     * The cleanup path for item 5 of `000-000-0015`.
     *
     * `pim_token` grew without limit: cleanup only happened lazily, when an expired token was
     * presented once more. Whoever closes the browser leaves a row behind forever.
     *
     * **The task left open whether `013-003` replaces the table anyway. It does not:** the
     * story explicitly keeps the opaque DB token as refresh token. So the cleanup run is
     * needed — `appcms:token:cleanup`.
     *
     * The check goes through the database, not through the command call: the suite runs
     * against a test server, the command in a process of its own. What counts is the
     * calculation it decides by — and that is the same as in `checkToken()`.
     */
    public function testExpiredLoginTokensCanBeCleanedUp(): void
    {
        $userId = $this->pdo()->query("SELECT id FROM pim_user WHERE alias = 'admin'")->fetchColumn();

        // An expired login token (no referrer) and an API token (with referrer).
        $expired  = 'old-'.bin2hex(random_bytes(16));
        $apiToken = 'api-'.bin2hex(random_bytes(16));

        // pim_token.id is an integer column with auto-increment — unlike the entities that
        // inherit from Base and carry a GUID. The id therefore comes from MySQL.
        $insert = $this->pdo()->prepare(
            'INSERT INTO pim_token (user_id, token, referrer, created, modified)'
            .' VALUES (:u, :t, :r, :c, :m)'
        );
        $old = (new \DateTime('-30 days'))->format('Y-m-d H:i:s');

        $insert->execute(array('u' => $userId, 't' => $expired, 'r' => null, 'c' => $old, 'm' => $old));
        $idExpired = $this->pdo()->lastInsertId();

        $insert->execute(array('u' => $userId, 't' => $apiToken, 'r' => 'https://example.invalid', 'c' => $old, 'm' => $old));
        $idApi = $this->pdo()->lastInsertId();

        $this->deleteAfterTest('pim_token', $idExpired);
        $this->deleteAfterTest('pim_token', $idApi);

        $output = array();
        exec(
            sprintf('%s %s appcms:token:cleanup 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg(self::console())),
            $output
        );

        $count = $this->pdo()->prepare('SELECT COUNT(*) FROM pim_token WHERE id = :id');

        $count->execute(array('id' => $idExpired));
        $this->assertSame('0', (string) $count->fetchColumn(),
            'The expired login token is gone — '.implode(' ', $output));

        $count->execute(array('id' => $idApi));
        $this->assertSame('1', (string) $count->fetchColumn(),
            'The API token with referrer stays: it does not expire over time');
    }

    public function testEveryLoginCreatesRowThatOnlyExpiresOnNextUse(): void
    {
        // Belongs here because pim_token is the table this controller manages — and because
        // listTokens explicitly does **not** show it (no referrer).
        //
        // /auth/login creates one row per login. Cleanup only happens lazily, in the opaque
        // branch of the token handler: if an expired token is presented once more, it
        // disappears. A token nobody uses again — the normal case when closing the
        // browser — stays forever. There is no cleanup run, no console command and no
        // endpoint for it.
        //
        // UPDATED WITH 013-002-0004: until then the lazy deletion branch was in
        // BaseControllerProvider::checkToken(). The method is gone; the branch lives on
        // unchanged in Classes/Security/TokenHandler.php.
        //
        // Finding, noted in 000-000-0015. Story 013-003 (JWT and revocation) probably solves
        // the problem anyway; until then it is recorded.
        $before = (int) $this->pdo()->query('SELECT COUNT(*) FROM pim_token')->fetchColumn();

        $fresh = $this->login();

        $after = (int) $this->pdo()->query('SELECT COUNT(*) FROM pim_token')->fetchColumn();
        $this->assertSame($before + 1, $after, 'The login leaves a row behind');

        // The lookup uses the HASH. The plain text used to be here — a leftover that
        // 013-001-0004 overlooked. Since then the query found NOTHING, `fetch()` returned
        // `false`, and `$found['referrer']` was therefore null: the assertion below held
        // without checking anything. It was only noticed when the source probe at the end of
        // this test turned red because of 013-002-0004.
        $row = $this->pdo()->prepare('SELECT id, referrer FROM pim_token WHERE token = :t');
        $row->execute(array('t' => hash('sha256', $fresh)));
        $found = $row->fetch(\PDO::FETCH_ASSOC);

        $this->assertIsArray($found, 'The row of the login can be found');
        $this->deleteAfterTest('pim_token', (string) $found['id']);

        $this->assertNull($found['referrer'],
            'Without referrer — therefore it is subject to the timeout and does not show up in listTokens');

        $source = file_get_contents(CONTENTFLY_PROJECT_DIR.'/lib/contentfly/Classes/Security/TokenHandler.php');
        $this->assertStringContainsString('$this->em->remove($row);', $source,
            'Removal happens only in the opaque branch — so only when the token is presented again');
    }

    // ── flushSchemaCache ───────────────────────────────────────────────────────────────

    public function testFlushSchemaCacheReportsSuccessAndLeavesSchemaReadable(): void
    {
        [$status, $body] = $this->systemDo('flushSchemaCache');

        $this->assertSame(200, $status);
        $this->assertSame('Schema cache cleared!', $body['message']);

        [$statusSchema] = $this->get('/api/schema', $this->token());
        $this->assertSame(200, $statusSchema, 'The schema is rebuilt afterwards');
    }

    public function testFlushSchemaCacheReallyClearsQueryCache(): void
    {
        // NEW WITH 010-002-0003, because the two tests above and below only half assert the
        // method: they check the message and the file data/cache/schema.cache — not the
        // Doctrine caches the method is actually about. A change from deleteAll() to clear()
        // would not have been noticed by them, and neither would a call that no longer
        // clears anything.
        //
        // The directory is observed, not the cache itself: the test run sees the application
        // only via HTTP, and with the shipped configuration the query cache lives under
        // data/cache/query. The metadata cache is left out — it writes nothing, and for a
        // reason of its own (010-002-0005).
        //
        // THE CHECK IS THAT NO FILE FROM BEFORE SURVIVES — not that the directory is empty
        // afterwards. The difference is measured: after the flush there are files again, but
        // different ones. The request continues after the action and runs queries again;
        // demanding an empty directory would attribute something to the endpoint that it
        // does not promise at all.
        $directory = self::dataDir().'/cache/query';

        // Put something into the cache: /api/list runs a DQL query.
        $this->postJson('/api/list', array('entity' => 'PIM\\User'), $this->token());

        $before = $this->listFiles($directory);
        $this->assertNotEmpty($before, 'Precondition: the query cache holds entries');

        $this->systemDo('flushSchemaCache');

        $survived = array_intersect($before, $this->listFiles($directory));

        $this->assertSame(array(), array_values($survived),
            'None of the files from before survived the flush');
    }

    /** Lists the files below a directory; if it is missing, the list is empty. */
    private function listFiles(string $directory): array
    {
        if (!is_dir($directory)) {
            return array();
        }

        $files = array();

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)) as $entry) {
            if ($entry->isFile()) {
                $files[] = $entry->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    public function testFlushSchemaCacheReportsSuccessEvenWhenNothingToDelete(): void
    {
        // The method clears two things: the file data/cache/schema.cache and the Doctrine
        // caches. But the file is only created when APP_ENABLE_SCHEMA_CACHE is on — and the
        // template switches it off. Under the shipped configuration the first part therefore
        // has no effect; the message still reads "cleared!" unchanged.
        $template = file_get_contents(CONTENTFLY_PROJECT_DIR.'/custom/config.php');

        $this->assertMatchesRegularExpression(
            '/APP_ENABLE_SCHEMA_CACHE\s*=\s*false;/',
            $template,
            'Precondition: the template switches the schema cache off'
        );

        $this->assertFileDoesNotExist(self::dataDir().'/cache/schema.cache');

        [, $body] = $this->systemDo('flushSchemaCache');

        $this->assertSame('Schema cache cleared!', $body['message'],
            'The message does not say whether there was anything at all');
    }

    // ── D: The emergency lock ──────────────────────────────────────────────────────────

    /**
     * **Inverted with `000-000-0015`, not deleted.**
     *
     * The test used to record that `validateORM` is on the exception list but does not exist: the
     * emergency lock let `validateORM` and `updateDatabase` through **without token and
     * without admin rights** — and the first of the two did not exist in the controller. An
     * exception into the void.
     *
     * **Removed instead of restored.** Doctrine would bring everything needed with
     * `SchemaValidator`, and the import is still at the top of the file — but a restored
     * method would be a second endpoint without token and without admin rights. An emergency
     * lock should be as small as possible.
     */
    public function testEmergencyLockOnlyKnowsUpdateDatabase(): void
    {
        $this->assertFalse(method_exists(SystemController::class, 'validateORM'),
            'validateORM still does not exist');

        $source = file_get_contents(CONTENTFLY_PROJECT_DIR.'/lib/contentfly/Classes/Controller/Provider/Base/SystemControllerProvider.php');
        $this->assertStringNotContainsString("== 'validateORM'", $source,
            'and is no longer in the exception list');
        // The notation changed with 009-003-0001 — Request::get() is deprecated in
        // Symfony 7.4, the value is now read from the request bag. The condition itself is
        // the same, and it is exactly what is meant.
        $this->assertStringContainsString("all()['method'] ?? null) == 'updateDatabase'", $source,
            'updateDatabase stays — a broken schema must be repairable');

        [$status] = $this->systemDo('validateORM');
        $this->assertSame(500, $status, 'and is rejected as an unknown method');
    }

    public function testEmergencyLockOnlyTriggersOnInvalidFieldNameException(): void
    {
        // The exception branch hangs on exactly one Doctrine exception: if a column that
        // checkToken() reads goes missing, an InvalidFieldNameException occurs — and then
        // updateDatabase is reachable **without token and without admin rights** (since
        // 000-000-0015 only this one method; validateORM was listed here as well and did not
        // exist). The purpose is recognizable (a broken schema must stay repairable without
        // being able to log in); the price is an unprotected write operation on the schema.
        //
        // **Why there is no live test here:** to trigger the branch, the test would have to
        // damage the schema of the shared test database on purpose — and then hope that
        // updateDatabase restores it completely. If it fails halfway, the database is broken
        // for every following test. The test net should create trust, not put the
        // environment at risk; so the condition is recorded here and the effect is not
        // forced.
        //
        // Whoever implements epic 009 finds in this test what the new kernel has to either
        // replicate or deliberately drop.
        $source = file_get_contents(CONTENTFLY_PROJECT_DIR.'/lib/contentfly/Classes/Controller/Provider/Base/SystemControllerProvider.php');

        $this->assertStringContainsString('catch(InvalidFieldNameException $e)', $source,
            'Only this one exception opens the emergency lock');
        $this->assertStringContainsString('throw $e;', $source,
            'Every other method keeps flying — the lock only opens for this one');
    }
}
