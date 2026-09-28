<?php
namespace Tests\Integration\Api;

use Areanet\PIM\Entity\Permission;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Integration\IntegrationTestCase;

/**
 * A group has more rights-relevant fields than `permissions` (`015-000-0013`).
 *
 * THE FINDING. `RightsManagement` declares managing rights to be an admin's business and then
 * blocked exactly one key on `PIM\Group`. Two more fields decide what somebody may do:
 *
 *   `languages`     the language rights that `I18nPermission` enforces. `{"languages":"{}"}`
 *                   lifted the restrictions of the caller's own group.
 *   `tokenTimeout`  the lifetime of the group's tokens; `0` means "never expires".
 *
 * `apiQueryEnabled` belongs to the same class. It has had no effect since `000-000-0097` made
 * `/api/query` admin-only — which is the reason to guard it rather than not to: a column without
 * effect today is one somebody gives an effect back to tomorrow.
 *
 * WHAT IS MEASURED IS THE COLUMN, not the status code alone: a refusal that still wrote would
 * pass a status assertion.
 *
 * The write right on `PIM\Group` that this needs is a supported configuration, and the notes to
 * `000-000-0090` say no known project grants it — which is why these findings were MEDIUM and
 * not HIGH, and why they survived two stories.
 */
class GroupRightsFieldsApiTest extends IntegrationTestCase
{
    /** @return array<string, array{0: string, 1: mixed, 2: string}> field, new value, column */
    public static function guardedFields(): array
    {
        return array(
            'languages lifted'      => array('languages', array(), 'languages'),
            'languages narrowed'    => array('languages', array('de' => 'readable'), 'languages'),
            'tokenTimeout endless'  => array('tokenTimeout', 0, 'tokenTimeout'),
            'tokenTimeout extended' => array('tokenTimeout', 99999, 'tokenTimeout'),
            'apiQueryEnabled'       => array('apiQueryEnabled', 'enabled', 'apiQueryEnabled'),
        );
    }

    #[DataProvider('guardedFields')]
    public function testANonAdminCannotChangeARightsFieldOfHisGroup(string $field, mixed $value, string $column): void
    {
        [$token, , $group] = $this->memberWithWriteRightOnGroups();
        $before = $this->groupColumn($group, $column);

        [$status, $body] = $this->postJson('/api/update', array(
            'entity' => 'PIM\\Group', 'id' => $group, 'data' => array($field => $value),
        ), $token);

        $this->assertDenied($status, $body);
        $this->assertSame($before, $this->groupColumn($group, $column), 'and the column is untouched');
    }

    /** The same for another group — a member must not set restrictions on somebody else either. */
    public function testANonAdminCannotChangeARightsFieldOfAnotherGroup(): void
    {
        [$token]           = $this->memberWithWriteRightOnGroups();
        [, , $otherGroup]  = $this->createTestUser();
        $before            = $this->groupColumn($otherGroup, 'languages');

        [$status, $body] = $this->postJson('/api/update', array(
            'entity' => 'PIM\\Group', 'id' => $otherGroup,
            'data'   => array('languages' => array('de' => 'readable')),
        ), $token);

        $this->assertDenied($status, $body);
        $this->assertSame($before, $this->groupColumn($otherGroup, 'languages'));
    }

    /** `permissions` stays refused on the key alone, as before. */
    public function testPermissionsStayRefused(): void
    {
        [$token, , $group] = $this->memberWithWriteRightOnGroups();

        [$status, $body] = $this->postJson('/api/update', array(
            'entity' => 'PIM\\Group', 'id' => $group, 'data' => array('permissions' => array()),
        ), $token);

        $this->assertDenied($status, $body);
    }

    // ── what still works ───────────────────────────────────────────────────────────────────

