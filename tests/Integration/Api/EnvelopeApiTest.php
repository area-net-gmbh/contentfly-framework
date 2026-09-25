<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * ONE READER FOR EVERY `/api/*` ENDPOINT — the acceptance test of `011-001-0002`.
 *
 * The other test classes each check their own endpoint and, along the way, that it answers in the
 * envelope. That is not the same claim. The claim of Epic `011` is that **a client can evaluate
 * every endpoint with the same code** — and that only shows if one piece of code walks all of them.
 *
 * So this test has no knowledge of any single endpoint. It calls each one, hands the response to
 * one reader, and the reader knows nothing but `data`, `errors` and `meta`. A new endpoint that
 * brings a shape of its own fails here, not in one of the twelve classes that each look at one.
 *
 * SINCE `011-001-0004` IT COVERS THE WHOLE SCOPE — `/api/*`, `/auth/*`, `/file/*` and `/system/do`,
 * in the success case and in the error case. That is the acceptance of Epic `011`'s promise, and
 * the reason it is one test and not twenty: twenty tests each prove that one endpoint has a shape,
 * not that all of them have the SAME one.
 *
 * `/api/translations` is missing from the list, and deliberately: the template configures no
 * languages, so the endpoint answers with an error and has no success case to read
 * (`TreeApiTest::testTranslationsForEntityWithoutI18nThrows` records that). `/file/get` is not in
 * scope at all — it delivers the file itself, not JSON.
 */
class EnvelopeApiTest extends IntegrationTestCase
{
    /** @var list<string> Ids created here, for cleanup. */
    private array $created = array();

    protected function tearDown(): void
    {
        foreach ($this->created as $id) {
            $this->pdo()->prepare('DELETE FROM pim_log WHERE model_id = :id')->execute(array('id' => $id));
            $this->pdo()->prepare('DELETE FROM pim_tag WHERE id = :id')->execute(array('id' => $id));
        }

        /*
         * THE REVOCATION THIS TEST CREATES (000-000-0050).
         *
         * `/auth/logout` on a JWT session writes a row into `pim_revoked_token` — that is the
         * purpose of the revocation, not a side effect. This class left it lying around from
         * `011-001-0004` on, and `AuthApiTest` counted the table. One seed in four came out in the
         * wrong order, and the suite was red without anyone touching it.
         *
         * A test that leaves state behind is a test that breaks other tests — visible only in an
         * order nobody chose.
         */
        $this->pdo()->exec('DELETE FROM pim_revoked_token');

        $this->created = array();

        parent::tearDown();
    }

    /**
     * The reader. It knows the envelope and nothing else — that is the point.
     *
     * @param array<string,mixed> $body
     * @return array{0:mixed,1:string} payload and framework version
     */
    private function read(array $body): array
    {
        $this->assertArrayHasKey('data', $body);
        $this->assertArrayHasKey('errors', $body);
        $this->assertArrayHasKey('meta', $body);
        $this->assertNull($body['errors']);
        $this->assertArrayHasKey('version', $body['meta']);

        return array($body['data'], $body['meta']['version']);
    }

    public function testEveryApiEndpointIsReadableWithTheSameCode(): void
    {
        $token = $this->token();

        // One object, so that the endpoints that need an id have one.
        [$insertStatus, $insertBody] = $this->postJson(
            '/api/insert',
            array('entity' => 'PIM\\Tag', 'data' => array('title' => 'Envelope-'.bin2hex(random_bytes(6)))),
            $token
        );
        $this->assertSame(200, $insertStatus, 'Precondition: creating succeeds');

        [$created] = $this->read($insertBody);
        $id = $created['id'];
        $this->created[] = $id;

        $calls = array(
            'GET /api/config'       => array('GET',  '/api/config',       array()),
            'GET /api/schema'       => array('GET',  '/api/schema',       array()),
            'POST /api/all'         => array('POST', '/api/all',          array()),
            'POST /api/count'       => array('POST', '/api/count',        array('entity' => 'PIM\\Tag')),
            'POST /api/deleted'     => array('POST', '/api/deleted',      array()),
            'POST /api/list'        => array('POST', '/api/list',         array('entity' => 'PIM\\Tag')),
            'POST /api/list (page)' => array('POST', '/api/list',         array('entity' => 'PIM\\Tag', 'currentPage' => 1)),
            'POST /api/list (count)'=> array('POST', '/api/list',         array('entity' => 'PIM\\Tag', 'count' => true)),
            'POST /api/single'      => array('POST', '/api/single',       array('entity' => 'PIM\\Tag', 'id' => $id)),
            'POST /api/query'       => array('POST', '/api/query',        array('select' => 'id', 'from' => 'PIM\\Tag')),
            'POST /api/tree'        => array('POST', '/api/tree',         array('entity' => 'PIM\\Folder')),
            'POST /api/tree2'       => array('POST', '/api/tree2',        array('entity' => 'PIM\\Folder')),
            'POST /api/multiupdate' => array('POST', '/api/multiupdate',  array('objects' => array(
                array('entity' => 'PIM\\Tag', 'id' => $id, 'data' => array('views' => 1)),
            ))),
            'POST /api/update'      => array('POST', '/api/update',       array('entity' => 'PIM\\Tag', 'id' => $id, 'data' => array('views' => 2))),
            'POST /api/delete'      => array('POST', '/api/delete',       array('entity' => 'PIM\\Tag', 'id' => $id)),
        );

        $versions = array();

        foreach ($calls as $name => [$method, $path, $payload]) {
            [$status, $body] = $method === 'GET'
                ? array_slice($this->getAsJson($path, $token), 0, 2)
                : $this->postJson($path, $payload, $token);

            $this->assertSame(200, $status, $name.' answers with 200');

            [, $version] = $this->read($body);
            $versions[$name] = $version;
        }

        $this->assertCount(15, $versions, 'Every endpoint of the list was read');
        $this->assertCount(1, array_unique($versions),
            'And every one of them names the same framework version — the meta is the same everywhere');
    }

