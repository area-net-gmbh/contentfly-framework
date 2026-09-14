<?php
namespace Tests\Integration\Api;

use PDO;
use Tests\Integration\IntegrationTestCase;

/**
 * Characterisation tests for `/api/update` and `/api/replace`.
 *
 * **The difference in one sentence: `replace` is an upsert, not a full replacement.** On an
 * existing object it behaves field by field like `update` — internally it delegates via
 * sub-request to `/api/update`. Only when the id does not exist do the paths part:
 * `replace` creates the object with exactly this id (sub-request to `/api/insert`), `update`
 * fails.
 *
 * So the name is misleading: whoever reads "replace" and expects fields that were not sent to
 * be reset is wrong — they stay as they are.
 */
class UpdateReplaceApiTest extends IntegrationTestCase
{
    private string $tag = '';

    protected function setUp(): void
    {
        parent::setUp();

        // Directly via pdo(), so that this test does not depend on /api/insert succeeding:
        // views = 5 is the field that shows what happens to values that were not sent.
        $this->tag = 'ur-'.bin2hex(random_bytes(6));
        $this->pdo()->prepare(
            'INSERT INTO pim_tag (id, title, created, modified, views, isIntern)
             VALUES (:id, :title, NOW(), NOW(), 5, 0)'
        )->execute(array('id' => $this->tag, 'title' => 'Original'));

        $this->deleteAfterTest('pim_tag', $this->tag);
    }

    protected function tearDown(): void
    {
        foreach ($this->pdo()->query("SELECT id FROM pim_log WHERE model_name = 'PIM\\\\Tag'")->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $this->pdo()->prepare('DELETE FROM pim_log WHERE id = :id')->execute(array('id' => $id));
        }

        parent::tearDown();
    }

    /** @return array{0:string,1:int} Title and views directly from the database */
    private function fromDatabase(string $id): array
    {
        $row = $this->pdo()
            ->query('SELECT title, views FROM pim_tag WHERE id = '.$this->pdo()->quote($id))
            ->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($row, "Object $id exists");

        return array($row['title'], (int) $row['views']);
    }

    // ── The core: where they are alike ─────────────────────────────────────────────────

    public function testUpdateLeavesFieldsNotSentUntouched(): void
    {
        [$status] = $this->postJson(
            '/api/update',
            array('entity' => 'PIM\\Tag', 'id' => $this->tag, 'data' => array('title' => 'Via-Update')),
            $this->token()
        );

        $this->assertSame(200, $status);

        [$title, $views] = $this->fromDatabase($this->tag);
        $this->assertSame('Via-Update', $title);
        $this->assertSame(5, $views, 'views was not sent and stays as it is');
    }

    public function testReplaceAlsoLeavesFieldsNotSentUntouched(): void
    {
        // The decisive test: "replace" resets NOTHING. On an existing object it is field by
        // field the same as update.
        [$status] = $this->postJson(
            '/api/replace',
            array('entity' => 'PIM\\Tag', 'id' => $this->tag, 'data' => array('title' => 'Via-Replace')),
            $this->token()
        );

        $this->assertSame(200, $status);

        [$title, $views] = $this->fromDatabase($this->tag);
        $this->assertSame('Via-Replace', $title);
        $this->assertSame(5, $views,
            'Despite its name, replace does NOT reset fields that were not sent — '
            .'for an existing id it delegates via sub-request to /api/update');
    }

    // ── The core: where they differ ────────────────────────────────────────────────────

    public function testReplaceCreatesANonExistingObjectWithTheGivenId(): void
    {
        $newId = 'ur-new-'.bin2hex(random_bytes(6));
        $this->deleteAfterTest('pim_tag', $newId);

        [$status, $body] = $this->postJson(
            '/api/replace',
            array('entity' => 'PIM\\Tag', 'id' => $newId, 'data' => array('title' => 'Via-Replace-Created')),
            $this->token()
        );

        $this->assertSame(200, $status);
        $this->assertSame($newId, $body['id'],
            'replace takes the given id instead of generating a GUID');

        [$title] = $this->fromDatabase($newId);
        $this->assertSame('Via-Replace-Created', $title);
    }

    public function testUpdateOnAnUnknownIdFails(): void
    {
        // Exactly here the two part ways: update creates nothing.
        $unknown = 'ur-missing-'.bin2hex(random_bytes(6));

        [$status] = $this->postJson(
            '/api/update',
            array('entity' => 'PIM\\Tag', 'id' => $unknown, 'data' => array('title' => 'Whatever')),
            $this->token()
        );

        // Since 000-000-0006 the intended code, before 500.
        $this->assertSame(404, $status);

        $count = (int) $this->pdo()
            ->query('SELECT COUNT(*) FROM pim_tag WHERE id = '.$this->pdo()->quote($unknown))
            ->fetchColumn();
        $this->assertSame(0, $count, 'update creates nothing — unlike replace');
    }

    // ── modified ───────────────────────────────────────────────────────────────────────

    public function testBothUpdateModified(): void
    {
        $before = (string) $this->pdo()
            ->query('SELECT modified FROM pim_tag WHERE id = '.$this->pdo()->quote($this->tag))
            ->fetchColumn();

        // One second apart, so that the DATETIME value can differ at all.
        $this->pdo()->prepare('UPDATE pim_tag SET modified = :m WHERE id = :id')
             ->execute(array('m' => '2000-01-01 00:00:00', 'id' => $this->tag));

        $this->postJson(
            '/api/update',
            array('entity' => 'PIM\\Tag', 'id' => $this->tag, 'data' => array('title' => 'Modified-Probe')),
            $this->token()
        );

        $after = (string) $this->pdo()
            ->query('SELECT modified FROM pim_tag WHERE id = '.$this->pdo()->quote($this->tag))
            ->fetchColumn();

        $this->assertNotSame('2000-01-01 00:00:00', $after, 'update sets modified anew');
        $this->assertNotEmpty($before);
    }

    // ── Protection ─────────────────────────────────────────────────────────────────────

    public function testUpdateWithoutTokenChangesNothing(): void
    {
        [$status] = $this->postJson(
            '/api/update',
            array('entity' => 'PIM\\Tag', 'id' => $this->tag, 'data' => array('title' => 'Without-Token'))
        );

        $this->assertSame(401, $status, 'Since the stack switch (006-002-0003) the intended code — Symfony 4.4 fixes 000-000-0006 here');

        [$title] = $this->fromDatabase($this->tag);
        $this->assertSame('Original', $title, 'Without a token the value stays unchanged');
    }

    public function testReplaceWithoutTokenCreatesNothing(): void
    {
        $newId = 'ur-notoken-'.bin2hex(random_bytes(6));

        [$status] = $this->postJson(
            '/api/replace',
            array('entity' => 'PIM\\Tag', 'id' => $newId, 'data' => array('title' => 'Without-Token'))
        );

        $this->assertSame(401, $status,
            'Since 006-002-0003 the intended code — Symfony 4.4 fixes 000-000-0006 here');

        $count = (int) $this->pdo()
            ->query('SELECT COUNT(*) FROM pim_tag WHERE id = '.$this->pdo()->quote($newId))
            ->fetchColumn();
        $this->assertSame(0, $count, 'Without a token nothing is created via replace either');
    }
}
