<?php
namespace Tests\Integration\Api;

use Areanet\PIM\Entity\Permission;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Integration\IntegrationTestCase;

/**
 * THE RELATION FIELD TYPES, WRITTEN AND READ OVER HTTP (000-000-0067).
 *
 * `file`, `multifile`, `checkbox`, `radio`, `onejoin` and `permissions` had no test that reached
 * their `toDatabase()` or `fromDatabase()`: no entity of the framework or the template used them,
 * and the first coverage measurement found `Classes/Types` at 34 %. Projects use them, and every
 * value of such a field passes through that code — including the permission checks that hide a
 * joined record from a caller who may not read it.
 *
 * The first five run on the template's `Core\ExampleRelations`, which exists for exactly this.
 * `permissions` runs on `PIM\Group`, the only entity that has such a field.
 *
 * FOUR DEFECTS SURFACED WHILE WRITING THESE TESTS, all fixed in the same task:
 *  - a one-to-one field came back as `null` in every `/api/list` (`Api::getList()`),
 *  - `multifile` with `properties` returned `{}` per file,
 *  - `permissions` returned the group's own id instead of each permission row's,
 *  - `properties: ["permissions"]` on `PIM\Group` answered 500.
 * Each has a test below that was red before the fix.
 */
class FieldTypeApiTest extends IntegrationTestCase
{
    private const ENTITY = 'Core\\ExampleRelations';

    /** @var list<string> Ids whose log rows tearDown() removes. */
    private array $logged = array();

    protected function tearDown(): void
    {
        foreach ($this->logged as $id) {
            $this->pdo()->prepare('DELETE FROM pim_log WHERE model_id = :id')->execute(array('id' => $id));
        }
        $this->logged = array();

        parent::tearDown();
    }

    // ── file ───────────────────────────────────────────────────────────────────────────────

    public function testFileFieldStoresTheFileAndReturnsIt(): void
    {
        $file   = $this->upload('attachment.txt');
        $record = $this->record(array('attachment' => $file));

        $this->assertSame($file, $this->single($record)['attachment']['id']);
        $this->assertSame('attachment.txt', $this->listed($record)['attachment']['name'],
            'The list delivers the file itself, not only its id');
    }

    public function testFileFieldRejectsAnUnknownFile(): void
    {
        [$status, $body] = $this->postJson('/api/insert', array(
            'entity' => self::ENTITY,
            'data'   => array('attachment' => 'no-such-file'),
        ), $this->token());

        $this->assertSame(404, $status);
        $this->assertErrorEnvelope($body);
    }

    public function testFileFieldIsBlockedWithoutReadPermissionOnFiles(): void
    {
        $file   = $this->upload('secret.txt');
        $record = $this->record(array('attachment' => $file));

        [$token] = $this->createTestUser(array(self::ENTITY => array('readable' => Permission::ALL)));

        $this->assertSame(array('id' => $file, 'pim_blocked' => true), $this->listed($record, $token)['attachment'],
            'The caller learns that a file is attached — and nothing about it');
    }

    // ── multifile ──────────────────────────────────────────────────────────────────────────

    public function testMultifileStoresSeveralFilesAndReturnsThem(): void
    {
        $first  = $this->upload('first.txt');
        $second = $this->upload('second.txt');
        $record = $this->record(array('attachments' => array($first, array('id' => $second))));

        $ids = array_column($this->single($record)['attachments'], 'id');
        sort($ids);
        $expected = array($first, $second);
        sort($expected);

        $this->assertSame($expected, $ids, 'Plain ids and {"id": …} objects are both accepted');
    }

    public function testMultifileWithPropertiesReturnsTheFilesNotEmptyObjects(): void
    {
        // Red before 000-000-0067: this branch serialised the RECORD as a file, filtered by the
        // caller's property list — every entry came back as {}.
        $file   = $this->upload('listed.txt');
        $record = $this->record(array('attachments' => array($file)));

        [, $body] = $this->postJson('/api/list', array('entity' => self::ENTITY, 'properties' => array('id', 'attachments')), $this->token());
        $row = $this->row($body['data'], $record);

        $this->assertSame($file, $row['attachments'][0]['id']);
        $this->assertSame('listed.txt', $row['attachments'][0]['name']);
    }

