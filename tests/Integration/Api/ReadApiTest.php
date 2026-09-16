<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * Characterization tests for the reading endpoints `/api/single` and `/api/list`.
 *
 * **Characterization means: record what is** — including the questionable. Two of the
 * oddities recorded here have since been fixed and their assertions deliberately inverted:
 * an access without a token ends with 401 instead of 500 since the stack switch
 * (`006-002-0003`), and an unknown id returns a 404 since `000-000-0006` instead of a 200 with
 * an empty `headers` object. What remains still describes the current state that epic `009`
 * has to reproduce during the kernel swap.
 *
 * Test data is created via `pdo()`, not via the write endpoints: story `008-001` must not
 * depend on `008-002`, and a precondition via the unverified write path would undermine the
 * significance of the read tests.
 */
class ReadApiTest extends IntegrationTestCase
{
    private string $tagA = '';
    private string $tagB = '';
    private string $adminId = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminId = (string) $this->pdo()
            ->query("SELECT id FROM pim_user WHERE alias = 'admin'")
            ->fetchColumn();

        // The ids are deliberately assigned opposite to the titles: 'a-…' carries 'Zeta',
        // 'z-…' carries 'Alpha'. This way the two possible sort orders — by id descending
        // and by title ascending — produce clearly different results.
        $run = bin2hex(random_bytes(6));
        $this->tagB = $this->createTag('a-'.$run, 'Zeta',  null);
        $this->tagA = $this->createTag('z-'.$run, 'Alpha', $this->adminId);
    }

    private function createTag(string $id, string $title, ?string $userCreated): string
    {

        $this->pdo()->prepare(
            'INSERT INTO pim_tag (id, title, created, modified, views, isIntern, usercreated_id)
             VALUES (:id, :title, NOW(), NOW(), 0, 0, :uc)'
        )->execute(array('id' => $id, 'title' => $title, 'uc' => $userCreated));

        $this->deleteAfterTest('pim_tag', $id);

        return $id;
    }

    // ── /api/single ────────────────────────────────────────────────────────────────────

    public function testSingleReturnsTheObjectInTheOneEnvelope(): void
    {
        [$status, $body] = $this->postJson(
            '/api/single',
            array('entity' => 'PIM\\Tag', 'id' => $this->tagA),
            $this->token()
        );

        $this->assertSame(200, $status);
        // Until 011-001-0002 this test said "without totalItems, unlike /api/list". There is
        // nothing left to say that about — both answer in the same envelope now, and what one has
        // and the other has not is a meta key, not a different shape.
        $tag = $this->assertEnvelope($body);
        $this->assertSame($this->tagA, $tag['id']);
        $this->assertSame('Alpha', $tag['title']);
    }

    public function testDateFieldsComeAsGroupOfFour(): void
    {
        [, $body] = $this->postJson(
            '/api/single',
            array('entity' => 'PIM\\Tag', 'id' => $this->tagA),
            $this->token()
        );

        foreach (array('created', 'modified') as $field) {
            $this->assertSame(
                array('LOCAL_TIME', 'LOCAL', 'ISO8601', 'TIMESTAMP'),
                array_keys($body['data'][$field]),
                "Every datetime field comes as these four representations ($field)"
            );
            $this->assertIsInt($body['data'][$field]['TIMESTAMP']);
        }
    }

    public function testNestedObjectCarriesAllProperties(): void
    {
        // Since 012-005-0003 nested objects are no longer restricted to the list columns
        // of the deleted UI; the only limit is DB_NESTED_LEVELS.
        [, $body] = $this->postJson(
            '/api/single',
            array('entity' => 'PIM\\Tag', 'id' => $this->tagA),
            $this->token()
        );

        $joined = $body['data']['userCreated'];

        $this->assertSame($this->adminId, $joined['id']);
        foreach (array('alias', 'isActive', 'isAdmin', 'isIntern', 'loginManager') as $field) {
            $this->assertArrayHasKey($field, $joined,
                "Nested objects return all properties, not just the id ($field)");
        }
        $this->assertArrayNotHasKey('pass', $joined, 'The password hash is not delivered');
    }

    public function testUnknownIdReturns404(): void
    {
        // Inverted with 000-000-0006. Previously this test recorded that the endpoint
        // responds with 200 and `data: {"headers": {}}` — the artefact of a JsonResponse
        // that Api::getSingle() returned as "not found" and singleAction() passed on as
        // payload.
        [$status, $body] = $this->postJson(
            '/api/single',
            array('entity' => 'PIM\\Tag', 'id' => 'doesnotexist'),
            $this->token()
        );

        $this->assertSame(404, $status);
        $this->assertSame('contentfly_general_not_found', $body['message']);
        $this->assertArrayNotHasKey('data', $body);
    }

    public function testUnknownEntityReturns404(): void
    {
        // Also 000-000-0006: the exception is not translated into an API response.
        [$status] = $this->postJson(
            '/api/single',
            array('entity' => 'PIM\\DoesNotExist', 'id' => 'irrelevant'),
            $this->token()
        );

        $this->assertSame(404, $status,
            'Since 006-002-0003 the intended code — Symfony 4.4 fixes 000-000-0006 here');
    }

    public function testSingleWithoutTokenReturns401(): void
    {
        // The access protection works, only the response is wrong: 500 instead of 401.
        // Already recorded this way in 012-004-0003, repeated here for /api/single.
        [$status, $body] = $this->postJson(
            '/api/single',
            array('entity' => 'PIM\\Tag', 'id' => $this->tagA)
        );

        $this->assertSame(401, $status, 'Since the stack switch (006-002-0003) the intended code — Symfony 4.4 fixes 000-000-0006 here');
        $this->assertArrayNotHasKey('data', $body, 'Without a token no data flows');
    }

    // ── /api/list ──────────────────────────────────────────────────────────────────────

    public function testListReturnsTheSameEnvelopeAsSingle(): void
    {
        /*
         * INVERTED WITH 011-001-0002, including its name. It used to record the inconsistency:
         * "list carries totalItems but no ts — single the other way round". That was exactly the
         * finding of `000-000-0014`, and it is what this story removes. What list has beyond
         * single is now `totalItems` in the meta — one key more, not another shape.
         */
        [$status, $body] = $this->postJson('/api/list', array('entity' => 'PIM\\Tag'), $this->token());

        $this->assertSame(200, $status);
        $this->assertEnvelope($body, array('totalItems'));
        $this->assertGreaterThanOrEqual(2, $body['meta']['totalItems']);
    }

    public function testListWithoutOrderParameterSortsByIdDescending(): void
    {
        // Api::getList() does NOT evaluate the entity's sortBy/sortOrder. Without `order` in
        // the request it stays at `ORDER BY id DESC`. PIM\Tag carries sortBy="title",
        // sortOrder="ASC" — without effect on this response.
        //
        // 000-000-0013 decided that it STAYS THIS WAY, and the reasoning is there:
        // `id` is unique, `created` (the default for every entity without its own setting) is
        // not — applying the sort order would have swapped a stable paging order for an
        // unstable one, silently, for every client that sends no `order`. Whoever wants the
        // declared order reads it from the schema and sends it along as `order`.
        [, $body] = $this->postJson('/api/list', array('entity' => 'PIM\\Tag'), $this->token());

        $ids = array_values(array_intersect(
            array_column($body['data'], 'id'),
            array($this->tagA, $this->tagB)
        ));

        $this->assertSame(array($this->tagA, $this->tagB), $ids,
            'Without an order parameter the list sorts by id descending — the entity settings '
            .'sortBy/sortOrder are ignored');
    }

    public function testSortByAndSortOrderAreInTheSchemaButDoNotAffectTheResponse(): void
    {
        // Recorded because 012-005-0002 kept these two fields as "sort order of the
        // API responses". They are in the schema and a client can read them, but no reader
        // in the framework applies them — unlike sortRestrictTo, which
        // JoinBidirectionalType actually evaluates.
        //
        // The reasoning from 012-005-0002 was corrected with 000-000-0013 in
        // an_project/docs/pim-annotationen-migration.md: they are hints for the client, not
        // the sort order of the API responses.
        [, $schema] = $this->postJson('/api/list', array('entity' => 'PIM\\Tag'), $this->token());
        [$status, $raw] = $this->get('/api/schema', $this->token());

        $this->assertSame(200, $status);
        $settings = json_decode($raw, true)['data']['PIM\\Tag']['settings'];

        $this->assertSame('title', $settings['sortBy']);
        $this->assertSame('ASC', $settings['sortOrder']);

        $titles = array_values(array_intersect(
            array_column($schema['data'], 'title'),
            array('Alpha', 'Zeta')
        ));
        $this->assertSame(array('Alpha', 'Zeta'), $titles,
            'Coincidence would not be recognisable here: Alpha sits on the higher id and therefore '
            .'comes first with id DESC — not because of sortBy="title"');
    }

    public function testOrderParameterDeterminesTheOrder(): void
    {
        [, $body] = $this->postJson(
            '/api/list',
            array('entity' => 'PIM\\Tag', 'order' => array('title' => 'ASC')),
            $this->token()
        );

        $titles = array_values(array_intersect(
            array_column($body['data'], 'title'),
            array('Alpha', 'Zeta')
        ));

        $this->assertSame(array('Alpha', 'Zeta'), $titles,
            'The sort order comes from the request, not from the entity settings');
    }

    public function testPropertiesRestrictsTheFieldSet(): void
    {
        [, $body] = $this->postJson(
            '/api/list',
            array('entity' => 'PIM\\Tag', 'properties' => array('id', 'title')),
            $this->token()
        );

        $this->assertNotEmpty($body['data']);
        foreach ($body['data'] as $entry) {
            $this->assertSame(array('id', 'title'), array_keys($entry),
                'With properties exactly the requested fields are returned');
        }
    }

    public function testPartialSelectReturnsTheLabelPropertyOfTheJoinedTarget(): void
    {
        // Protects the decision from 012-005-0002: labelProperty was on the removal list,
        // but stays — Api::getList() includes exactly this field of the joined target in
        // the partial select. PIM\User carries labelProperty="alias".
        [, $body] = $this->postJson(
            '/api/list',
            array('entity' => 'PIM\\Tag', 'properties' => array('id', 'title', 'userCreated')),
            $this->token()
        );

        $withUser = array_values(array_filter(
            $body['data'],
            fn (array $e): bool => $e['id'] === $this->tagA
        ));

        $this->assertCount(1, $withUser);
        $this->assertSame($this->adminId, $withUser[0]['userCreated']['id']);
        $this->assertSame('admin', $withUser[0]['userCreated']['alias'],
            'The labelProperty of the target is included in the partial select — see 012-005-0002');
    }

    public function testListWithoutTokenReturnsNoData(): void
    {
        [$status, $body] = $this->postJson('/api/list', array('entity' => 'PIM\\Tag'));

        $this->assertSame(401, $status, 'Since the stack switch (006-002-0003) the intended code — Symfony 4.4 fixes 000-000-0006 here');
        $this->assertArrayNotHasKey('data', $body);
    }
}
