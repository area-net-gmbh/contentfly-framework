<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * Characterisation tests for `/api/insert` and `/api/delete` — the two ends of the
 * life cycle.
 *
 * Unlike the read tests, the data here is created **via the API itself**: the write path is
 * what is under test. Cleanup still goes through `pdo()`, so that a failed test does not
 * taint the following ones.
 */
class WriteApiTest extends IntegrationTestCase
{
    /** Creates a tag via the API and registers it for cleanup. */
    private function createTag(string $title): array
    {
        [$status, $body] = $this->postJson(
            '/api/insert',
            array('entity' => 'PIM\\Tag', 'data' => array('title' => $title)),
            $this->token()
        );

        $this->assertSame(200, $status, 'Precondition: creating succeeds');

        $this->deleteAfterTest('pim_tag', $body['id']);
        $this->cleanUpLogRows($body['id']);

        return $body;
    }

    /** Every write operation leaves log rows behind; they have to be cleaned up as well. */
    private function cleanUpLogRows(string $modelId): void
    {
        $ids = $this->pdo()
            ->query('SELECT id FROM pim_log WHERE model_id = '.$this->pdo()->quote($modelId))
            ->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($ids as $id) {
            $this->deleteAfterTest('pim_log', $id);
        }
    }

    protected function tearDown(): void
    {
        // Log rows only come into being on writing — that is, after registering for cleanup.
        // So follow up here once more, before the base class cleans up.
        foreach ($this->pdo()->query("SELECT id, model_id FROM pim_log WHERE model_name = 'PIM\\\\Tag'") as $row) {
            $this->pdo()->prepare('DELETE FROM pim_log WHERE id = :id')->execute(array('id' => $row['id']));
        }

        parent::tearDown();
    }

    // ── /api/insert ────────────────────────────────────────────────────────────────────

    public function testInsertReturnsTheGeneratedIdOnTheTopLevel(): void
    {
        $body = $this->createTag('Insert-Probe');

        $this->assertSame(array('ts', 'id', 'data', 'version', 'hash'), array_keys($body),
            'insert carries the generated id next to data on the top level — unlike single and list');
        $this->assertSame($body['id'], $body['data']['id']);
    }