    public function testMultifileIsHiddenWithoutReadPermissionOnFiles(): void
    {
        $record = $this->record(array('attachments' => array($this->upload('hidden.txt'))));

        [$token] = $this->createTestUser(array(self::ENTITY => array('readable' => Permission::ALL)));

        $this->assertNull($this->listed($record, $token)['attachments']);
    }

    // ── checkbox ───────────────────────────────────────────────────────────────────────────

    public function testCheckboxStoresOptionsOfItsGroup(): void
    {
        $group = $this->optionGroup('categories');
        $a     = $this->option($group, 'Alpha');
        $b     = $this->option($group, 'Beta');

        $record = $this->record(array('categories' => array($a, $b)));

        $values = array_column($this->single($record)['categories'], 'value');
        sort($values);
        $this->assertSame(array('Alpha', 'Beta'), $values);

        [, $body] = $this->postJson('/api/list', array('entity' => self::ENTITY, 'properties' => array('id', 'categories')), $this->token());
        $ids = $this->row($body['data'], $record)['categories'];
        sort($ids);
        $expected = array($a, $b);
        sort($expected);
        $this->assertSame($expected, $ids, 'With properties the field returns the option ids');
    }

    public function testCheckboxWithAnEmptyListClearsTheSelection(): void
    {
        $group  = $this->optionGroup('categories');
        $record = $this->record(array('categories' => array($this->option($group, 'Gone'))));

        [$status] = $this->postJson('/api/update', array(
            'entity' => self::ENTITY, 'id' => $record, 'data' => array('categories' => array()),
        ), $this->token());

        $this->assertSame(200, $status);
        $this->assertSame(array(), $this->single($record)['categories']);
    }

    public function testCheckboxIsHiddenWithoutReadPermissionOnOptions(): void
    {
        $record = $this->record(array('categories' => array($this->option($this->optionGroup('categories'), 'Hidden'))));

        [$token] = $this->createTestUser(array(self::ENTITY => array('readable' => Permission::ALL)));

        $this->assertNull($this->listed($record, $token)['categories']);
    }

    // ── radio ──────────────────────────────────────────────────────────────────────────────

    public function testRadioStoresOneOptionAndCanBeCleared(): void
    {
        $option = $this->option($this->optionGroup('category'), 'Chosen');
        $record = $this->record(array('category' => array('id' => $option)));

        $this->assertSame('Chosen', $this->listed($record)['category']['value']);

        $this->postJson('/api/update', array(
            'entity' => self::ENTITY, 'id' => $record, 'data' => array('category' => null),
        ), $this->token());

        $this->assertNull($this->single($record)['category']);
    }

    public function testRadioIsBlockedWithoutReadPermissionOnOptions(): void
    {
        $option = $this->option($this->optionGroup('category'), 'Blocked');
        $record = $this->record(array('category' => $option));

        [$token] = $this->createTestUser(array(self::ENTITY => array('readable' => Permission::ALL)));

        $this->assertSame(array('id' => $option, 'pim_blocked' => true), $this->listed($record, $token)['category']);
    }

    public function testRadioIsBlockedWhenTheCallerMayReadOnlyOwnOptions(): void
    {
        // OWN on the target: the admin created the option, so it is someone else's.
        $option = $this->option($this->optionGroup('category'), 'Foreign');
        $record = $this->record(array('category' => $option));

        [$token] = $this->createTestUser(array(
            self::ENTITY  => array('readable' => Permission::ALL),
            'PIM\\Option' => array('readable' => Permission::OWN),
        ));

        $this->assertSame(array('id' => $option, 'pim_blocked' => true), $this->listed($record, $token)['category']);
    }

    // ── onejoin ────────────────────────────────────────────────────────────────────────────

    public function testOnejoinCreatesTheJoinedRecordAndUpdatesIt(): void
    {
        $record = $this->record(array('example' => array('name' => 'Inner')));
        $inner  = $this->single($record)['example']['id'];
        $this->registerExample($inner);

        $this->assertSame('Inner', $this->pdo()->query('SELECT name FROM example_entity WHERE id = '.$this->pdo()->quote($inner))->fetchColumn(),
            'Without an id the nested object creates the joined record');

        $this->postJson('/api/update', array(
            'entity' => self::ENTITY, 'id' => $record, 'data' => array('example' => array('id' => $inner, 'name' => 'Changed')),
        ), $this->token());

        $this->assertSame('Changed', $this->pdo()->query('SELECT name FROM example_entity WHERE id = '.$this->pdo()->quote($inner))->fetchColumn(),
            'With an id it updates that record instead of creating a second one');
    }

