<?php
namespace Tests\Integration\Api;

use Areanet\PIM\Entity\Permission;
use Tests\Integration\IntegrationTestCase;

/**
 * Characterisation tests for two things on their way out: a back door that `013-001` removes,
 * and two permission fields that are published but enforced nowhere.
 *
 * **`canExport` and `getExtended` were the fifth and sixth case** of a pattern running through
 * epic `008`: fields whose only users were the deleted user interface. They stood in the
 * `permissions` block of the schema and were checked nowhere.
 *
 * **With `000-000-0012` they are removed from the schema**, and the assertions here are
 * deliberately inverted: they now record that the two keys *no longer* appear and that the
 * database columns have been left untouched. The reasoning is in the class comment of
 * `Areanet\PIM\Classes\Permission`; in short: a right that the server publishes and does not
 * enforce looks like a guarantee, but is not one.
 */
class UnenforcedPermissionApiTest extends IntegrationTestCase
{
    private function tag(string $title): string
    {
        $id = 'ue-'.bin2hex(random_bytes(6));

        $this->pdo()->prepare(
            'INSERT INTO pim_tag (id, title, created, modified, views, isIntern)
             VALUES (:id, :title, NOW(), NOW(), 0, 0)'
        )->execute(array('id' => $id, 'title' => $title));

        $this->deleteAfterTest('pim_tag', $id);

        return $id;
    }

    // ── The master password ────────────────────────────────────────────────────────────

