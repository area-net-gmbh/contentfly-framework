<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * Characterisation tests for the sync endpoints `/api/all`, `/api/deleted` and `/api/count`.
 *
 * Until task `000-000-0007`, `/api/all` answered unconditionally with HTTP 500 — a path in
 * `Api::getAll()` carried one `../` too many and pointed out of the repo. The tests recorded that
 * as the current state; with the fix they were **inverted**, as their comment intended. They now
 * describe the sync contract, no longer its failure.
 */
class SyncApiTest extends IntegrationTestCase
{
    private string $tag = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tag = 'sync-'.bin2hex(random_bytes(6));

        $this->pdo()->prepare(
            'INSERT INTO pim_tag (id, title, created, modified, views, isIntern)
             VALUES (:id, :title, NOW(), NOW(), 0, 0)'
        )->execute(array('id' => $this->tag, 'title' => 'Sync-Probe'));

        $this->deleteAfterTest('pim_tag', $this->tag);
    }

    // ── /api/all ───────────────────────────────────────────────────────────────────────

    public function testAllReturnsTheDataOfAllSyncableEntities(): void
    {
        [$status, $body] = $this->postJson('/api/all', array(), $this->token());

        $this->assertSame(200, $status, 'Until 000-000-0007 this was an HTTP 500');
        // 011-001-0002: all used to carry `lastModified` beside `data` — the next shape of its own.
        // It is now one meta key, and the payload is the entity map itself.
        $this->assertEnvelope($body, array('lastModified'));
        $this->assertArrayHasKey('PIM\\Tag', $body['data'],
            'Since 000-000-0007 the schema determines the entities, no longer a hard-wired '
            .'list of File, User and Group');
        $this->assertContains($this->tag, array_column($body['data']['PIM\\Tag'], 'id'));
    }

    public function testAllExcludesTheSameEntitiesAsDeleted(): void
    {
        // The core of 000-000-0007: before, getDeleted() reported deletions for entities that
        // getAll() never delivered — a client learned about the disappearance of objects it
        // had never received. Both now use the same exclusion list.
        [, $body] = $this->postJson('/api/all', array(), $this->token());

        foreach (array('PIM\\Folder', 'PIM\\Token', 'PIM\\Group', 'PIM\\Log', 'PIM\\Permission') as $excluded) {
            $this->assertArrayNotHasKey($excluded, $body['data'],
                "$excluded is on the exclusion list of both sync halves");
        }
    }

    public function testAllWithoutTokenReturnsNoData(): void
    {
        [$status, $body] = $this->postJson('/api/all', array());

        $this->assertSame(401, $status,
            'Since 006-002-0003 the intended code — Symfony 4.4 fixes 000-000-0006 here');
        // 011-001-0003: `data` is present and null instead of missing — the stronger statement.
        $this->assertErrorEnvelope($body);
    }

    // ── /api/deleted ───────────────────────────────────────────────────────────────────

    public function testDeletedReturnsAListInTheStandardEnvelope(): void
    {
        [$status, $body] = $this->postJson('/api/deleted', array(), $this->token());

        $this->assertSame(200, $status);
        // 011-001-0002: `ts` moved into the meta, where it now stands for every endpoint.
        $this->assertIsArray($this->assertEnvelope($body));
    }

    public function testDeletedReturnsAFlatListOfRawLogRows(): void
    {
        // /api/deleted reads the second half of the sync contract from pim_log. The response
        // is **not** a structure grouped by entity, but a flat list of the columns model_name
        // and model_id — array_merge over the matches per entity.
        $logId = 'synclog-'.bin2hex(random_bytes(6));
        $this->logRow($logId, 'PIM\\Tag', $this->tag);

        [$status, $body] = $this->postJson('/api/deleted', array(), $this->token());

        $this->assertSame(200, $status);
        $this->assertNotEmpty($body['data'], 'The deleted row is reported');

        $matches = array_values(array_filter(
            $body['data'],
            fn (array $row): bool => ($row['model_id'] ?? null) === $this->tag
        ));

        $this->assertCount(1, $matches);
        $this->assertSame(array('model_name', 'model_id'), array_keys($matches[0]),
            'Each row carries exactly these two columns — raw from pim_log');
        $this->assertSame('PIM\\Tag', $matches[0]['model_name']);
    }

    public function testDeletedExcludesWhatExcludeFromSyncSets(): void
    {
        // Inverted with 000-000-0013. The test used to record that deleted excludes a fixed list of
        // entities — a second, hard-wired exclusion list in the code. The effect has stayed the
        // same, the cause is a different one: PIM\\Folder now carries
        // @PIM\\Config(excludeFromSync=true), and getDeleted() checks the field — which it never
        // did before.
        $logId  = 'synclog-'.bin2hex(random_bytes(6));
        $folder = 'sync-f-'.bin2hex(random_bytes(6));
        $this->logRow($logId, 'PIM\\Folder', $folder);

        [, $body] = $this->postJson('/api/deleted', array(), $this->token());

        $ids = array_column($body['data'], 'model_id');
        $this->assertNotContains($folder, $ids,
            'PIM\\Folder is taken out of synchronisation with excludeFromSync');
    }

    public function testTwoDeletionsInTheSameSecondAreBothReturned(): void
    {
        // The proof for 000-000-0013 C. pim_log.created has second resolution; two deletions
        // in the same API call carry the same timestamp. With the old `created > ?` a sync
        // client lost every deletion from the second whose timestamp it had remembered. Now
        // `>=`: better to report twice than to lose.
        $first  = 'synca-'.bin2hex(random_bytes(6));
        $second = 'syncb-'.bin2hex(random_bytes(6));

        // THE SHARED SECOND IS ESTABLISHED, NOT HOPED FOR (000-000-0032). Both rows used to get
        // their own NOW() in two separate INSERTs. If a second boundary fell between them, the
        // first row carried the second before, dropped out of the result, and the test turned
        // red with nothing wrong in the framework — measured at 2 of 1500 pairs. One value from
        // the database clock, bound to both rows, is exactly the state the test is named after.
        $sameSecond = (string) $this->pdo()->query('SELECT NOW()')->fetchColumn();

        $this->logRow('synclog-'.bin2hex(random_bytes(6)), 'PIM\\Tag', $first, $sameSecond);
        $this->logRow('synclog-'.bin2hex(random_bytes(6)), 'PIM\\Tag', $second, $sameSecond);

        // The timestamp a client would remember after this pass: the one of the last reported
        // row. Both rows carry it.
        $boundary = (string) $this->pdo()
            ->query('SELECT created FROM pim_log WHERE model_id = '.$this->pdo()->quote($second))
            ->fetchColumn();

        [, $body] = $this->postJson('/api/deleted', array('lastModified' => $boundary), $this->token());

        $ids = array_column($body['data'], 'model_id');
        $this->assertContains($second, $ids, 'The row at the boundary itself');
        $this->assertContains($first, $ids, 'And the other one from the same second — otherwise it would be lost forever');
    }

    private function logRow(string $logId, string $entityName, string $modelId, ?string $created = null): void
    {
        // Bound parameters instead of inserted strings: the entity name carries a backslash,
        // and that does not reliably survive any of the three escaping levels.
        //
        // Without $created the database clock decides, as before. COALESCE keeps that in SQL, so
        // both cases run through the same statement.
        $this->pdo()->prepare(
            'INSERT INTO pim_log (id, model_id, model_name, mode, created, modified, views, isIntern)
             VALUES (:id, :modelId, :modelName, :mode, COALESCE(:created, NOW()), COALESCE(:created2, NOW()), 0, 0)'
        )->execute(array(
            'id' => $logId, 'modelId' => $modelId, 'modelName' => $entityName, 'mode' => 'DEL',
            'created' => $created, 'created2' => $created,
        ));

        $this->deleteAfterTest('pim_log', $logId);
    }

    public function testDeletedWithoutTokenReturnsNoData(): void
    {
        [$status, $body] = $this->postJson('/api/deleted', array());

        $this->assertSame(401, $status,
            'Since 006-002-0003 the intended code — Symfony 4.4 fixes 000-000-0006 here');
        $this->assertErrorEnvelope($body); // 011-001-0003: `data` is present and null
    }

    // ── /api/count ─────────────────────────────────────────────────────────────────────

    public function testCountIsAGlobalStatisticNotAFilteredCounter(): void
    {
        // Surprising, but the current state: /api/count does not count the matches of a query,
        // but returns an inventory overview of records and files.
        [$status, $body] = $this->postJson('/api/count', array('entity' => 'PIM\\Tag'), $this->token());

        $this->assertSame(200, $status);
        $this->assertEnvelope($body); // 011-001-0002
        $this->assertSame(
            array('dataCount', 'filesCount', 'filesSize', 'details'),
            array_keys($body['data']),
            'count returns a statistic, not a match count for filters'
        );
    }

    public function testCountReportsTheNumberPerEntityInDetails(): void
    {
        [, $body] = $this->postJson('/api/count', array('entity' => 'PIM\\Tag'), $this->token());

        $this->assertArrayHasKey('PIM\\Tag', $body['data']['details']);
        $this->assertGreaterThanOrEqual(1, $body['data']['details']['PIM\\Tag'],
            'The tag created in setUp is counted');
        $this->assertIsInt($body['data']['dataCount']);
    }

    public function testCountWithoutTokenReturnsNoData(): void
    {
        [$status, $body] = $this->postJson('/api/count', array('entity' => 'PIM\\Tag'));

        $this->assertSame(401, $status,
            'Since 006-002-0003 the intended code — Symfony 4.4 fixes 000-000-0006 here');
        $this->assertErrorEnvelope($body); // 011-001-0003: `data` is present and null
    }

    // ── excludeFromSync ────────────────────────────────────────────────────────────────

    public function testFiveEntitiesSetExcludeFromSync(): void
    {
        // Inverted with 000-000-0013, and the old test demanded exactly that: it asserted that no
        // entity sets excludeFromSync and failed as soon as someone set the flag.
        //
        // Background: 012-005-0002 kept excludeFromSync with the reasoning "controls the sync
        // API". That was only half right — until 000-000-0007 it was checked exclusively in
        // getCount(), so it affected the inventory statistic and never the endpoint it is
        // named after. getAll() has checked it since the fix, getDeleted() since 000-000-0013.
        //
        // Seven until 000-000-0077 removed PIM\Nav and PIM\NavItem.
        [$status, $raw] = $this->get('/api/schema', $this->token());
        $this->assertSame(200, $status);

        $schema = json_decode($raw, true)['data'];

        $withFlag = array();
        foreach ($schema as $name => $entry) {
            if ($name === '_hash' || !isset($entry['settings']['excludeFromSync'])) {
                continue;
            }
            if ($entry['settings']['excludeFromSync']) {
                $withFlag[] = $name;
            }
        }

        sort($withFlag);

        $this->assertSame(
            array('PIM\\Folder', 'PIM\\Group', 'PIM\\Log', 'PIM\\Permission', 'PIM\\ThumbnailSetting'),
            $withFlag,
            'Exactly the entities from the formerly hard-wired list — no more and no fewer'
        );
    }

    public function testTheExclusionIsNoLongerInTheCode(): void
    {
        // The actual point of 000-000-0013 A: a project should be able to see why an entity
        // is never synchronised. As long as the list was in the code, it could not. This test
        // records that it does not return there.
        $source = file_get_contents(CONTENTFLY_PROJECT_DIR.'/lib/contentfly/Classes/Api.php');

        $this->assertStringNotContainsString('$entitiesToExclude', $source,
            'The hard-wired exclusion lists have become excludeFromSync');
    }
}
