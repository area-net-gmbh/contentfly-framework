<?php
namespace Tests\Integration\Api;

use Areanet\PIM\Entity\Permission;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Integration\IntegrationTestCase;

/**
 * The permission matrix: level × operation × ownership of the record (000-000-0057).
 *
 * Contentfly has **no roles**. What exists is an `isAdmin` flag, one group per user, and per
 * group one permission row per entity with three operations, each on one of four levels. The
 * axis to check is therefore level × operation × ownership — 4 × 3 × 3 = 36 cases, identical for
 * every entity. One test entity (`PIM\Tag`) is enough; admin bypass and "user without group"
 * are cases of their own.
 *
 * Everything goes through HTTP. What is checked is what a client gets — status code and the
 * state of the database — not what `Permission::is()` returns.
 *
 * THE CONSTANTS ARE NOT ASCENDING: `NONE` 0, `OWN` 1, `ALL` 2, `GROUP` 3. The code compares with
 * `==` everywhere, so today this does no harm. A single `>=` would: `GROUP` would then be the
 * widest level instead of a narrower one. `canExport()` fell into exactly this trap (see
 * Classes/Permission.php). The matrix covers `GROUP` for every operation, and
 * testGroupIsNarrowerThanAllDespiteTheHigherNumber() names the trap on its own.
 *
 * THE SIX PLACES THAT CHECK THE LEVEL ONLY AGAINST 0 — each with a result:
 *
 * | Place | Result |
 * |---|---|
 * | `Api::doInsert()` | **Correct** for a new record: it has no owner yet, there is nothing to narrow. Its i18n branch (an insert that carries an existing `id` creates a language variant of *that* record) **narrows since 000-000-0059** by the ownership of the existing record. |
 * | `Api::getTranslations()` | **Narrowed since 000-000-0059** like `getCount()`; it used to count untranslated records across all owners. |
 * | `MultijoinType` (write check, 2 places) | **Not reachable**: only the `mappedBy` branch checks, and it needs `acceptFrom`, which no property carries. WritePermissionApiTest::testTheWriteCheckInMultijoinTypeCannotBeTriggered() fails as soon as that changes — the check has to be narrowed then. |
 * | `FileController::uploadAction()` | **Correct**: an upload creates a new file, nothing to narrow. |
 * | `FileController::overwriteAction()` | **Correct since 000-000-0060.** It replaced the content of `destId` with that of `sourceId` and checked the ownership of neither; both are now narrowed like `Api::doUpdate()`. Covered in FileApiTest. |

 *
 * OUTSIDE THE SIX — routes that checked no level at all. `Api::getTree()` and `Api::getTree2()`
 * never called `Permission::isReadable()`: a user without any read right got the full tree, where
 * `/api/list` answers 403; `getDeleted()` reported deletions of every entity. **Fixed with
 * 000-000-0061**, covered below. `getTree2()` also put the request's `lang` into its SQL as a
 * string — **fixed with 000-000-0062** (Tree2LangBindingTest).
 */
class PermissionMatrixApiTest extends IntegrationTestCase
{
    private const LEVELS = array(
        'NONE'  => Permission::NONE,
        'OWN'   => Permission::OWN,
        'ALL'   => Permission::ALL,
        'GROUP' => Permission::GROUP,
    );

    /** Which records each level reaches: own, shared with the own group, foreign. */
    private const REACHES = array(
        'NONE'  => array(),
        'OWN'   => array('own'),
        'ALL'   => array('own', 'group', 'foreign'),
        'GROUP' => array('own', 'group'),
    );

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

    /** @return iterable<string, array{0:string,1:string,2:string,3:bool}> */
    public static function matrix(): iterable
    {
        foreach (array('readable', 'writable', 'deletable') as $operation) {
            foreach (self::REACHES as $level => $reached) {
                foreach (array('own', 'group', 'foreign') as $record) {
                    $allowed = in_array($record, $reached, true);

                    yield "$operation $level on $record record" => array($operation, $level, $record, $allowed);
                }
            }
        }
    }