    /**
     * `/auth/*`, `/file/*` and `/system/do` — the endpoints `011-001-0004` brought in.
     *
     * They are in their own test because each of them needs its own preparation: a login that is
     * not the cached one, a file on disk, an admin token. The reader is the same one.
     */
    public function testTheRemainingEndpointsAreReadableWithTheSameCode(): void
    {
        $token = $this->token();

        // ── /auth/login, and the session it hands over
        [$loginStatus, $loginBody] = $this->postJson('/auth/login', array(
            'alias' => 'admin', 'pass' => $this->pass(), 'tokenType' => 'jwt',
        ));
        $this->assertSame(200, $loginStatus);

        [$session] = $this->read($loginBody);
        $this->assertArrayHasKey('token', $session, 'The login hands over the session as payload');
        $this->assertArrayNotHasKey('message', $session, 'The sentence "Login successful" is gone');

        // ── /auth/refresh
        [$refreshStatus, $refreshBody] = $this->postJson('/auth/refresh', array(
            'refreshToken' => $session['refreshToken'],
        ));
        $this->assertSame(200, $refreshStatus);
        [$refreshed] = $this->read($refreshBody);
        $this->assertArrayHasKey('token', $refreshed);

        // ── /file/upload
        $upload = $this->uploadForEnvelope('envelope.txt', "envelope\n", $token);
        [$file] = $this->read($upload);
        $this->assertNotEmpty($file['id']);

        // ── /file/overwrite
        $second = $this->uploadForEnvelope('envelope.txt', "second\n", $token);
        [$secondFile] = $this->read($second);

        [$overwriteStatus, $overwriteBody] = $this->postJson(
            '/file/overwrite',
            array('sourceId' => $secondFile['id'], 'destId' => $file['id']),
            $token
        );
        $this->assertSame(200, $overwriteStatus);
        $this->assertSame(array('sourceId' => $secondFile['id'], 'destId' => $file['id']), $this->read($overwriteBody)[0]);

        // ── /system/do
        [$systemStatus, $systemBody] = $this->postJson('/system/do', array('method' => 'generateToken'), $token);
        $this->assertSame(200, $systemStatus);
        $this->assertSame(array('method', 'message'), array_keys($this->read($systemBody)[0]));

        // ── /auth/logout — nothing to hand back, so the payload is null
        [$logoutStatus, $logoutRaw] = $this->get('/auth/logout', $session['token']);
        $this->assertSame(200, $logoutStatus);
        $this->assertNull($this->read(json_decode($logoutRaw, true))[0]);
    }

