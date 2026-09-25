<?php
namespace Tests\Integration\Api;

use Areanet\PIM\Entity\Permission;
use Tests\Integration\IntegrationTestCase;

/**
 * The permission branches in `Api.php` that no test reached (000-000-0074).
 *
 * `000-000-0069` listed every place with `Permission::` or `I18nPermission::` that the coverage run
 * did not reach. A permission check without a test is a claim — `000-000-0072` showed that one of
 * them never worked. Every test here checks both directions: what must be visible or allowed, and
 * what must not be.
 *
 * WHAT THE TABLE OF 0069 SAID, AND WHERE IT IS ANSWERED NOW:
 *
 * | Method | Branch | Answered by |
 * |---|---|---|
 * | `getAll` | narrowing with `OWN` and `GROUP` | this class |
 * | `getAll`, `getCount`, `getList`, `getTree`, `getTree2`, `getTranslations`, `getQuery` | `GROUP` for a user **without** a group | **unreachable**, see below |
 * | `getDeleted` | entity without read right is left out | PermissionMatrixApiTest::testTheDeletionLogReportsOnlyReadableEntities() (000-000-0061) |
 * | `getCount` | narrowing with `OWN` and `GROUP` | this class; `GROUP` also in ReadPermissionApiTest (000-000-0072) |
 * | `getTree` / `getTree2` | `OWN`, `GROUP` with a group | PermissionMatrixApiTest::testTreeRoutesApplyTheReadLevel() (000-000-0061) |
 * | `getTranslations` | without read right | this class; `GROUP` in PermissionMatrixApiTest (000-000-0059) |
 * | `doInsert` / `doUpdate` / `doDelete` | a language the group may not write | this class |
 * | `doUpdate` | `PIM\User.pass` without or with a wrong current password | this class |
 *
 * THE "WITHOUT A GROUP" BRANCHES ARE UNREACHABLE. Seven methods carry
 * `elseif($permission == GROUP){ if(!$group){ … } }`. `Permission::is()` returns `NONE` for a
 * user without a group before it looks at a single permission row — so a user without a group
 * never gets the level `GROUP`, and the inner branch cannot run. What such a user does get is
 * checked in ReadPermissionApiTest::testUserWithoutGroupSeesNothing() and
 * PermissionMatrixApiTest::testUserWithoutGroupIsDeniedEveryOperation(); getAll() is added here,
 * because it answers with an empty set instead of 403.
 *
 * THE `continue` LINES ARE NOT OPEN. `getDeleted` and `getAll` leave an unreadable entity out
 * with `continue;`, and the coverage report lists that line as never run. PCOV counts no
 * `continue;` line at all — `getAll()` skips `_hash` on every single call, and that line is
 * "open" too. The branch runs; the report cannot show it.
 */
class PermissionBranchApiTest extends IntegrationTestCase
{
    /** The template's translatable entity, see custom/Entity/Core/ExampleI18n.php. */
    private const I18N_ENTITY = 'Core\\ExampleI18n';