    #[DataProvider('matrix')]
    public function testLevelOperationAndOwnership(string $operation, string $level, string $record, bool $allowed): void
    {
        // Only the operation under test varies. Update and delete load the record through
        // getSingle() first, which checks the read right — so reading stays at ALL for them,
        // otherwise a write case would measure the read check.
        $permissions = array(
            'readable'  => $operation === 'readable'  ? self::LEVELS[$level] : Permission::ALL,
            'writable'  => $operation === 'writable'  ? self::LEVELS[$level] : Permission::NONE,
            'deletable' => $operation === 'deletable' ? self::LEVELS[$level] : Permission::NONE,
        );

        [$token, $userId, $groupId] = $this->createTestUser(array('PIM\\Tag' => $permissions));

        $tag = match ($record) {
            'own'     => $this->tag($userId),
            'group'   => $this->tag($this->adminId, $groupId),
            'foreign' => $this->tag($this->adminId),
        };

        $case = "$operation = $level on the $record record";

        $this->assertOperation($operation, $tag, $token, $allowed, $case);
    }

    public function testGroupIsNarrowerThanAllDespiteTheHigherNumber(): void
    {
        // GROUP is 3, ALL is 2. Whoever reads the numbers as an order takes GROUP for the
        // widest level. It is narrower: a foreign record without a group relation stays out
        // of reach for all three operations.
        $this->assertGreaterThan(Permission::ALL, Permission::GROUP,
            'The premise of this test: the numbers are not in the order of the levels');

        foreach (array('readable', 'writable', 'deletable') as $operation) {
            [$token] = $this->createTestUser(array('PIM\\Tag' => array(
                'readable'  => $operation === 'readable'  ? Permission::GROUP : Permission::ALL,
                'writable'  => $operation === 'writable'  ? Permission::GROUP : Permission::NONE,
                'deletable' => $operation === 'deletable' ? Permission::GROUP : Permission::NONE,
            )));

            $foreign = $this->tag($this->adminId);

            $this->assertOperation($operation, $foreign, $token, false,
                "$operation = GROUP on a foreign record — GROUP must not act like a level above ALL");
        }
    }

    public function testAdminBypassesEveryLevelWithoutAPermissionRow(): void
    {
        // Permission::is() returns ALL for an admin before any group or permission row is
        // looked at. The admin has neither for PIM\Tag.
        foreach (array('readable', 'writable', 'deletable') as $operation) {
            $foreign = $this->tag($this->anotherUser());

            $this->assertOperation($operation, $foreign, $this->token(), true,
                "$operation as admin on another user's record");
        }
    }

    public function testUserWithoutGroupIsDeniedEveryOperation(): void
    {
        // Permission::is() returns NONE as soon as the user has no group — even on a record
        // the user created.
        [$token, $userId] = $this->createUserWithoutGroup();

        foreach (array('readable', 'writable', 'deletable') as $operation) {
            $own = $this->tag($userId);

            $this->assertOperation($operation, $own, $token, false,
                "$operation without a group, on the user's own record");
        }
    }

    // ── Whole trees and the deletion log (000-000-0061) ────────────────────────────────

    /** @return iterable<string, array{0:string,1:string}> */
    public static function treeRoutes(): iterable
    {
        foreach (array('tree', 'tree2') as $route) {
            foreach (array_keys(self::REACHES) as $level) {
                yield "/api/$route with $level" => array($route, $level);
            }
        }
    }

    #[DataProvider('treeRoutes')]
    public function testTreeRoutesApplyTheReadLevel(string $route, string $level): void
    {
        // Both routes used to check only THAT someone is logged in. A user without any read
        // right got the full tree, where /api/list answers 403.
        [$token, $userId, $groupId] = $this->createTestUser(array('PIM\\Folder' => array(
            'readable' => self::LEVELS[$level],
        )));

        $records = array(
            'own'     => $this->folder($userId),
            'group'   => $this->folder($this->adminId, $groupId),
            'foreign' => $this->folder($this->adminId),
        );

        [$status, $body] = $this->postJson("/api/$route", array('entity' => 'PIM\\Folder'), $token);

        if ($level === 'NONE') {
            $this->assertSame(403, $status, "/api/$route without read right: status code");
            $this->assertErrorEnvelope($body);

            return;
        }

        $this->assertSame(200, $status, "/api/$route with $level: status code");
        $visible = $this->treeIds($this->assertEnvelope($body));

        foreach ($records as $record => $id) {
            $reached = in_array($record, self::REACHES[$level], true);

            $this->assertSame($reached, in_array($id, $visible, true),
                "/api/$route with readable = $level: the $record folder is ".($reached ? 'visible' : 'hidden'));
        }
    }

