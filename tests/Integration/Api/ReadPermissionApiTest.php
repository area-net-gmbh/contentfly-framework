<?php
namespace Tests\Integration\Api;

use Areanet\PIM\Entity\Permission;
use Tests\Integration\IntegrationTestCase;

/**
 * Characterization tests for `Permission::isReadable()` at all four levels.
 *
 * This is the place where a mistake during the kernel swap **does not break visibly, but
 * silently hands out too much**. Every test here therefore checks both directions: what must
 * be visible *and* what must not be. A test that only proves visibility would not notice a
 * filter that is opened too wide.
 *
 * The levels are written as constants because their values are **not in ascending order**:
 * `NONE` 0, `OWN` 1, `ALL` 2, `GROUP` 3.
 */
class ReadPermissionApiTest extends IntegrationTestCase
{
    private string $adminId = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminId = (string) $this->pdo()
            ->query("SELECT id FROM pim_user WHERE alias = 'admin'")
            ->fetchColumn();
    }

    /** Creates a tag; $userCreated and $groups determine who may see it. */
    private function tag(string $title, ?string $userCreated = null, ?string $groups = null, ?string $users = null): string
    {
        $id = 'rp-'.bin2hex(random_bytes(6));

        $this->pdo()->prepare(
            // `groups` is a reserved word in MySQL 8 — the same trap that
            // 012-005-0003 fixed in Api::getTree2().
            'INSERT INTO pim_tag (id, title, created, modified, views, isIntern, usercreated_id, `groups`, users)
             VALUES (:id, :title, NOW(), NOW(), 0, 0, :uc, :grp, :usr)'
        )->execute(array('id' => $id, 'title' => $title, 'uc' => $userCreated, 'grp' => $groups, 'usr' => $users));

        $this->deleteAfterTest('pim_tag', $id);

        return $id;
    }

    /** @return array<int,string> The ids that /api/list returns for this token. */
    private function visibleIds(string $token): array
    {
        [$status, $body] = $this->postJson('/api/list', array('entity' => 'PIM\\Tag'), $token);

        $this->assertSame(200, $status);

        return array_column($body['data'], 'id');
    }

    // ── Level ALL ──────────────────────────────────────────────────────────────────────

    public function testWithLevelAllAllObjectsAreVisible(): void
    {
        [$token, $userId] = $this->createTestUser(array('PIM\\Tag' => array('readable' => Permission::ALL)));

        $own     = $this->tag('Own', $userId);
        $foreign = $this->tag('Foreign', $this->adminId);

        $visible = $this->visibleIds($token);

        $this->assertContains($own, $visible);
        $this->assertContains($foreign, $visible, 'ALL means everything, including other users\' objects');
    }

    // ── Level OWN ──────────────────────────────────────────────────────────────────────

    public function testWithLevelOwnOnlyOwnObjectsAreVisible(): void
    {
        [$token, $userId] = $this->createTestUser(array('PIM\\Tag' => array('readable' => Permission::OWN)));

        $own     = $this->tag('Own', $userId);
        $foreign = $this->tag('Foreign', $this->adminId);

        $visible = $this->visibleIds($token);

        $this->assertContains($own, $visible);
        $this->assertNotContains($foreign, $visible,
            'The other direction — without it a filter opened too wide would go unnoticed');
    }

    public function testWithLevelOwnTheUsersListMakesAnObjectVisible(): void
    {
        // Api::getList() filters on "userCreated = me OR I am in users" — the
        // virtual join from Base that 012-005-0001 kept as data-relevant.
        [$token, $userId] = $this->createTestUser(array('PIM\\Tag' => array('readable' => Permission::OWN)));

        $shared = $this->tag('Shared', $this->adminId, null, $userId);

        $this->assertContains($shared, $this->visibleIds($token),
            'Another user\'s object becomes visible when it lists me in users');
    }

    // ── Level GROUP ────────────────────────────────────────────────────────────────────

    public function testWithLevelGroupOwnAndGroupSharedObjectsAreVisible(): void
    {
        [$token, $userId, $groupId] = $this->createTestUser(array('PIM\\Tag' => array('readable' => Permission::GROUP)));

        $own       = $this->tag('Own', $userId);
        $groupTag  = $this->tag('For the group', $this->adminId, $groupId);
        $unrelated = $this->tag('Unrelated', $this->adminId);

        $visible = $this->visibleIds($token);

        $this->assertContains($own, $visible);
        $this->assertContains($groupTag, $visible, 'The own group is listed in groups');
        $this->assertNotContains($unrelated, $visible, 'Without a relation it stays invisible');
    }

    // ── No permission ──────────────────────────────────────────────────────────────────

    public function testWithoutReadPermissionListThrowsInsteadOfReturningAnEmptySet(): void
    {
        // The question the task was supposed to measure: filtered list or error? It is an
        // error — Api::getList() throws contentfly_general_permission_denied instead of
        // returning an empty list.
        [$token] = $this->createTestUser(array('PIM\\User' => array('readable' => Permission::ALL)));

        $this->tag('Unreachable', $this->adminId);

        [$status, $body] = $this->postJson('/api/list', array('entity' => 'PIM\\Tag'), $token);

        $this->assertSame(403, $status, 'Since the stack switch (006-002-0003) the intended code — Symfony 4.4 fixes 000-000-0006 here');
        // 011-001-0003: `data` is present and null instead of missing — the stronger statement.
        $this->assertErrorEnvelope($body);
    }

    public function testWithoutReadPermissionSingleAlsoThrows(): void
    {
        [$token] = $this->createTestUser(array('PIM\\User' => array('readable' => Permission::ALL)));

        $tag = $this->tag('Unreachable', $this->adminId);

        [$status, $body] = $this->postJson(
            '/api/single',
            array('entity' => 'PIM\\Tag', 'id' => $tag),
            $token
        );

        $this->assertSame(403, $status,
            'Since 006-002-0003 the intended code — Symfony 4.4 fixes 000-000-0006 here');
        $this->assertErrorEnvelope($body); // 011-001-0003: `data` is present and null
    }

    public function testUserWithoutGroupSeesNothing(): void
    {
        // Permission::is() returns 0 as soon as the user belongs to no group — even
        // before any check of the permission rows.
        $id    = 'rp-nogrp-'.bin2hex(random_bytes(6));
        $salt  = bin2hex(random_bytes(16));

        $this->pdo()->prepare(
            'INSERT INTO pim_user (id, isAdmin, alias, pass, isActive, salt, created, modified, views, isIntern)
             VALUES (:id, 0, :alias, :pass, 1, :salt, NOW(), NOW(), 0, 0)'
        )->execute(array(
            'id' => $id, 'alias' => $id,
            'pass' => hash('sha256', self::TEST_PASSWORD.$salt), 'salt' => $salt,
        ));
        $this->deleteAfterTest('pim_user', $id);

        [, $login] = $this->postJson('/auth/login', array('alias' => $id, 'pass' => self::TEST_PASSWORD));
        $this->assertArrayHasKey('token', $login);

        [$status] = $this->postJson('/api/list', array('entity' => 'PIM\\Tag'), $login['token']);

        $this->assertSame(403, $status,
            'Since 006-002-0003 the intended code — Symfony 4.4 fixes 000-000-0006 here');
    }

    // ── Admin ──────────────────────────────────────────────────────────────────────────

    public function testAdminBypassesAllLevels(): void
    {
        // Permission::is() returns 2 for admins before any group or permission row is even
        // looked at.
        $foreign = $this->tag('Foreign', $this->adminId);

        $this->assertContains($foreign, $this->visibleIds($this->token()));
    }

    // ── pim_blocked for joined objects ─────────────────────────────────────────────────

    public function testUnreadableJoinedObjectComesAsPimBlocked(): void
    {
        // PIM\Tag.userCreated is a join to PIM\User. Whoever may read tags but not users
        // gets only the object's id plus the marker instead of the object — the client
        // learns THAT something is there, but not what. The behaviour lives in JoinType and
        // is duplicated across four more type classes.
        [$token, $userId] = $this->createTestUser(array('PIM\\Tag' => array('readable' => Permission::ALL)));

        $tag = $this->tag('With creator', $userId);

        [$status, $body] = $this->postJson(
            '/api/single',
            array('entity' => 'PIM\\Tag', 'id' => $tag),
            $token
        );

        $this->assertSame(200, $status);
        $this->assertSame(
            array('id' => $userId, 'pim_blocked' => true),
            $body['data']['userCreated'],
            'The id is passed through, the object is not'
        );
    }

    public function testWithReadPermissionOnTargetEntityJoinedObjectComesInFull(): void
    {
        // The opposite direction: with read permission on PIM\User the marker is dropped.
        [$token, $userId] = $this->createTestUser(array(
            'PIM\\Tag'  => array('readable' => Permission::ALL),
            'PIM\\User' => array('readable' => Permission::ALL),
        ));

        $tag = $this->tag('With creator', $userId);

        [, $body] = $this->postJson(
            '/api/single',
            array('entity' => 'PIM\\Tag', 'id' => $tag),
            $token
        );

        $this->assertArrayNotHasKey('pim_blocked', $body['data']['userCreated']);
        $this->assertArrayHasKey('alias', $body['data']['userCreated']);
    }
}