    public function testTheMasterPasswordNoLongerExists(): void
    {
        // INVERTED WITH 013-001-0002, as the old test announced.
        //
        // It was called `testDasMasterPasswortIstInDerVorlageNichtGesetzt` and asserted that the
        // default value is `null` — "the back door is closed, but present". It is gone now:
        // `APP_MASTER_PASSWORD` exists neither in `Classes/Config.php` nor in the
        // `AuthController`.
        //
        // The source code is checked and not the behaviour, because there is no behaviour any
        // more — you cannot configure what does not exist. That is the difference between
        // "switched off" and "removed", and that is exactly what this was about.
        foreach (array('lib/contentfly/Classes/Config.php', 'lib/contentfly/Controller/AuthController.php') as $file) {
            $source = file_get_contents(CONTENTFLY_PROJECT_DIR.'/'.$file);

            // The name may appear in EXPLANATIONS — they describe what has been dropped.
            $withoutComments = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $source);

            $this->assertStringNotContainsString('APP_MASTER_PASSWORD', (string) $withoutComments,
                $file.' names the master password only in explanations, not in code');
        }
    }

    public function testAWrongLoginFails(): void
    {
        // The addition "as long as no master password is set" was dropped with 013-001-0002.
        // Before, this assertion only held under a condition that a configuration line could
        // lift. Now it holds.
        [$status, $body] = $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => 'wrong'));

        $this->assertSame(401, $status);
        $this->assertArrayNotHasKey('token', $body);
    }

    // ── canExport and getExtended: no longer published ─────────────────────────────────

    public function testThePermissionsBlockListsOnlyTheThreeEnforcedRights(): void
    {
        // Inverted with 000-000-0012. The test was called
        // testDerPermissionsBlockDesSchemasFuehrtExportUndExtended and recorded five keys,
        // two of which had no enforcement point.
        [$status, $raw] = $this->get('/api/schema', $this->token());
        $this->assertSame(200, $status);

        $rights = json_decode($raw, true)['permissions'];

        $this->assertSame(
            array('readable', 'writable', 'deletable'),
            array_keys($rights['PIM\\Tag']),
            'Three fields per entity — and each of them is checked'
        );
    }

    public function testEvenForANonAdminTheTwoFieldsNoLongerAppear(): void
    {
        // The user gets both columns explicitly set. Still, none of it shows up in the schema:
        // the value is in the database, the API no longer claims anything about it.
        [$token] = $this->createTestUser(array('PIM\\Tag' => array(
            'readable' => Permission::ALL,
            'export'   => 0,
            'extended' => '{"fields":["title"]}',
        )));

        [, $raw] = $this->get('/api/schema', $token);
        $rights  = json_decode($raw, true)['permissions']['PIM\\Tag'];

        $this->assertArrayNotHasKey('export', $rights);
        $this->assertArrayNotHasKey('extended', $rights);
        $this->assertSame(Permission::ALL, $rights['readable'], 'The three others are there unchanged');
    }

    public function testTheColumnsRemainAndAreReadable(): void
    {
        // The counter-proof to the removal: only the keys in the schema were dropped, not the
        // data. An existing project may have values in pim_permission.export and .extended;
        // throwing them away would be the irreversible direction.
        [, , $groupId] = $this->createTestUser(array('PIM\\Tag' => array(
            'readable' => Permission::ALL,
            'export'   => Permission::ALL,
            'extended' => '{"fields":["title"]}',
        )));

        $row = $this->pdo()
            ->query('SELECT export, extended FROM pim_permission WHERE group_id = '.$this->pdo()->quote($groupId))
            ->fetch(\PDO::FETCH_ASSOC);

        $this->assertSame(Permission::ALL, (int) $row['export']);
        $this->assertSame('{"fields":["title"]}', $row['extended']);
    }

    // ── The columns still have no effect ───────────────────────────────────────────────

    public function testWithoutExportRightEverythingTheApiOffersIsStillPossible(): void
    {
        // The proof of ineffectiveness: export = 0, and the user can still read, write and
        // delete. There is no endpoint that checks the right — the ExportController that would
        // have done so was dropped with 012-001-0003.
        //
        // The test stays unchanged after 000-000-0012, and that is intentional: before, it
        // recorded a contradiction (the API publishes a right and ignores it), and now it
        // records a statement (the column is project data, nothing else).
        [$token] = $this->createTestUser(array('PIM\\Tag' => array(
            'readable'  => Permission::ALL,
            'writable'  => Permission::ALL,
            'deletable' => Permission::ALL,
            'export'    => 0,
        )));

        $this->tag('Despite-Export-Block');

        [$statusList] = $this->postJson('/api/list', array('entity' => 'PIM\\Tag'), $token);
        $this->assertSame(200, $statusList, 'Reading works');

        [$statusInsert, $created] = $this->postJson(
            '/api/insert',
            array('entity' => 'PIM\\Tag', 'data' => array('title' => 'Despite-Export-Block-new')),
            $token
        );
        $this->assertSame(200, $statusInsert, 'Writing works');
        $this->deleteAfterTest('pim_tag', $created['id']);

        [$statusDelete] = $this->postJson(
            '/api/delete',
            array('entity' => 'PIM\\Tag', 'id' => $created['id']),
            $token
        );
        $this->assertSame(200, $statusDelete, 'Deleting works — export=0 changes nothing at all');

        $this->pdo()->prepare('DELETE FROM pim_log WHERE model_id = :id')->execute(array('id' => $created['id']));
    }

    public function testAnExtendedEntryDoesNotChangeTheResponse(): void
    {
        // extended carries a JSON structure that was once meant to restrict the form.
        // A client can read it from the schema; the API itself does not evaluate it —
        // the response contains all fields, regardless of what is in there.
        [$token] = $this->createTestUser(array('PIM\\Tag' => array(
            'readable' => Permission::ALL,
            'extended' => '{"onlyTheseFields":["id"]}',
        )));

        $tag = $this->tag('Extended-Probe');

        [$status, $body] = $this->postJson(
            '/api/single',
            array('entity' => 'PIM\\Tag', 'id' => $tag),
            $token
        );

        $this->assertSame(200, $status);
        $this->assertArrayHasKey('title', $body['data'],
            'Despite extended the full object comes back — the field is not evaluated');
        $this->assertArrayHasKey('created', $body['data']);
    }
}