    /**
     * THE PROOF OF THE EPIC, IN ONE LOOP: eleven endpoints from four route groups, success and
     * failure, one reader — and it knows `data`, `errors`, `meta` and nothing else.
     *
     * A client can therefore decide FIRST whether it holds a fault and only then look at the
     * payload, without knowing beforehand which endpoint answered or how it went. Before Epic
     * `011` that was impossible: seven success shapes, an eighth for errors, and which keys the
     * eighth carried depended on the exception class.
     */
    public function testSuccessAndFailureAreTheSameShapeEverywhere(): void
    {
        $token = $this->token();

        $cases = array(
            // path, payload, token, expect success?
            array('/api/list',    array('entity' => 'PIM\\Tag'),         $token, true),
            array('/api/list',    array('entity' => 'PIM\\DoesNotExist'), $token, false),
            array('/api/single',  array('entity' => 'PIM\\Tag', 'id' => 'nope'), $token, false),
            array('/api/count',   array('entity' => 'PIM\\Tag'),         $token, true),
            array('/auth/login',  array('alias' => 'admin', 'pass' => $this->pass()), null, true),
            array('/auth/login',  array('alias' => 'admin', 'pass' => 'wrong'),       null, false),
            array('/auth/refresh', array('refreshToken' => 'nothing'),     null, false),
            array('/system/do',   array('method' => 'generateToken'),      $token, true),
            array('/system/do',   array('method' => 'doesNotExist'),       $token, false),
            array('/file/overwrite', array('sourceId' => 'x', 'destId' => 'y'), $token, false),
        );

        foreach ($cases as [$path, $payload, $with, $succeeds]) {
            [, $body] = $this->postJson($path, $payload, $with);
            $label    = $path.' '.($succeeds ? 'success' : 'failure');

            // Three keys, always, in this order — for every one of them.
            $this->assertSame(array('data', 'errors', 'meta'), array_keys($body), $label);

            if ($succeeds) {
                $this->assertNull($body['errors'], $label);
            } else {
                $this->assertNull($body['data'], $label);
                $this->assertSame(array('code', 'detail', 'type', 'context'), array_keys($body['errors'][0]), $label);
            }

            // And the same meta everywhere, whatever happened.
            $this->assertSame(array('ts', 'version', 'projectVersion', 'hash'),
                array_slice(array_keys($body['meta']), 0, 4), $label);
        }

        // The eleventh: a GET, so that the shape does not hang on the method either.
        [, $config] = $this->get('/api/config');
        $this->assertSame(array('data', 'errors', 'meta'), array_keys(json_decode($config, true)));
    }

    /** Uploads a file over HTTP and registers it for cleanup. */
    private function uploadForEnvelope(string $name, string $content, string $token): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cf-envelope-');
        file_put_contents($tmp, $content);

        $ch = curl_init(self::$baseUrl.'/file/upload');
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => array('file' => new \CURLFile($tmp, 'text/plain', $name)),
            CURLOPT_HTTPHEADER     => array('appcms-token: '.$token),
        ));
        $raw = (string) curl_exec($ch);
        unlink($tmp);

        $body = json_decode($raw, true) ?: array();

        if (isset($body['data']['id'])) {
            $this->deleteAfterTest('pim_file', $body['data']['id']);
            $this->deleteDirectoryAfterTest(self::dataDir().'/files/'.$body['data']['id']);
        }

        return $body;
    }

    public function testAnEmptySetIsAnEmptyListAndNotAnEmptyBody(): void
    {
        /*
         * INVERTED WITH 011-001-0002 for /api/all, which answered `204` — no body, and so no
         * envelope. `/api/list` gave that up with `000-000-0014`. The two halves of the sync
         * contract now behave the same, and a client needs no special case for "nothing there".
         *
         * PIM\Folder is empty after a fresh installation. PIM\Nav was the second example until
         * 000-000-0077 removed it.
         */
        foreach (array('PIM\\Folder') as $entity) {
            [$status, $body] = $this->postJson('/api/list', array('entity' => $entity), $this->token());

            $this->assertSame(200, $status, $entity);
            $this->assertSame(array(), $this->read($body)[0], $entity);
        }

        [$statusTree, $tree] = $this->postJson('/api/tree', array('entity' => 'PIM\\Folder'), $this->token());

        $this->assertSame(200, $statusTree);
        $this->assertSame(array(), $this->read($tree)[0]);
    }

    public function testTheMetaCarriesBothVersions(): void
    {
        /*
         * `/api/config` handed in `APP_VERSION.'/'.CUSTOM_VERSION` and renderResponse() overwrote
         * the key right afterwards with APP_VERSION alone — the project version never reached a
         * client (found in 011-001-0001). Two fields now, so that nobody has to split a string.
         */
        [$status, $body] = $this->getAsJson('/api/config', null);

        $this->assertSame(200, $status);
        $this->assertSame(APP_VERSION, $body['meta']['version']);
        $this->assertSame(CUSTOM_VERSION, $body['meta']['projectVersion']);
        $this->assertNotSame($body['meta']['version'], $body['meta']['projectVersion'],
            'Framework and project carry different version numbers — a single string hid that');
    }

    /** @return array{0:int,1:array,2:string} */
    private function getAsJson(string $path, ?string $token): array
    {
        [$status, $raw, $headers] = $this->get($path, $token);

        return array($status, json_decode($raw, true) ?: array(), $headers);
    }
}