    public function testInsertGeneratesAGuid(): void
    {
        $body = $this->createTag('Guid-Probe');

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $body['id'],
            'The installation ran with --db-strategy=guid'
        );
    }

    public function testInsertSetsCreatedModifiedAndUserCreated(): void
    {
        $body = $this->createTag('Automatic-Probe');
        $data = $body['data'];

        foreach (array('created', 'modified') as $field) {
            $this->assertSame(
                array('LOCAL_TIME', 'LOCAL', 'ISO8601', 'TIMESTAMP'),
                array_keys($data[$field]),
                "$field is set automatically and comes as a group of four"
            );
            $this->assertGreaterThan(0, $data[$field]['TIMESTAMP']);
        }

        $adminId = (string) $this->pdo()->query("SELECT id FROM pim_user WHERE alias = 'admin'")->fetchColumn();
        $this->assertSame(array('id' => $adminId), $data['userCreated'],
            'userCreated is set to the logged-in user');
    }

    public function testTheCreatedObjectCanBeFetchedViaSingle(): void
    {
        $body = $this->createTag('Fetch-Probe');

        [$status, $single] = $this->postJson(
            '/api/single',
            array('entity' => 'PIM\\Tag', 'id' => $body['id']),
            $this->token()
        );

        $this->assertSame(200, $status);
        $this->assertSame('Fetch-Probe', $single['data']['title']);
    }

    public function testInsertAndSingleRepresentBooleanValuesDifferently(): void
    {
        // Current state and inconsistent: the response of insert passes the raw value through
        // (isIntern as 0), while single serialises via the type classes (false).
        $body = $this->createTag('Boolean-Probe');

        [, $single] = $this->postJson(
            '/api/single',
            array('entity' => 'PIM\\Tag', 'id' => $body['id']),
            $this->token()
        );

        $this->assertSame(0, $body['data']['isIntern'], 'insert returns the integer');
        $this->assertFalse($single['data']['isIntern'], 'single returns the boolean value');
    }

    public function testInsertWithoutDataIsRejected(): void
    {
        [$status] = $this->postJson('/api/insert', array('entity' => 'PIM\\Tag'), $this->token());

        $this->assertSame(500, $status, 'Today 500 instead of 400 — see 000-000-0006');
    }

    public function testInsertWithUnknownEntityIsRejected(): void
    {
        [$status] = $this->postJson(
            '/api/insert',
            array('entity' => 'PIM\\DoesNotExist', 'data' => array('title' => 'whatever')),
            $this->token()
        );

        $this->assertSame(404, $status,
            'Since 006-002-0003 the intended code — Symfony 4.4 fixes 000-000-0006 here');
    }

    public function testInsertWithoutTokenCreatesNothing(): void
    {
        [$status] = $this->postJson(
            '/api/insert',
            array('entity' => 'PIM\\Tag', 'data' => array('title' => 'Without-Token'))
        );

        $this->assertSame(401, $status, 'Since the stack switch (006-002-0003) the intended code — Symfony 4.4 fixes 000-000-0006 here');

        $count = (int) $this->pdo()
            ->query("SELECT COUNT(*) FROM pim_tag WHERE title = 'Without-Token'")
            ->fetchColumn();
        $this->assertSame(0, $count, 'Without a token no object is created — checked against the database');
    }

    // ── /api/delete ────────────────────────────────────────────────────────────────────

    public function testDeleteRemovesTheObject(): void
    {
        $body = $this->createTag('Delete-Probe');

        [$status, $response] = $this->postJson(
            '/api/delete',
            array('entity' => 'PIM\\Tag', 'id' => $body['id']),
            $this->token()
        );

        $this->assertSame(200, $status);
        $this->assertSame(array('ts', 'id', 'version', 'hash'), array_keys($response),
            'delete returns the id, but no data');
        $this->assertSame($body['id'], $response['id']);

        $count = (int) $this->pdo()
            ->query('SELECT COUNT(*) FROM pim_tag WHERE id = '.$this->pdo()->quote($body['id']))
            ->fetchColumn();
        $this->assertSame(0, $count, 'The row is really gone — checked against the database');
    }

    public function testAfterDeletingSingleReturnsA404(): void
    {
        $body = $this->createTag('After-Probe');
        $this->postJson('/api/delete', array('entity' => 'PIM\\Tag', 'id' => $body['id']), $this->token());

        [$status, $single] = $this->postJson(
            '/api/single',
            array('entity' => 'PIM\\Tag', 'id' => $body['id']),
            $this->token()
        );

        // Inverted with 000-000-0006: the same as for an id that never existed — before, the
        // `headers` artefact with 200, now a 404. Until then the test recorded that single
        // returns the empty headers artefact after a delete.
        $this->assertSame(404, $status);
        $this->assertArrayNotHasKey('data', $single);
    }

    public function testDeleteWithUnknownIdIsRejected(): void
    {
        [$status] = $this->postJson(
            '/api/delete',
            array('entity' => 'PIM\\Tag', 'id' => 'doesnotexist'),
            $this->token()
        );

        // Since 000-000-0006 the intended code. Before, doUpdate()/doDelete() bypassed its own
        // not-found check, because getSingle() returned a JsonResponse, and died further down
        // with a TypeError.
        $this->assertSame(404, $status);
    }

    public function testDeleteWithoutTokenDeletesNothing(): void
    {
        $body = $this->createTag('Protected-Probe');

        [$status] = $this->postJson('/api/delete', array('entity' => 'PIM\\Tag', 'id' => $body['id']));

        $this->assertSame(401, $status, 'Since the stack switch (006-002-0003) the intended code — Symfony 4.4 fixes 000-000-0006 here');

        $count = (int) $this->pdo()
            ->query('SELECT COUNT(*) FROM pim_tag WHERE id = '.$this->pdo()->quote($body['id']))
            ->fetchColumn();
        $this->assertSame(1, $count, 'Without a token the object remains');
    }
}
