<?php
namespace Tests\Integration\Api;

use Areanet\PIM\Entity\Permission;
use Tests\Integration\IntegrationTestCase;

/**
 * Characterisation tests for `Permission::isWritable()` and `isDeletable()`.
 *
 * The counterparts of `isReadable`, with the difference that a bug here **costs data** instead
 * of disclosing it. That is why every assertion about a rejected operation additionally checks
 * the database: an HTTP 500 says nothing about whether the operation took effect anyway.
 *
 * The levels are written as constants — their values are not in ascending order
 * (`NONE` 0, `OWN` 1, `ALL` 2, `GROUP` 3).
 */
class WritePermissionApiTest extends IntegrationTestCase
{
    private string $adminId = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminId = (string) $this->pdo()
            ->query("SELECT id FROM pim_user WHERE alias = 'admin'")
            ->fetchColumn();
    }

    private function tag(string $title, ?string $userCreated = null, ?string $groups = null): string
    {
        $id = 'wp-'.bin2hex(random_bytes(6));

        // `groups` quoted — a reserved word in MySQL 8.
        $this->pdo()->prepare(
            'INSERT INTO pim_tag (id, title, created, modified, views, isIntern, usercreated_id, `groups`)
             VALUES (:id, :title, NOW(), NOW(), 0, 0, :uc, :grp)'
        )->execute(array('id' => $id, 'title' => $title, 'uc' => $userCreated, 'grp' => $groups));

        $this->deleteAfterTest('pim_tag', $id);

        return $id;
    }

    private function title(string $id): ?string
    {
        $value = $this->pdo()
            ->query('SELECT title FROM pim_tag WHERE id = '.$this->pdo()->quote($id))
            ->fetchColumn();

        return $value === false ? null : (string) $value;
    }

    private function exists(string $id): bool
    {
        return (int) $this->pdo()
            ->query('SELECT COUNT(*) FROM pim_tag WHERE id = '.$this->pdo()->quote($id))
            ->fetchColumn() === 1;
    }

    // ── insert: only the entity right counts ───────────────────────────────────────────

    public function testInsertChecksOnlyTheRightOnTheEntity(): void
    {
        // Consistent: a new object has no owner yet against which OWN or GROUP could be
        // measured. Api::insert() therefore only checks at line 248 whether there is any
        // write right at all.
        [$token] = $this->createTestUser(array('PIM\\Tag' => array(
            'readable' => Permission::ALL, 'writable' => Permission::OWN,
        )));

        [$status, $body] = $this->postJson(
            '/api/insert',
            array('entity' => 'PIM\\Tag', 'data' => array('title' => 'Created with OWN')),
            $token
        );

        $this->assertSame(200, $status, 'OWN is enough for creating — there is nothing foreign yet');
        $created = $body['data']; // 011-001-0002: insert answers with the object as payload
        $this->deleteAfterTest('pim_tag', $created['id']);
        $this->pdo()->prepare('DELETE FROM pim_log WHERE model_id = :id')->execute(array('id' => $created['id']));
    }

    public function testWithoutWriteRightNothingIsCreated(): void
    {
        [$token] = $this->createTestUser(array('PIM\\Tag' => array('readable' => Permission::ALL)));

        $title = 'Must-not-be-created-'.bin2hex(random_bytes(4));

        [$status] = $this->postJson(
            '/api/insert',
            array('entity' => 'PIM\\Tag', 'data' => array('title' => $title)),
            $token
        );

        $this->assertSame(403, $status, 'Since the stack switch (006-002-0003) the intended code — Symfony 4.4 fixes 000-000-0006 here');

        $count = (int) $this->pdo()
            ->query('SELECT COUNT(*) FROM pim_tag WHERE title = '.$this->pdo()->quote($title))
            ->fetchColumn();
        $this->assertSame(0, $count, 'Checked against the database — the status code alone says nothing');
    }

    // ── update: object ownership is checked ────────────────────────────────────────────

    public function testWithLevelOwnTheOwnObjectCanBeChanged(): void
    {
        [$token, $userId] = $this->createTestUser(array('PIM\\Tag' => array(
            'readable' => Permission::ALL, 'writable' => Permission::OWN,
        )));

        $own = $this->tag('Original', $userId);

        [$status] = $this->postJson(
            '/api/update',
            array('entity' => 'PIM\\Tag', 'id' => $own, 'data' => array('title' => 'Changed')),
            $token
        );

        $this->assertSame(200, $status);
        $this->assertSame('Changed', $this->title($own));
        $this->pdo()->prepare('DELETE FROM pim_log WHERE model_id = :id')->execute(array('id' => $own));
    }

    public function testWithLevelOwnAForeignObjectStaysUnchanged(): void
    {
        // The question this task was meant to answer: does Api::update() also check the
        // ownership of the object, or does it only know the right on the entity?
        // Answer: it checks (Api.php:452). There is no gap here.
        [$token] = $this->createTestUser(array('PIM\\Tag' => array(
            'readable' => Permission::ALL, 'writable' => Permission::OWN,
        )));

        $foreign = $this->tag('Foreign-Original', $this->adminId);

        [$status] = $this->postJson(
            '/api/update',
            array('entity' => 'PIM\\Tag', 'id' => $foreign, 'data' => array('title' => 'Intrusion')),
            $token
        );

        $this->assertSame(403, $status,
            'Since 006-002-0003 the intended code — Symfony 4.4 fixes 000-000-0006 here');
        $this->assertSame('Foreign-Original', $this->title($foreign),
            'The old value is still there — checked against the database');
    }

    public function testWithLevelGroupTheGroupOfTheObjectCounts(): void
    {
        [$token, , $groupId] = $this->createTestUser(array('PIM\\Tag' => array(
            'readable' => Permission::ALL, 'writable' => Permission::GROUP,
        )));

        $shared    = $this->tag('Shared-Original', $this->adminId, $groupId);
        $unrelated = $this->tag('Unrelated-Original', $this->adminId);

        [$statusShared] = $this->postJson(
            '/api/update',
            array('entity' => 'PIM\\Tag', 'id' => $shared, 'data' => array('title' => 'Shared-new')),
            $token
        );
        [$statusForeign] = $this->postJson(
            '/api/update',
            array('entity' => 'PIM\\Tag', 'id' => $unrelated, 'data' => array('title' => 'Intrusion')),
            $token
        );

        $this->assertSame(200, $statusShared);
        $this->assertSame('Shared-new', $this->title($shared));

        $this->assertSame(403, $statusForeign,
            'Since 006-002-0003 the intended code — Symfony 4.4 fixes 000-000-0006 here');
        $this->assertSame('Unrelated-Original', $this->title($unrelated),
            'Without a group relation the object stays untouched');

        $this->pdo()->prepare('DELETE FROM pim_log WHERE model_id = :id')->execute(array('id' => $shared));
    }

    // ── delete ─────────────────────────────────────────────────────────────────────────

    public function testWithoutDeleteRightTheObjectRemains(): void
    {
        [$token] = $this->createTestUser(array('PIM\\Tag' => array(
            'readable' => Permission::ALL, 'writable' => Permission::ALL,
        )));

        $tag = $this->tag('Undeletable', $this->adminId);

        [$status] = $this->postJson('/api/delete', array('entity' => 'PIM\\Tag', 'id' => $tag), $token);

        $this->assertSame(403, $status,
            'Since 006-002-0003 the intended code — Symfony 4.4 fixes 000-000-0006 here');
        $this->assertTrue($this->exists($tag),
            'Write right alone does not entitle to delete — checked against the database');
    }

    public function testWithDeleteRightOwnAForeignObjectRemains(): void
    {
        [$token, $userId] = $this->createTestUser(array('PIM\\Tag' => array(
            'readable' => Permission::ALL, 'deletable' => Permission::OWN,
        )));

        $own     = $this->tag('Own', $userId);
        $foreign = $this->tag('Foreign', $this->adminId);

        [$statusForeign] = $this->postJson('/api/delete', array('entity' => 'PIM\\Tag', 'id' => $foreign), $token);
        [$statusOwn]     = $this->postJson('/api/delete', array('entity' => 'PIM\\Tag', 'id' => $own), $token);

        $this->assertSame(403, $statusForeign,
            'Since 006-002-0003 the intended code — Symfony 4.4 fixes 000-000-0006 here');
        $this->assertTrue($this->exists($foreign), 'The foreign object is still there');

        $this->assertSame(200, $statusOwn);
        $this->assertFalse($this->exists($own), 'The own one is gone');

        $this->pdo()->prepare('DELETE FROM pim_log WHERE model_id = :id')->execute(array('id' => $own));
    }

    // ── The special rule: a user may always change themselves ──────────────────────────

    public function testWithLevelOwnAUserMayAlwaysChangeThemselves(): void
    {
        // Api.php:452 carries a third condition added to the two other checks:
        // `&& $object != $this->app['auth.user']`. A user thus never falls under the OWN lock
        // for themselves — not even when they did not create themselves.
        [$token, $userId] = $this->createTestUser(array('PIM\\User' => array(
            'readable' => Permission::ALL, 'writable' => Permission::OWN,
        )));

        [$status] = $this->postJson(
            '/api/update',
            array('entity' => 'PIM\\User', 'id' => $userId, 'data' => array('isIntern' => true)),
            $token
        );

        $this->assertSame(200, $status,
            'The test user was created by the test, not by itself — and may still '
            .'change itself');

        $this->pdo()->prepare('DELETE FROM pim_log WHERE model_id = :id')->execute(array('id' => $userId));
    }

    // ── MultijoinType: cannot be triggered ─────────────────────────────────────────────

    public function testTheWriteCheckInMultijoinTypeCannotBeTriggered(): void
    {
        // MultijoinType checks the write right on the target entity in two places (lines 190
        // and 230) — but only in the `mappedBy` branch, which requires `acceptFrom`.
        //
        // In the framework and in the template **not a single property** carries an
        // `acceptFrom`. The only multijoin is PIM\File.tags, and it has none. The code path
        // thus has no trigger.
        //
        // If a bidirectional multijoin relation comes into being, this test fails. Then the
        // proof that without write right on the target entity an AccessDeniedHttpException
        // is raised belongs here.
        [$status, $raw] = $this->get('/api/schema', $this->token());
        $this->assertSame(200, $status);

        $withAcceptFrom = array();
        foreach (json_decode($raw, true)['data'] as $entity => $entry) {
            if ($entity === '_hash' || !isset($entry['properties'])) {
                continue;
            }
            foreach ($entry['properties'] as $name => $config) {
                if (!empty($config['acceptFrom'])) {
                    $withAcceptFrom[] = $entity.'.'.$name;
                }
            }
        }

        $this->assertSame(array(), $withAcceptFrom,
            'Without acceptFrom the write check in MultijoinType does not apply. If that '
            .'changes, the proof belongs in this test.');
    }

    // ── Admin ──────────────────────────────────────────────────────────────────────────

    public function testAnAdminWritesAndDeletesWithoutAPermissionRow(): void
    {
        $tag = $this->tag('Admin-Probe', $this->adminId);

        [$statusUpdate] = $this->postJson(
            '/api/update',
            array('entity' => 'PIM\\Tag', 'id' => $tag, 'data' => array('title' => 'Admin-changed')),
            $this->token()
        );
        $this->assertSame(200, $statusUpdate);

        [$statusDelete] = $this->postJson('/api/delete', array('entity' => 'PIM\\Tag', 'id' => $tag), $this->token());
        $this->assertSame(200, $statusDelete);
        $this->assertFalse($this->exists($tag));

        $this->pdo()->prepare('DELETE FROM pim_log WHERE model_id = :id')->execute(array('id' => $tag));
    }
}
