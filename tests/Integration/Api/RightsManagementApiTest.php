<?php
namespace Tests\Integration\Api;

use Areanet\PIM\Entity\Permission;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Integration\IntegrationTestCase;

/**
 * MANAGING RIGHTS IS FOR ADMINS (000-000-0090).
 *
 * Every test here is a non-admin who holds the write right on one of the three entities that
 * decide what somebody may do — `PIM\Group`, `PIM\Permission`, `PIM\User` — and uses it for
 * himself. Before 000-000-0090 each attempt succeeded; setting another user's password ended in a
 * login as that user, the admin included.
 *
 * The tests check the effect — the database, or the next request — not only the status code. And
 * they check the other direction: what a non-admin may still do with these entities.
 */
class RightsManagementApiTest extends IntegrationTestCase
{
    // ── PIM\Group ──────────────────────────────────────────────────────────────────────────

    public function testANonAdminCannotWriteThePermissionsOfHisGroup(): void
    {
        [$token, , $group] = $this->createTestUser(array('PIM\\Group' => $this->readWrite()));
        $before = $this->permissionRows($group);

        [$status, $body] = $this->postJson('/api/update', array(
            'entity' => 'PIM\\Group', 'id' => $group, 'data' => array('permissions' => array($this->fullAccessTo('PIM\\User'))),
        ), $token);

        $this->assertDenied($status, $body);
        $this->assertSame($before, $this->permissionRows($group));
        $this->assertSame(403, $this->postJson('/api/list', array('entity' => 'PIM\\User'), $token)[0], 'Still no access to users.');
    }

    public function testANonAdminCannotCreateAGroupWithPermissions(): void
    {
        [$token] = $this->createTestUser(array('PIM\\Group' => $this->readWrite()));
        $name = 'Rights '.bin2hex(random_bytes(4));

        [$status, $body] = $this->postJson('/api/insert', array(
            'entity' => 'PIM\\Group',
            'data'   => array('name' => $name, 'tokenTimeout' => 60, 'permissions' => array($this->fullAccessTo('PIM\\User'))),
        ), $token);

        $created = $this->cleanUpGroupNamed($name);
        $this->assertDenied($status, $body);
        $this->assertFalse($created);
    }

    public function testANonAdminMayStillRenameHisGroup(): void
    {
        [$token, , $group] = $this->createTestUser(array('PIM\\Group' => $this->readWrite()));
        $name = 'Renamed '.bin2hex(random_bytes(4));

        [$status] = $this->postJson('/api/update', array('entity' => 'PIM\\Group', 'id' => $group, 'data' => array('name' => $name)), $token);

        $this->assertSame(200, $status);
        $this->assertSame($name, $this->pdo()->query('SELECT name FROM pim_group WHERE id = '.$this->pdo()->quote($group))->fetchColumn());
    }

    // ── PIM\Permission ─────────────────────────────────────────────────────────────────────

    public function testANonAdminCannotInsertAPermissionRow(): void
    {
        [$token, , $group] = $this->createTestUser(array('PIM\\Permission' => $this->readWrite()));
        $before = $this->permissionRows($group);

        [$status, $body] = $this->postJson('/api/insert', array(
            'entity' => 'PIM\\Permission',
            'data'   => array('entityName' => 'PIM\\User', 'readable' => Permission::ALL, 'writable' => Permission::ALL, 'deletable' => Permission::ALL, 'export' => 0, 'group' => $group),
        ), $token);

        foreach (array_diff(array_keys($this->permissionRows($group)), array_keys($before)) as $row) {
            $this->deleteAfterTest('pim_permission', $row);
        }
        $this->assertDenied($status, $body);
        $this->assertSame(403, $this->postJson('/api/list', array('entity' => 'PIM\\User'), $token)[0], 'Still no access to users.');
    }

