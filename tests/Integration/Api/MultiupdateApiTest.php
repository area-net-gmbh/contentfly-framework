<?php
namespace Tests\Integration\Api;

use PDO;
use Tests\Integration\IntegrationTestCase;

/**
 * Characterization tests for `/api/multiupdate`.
 *
 * **Two of these tests recorded a defect and were inverted with `000-000-0009`.**
 * The endpoint ran without a transaction: if an object failed in the middle of the batch, the
 * ones processed before it stayed changed, the ones after it were never touched, and the
 * response was an error without saying how far it got.
 *
 * Since `000-000-0009` it is all or nothing. `testPartialFailureRollsBackTheWholeBatch()`
 * checks the opposite of what it asserted before (that a partial failure leaves the earlier
 * objects written); `testResponseListsTimestampAndUpdatedObjects()` used to assert that the
 * response names neither timestamp nor result. The former names are in `000-000-0009`. Both were rewritten on purpose, not
 * deleted — the old assertion is in the history.
 */
class MultiupdateApiTest extends IntegrationTestCase
{
    private string $firstTag = '';
    private string $lastTag = '';

    protected function setUp(): void
    {
        parent::setUp();

        $run = bin2hex(random_bytes(6));

        // 'a-' and 'z-', so that the order within the batch is determined solely by the order
        // in the request, independent of the id sorting.
        $this->firstTag = $this->createTag('mu-a-'.$run, 'First');
        $this->lastTag  = $this->createTag('mu-z-'.$run, 'Last');
    }

    private function createTag(string $id, string $title): string
    {
        $this->pdo()->prepare(
            'INSERT INTO pim_tag (id, title, created, modified, views, isIntern)
             VALUES (:id, :title, NOW(), NOW(), 0, 0)'
        )->execute(array('id' => $id, 'title' => $title));

        $this->deleteAfterTest('pim_tag', $id);

        return $id;
    }

    protected function tearDown(): void
    {
        foreach ($this->pdo()->query("SELECT id FROM pim_log WHERE model_name = 'PIM\\\\Tag'")->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $this->pdo()->prepare('DELETE FROM pim_log WHERE id = :id')->execute(array('id' => $id));
        }

        parent::tearDown();
    }

    private function title(string $id): string
    {
        return (string) $this->pdo()
            ->query('SELECT title FROM pim_tag WHERE id = '.$this->pdo()->quote($id))
            ->fetchColumn();
    }

    // ── Success case ───────────────────────────────────────────────────────────────────

    public function testMultipleObjectsAreUpdatedInOneCall(): void
    {
        [$status] = $this->postJson('/api/multiupdate', array('objects' => array(
            array('entity' => 'PIM\\Tag', 'id' => $this->firstTag, 'data' => array('title' => 'First-new')),
            array('entity' => 'PIM\\Tag', 'id' => $this->lastTag,  'data' => array('title' => 'Last-new')),
        )), $this->token());

        $this->assertSame(200, $status);
        $this->assertSame('First-new', $this->title($this->firstTag));
        $this->assertSame('Last-new', $this->title($this->lastTag));
    }

    public function testResponseListsTimestampAndUpdatedObjects(): void
    {
        // Previously the thinnest envelope of all endpoints: renderResponse(array()) — no ts,
        // no data, no list of the updated ids. Since 000-000-0009 the response lists what was
        // written, in the order of the request.
        [$status, $body] = $this->postJson('/api/multiupdate', array('objects' => array(
            array('entity' => 'PIM\\Tag', 'id' => $this->firstTag, 'data' => array('title' => 'Envelope-probe')),
            array('entity' => 'PIM\\Tag', 'id' => $this->lastTag,  'data' => array('title' => 'Envelope-probe-2')),
        )), $this->token());

        $this->assertSame(200, $status);
        $this->assertArrayHasKey('ts', $body);
        $this->assertSame(array(
            array('entity' => 'PIM\\Tag', 'id' => $this->firstTag),
            array('entity' => 'PIM\\Tag', 'id' => $this->lastTag),
        ), $body['data']);
    }

    // ── Partial failure — the actual finding ───────────────────────────────────────────