    /** Renaming the own group was allowed and stays allowed. */
    public function testANonAdminMayStillRenameHisGroup(): void
    {
        [$token, , $group] = $this->memberWithWriteRightOnGroups();
        $name = 'Renamed '.bin2hex(random_bytes(4));

        [$status, $body] = $this->postJson('/api/update', array(
            'entity' => 'PIM\\Group', 'id' => $group, 'data' => array('name' => $name),
        ), $token);

        $this->assertSame(200, $status, json_encode($body['errors'] ?? $body));
        $this->assertSame($name, $this->groupColumn($group, 'name'));
    }

    /**
     * A VALUE THAT DOES NOT CHANGE IS NOT A CHANGE — the promise the whole class rests on. A
     * client that posts the record back as it read it keeps working, guarded fields included.
     */
    public function testPostingTheGuardedFieldsBackUnchangedStillWorks(): void
    {
        [$token, , $group] = $this->memberWithWriteRightOnGroups();

        [$status, $body] = $this->postJson('/api/update', array(
            'entity' => 'PIM\\Group', 'id' => $group,
            'data'   => array(
                'name'            => $this->groupColumn($group, 'name'),
                'tokenTimeout'    => (int) $this->groupColumn($group, 'tokenTimeout'),
                'apiQueryEnabled' => $this->groupColumn($group, 'apiQueryEnabled'),
            ),
        ), $token);

        $this->assertSame(200, $status, json_encode($body['errors'] ?? $body));
    }

    /** An admin may set all of it — the rule is about who, not about what. */
    #[DataProvider('guardedFields')]
    public function testAnAdminMayStillChangeTheRightsFields(string $field, mixed $value, string $column): void
    {
        [, , $group] = $this->createTestUser();

        [$status, $body] = $this->postJson('/api/update', array(
            'entity' => 'PIM\\Group', 'id' => $group, 'data' => array($field => $value),
        ), $this->token());

        $this->assertSame(200, $status, json_encode($body['errors'] ?? $body));
    }

    // ── the shape of `languages` ───────────────────────────────────────────────────────────

    /** @return array<string, array{0: mixed}> */
    public static function malformedLanguageMaps(): array
    {
        return array(
            'unknown permission' => array(array('de' => 'writable')),
            'permission empty'   => array(array('de' => '')),
            'permission a list'  => array(array('de' => array('readable'))),
            'not a map'          => array('readable'),
        );
    }

    /**
     * The map is checked before it is stored — a key that is no language or a value that is no
     * permission was written through and then read as "no restriction", one layer earlier than
     * `015-000-0011`.
     *
     * Checked as an admin on purpose: the shape is not a question of who may write it, and
     * `RightsManagement` would answer for a non-admin before this check is ever reached.
     */
    #[DataProvider('malformedLanguageMaps')]
    public function testAMalformedLanguageMapIsRefused(mixed $languages): void
    {
        [, , $group] = $this->createTestUser();
        $before      = $this->groupColumn($group, 'languages');

        [$status, $body] = $this->postJson('/api/update', array(
            'entity' => 'PIM\\Group', 'id' => $group, 'data' => array('languages' => $languages),
        ), $this->token());

        $this->assertSame(400, $status, json_encode($body['errors'] ?? $body));
        $this->assertErrorEnvelope($body, 'contentfly_general_invalid_params');
        $this->assertSame($before, $this->groupColumn($group, 'languages'), 'and nothing was stored');
    }

    // ── helpers ────────────────────────────────────────────────────────────────────────────

    /** @return array{0:string,1:string,2:string} token, user id, group id */
    private function memberWithWriteRightOnGroups(): array
    {
        return $this->createTestUser(array('PIM\\Group' => array(
            'readable' => Permission::ALL,
            'writable' => Permission::ALL,
        )));
    }

    private function assertDenied(int $status, array $body): void
    {
        $this->assertSame(403, $status, json_encode($body['errors'] ?? $body));
        $this->assertErrorEnvelope($body, 'contentfly_general_permission_denied');
    }

    private function groupColumn(string $group, string $column): mixed
    {
        return $this->pdo()->query("SELECT `$column` FROM pim_group WHERE id = ".$this->pdo()->quote($group))->fetchColumn();
    }
}
