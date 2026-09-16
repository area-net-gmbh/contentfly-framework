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
 * `/api/translations` is missing from the list, and deliberately: the template configures no
 * languages, so the endpoint answers with an error and has no success case to read
 * (`TreeApiTest::testTranslationsForEntityWithoutI18nThrows` records that).
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

    public function testAnEmptySetIsAnEmptyListAndNotAnEmptyBody(): void
    {
        /*
         * INVERTED WITH 011-001-0002 for /api/all, which answered `204` — no body, and so no
         * envelope. `/api/list` gave that up with `000-000-0014`. The two halves of the sync
         * contract now behave the same, and a client needs no special case for "nothing there".
         *
         * PIM\Nav and PIM\Folder are empty after a fresh installation.
         */
        foreach (array('PIM\\Nav', 'PIM\\Folder') as $entity) {
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