    public function testPartialFailureRollsBackTheWholeBatch(): void
    {
        // Inverted with 000-000-0009. Previously this test recorded that 'Before-the-error'
        // stays in place; now exactly that must not happen.
        [$status] = $this->postJson('/api/multiupdate', array('objects' => array(
            array('entity' => 'PIM\\Tag', 'id' => $this->firstTag, 'data' => array('title' => 'Before-the-error')),
            array('entity' => 'PIM\\Tag', 'id' => 'doesnotexist',  'data' => array('title' => 'Fails')),
            array('entity' => 'PIM\\Tag', 'id' => $this->lastTag,  'data' => array('title' => 'After-the-error')),
        )), $this->token());

        // 404 since 000-000-0006; until then the exception from doUpdate() arrived as a 500.
        $this->assertSame(404, $status, 'The code of the failed entry, passed through');

        $this->assertSame('First', $this->title($this->firstTag),
            'The object processed before the error is rolled back');
        $this->assertSame('Last', $this->title($this->lastTag),
            'The object after the error is still never reached');
    }

    public function testFailureAlsoLeavesNoLogRow(): void
    {
        // The rollback must also take along what doUpdate() writes on the side:
        // pim_log gets one row per change. If it stayed, the log would claim a change that
        // no longer exists.
        $before = (int) $this->pdo()->query("SELECT COUNT(*) FROM pim_log WHERE model_name = 'PIM\\\\Tag'")->fetchColumn();

        $this->postJson('/api/multiupdate', array('objects' => array(
            array('entity' => 'PIM\\Tag', 'id' => $this->firstTag, 'data' => array('title' => 'Gets-rolled-back')),
            array('entity' => 'PIM\\Tag', 'id' => 'doesnotexist',   'data' => array('title' => 'Fails')),
        )), $this->token());

        $after = (int) $this->pdo()->query("SELECT COUNT(*) FROM pim_log WHERE model_name = 'PIM\\\\Tag'")->fetchColumn();

        $this->assertSame($before, $after, 'No log entry survives the rollback');
    }

    public function testFailureResponseReportsNoUpdatedObjects(): void
    {
        // The error response is deliberately NOT reshaped here — unifying the envelopes is
        // 000-000-0014. What 000-000-0009 guarantees is in the database, and exactly that is
        // checked here. Since 000-000-0006 the code is the one of the exception.
        [$status, $body] = $this->postJson('/api/multiupdate', array('objects' => array(
            array('entity' => 'PIM\\Tag', 'id' => $this->firstTag, 'data' => array('title' => 'Irrelevant')),
            array('entity' => 'PIM\\Tag', 'id' => 'doesnotexist',   'data' => array('title' => 'Fails')),
        )), $this->token());

        $this->assertSame(404, $status);
        $this->assertArrayNotHasKey('data', $body,
            'The error response claims no change');
        $this->assertSame('First', $this->title($this->firstTag),
            'And there was none either — the batch failed as a whole');
    }

    // ── Protection ─────────────────────────────────────────────────────────────────────

    public function testMultiupdateWithoutTokenChangesNothing(): void
    {
        [$status] = $this->postJson('/api/multiupdate', array('objects' => array(
            array('entity' => 'PIM\\Tag', 'id' => $this->firstTag, 'data' => array('title' => 'Without-token')),
        )));

        $this->assertSame(401, $status, 'Since the stack switch (006-002-0003) the intended code — Symfony 4.4 fixes 000-000-0006 here');
        $this->assertSame('First', $this->title($this->firstTag),
            'Without a token the value stays unchanged — checked against the database');
    }

    public function testEmptyBatchIsNotAnError(): void
    {
        [$status, $body] = $this->postJson('/api/multiupdate', array('objects' => array()), $this->token());

        $this->assertSame(200, $status, 'An empty batch runs through without doing anything');
        $this->assertSame(array(), $body['data'], 'and reports an empty list, not a missing one');
    }

    public function testMissingObjectsIsAnErrorNotANoOp(): void
    {
        // Previously foreach ran over null and the call ended with 200. Since the response
        // lists what was written, that would be false information (000-000-0009).
        [$status] = $this->postJson('/api/multiupdate', array(), $this->token());

        $this->assertSame(500, $status);
    }
}