    public function testANonAdminCannotChangeOrDeleteAPermissionRow(): void
    {
        [$token, , $group] = $this->createTestUser(array('PIM\\Permission' => array('readable' => Permission::ALL, 'writable' => Permission::ALL, 'deletable' => Permission::ALL)));
        $row    = array_key_first($this->permissionRows($group));
        $before = $this->permissionRows($group);

        [$status, $body] = $this->postJson('/api/update', array('entity' => 'PIM\\Permission', 'id' => $row, 'data' => array('entityName' => 'PIM\\User')), $token);
        $this->assertDenied($status, $body);

        [$status, $body] = $this->postJson('/api/delete', array('entity' => 'PIM\\Permission', 'id' => $row), $token);
        $this->assertDenied($status, $body);

        $this->assertSame($before, $this->permissionRows($group));
    }

    // ── PIM\User: isAdmin and group ────────────────────────────────────────────────────────

    /** OWN is enough: doUpdate() lets a user with OWN write his own record. */
    public function testANonAdminCannotMakeHimselfAdmin(): void
    {
        [$token, $user] = $this->createTestUser(array('PIM\\User' => array('readable' => Permission::OWN, 'writable' => Permission::OWN)));

        [$status, $body] = $this->postJson('/api/update', array('entity' => 'PIM\\User', 'id' => $user, 'data' => array('isAdmin' => true)), $token);

        $this->assertDenied($status, $body);
        $this->assertSame(0, (int) $this->userColumn($user, 'isAdmin'));
    }

    public function testMultiupdateIsGuardedToo(): void
    {
        [$token, $user] = $this->createTestUser(array('PIM\\User' => array('readable' => Permission::OWN, 'writable' => Permission::OWN)));

        [$status, $body] = $this->postJson('/api/multiupdate', array('objects' => array(
            array('entity' => 'PIM\\User', 'id' => $user, 'data' => array('isAdmin' => true)),
        )), $token);

        $this->assertDenied($status, $body);
        $this->assertSame(0, (int) $this->userColumn($user, 'isAdmin'));
    }

    public function testANonAdminCannotMoveHimselfIntoAnotherGroup(): void
    {
        [$token, $user, $group] = $this->createTestUser(array('PIM\\User' => array('readable' => Permission::OWN, 'writable' => Permission::OWN)));
        [, , $stronger]         = $this->createTestUser(array('PIM\\User' => $this->readWrite()));

        [$status, $body] = $this->postJson('/api/update', array('entity' => 'PIM\\User', 'id' => $user, 'data' => array('group' => $stronger)), $token);

        $this->assertDenied($status, $body);
        $this->assertSame($group, $this->userColumn($user, 'group_id'));
    }