    public function testOnejoinIsPartOfEveryList(): void
    {
        // Red before 000-000-0067: Api::getList() did not join one-to-one fields, and under
        // HINT_FORCE_PARTIAL_LOAD an unjoined relation is null — in every list, while
        // /api/single returned it.
        $record = $this->record(array('example' => array('name' => 'Listed')));
        $inner  = $this->single($record)['example']['id'];
        $this->registerExample($inner);

        $this->assertSame('Listed', $this->listed($record)['example']['name']);

        [, $body] = $this->postJson('/api/list', array('entity' => self::ENTITY, 'flatten' => true), $this->token());
        $this->assertSame(array('id' => $inner), $this->row($body['data'], $record)['example']);
    }

    public function testOnejoinIsBlockedWithoutReadPermissionOnTheTarget(): void
    {
        $record = $this->record(array('example' => array('name' => 'Private')));
        $inner  = $this->single($record)['example']['id'];
        $this->registerExample($inner);

        [$token] = $this->createTestUser(array(self::ENTITY => array('readable' => Permission::ALL)));

        $this->assertSame(array('id' => $inner, 'pim_blocked' => true), $this->listed($record, $token)['example']);
    }

    // ── permissions (PIM\Group) ────────────────────────────────────────────────────────────

    public function testGroupPermissionsAreStoredAndReturnedToAdmins(): void
    {
        $group = $this->group(array(
            array('name' => 'Core\\Example', 'readable' => Permission::ALL, 'writable' => Permission::OWN, 'deletable' => Permission::NONE, 'export' => 0),
        ));

        $rows = $this->groupRow($group, array())['permissions'];
        $example = array_values(array_filter($rows, fn ($row) => $row['entityName'] === 'Core\\Example'));

        $this->assertCount(1, $example);
        $this->assertSame(Permission::ALL, (int) $example[0]['readable']);
        $this->assertSame(Permission::OWN, (int) $example[0]['writable']);
    }

    public function testGroupPermissionsReturnEachRowAndNotTheGroup(): void
    {
        // Red before 000-000-0067: flatten and properties returned the group's own id once per row.
        $group = $this->group(array(
            array('name' => 'Core\\Example', 'readable' => Permission::ALL, 'writable' => 0, 'deletable' => 0, 'export' => 0),
        ));
        $expected = $this->pdo()->query('SELECT id FROM pim_permission WHERE group_id = '.$this->pdo()->quote($group).' ORDER BY id')->fetchAll(\PDO::FETCH_COLUMN);

        $flat = array_column($this->groupRow($group, array('flatten' => true))['permissions'], 'id');
        sort($flat);
        $this->assertSame($expected, $flat);

        $ids = $this->groupRow($group, array('properties' => array('id', 'permissions')))['permissions'];
        sort($ids);
        $this->assertSame($expected, $ids, 'properties: ["permissions"] answered 500 before — the collection went into the partial select');
    }

    /**
     * Turned around with `000-000-0070`. Until then this pinned the opposite: every group whose
     * permissions were written also got a `PIM\Tag` row with read, write and delete at ALL —
     * whatever the request said.
     */
    public function testWritingGroupPermissionsGrantsNothingThatWasNotAskedFor(): void
    {
        $group = $this->group(array());

        $rows = $this->pdo()->query('SELECT entityName FROM pim_permission WHERE group_id = '.$this->pdo()->quote($group))->fetchAll(\PDO::FETCH_COLUMN);

        $this->assertSame(array(), $rows, 'An empty permissions list must write no row at all.');
    }