    private string $adminId = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminId = (string) $this->pdo()
            ->query("SELECT id FROM pim_user WHERE alias = 'admin'")
            ->fetchColumn();
    }

    // ── getAll() ─────────────────────────────────────────────────────────────────────

    public function testAllWithLevelOwnDeliversOwnAndSharedObjectsOnly(): void
    {
        [$token, $userId] = $this->createTestUser(array('PIM\\Tag' => array('readable' => Permission::OWN)));

        $own     = $this->tag($userId);
        $shared  = $this->tag($this->adminId, null, $userId);
        $foreign = $this->tag($this->adminId);

        $delivered = $this->allIds($token);

        $this->assertContains($own, $delivered, 'OWN: the record the user created');
        $this->assertContains($shared, $delivered, 'OWN: a foreign record that lists the user in users');
        $this->assertNotContains($foreign, $delivered, 'OWN: a foreign record without a relation stays out');
    }

    public function testAllWithLevelGroupDeliversOwnSharedAndGroupSharedObjectsOnly(): void
    {
        // GROUP reaches what OWN reaches — created or listed in users — plus what is shared with
        // the group (reachesRow()). Until 000-000-0093 getAll() left the users list out: /api/list
        // showed the shared tag, /api/all did not, and a sync client never received it.
        [$token, $userId, $groupId] = $this->createTestUser(array('PIM\\Tag' => array('readable' => Permission::GROUP)));

        $own       = $this->tag($userId);
        $shared    = $this->tag($this->adminId, null, $userId);
        $groupTag  = $this->tag($this->adminId, $groupId);
        $unrelated = $this->tag($this->adminId);

        $delivered = $this->allIds($token);

        $this->assertContains($own, $delivered, 'GROUP: the record the user created');
        $this->assertContains($shared, $delivered, 'GROUP: a foreign record that lists the user in users');
        $this->assertContains($groupTag, $delivered, 'GROUP: a record that lists the own group');
        $this->assertNotContains($unrelated, $delivered, 'GROUP: a record without a relation stays out');
    }

    public function testAllForAUserWithoutGroupDeliversNothing(): void
    {
        // /api/list answers 403 here; /api/all leaves every unreadable entity out and answers
        // 200 with what is left — for a user without a group that is nothing, not even the own
        // record.
        [$token, $userId] = $this->createUserWithoutGroup();

        $own = $this->tag($userId);

        [$status, $body] = $this->postJson('/api/all', array(), $token);

        $this->assertSame(200, $status);
        $this->assertNotContains($own, array_column($this->assertEnvelope($body, array('lastModified'))['PIM\\Tag'] ?? array(), 'id'),
            'Without a group the level is NONE — the own record is not delivered either');
    }

    public function testAllWithoutReadRightLeavesTheEntityOut(): void
    {
        [$token] = $this->createTestUser(array('PIM\\Folder' => array('readable' => Permission::ALL)));

        $this->tag($this->adminId);

        [$status, $body] = $this->postJson('/api/all', array(), $token);

        $this->assertSame(200, $status);
        $this->assertArrayNotHasKey('PIM\\Tag', $this->assertEnvelope($body, array('lastModified')),
            'Without read right on PIM\\Tag the entity is not part of the answer');
    }

    // ── getCount() ───────────────────────────────────────────────────────────────────

    public function testCountWithLevelOwnCountsOwnAndSharedObjectsOnly(): void
    {
        [$token, $userId] = $this->createTestUser(array('PIM\\Tag' => array('readable' => Permission::OWN)));

        $this->tag($userId);
        $this->tag($this->adminId, null, $userId);
        $this->tag($this->adminId);

        [$status, $body] = $this->postJson('/api/count', array('entity' => 'PIM\\Tag'), $token);

        $this->assertSame(200, $status, json_encode($body['errors'] ?? null));
        $this->assertSame(2, $this->assertEnvelope($body)['details']['PIM\\Tag'],
            'The own tag and the one listing the user in users — the foreign one is not counted');
    }

    public function testCountWithLevelGroupCountsOwnSharedAndGroupSharedObjectsOnly(): void
    {
        // The same set as /api/all delivers — until 000-000-0093 the tag listing the user in
        // users was not counted.
        [$token, $userId, $groupId] = $this->createTestUser(array('PIM\\Tag' => array('readable' => Permission::GROUP)));

        $this->tag($userId);
        $this->tag($this->adminId, null, $userId);
        $this->tag($this->adminId, $groupId);
        $this->tag($this->adminId);

        [$status, $body] = $this->postJson('/api/count', array('entity' => 'PIM\\Tag'), $token);

        $this->assertSame(200, $status, json_encode($body['errors'] ?? null));
        $this->assertSame(3, $this->assertEnvelope($body)['details']['PIM\\Tag'],
            'Own, listed in users and shared with the group — the unrelated one is not counted');
    }

    // ── getTranslations() ────────────────────────────────────────────────────────────

    public function testTranslationsWithoutReadRightAreDenied(): void
    {
        [$token] = $this->createTestUser(array('PIM\\Tag' => array('readable' => Permission::ALL)));

        $this->i18nRecord($this->adminId);

        [$status, $body] = $this->postJson('/api/translations', array(
            'entity' => self::I18N_ENTITY,
            'lang'   => 'en',
        ), $token);

        $this->assertSame(403, $status, 'Without read right no count flows, not even an empty one');
        $this->assertErrorEnvelope($body);
    }

    public function testTranslationsWithReadRightAreCounted(): void
    {
        // The other direction of the test above, on the same records.
        [$token] = $this->createTestUser(array(self::I18N_ENTITY => array('readable' => Permission::ALL)));

        $this->i18nRecord($this->adminId);

        [$status, $body] = $this->postJson('/api/translations', array(
            'entity' => self::I18N_ENTITY,
            'lang'   => 'en',
        ), $token);

        $this->assertSame(200, $status);
        $counts = array_column($this->assertEnvelope($body), 'records', 'lang');
        $this->assertGreaterThanOrEqual(1, (int) ($counts['de'] ?? 0));
    }

    // ── Languages the group may not write (I18nPermission) ───────────────────────────
    //
    // `pim_group.languages` maps a language to `readable` or `translatable`; a language that is
    // not listed is writable. `readable` alone stops insert and update, anything listed stops
    // delete — delete asks for isWritable(), the other two for isOnlyReadable().
    //
    // THE REFUSAL IS A 403 (000-000-0095). Until then `ContentflyI18NException` passed 550 as its
    // code, and the envelope turned it into the status — no HTTP status, and as a 5xx it read as a
    // server error a client or proxy may retry. The error code in the envelope is unchanged.

    public function testInsertInALanguageTheGroupMayOnlyReadIsDenied(): void
    {
        [$status, $id, $body] = $this->insertTranslation('{"en":"readable"}');

        $this->assertSame(403, $status, 'en is readable only');
        $this->assertErrorEnvelope($body, 'contentfly_i18n_permission_denied');
        $this->assertFalse($this->i18nRowExists($id, 'en'), 'Checked against the database');
    }

    public function testInsertInALanguageTheGroupMayTranslateIsAllowed(): void
    {
        [$status, $id] = $this->insertTranslation('{"en":"translatable"}');

        $this->assertSame(200, $status, 'en is translatable');
        $this->assertTrue($this->i18nRowExists($id, 'en'), 'Checked against the database');
    }

    public function testUpdateInALanguageTheGroupMayOnlyReadIsDenied(): void
    {
        [$status, $id, $body] = $this->updateTranslation('{"en":"readable"}');

        $this->assertSame(403, $status, 'en is readable only');
        $this->assertErrorEnvelope($body, 'contentfly_i18n_permission_denied');
        $this->assertSame("Record $id", $this->i18nTitle($id, 'en'), 'The en variant is unchanged');
    }

    public function testUpdateInALanguageTheGroupMayTranslateIsAllowed(): void
    {
        [$status, $id] = $this->updateTranslation('{"en":"translatable"}');

        $this->assertSame(200, $status, 'en is translatable');
        $this->assertSame("Changed $id", $this->i18nTitle($id, 'en'), 'The en variant is changed');
    }

    public function testUpdateLeavesTheUniversalFieldsOfAReadOnlyLanguageAlone(): void
    {
        // An update in de carries an i18n_universal field (`code`) over to the other languages —
        // but only to those the group may write. doUpdate() drops the others from the list
        // before the update is passed on.
        $readOnly = $this->updateUniversalField('{"en":"readable"}');
        $open     = $this->updateUniversalField(null);

        $this->assertSame('before', $readOnly, 'en is readable only: its code stays');
        $this->assertSame('after', $open, 'No language restriction: the code reaches en too');
    }

    public function testDeleteInALanguageTheGroupMayNotWriteIsDenied(): void
    {
        // translatable is not writable: a translator adds and changes variants, but does not
        // delete the record.
        [$status, $id, $body] = $this->deleteRecord('{"de":"translatable"}');

        $this->assertSame(403, $status, 'de is translatable, not writable');
        $this->assertErrorEnvelope($body, 'contentfly_i18n_permission_denied');
        $this->assertTrue($this->i18nRowExists($id, 'de'), 'Checked against the database');
    }

    public function testDeleteInALanguageTheGroupMayWriteIsAllowed(): void
    {
        [$status, $id] = $this->deleteRecord('{"en":"readable"}');

        $this->assertSame(200, $status, 'de is not listed and therefore writable');
        $this->assertFalse($this->i18nRowExists($id, 'de'), 'Checked against the database');
    }

    // ── The own password (doUpdate) ──────────────────────────────────────────────────

    public function testChangingTheOwnPasswordWithoutTheCurrentOneIsDenied(): void
    {
        [$token, $userId] = $this->createUserThatMayWriteItself();

        [$status, $body] = $this->postJson('/api/update', array(
            'entity' => 'PIM\\User',
            'id'     => $userId,
            'data'   => array('pass' => 'a-new-password-for-this-run'),
        ), $token);

        $this->assertNotSame(200, $status, 'Without the current password the change is refused');
        $this->assertErrorEnvelope($body, 'contentfly_general_invalid_password');
        $this->assertTrue($this->canLogIn($userId, self::TEST_PASSWORD), 'The old password still works');
    }

    public function testChangingTheOwnPasswordWithAWrongCurrentOneIsDenied(): void
    {
        [$token, $userId] = $this->createUserThatMayWriteItself();

        [$status, $body] = $this->postJson('/api/update', array(
            'entity' => 'PIM\\User',
            'id'     => $userId,
            'pass'   => 'not-the-current-password',
            'data'   => array('pass' => 'a-new-password-for-this-run'),
        ), $token);

        $this->assertNotSame(200, $status, 'A wrong current password is refused');
        $this->assertErrorEnvelope($body, 'contentfly_general_invalid_password');
        $this->assertTrue($this->canLogIn($userId, self::TEST_PASSWORD), 'The old password still works');
    }

    public function testChangingTheOwnPasswordWithTheCurrentOneIsAllowed(): void
    {
        [$token, $userId] = $this->createUserThatMayWriteItself();

        [$status] = $this->postJson('/api/update', array(
            'entity' => 'PIM\\User',
            'id'     => $userId,
            'pass'   => self::TEST_PASSWORD,
            'data'   => array('pass' => 'a-new-password-for-this-run'),
        ), $token);

        $this->assertSame(200, $status);
        $this->assertTrue($this->canLogIn($userId, 'a-new-password-for-this-run'), 'The new password works');
        $this->assertFalse($this->canLogIn($userId, self::TEST_PASSWORD), 'The old one no longer does');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────────

    /**
     * Creates a tag; $userCreated, $groups and $users decide whose record it is. The id is part
     * of the title because `pim_tag.title` is unique.
     */
    private function tag(?string $userCreated, ?string $groups = null, ?string $users = null): string
    {
        $id = 'pb-'.bin2hex(random_bytes(6));

        $this->pdo()->prepare(
            // `groups` is a reserved word in MySQL 8.
            'INSERT INTO pim_tag (id, title, created, modified, views, isIntern, usercreated_id, `groups`, users)
             VALUES (:id, :title, NOW(), NOW(), 0, 0, :uc, :grp, :usr)'
        )->execute(array('id' => $id, 'title' => "Tag $id", 'uc' => $userCreated, 'grp' => $groups, 'usr' => $users));

        $this->deleteAfterTest('pim_tag', $id);

        return $id;
    }

    /** @return array<int, string> the ids of PIM\Tag that /api/all delivers */
    private function allIds(string $token): array
    {
        [$status, $body] = $this->postJson('/api/all', array(), $token);

        $this->assertSame(200, $status, json_encode($body['errors'] ?? null));

        return array_column($this->assertEnvelope($body, array('lastModified'))['PIM\\Tag'] ?? array(), 'id');
    }

    /** Creates a record of the translatable example entity in the main language `de`. */
    private function i18nRecord(?string $userCreated, ?string $code = null): string
    {
        $id = 'pb-i18n-'.bin2hex(random_bytes(6));

        $this->pdo()->prepare(
            'INSERT INTO example_i18n (id, lang, created, modified, views, isIntern, usercreated_id, title, code)
             VALUES (:id, \'de\', NOW(), NOW(), 0, 0, :uc, :title, :code)'
        )->execute(array('id' => $id, 'uc' => $userCreated, 'title' => "Record $id", 'code' => $code));

        // Removes every language variant — they share the id.
        $this->deleteAfterTest('example_i18n', $id);

        return $id;
    }

    /** Adds the `en` variant of a record. */
    private function i18nVariant(string $id, ?string $code = null): void
    {
        $this->pdo()->prepare(
            'INSERT INTO example_i18n (id, lang, created, modified, views, isIntern, usercreated_id, title, code)
             VALUES (:id, \'en\', NOW(), NOW(), 0, 0, :uc, :title, :code)'
        )->execute(array('id' => $id, 'uc' => $this->adminId, 'title' => "Record $id", 'code' => $code));
    }

    /** @return array{0:int,1:string,2:array} status, the id of the record the en variant was meant for, body */
    private function insertTranslation(string $languages): array
    {
        [$token] = $this->createTestUser(
            array(self::I18N_ENTITY => array('readable' => Permission::ALL, 'writable' => Permission::ALL)),
            array('languages' => $languages)
        );

        $id = $this->i18nRecord($this->adminId);

        [$status, $body] = $this->postJson('/api/insert', array(
            'entity' => self::I18N_ENTITY,
            'lang'   => 'en',
            'data'   => array('id' => $id, 'title' => "Translation $id"),
        ), $token);

        $this->forgetLog($id);

        return array($status, $id, $body);
    }

    /** @return array{0:int,1:string,2:array} status, the id of the record whose en variant was changed, body */
    private function updateTranslation(string $languages): array
    {
        [$token] = $this->createTestUser(
            array(self::I18N_ENTITY => array('readable' => Permission::ALL, 'writable' => Permission::ALL)),
            array('languages' => $languages)
        );

        $id = $this->i18nRecord($this->adminId);
        $this->i18nVariant($id);

        [$status, $body] = $this->postJson('/api/update', array(
            'entity' => self::I18N_ENTITY,
            'id'     => $id,
            'lang'   => 'en',
            'data'   => array('title' => "Changed $id"),
        ), $token);

        $this->forgetLog($id);

        return array($status, $id, $body);
    }

    /** Changes `code` in de and returns what the en variant carries afterwards. */
    private function updateUniversalField(?string $languages): ?string
    {
        [$token] = $this->createTestUser(
            array(self::I18N_ENTITY => array('readable' => Permission::ALL, 'writable' => Permission::ALL)),
            $languages !== null ? array('languages' => $languages) : array()
        );

        $id = $this->i18nRecord($this->adminId, 'before');
        $this->i18nVariant($id, 'before');

        [$status, $body] = $this->postJson('/api/update', array(
            'entity' => self::I18N_ENTITY,
            'id'     => $id,
            'lang'   => 'de',
            'data'   => array('code' => 'after'),
        ), $token);

        $this->assertSame(200, $status, json_encode($body['errors'] ?? null));
        $this->forgetLog($id);

        $statement = $this->pdo()->prepare('SELECT code FROM example_i18n WHERE id = :id AND lang = \'en\'');
        $statement->execute(array('id' => $id));

        return $statement->fetchColumn() ?: null;
    }

    /** @return array{0:int,1:string,2:array} status, the id of the record meant to be deleted in de, body */
    private function deleteRecord(string $languages): array
    {
        [$token] = $this->createTestUser(
            array(self::I18N_ENTITY => array('readable' => Permission::ALL, 'deletable' => Permission::ALL)),
            array('languages' => $languages)
        );

        $id = $this->i18nRecord($this->adminId);

        [$status, $body] = $this->postJson('/api/delete', array(
            'entity' => self::I18N_ENTITY,
            'id'     => $id,
            'lang'   => 'de',
        ), $token);

        $this->forgetLog($id);

        return array($status, $id, $body);
    }

    private function i18nRowExists(string $id, string $lang): bool
    {
        $statement = $this->pdo()->prepare('SELECT COUNT(*) FROM example_i18n WHERE id = :id AND lang = :lang');
        $statement->execute(array('id' => $id, 'lang' => $lang));

        return (int) $statement->fetchColumn() === 1;
    }

    private function i18nTitle(string $id, string $lang): ?string
    {
        $statement = $this->pdo()->prepare('SELECT title FROM example_i18n WHERE id = :id AND lang = :lang');
        $statement->execute(array('id' => $id, 'lang' => $lang));

        return $statement->fetchColumn() ?: null;
    }

    /** Removes the log rows a write left behind — the log is not part of what is checked here. */
    private function forgetLog(string $id): void
    {
        $this->pdo()->prepare('DELETE FROM pim_log WHERE model_id = :id')->execute(array('id' => $id));
    }

    /**
     * A non-admin who may write PIM\User at level OWN — which always covers the user's own record
     * (WritePermissionApiTest::testWithLevelOwnAUserMayAlwaysChangeThemselves()).
     *
     * @return array{0:string,1:string} token, user id
     */
    private function createUserThatMayWriteItself(): array
    {
        [$token, $userId] = $this->createTestUser(array('PIM\\User' => array(
            'readable' => Permission::ALL,
            'writable' => Permission::OWN,
        )));

        return array($token, $userId);
    }

    private function canLogIn(string $userId, string $password): bool
    {
        $statement = $this->pdo()->prepare('SELECT alias FROM pim_user WHERE id = :id');
        $statement->execute(array('id' => $userId));

        [$status] = $this->postJson('/auth/login', array('alias' => $statement->fetchColumn(), 'pass' => $password));

        return $status === 200;
    }

    /** @return array{0:string,1:string} token, user id */
    private function createUserWithoutGroup(): array
    {
        $id   = 'pb-nogrp-'.bin2hex(random_bytes(6));
        $salt = bin2hex(random_bytes(16));

        $this->pdo()->prepare(
            'INSERT INTO pim_user (id, isAdmin, alias, pass, isActive, salt, created, modified, views, isIntern)
             VALUES (:id, 0, :alias, :pass, 1, :salt, NOW(), NOW(), 0, 0)'
        )->execute(array(
            'id' => $id, 'alias' => $id,
            'pass' => hash('sha256', self::TEST_PASSWORD.$salt), 'salt' => $salt,
        ));
        $this->deleteAfterTest('pim_user', $id);

        [, $login] = $this->postJson('/auth/login', array('alias' => $id, 'pass' => self::TEST_PASSWORD));

        return array($this->assertEnvelope($login)['token'], $id);
    }
}