    public function testATreeNodeBelowAHiddenParentIsHiddenToo(): void
    {
        // Decided with 000-000-0061: both routes build the tree from the visible nodes only. A
        // node whose parent is out of reach has no place to hang in that tree — it is left out,
        // not moved to the top level. /api/tree reaches children through their parent anyway.
        [$token, $userId] = $this->createTestUser(array('PIM\\Folder' => array(
            'readable' => Permission::OWN,
        )));

        $foreignRoot = $this->folder($this->adminId);
        $ownChild    = $this->folder($userId, null, $foreignRoot);

        foreach (array('tree', 'tree2') as $route) {
            [, $body] = $this->postJson("/api/$route", array('entity' => 'PIM\\Folder'), $token);
            $visible  = $this->treeIds($this->assertEnvelope($body));

            $this->assertNotContains($foreignRoot, $visible, "/api/$route: the foreign parent is hidden");
            $this->assertNotContains($ownChild, $visible, "/api/$route: so is the own child below it");
        }
    }

    public function testTheDeletionLogReportsOnlyReadableEntities(): void
    {
        // /api/deleted used to report the deletions of every entity. Only ids — but ids of
        // records the user may not know exist.
        $tag = 'pm-deleted-'.bin2hex(random_bytes(6));
        $log = 'pm-log-'.bin2hex(random_bytes(6));

        $this->pdo()->prepare(
            'INSERT INTO pim_log (id, model_id, model_name, mode, created, modified, views, isIntern)
             VALUES (:id, :modelId, :modelName, \'DEL\', NOW(), NOW(), 0, 0)'
        )->execute(array('id' => $log, 'modelId' => $tag, 'modelName' => 'PIM\\Tag'));
        $this->deleteAfterTest('pim_log', $log);

        [$reader]   = $this->createTestUser(array('PIM\\Tag' => array('readable' => Permission::ALL)));
        [$outsider] = $this->createTestUser(array('PIM\\Folder' => array('readable' => Permission::ALL)));

        $this->assertContains($tag, $this->deletedIds($reader), 'With read right the deletion is reported');
        $this->assertNotContains($tag, $this->deletedIds($outsider),
            'Without read right on PIM\\Tag the deletion is not reported');
    }

    // ── Translatable entities (000-000-0059) ─────────────────────────────────────────
    //
    // Measured on the template's Core\ExampleI18n — until 000-000-0059 no entity of the
    // framework or the template was translatable, so these paths were unreachable here.

    #[DataProvider('i18nInsertCases')]
    public function testInsertingATranslationOfARecordOutOfReachIsRejected(string $level, string $record, bool $allowed): void
    {
        // An insert that carries the id of an existing record creates a language variant of
        // THAT record. It used to check only writable != NONE on the entity: with OWN a user
        // added translations to anyone's records.
        //
        // ONLY THE REJECTED CASES, FOR NOW. A permitted translation insert does not get through
        // today at all: `id` is missing from the schema of every BaseI18n entity, and the insert
        // stops with unknown_property (000-000-0064). The ownership check runs before that, so
        // the rejections are measurable; the permitted cases join this provider with 0064.
        [$token, $userId, $groupId] = $this->createTestUser(array(self::I18N_ENTITY => array(
            'readable' => Permission::ALL,
            'writable' => self::LEVELS[$level],
        )));

        $id = match ($record) {
            'own'     => $this->i18nRecord($userId),
            'group'   => $this->i18nRecord($this->adminId, $groupId),
            'foreign' => $this->i18nRecord($this->adminId),
        };

        [$status] = $this->postJson('/api/insert', array(
            'entity' => self::I18N_ENTITY,
            'lang'   => 'en',
            'data'   => array('id' => $id, 'title' => "Translation $id"),
        ), $token);

        $case = "insert of an en variant with writable = $level on the $record record";

        $this->assertSame($allowed ? 200 : 403, $status, "$case: status code");
        $this->assertSame($allowed, $this->i18nRowExists($id, 'en'), "$case: checked against the database");

        $this->pdo()->prepare('DELETE FROM pim_log WHERE model_id = :id')->execute(array('id' => $id));
    }

    /** @return iterable<string, array{0:string,1:string,2:bool}> */
    public static function i18nInsertCases(): iterable
    {
        foreach (array('OWN', 'GROUP') as $level) {
            foreach (array('own', 'group', 'foreign') as $record) {
                if (!in_array($record, self::REACHES[$level], true)) {
                    yield "writable $level on $record record" => array($level, $record, false);
                }
            }
        }
    }

    #[DataProvider('translationCountCases')]
    public function testTranslationCountsAreNarrowedLikeGetCount(string $level, int $expected): void
    {
        // /api/translations counts the records not yet translated into a language. It counted
        // across all owners — only numbers, no content, but numbers about records the user may
        // not know exist. getCount() has always narrowed the same kind of number.
        [$token, $userId, $groupId] = $this->createTestUser(array(self::I18N_ENTITY => array(
            'readable' => self::LEVELS[$level],
        )));

        $this->i18nRecord($userId);
        $this->i18nRecord($this->adminId, $groupId);
        $this->i18nRecord($this->adminId);

        [$status, $body] = $this->postJson('/api/translations', array(
            'entity' => self::I18N_ENTITY,
            'lang'   => 'en',
        ), $token);

        $this->assertSame(200, $status);
        $counts = array_column($this->assertEnvelope($body), 'records', 'lang');

        if ($level === 'ALL') {
            // Other rows may exist in the table; ALL sees at least the three made here.
            $this->assertGreaterThanOrEqual($expected, (int) ($counts['de'] ?? 0));
        } else {
            $this->assertSame($expected, (int) ($counts['de'] ?? 0),
                "readable = $level counts only the records the level reaches");
        }
    }

    /** @return iterable<string, array{0:string,1:int}> */
    public static function translationCountCases(): iterable
    {
        yield 'OWN counts the own record'                  => array('OWN', 1);
        yield 'GROUP counts own and group-shared records'  => array('GROUP', 2);
        yield 'ALL counts every record'                    => array('ALL', 3);
    }

    /**
     * Runs one operation and checks the status code **and** the database. A status code alone
     * does not prove that nothing happened.
     */
    private function assertOperation(string $operation, string $tag, string $token, bool $allowed, string $case): void
    {
        $expectedStatus = $allowed ? 200 : 403;

        switch ($operation) {
            case 'readable':
                [$status, $body] = $this->postJson('/api/single', array('entity' => 'PIM\\Tag', 'id' => $tag), $token);

                $this->assertSame($expectedStatus, $status, "$case: status code");
                if ($allowed) {
                    $this->assertSame($tag, $this->assertEnvelope($body)['id'], "$case: the record is returned");
                } else {
                    $this->assertErrorEnvelope($body);
                }
                break;

            case 'writable':
                [$status] = $this->postJson(
                    '/api/update',
                    array('entity' => 'PIM\\Tag', 'id' => $tag, 'data' => array('title' => "Changed $tag")),
                    $token
                );

                $this->assertSame($expectedStatus, $status, "$case: status code");
                $this->assertSame($allowed ? "Changed $tag" : "Original $tag", $this->title($tag),
                    "$case: checked against the database");
                break;

            case 'deletable':
                [$status] = $this->postJson('/api/delete', array('entity' => 'PIM\\Tag', 'id' => $tag), $token);

                $this->assertSame($expectedStatus, $status, "$case: status code");
                $this->assertSame(!$allowed, $this->exists($tag), "$case: checked against the database");
                break;
        }

        $this->pdo()->prepare('DELETE FROM pim_log WHERE model_id = :id')->execute(array('id' => $tag));
    }

    /**
     * Creates a tag titled "Original <id>"; $userCreated and $groups decide whose record it is.
     * The id is part of the title because `pim_tag.title` is unique.
     */
    private function tag(?string $userCreated, ?string $groups = null): string
    {
        $id    = 'pm-'.bin2hex(random_bytes(6));
        $title = "Original $id";

        $this->pdo()->prepare(
            // `groups` is a reserved word in MySQL 8.
            'INSERT INTO pim_tag (id, title, created, modified, views, isIntern, usercreated_id, `groups`, users)
             VALUES (:id, :title, NOW(), NOW(), 0, 0, :uc, :grp, NULL)'
        )->execute(array('id' => $id, 'title' => $title, 'uc' => $userCreated, 'grp' => $groups));

        $this->deleteAfterTest('pim_tag', $id);

        return $id;
    }

    /** Creates a folder — a node of the tree entity PIM\Folder — owned like tag() does it. */
    private function folder(?string $userCreated, ?string $groups = null, ?string $parent = null): string
    {
        $id = 'pm-f-'.bin2hex(random_bytes(6));

        $this->pdo()->prepare(
            'INSERT INTO pim_tree (id, sorting, isActive, created, modified, views, isIntern, dtype, parent_id,
                                   usercreated_id, `groups`)
             VALUES (:id, 1, 1, NOW(), NOW(), 0, 0, \'folder\', :parent, :uc, :grp)'
        )->execute(array('id' => $id, 'parent' => $parent, 'uc' => $userCreated, 'grp' => $groups));
        $this->pdo()->prepare('INSERT INTO pim_folder (id, title) VALUES (:id, :title)')
             ->execute(array('id' => $id, 'title' => "Folder $id"));

        // pim_folder goes first on cleanup — pim_tree carries the key it points to.
        $this->deleteAfterTest('pim_tree', $id);
        $this->deleteAfterTest('pim_folder', $id);

        return $id;
    }

    /**
     * Every id in a tree response, at any depth. /api/tree nests under `treeChilds`, /api/tree2
     * under `childs` — the two routes return incompatible shapes (see TreeApiTest).
     *
     * @param array<int, array<string, mixed>> $tree
     * @return array<int, string>
     */
    private function treeIds(array $tree): array
    {
        $ids = array();

        foreach ($tree as $node) {
            $ids[] = $node['id'];
            $ids   = array_merge($ids, $this->treeIds($node['treeChilds'] ?? $node['childs'] ?? array()));
        }

        return $ids;
    }

    /** @return array<int, string> */
    private function deletedIds(string $token): array
    {
        [$status, $body] = $this->postJson('/api/deleted', array(), $token);

        $this->assertSame(200, $status);

        return array_column($this->assertEnvelope($body), 'model_id');
    }

    /** Creates a record of the translatable example entity in the main language `de`. */
    private function i18nRecord(?string $userCreated, ?string $groups = null): string
    {
        $id = 'pm-i18n-'.bin2hex(random_bytes(6));

        $this->pdo()->prepare(
            'INSERT INTO example_i18n (id, lang, created, modified, views, isIntern, usercreated_id, `groups`, title)
             VALUES (:id, \'de\', NOW(), NOW(), 0, 0, :uc, :grp, :title)'
        )->execute(array('id' => $id, 'uc' => $userCreated, 'grp' => $groups, 'title' => "Record $id"));

        // Removes every language variant — they share the id.
        $this->deleteAfterTest('example_i18n', $id);

        return $id;
    }

    private function i18nRowExists(string $id, string $lang): bool
    {
        $statement = $this->pdo()->prepare('SELECT COUNT(*) FROM example_i18n WHERE id = :id AND lang = :lang');
        $statement->execute(array('id' => $id, 'lang' => $lang));

        return (int) $statement->fetchColumn() === 1;
    }

    private function title(string $tag): ?string
    {
        $statement = $this->pdo()->prepare('SELECT title FROM pim_tag WHERE id = :id');
        $statement->execute(array('id' => $tag));

        $title = $statement->fetchColumn();

        return $title === false ? null : $title;
    }

    private function exists(string $tag): bool
    {
        $statement = $this->pdo()->prepare('SELECT COUNT(*) FROM pim_tag WHERE id = :id');
        $statement->execute(array('id' => $tag));

        return (int) $statement->fetchColumn() === 1;
    }

    /** A second, non-admin user, so the admin case is measured on a record that is not the admin's. */
    private function anotherUser(): string
    {
        [, $userId] = $this->createTestUser();

        return $userId;
    }

    /** @return array{0:string,1:string} token, user id */
    private function createUserWithoutGroup(): array
    {
        $id   = 'pm-nogrp-'.bin2hex(random_bytes(6));
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