    /**
     * The worse half of `000-000-0070`, and the reason the row could not simply stay.
     *
     * The hard-wired entry was written next to the requested ones, and `Classes\Permission::is()`
     * returned the FIRST entry matching the entity name. The association carries no `ORDER BY`;
     * with GUID ids the database returns the rows in id order, so it was chance per group whether
     * an explicit `PIM\Tag` entry or the ALL row counted. Since `000-000-0088` the most restrictive
     * row counts — see the tests on existing data below.
     */
    public function testAnExplicitTagPermissionIsTheOneThatCounts(): void
    {
        $group = $this->group(array(
            array('name' => 'PIM\\Tag', 'readable' => 1, 'writable' => 0, 'deletable' => 0, 'export' => 0),
        ));

        $rows = $this->pdo()->query("SELECT readable, writable, deletable FROM pim_permission WHERE entityName = 'PIM\\\\Tag' AND group_id = ".$this->pdo()->quote($group).' ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertCount(1, $rows, 'Exactly one row for PIM\Tag — a second one would shadow this.');
        $this->assertSame(array('readable' => 1, 'writable' => 0, 'deletable' => 0), array_map('intval', $rows[0]));
    }

    /**
     * EXISTING DATA (000-000-0088). A group written before `000-000-0070` with an explicit
     * `PIM\Tag` entry has two rows: the old ALL row and the restriction. The restriction must count
     * without the group being saved again.
     *
     * The rows are written by SQL with ids that put the ALL row first, and the test checks that the
     * database really returns it first — otherwise it would pass by the same chance that decided
     * these groups until now.
     */
    public function testALegacyAllRowNoLongerShadowsAnExplicitTagRestriction(): void
    {
        [$token, , $group] = $this->createTestUser();
        $this->legacyTagRows($group, array(
            array(Permission::ALL, Permission::ALL, Permission::ALL),
            array(Permission::NONE, Permission::NONE, Permission::NONE),
        ));

        $first = $this->pdo()->query("SELECT readable FROM pim_permission WHERE entityName = 'PIM\\\\Tag' AND group_id = ".$this->pdo()->quote($group))->fetchColumn();
        $this->assertSame(Permission::ALL, (int) $first, 'Precondition: the database returns the ALL row first.');

        [$status] = $this->postJson('/api/list', array('entity' => 'PIM\\Tag'), $token);

        $this->assertSame(403, $status, 'The explicit restriction counts, not the old ALL row.');
    }

    /**
     * A group with ONLY the old row keeps its tag access. Nobody asked for that access, but taking
     * it away without the group being written would change a working installation on deploy.
     * `0070` announced that the row goes when the group's permissions are written next.
     */
    public function testAGroupWithOnlyTheLegacyAllRowKeepsItsTagAccess(): void
    {
        [$token, , $group] = $this->createTestUser();
        $this->legacyTagRows($group, array(array(Permission::ALL, Permission::ALL, Permission::ALL)));

        [$status] = $this->postJson('/api/list', array('entity' => 'PIM\\Tag'), $token);

        $this->assertSame(200, $status);
    }

    public function testDuplicateEntityNamesAreRejectedAndLeaveThePermissionsAlone(): void
    {
        $group  = $this->group(array($this->entry('Core\\Example')));
        $before = $this->permissionRows($group);

        [$status, $body] = $this->postJson('/api/update', array(
            'entity' => 'PIM\\Group', 'id' => $group, 'data' => array('permissions' => array(
                $this->entry('PIM\\Tag', Permission::ALL),
                $this->entry('PIM\\Tag', Permission::NONE),
            )),
        ), $this->token());

        $this->assertSame(400, $status);
        $this->assertErrorEnvelope($body, 'contentfly_general_invalid_params');
        $this->assertSame($before, $this->permissionRows($group), 'A rejected write must not have deleted the existing rows.');
    }

    public function testDuplicateEntityNamesOnInsertCreateNoGroup(): void
    {
        $name = 'Types '.bin2hex(random_bytes(4));

        [$status] = $this->postJson('/api/insert', array(
            'entity' => 'PIM\\Group',
            'data'   => array('name' => $name, 'tokenTimeout' => 60, 'permissions' => array(
                $this->entry('Core\\Example'),
                $this->entry('Core\\Example'),
            )),
        ), $this->token());

        $created = $this->pdo()->query('SELECT id FROM pim_group WHERE name = '.$this->pdo()->quote($name))->fetchColumn();
        if ($created !== false) {
            $this->deleteAfterTest('pim_group', $created);
            foreach (array_keys($this->permissionRows($created)) as $row) {
                $this->deleteAfterTest('pim_permission', $row);
            }
        }

        $this->assertSame(400, $status);
        $this->assertFalse($created, 'The group must not exist half-written.');
    }

    /**
     * Before `000-000-0088` each of these answered 500 — and on update the group had lost all its
     * permissions by then, because the old rows were deleted before the new ones were read.
     * `null` and a string answered 200 with the same loss and a PHP warning in the log.
     *
     * @param mixed $permissions
     */
    #[DataProvider('malformedPermissions')]
    public function testAMalformedPermissionsValueIsRejectedAndLeavesThePermissionsAlone($permissions): void
    {
        $group  = $this->group(array($this->entry('Core\\Example')));
        $before = $this->permissionRows($group);

        [$status, $body] = $this->postJson('/api/update', array(
            'entity' => 'PIM\\Group', 'id' => $group, 'data' => array('permissions' => $permissions),
        ), $this->token());

        $this->assertSame(400, $status);
        $this->assertErrorEnvelope($body, 'contentfly_general_invalid_params');
        $this->assertSame($before, $this->permissionRows($group));
    }

    /** @return array<string, array{0:mixed}> */
    public static function malformedPermissions(): array
    {
        $entry = array('name' => 'Core\\Example', 'readable' => Permission::ALL, 'writable' => 0, 'deletable' => 0, 'export' => 0);

        return array(
            'without name'      => array(array(array_diff_key($entry, array('name' => 1)))),
            'empty name'        => array(array(array('name' => '') + $entry)),
            'without readable'  => array(array(array_diff_key($entry, array('readable' => 1)))),
            'without writable'  => array(array(array_diff_key($entry, array('writable' => 1)))),
            'without deletable' => array(array(array_diff_key($entry, array('deletable' => 1)))),
            'entry is a string' => array(array('Core\\Example')),
            'null'              => array(null),
            'a string'          => array('Core\\Example'),
        );
    }

    /**
     * `export` has had no effect since `000-000-0012`. Requiring it would force clients to send a
     * field that does nothing — it was only ever required by accident, as an undefined index.
     */
    public function testExportMayBeOmitted(): void
    {
        $entry = $this->entry('Core\\Example');
        unset($entry['export']);

        $group = $this->group(array($entry));
        $rows  = array_values($this->permissionRows($group));

        $this->assertSame(array('Core\\Example'), array_column($rows, 'entityName'));
        $this->assertSame(0, (int) $rows[0]['export']);
    }

    public function testGroupPermissionsAreHiddenFromNonAdmins(): void
    {
        $group = $this->group(array(
            array('name' => 'Core\\Example', 'readable' => Permission::ALL, 'writable' => 0, 'deletable' => 0, 'export' => 0),
        ));

        [$token] = $this->createTestUser(array('PIM\\Group' => array('readable' => Permission::ALL)));

        [, $body] = $this->postJson('/api/single', array('entity' => 'PIM\\Group', 'id' => $group), $token);

        $this->assertNull($body['data']['permissions'], 'Who may do what is for admins only');
    }

    // ── helpers ────────────────────────────────────────────────────────────────────────────

    /** Creates a record of the example entity as admin; returns its id. */
    private function record(array $data): string
    {
        [$status, $body] = $this->postJson('/api/insert', array('entity' => self::ENTITY, 'data' => $data), $this->token());

        $this->assertSame(200, $status, 'Precondition: insert succeeds — '.json_encode($body['errors'] ?? null));

        $id = $body['data']['id'];
        $this->deleteAfterTest('example_relations', $id);
        $this->logged[] = $id;

        return $id;
    }

    private function single(string $id, ?string $token = null): array
    {
        [$status, $body] = $this->postJson('/api/single', array('entity' => self::ENTITY, 'id' => $id), $token ?? $this->token());
        $this->assertSame(200, $status);

        return $body['data'];
    }

    private function listed(string $id, ?string $token = null): array
    {
        [$status, $body] = $this->postJson('/api/list', array('entity' => self::ENTITY), $token ?? $this->token());
        $this->assertSame(200, $status, json_encode($body['errors'] ?? null));

        return $this->row($body['data'], $id);
    }

    private function row(array $rows, string $id): array
    {
        foreach ($rows as $row) {
            if (($row['id'] ?? null) === $id) {
                return $row;
            }
        }

        $this->fail("Record $id is not in the list");
    }

    /** Uploads a text file as admin; returns its id. */
    private function upload(string $name): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cf-types-');
        file_put_contents($tmp, "field type test\n");

        $ch = curl_init(self::$baseUrl.'/file/upload');
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => array('file' => new \CURLFile($tmp, 'text/plain', $name)),
            CURLOPT_HTTPHEADER     => array('appcms-token: '.$this->token()),
        ));
        $body = json_decode((string) curl_exec($ch), true) ?: array();
        unlink($tmp);

        $id = $body['data']['id'] ?? null;
        $this->assertNotNull($id, 'Precondition: upload succeeds');

        $this->deleteAfterTest('pim_file', $id);
        $this->deleteDirectoryAfterTest(self::dataDir().'/files/'.$id);
        $this->logged[] = $id;

        return $id;
    }

    /** The id of the option group the schema created for a field of the example entity. */
    private function optionGroup(string $field): string
    {
        [, $raw] = $this->get('/api/schema', $this->token());

        return json_decode($raw, true)['data'][self::ENTITY]['properties'][$field]['group'];
    }

    private function option(string $group, string $value): string
    {
        [$status, $body] = $this->postJson('/api/insert', array(
            'entity' => 'PIM\\Option', 'data' => array('value' => $value, 'group' => $group),
        ), $this->token());
        $this->assertSame(200, $status);

        $id = $body['data']['id'];
        $this->deleteAfterTest('pim_option', $id);
        $this->logged[] = $id;

        return $id;
    }

    /** The joined record a onejoin insert creates — registered after the record, so it is deleted first. */
    private function registerExample(string $id): void
    {
        $this->deleteAfterTest('example_entity', $id);
        $this->logged[] = $id;
    }

    /** Creates a group through the API, with its permissions; returns its id. */
    private function group(array $permissions): string
    {
        [$status, $body] = $this->postJson('/api/insert', array(
            'entity' => 'PIM\\Group',
            'data'   => array('name' => 'Types '.bin2hex(random_bytes(4)), 'tokenTimeout' => 60, 'permissions' => $permissions),
        ), $this->token());
        $this->assertSame(200, $status, json_encode($body['errors'] ?? null));

        $id = $body['data']['id'];
        $this->deleteAfterTest('pim_group', $id);
        $this->logged[] = $id;

        // Registered after the group, so they are deleted before it.
        foreach ($this->pdo()->query('SELECT id FROM pim_permission WHERE group_id = '.$this->pdo()->quote($id))->fetchAll(\PDO::FETCH_COLUMN) as $permission) {
            $this->deleteAfterTest('pim_permission', $permission);
        }

        return $id;
    }

    /** One entry of a `permissions` request. */
    private function entry(string $entity, int $readable = Permission::ALL): array
    {
        return array('name' => $entity, 'readable' => $readable, 'writable' => 0, 'deletable' => 0, 'export' => 0);
    }

    /** @return array<string, array<string, string>> the group's rows by id, as the database has them */
    private function permissionRows(string $group): array
    {
        $rows = $this->pdo()->query('SELECT id, entityName, readable, writable, deletable, export FROM pim_permission WHERE group_id = '.$this->pdo()->quote($group).' ORDER BY id')->fetchAll(\PDO::FETCH_ASSOC);

        return array_column($rows, null, 'id');
    }

    /**
     * Writes `PIM\Tag` rows the way the code before `000-000-0070` left them: `export` 0 and
     * `extended` NULL. The ids sort in the order given, so the first row is the one the database
     * returns first.
     *
     * @param list<array{0:int,1:int,2:int}> $levels readable, writable, deletable per row
     */
    private function legacyTagRows(string $group, array $levels): void
    {
        $run = bin2hex(random_bytes(6));

        foreach ($levels as $number => [$readable, $writable, $deletable]) {
            $id = 'tlegacy-'.$run.'-'.$number;
            $this->pdo()->prepare(
                "INSERT INTO pim_permission (id, entityName, readable, writable, deletable, export, extended,
                                             created, modified, views, isIntern, group_id)
                 VALUES (:id, 'PIM\\\\Tag', :readable, :writable, :deletable, 0, NULL, NOW(), NOW(), 0, 0, :grp)"
            )->execute(array('id' => $id, 'readable' => $readable, 'writable' => $writable, 'deletable' => $deletable, 'grp' => $group));
            $this->deleteAfterTest('pim_permission', $id);
        }
    }

    private function groupRow(string $group, array $options): array
    {
        [$status, $body] = $this->postJson('/api/list', array('entity' => 'PIM\\Group') + $options, $this->token());
        $this->assertSame(200, $status, json_encode($body['errors'] ?? null));

        return $this->row($body['data'], $group);
    }
}