    /** @param array<string, mixed> $data */
    #[DataProvider('newUsersWithRights')]
    public function testANonAdminCannotCreateAUserWithRights(array $data): void
    {
        [$token, , $group] = $this->createTestUser(array('PIM\\User' => $this->readWrite()));
        $alias = 'rights-'.bin2hex(random_bytes(4));
        $data  = array('alias' => $alias, 'pass' => 'irrelevant-'.$alias) + array_map(fn ($v) => $v === 'OWN_GROUP' ? $group : $v, $data);

        [$status, $body] = $this->postJson('/api/insert', array('entity' => 'PIM\\User', 'data' => $data), $token);

        $created = $this->pdo()->query('SELECT id FROM pim_user WHERE alias = '.$this->pdo()->quote($alias))->fetchColumn();
        if ($created !== false) {
            $this->deleteAfterTest('pim_user', $created);
        }
        $this->assertDenied($status, $body);
        $this->assertFalse($created);
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function newUsersWithRights(): array
    {
        return array(
            'as admin'         => array(array('isAdmin' => true)),
            'in a group'       => array(array('group' => 'OWN_GROUP')),
        );
    }

    /** A client that posts the own record back as it read it must keep working. */
    public function testPostingTheOwnRecordBackUnchangedStillWorks(): void
    {
        [$token, $user, $group] = $this->createTestUser(array('PIM\\User' => array('readable' => Permission::OWN, 'writable' => Permission::OWN)));

        [$status, $body] = $this->postJson('/api/update', array(
            'entity' => 'PIM\\User', 'id' => $user, 'data' => array('isAdmin' => false, 'group' => array('id' => $group)),
        ), $token);

        $this->assertSame(200, $status, json_encode($body['errors'] ?? null));
    }

    // ── PIM\User: credentials ──────────────────────────────────────────────────────────────

    /** The worst of the four: the admin's password, with only the own one confirmed. */
    public function testANonAdminCannotSetTheAdminsPassword(): void
    {
        [$token]             = $this->createTestUser(array('PIM\\User' => $this->readWrite()));
        [, $victim]          = $this->createTestUser();
        $this->pdo()->prepare('UPDATE pim_user SET isAdmin = 1 WHERE id = :id')->execute(array('id' => $victim));
        $before              = $this->userColumn($victim, 'pass');

        [$status, $body] = $this->postJson('/api/update', array(
            'entity' => 'PIM\\User', 'id' => $victim, 'pass' => self::TEST_PASSWORD, 'data' => array('pass' => 'taken-over-123'),
        ), $token);

        $this->assertDenied($status, $body);
        $this->assertSame($before, $this->userColumn($victim, 'pass'));
    }

    #[DataProvider('loginFields')]
    public function testANonAdminCannotChangeTheLoginOfAnotherUser(string $field): void
    {
        [$token]    = $this->createTestUser(array('PIM\\User' => $this->readWrite()));
        [, $victim] = $this->createTestUser();
        $before     = $this->userColumn($victim, $field);

        [$status, $body] = $this->postJson('/api/update', array(
            'entity' => 'PIM\\User', 'id' => $victim, 'data' => array($field => 'chosen-by-the-caller'),
        ), $token);

        $this->assertDenied($status, $body);
        $this->assertSame($before, $this->userColumn($victim, $field));
    }

    /** @return array<string, array{0: string}> */
    public static function loginFields(): array
    {
        return array(
            'salt'         => array('salt'),
            'loginManager' => array('loginManager'),
            'externalId'   => array('externalId'),
        );
    }

    public function testANonAdminMayStillChangeHisOwnPassword(): void
    {
        [$token, $user] = $this->createTestUser(array('PIM\\User' => array('readable' => Permission::OWN, 'writable' => Permission::OWN)));
        $alias          = $this->userColumn($user, 'alias');

        [$status, $body] = $this->postJson('/api/update', array(
            'entity' => 'PIM\\User', 'id' => $user, 'pass' => self::TEST_PASSWORD, 'data' => array('pass' => 'my-own-new-one-123'),
        ), $token);

        $this->assertSame(200, $status, json_encode($body['errors'] ?? null));
        $this->assertSame(200, $this->postJson('/auth/login', array('alias' => $alias, 'pass' => 'my-own-new-one-123'))[0]);
    }

    // ── helpers ────────────────────────────────────────────────────────────────────────────

    /** @return array<string, int> */
    private function readWrite(): array
    {
        return array('readable' => Permission::ALL, 'writable' => Permission::ALL);
    }

    /** @return array<string, mixed> one entry of a `permissions` request */
    private function fullAccessTo(string $entity): array
    {
        return array('name' => $entity, 'readable' => Permission::ALL, 'writable' => Permission::ALL, 'deletable' => Permission::ALL, 'export' => 0);
    }

    private function assertDenied(int $status, array $body): void
    {
        $this->assertSame(403, $status, json_encode($body['errors'] ?? $body));
        $this->assertErrorEnvelope($body, 'contentfly_general_permission_denied');
    }

    /** @return array<string, array<string, string>> the group's rows by id */
    private function permissionRows(string $group): array
    {
        $rows = $this->pdo()->query('SELECT id, entityName, readable, writable, deletable FROM pim_permission WHERE group_id = '.$this->pdo()->quote($group).' ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);

        return array_column($rows, null, 'id');
    }

    private function userColumn(string $user, string $column): mixed
    {
        return $this->pdo()->query("SELECT `$column` FROM pim_user WHERE id = ".$this->pdo()->quote($user))->fetchColumn();
    }

    /** Registers a group of that name for cleanup, should it exist; returns its id or false. */
    private function cleanUpGroupNamed(string $name): string|false
    {
        $id = $this->pdo()->query('SELECT id FROM pim_group WHERE name = '.$this->pdo()->quote($name))->fetchColumn();
        if ($id !== false) {
            $this->deleteAfterTest('pim_group', $id);
            foreach (array_keys($this->permissionRows($id)) as $row) {
                $this->deleteAfterTest('pim_permission', $row);
            }
        }

        return $id;
    }
}
