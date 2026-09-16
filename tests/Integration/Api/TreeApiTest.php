<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * Characterisation tests for the tree endpoints `/api/tree` and `/api/tree2`.
 *
 * The two take entirely different routes and return the same data in **incompatible
 * shapes**: `tree` builds via the entities and serialises like the rest of the API, `tree2` reads
 * a single SQL query against `pim_tree` and passes the raw values through. That is not a
 * subtlety, but the difference between `"created": {"LOCAL": …, "ISO8601": …}` and
 * `"created": "2026-09-07 09:12:23"`.
 *
 * Two assertions here protect decisions from `012-005-0003`:
 * the field selection of `tree2` and the quoting of its column names.
 */
class TreeApiTest extends IntegrationTestCase
{
    private string $root  = '';
    private string $child = '';

    protected function setUp(): void
    {
        parent::setUp();

        $run         = bin2hex(random_bytes(6));
        $this->root  = 'tree-r-'.$run;
        $this->child = 'tree-c-'.$run;

        $this->createFolder($this->root,  'Root',  null, 1);
        $this->createFolder($this->child, 'Child', $this->root, 2);
    }

    private function createFolder(string $id, string $title, ?string $parentId, int $sorting): void
    {
        $this->pdo()->prepare(
            'INSERT INTO pim_tree (id, sorting, isActive, created, modified, views, isIntern, dtype, parent_id)
             VALUES (:id, :s, 1, NOW(), NOW(), 0, 0, :dtype, :parent)'
        )->execute(array('id' => $id, 's' => $sorting, 'dtype' => 'folder', 'parent' => $parentId));

        $this->pdo()->prepare('INSERT INTO pim_folder (id, title) VALUES (:id, :title)')
             ->execute(array('id' => $id, 'title' => $title));

        // pim_folder first — pim_tree carries the primary key it points to.
        $this->deleteAfterTest('pim_tree', $id);
        $this->deleteAfterTest('pim_folder', $id);
    }

    /** Finds a node in a tree, regardless of the name of the child key. */
    private function node(array $tree, string $id, string $childKey): ?array
    {
        foreach ($tree as $entry) {
            if (($entry['id'] ?? null) === $id) {
                return $entry;
            }
            $match = $this->node($entry[$childKey] ?? array(), $id, $childKey);
            if ($match !== null) {
                return $match;
            }
        }

        return null;
    }

    // ── /api/tree ──────────────────────────────────────────────────────────────────────

    public function testTreeAttachesChildrenAsTreeChilds(): void
    {
        [$status, $body] = $this->postJson('/api/tree', array('entity' => 'PIM\\Folder'), $this->token());

        $this->assertSame(200, $status);
        $this->assertEnvelope($body); // 011-001-0002

        $root = $this->node($body['data'], $this->root, 'treeChilds');
        $this->assertNotNull($root, 'The root is on the top level');
        $this->assertSame('Root', $root['title']);

        $childIds = array_column($root['treeChilds'], 'id');
        $this->assertContains($this->child, $childIds, 'The child hangs under treeChilds of the root');
    }

    public function testTreeSerialisesLikeTheRestOfTheApi(): void
    {
        [, $body] = $this->postJson('/api/tree', array('entity' => 'PIM\\Folder'), $this->token());
        $root     = $this->node($body['data'], $this->root, 'treeChilds');

        $this->assertSame(
            array('LOCAL_TIME', 'LOCAL', 'ISO8601', 'TIMESTAMP'),
            array_keys($root['created']),
            'tree returns date fields as a group of four — unlike tree2'
        );
        $this->assertIsBool($root['isActive'], 'tree returns real boolean values');
    }

    public function testTreePropertiesRestrictsTheFieldSet(): void
    {
        [, $body] = $this->postJson(
            '/api/tree',
            array('entity' => 'PIM\\Folder', 'properties' => array('id', 'title')),
            $this->token()
        );

        $root = $this->node($body['data'], $this->root, 'treeChilds');

        $this->assertNotNull($root);
        $this->assertSame('Root', $root['title']);
        $this->assertArrayNotHasKey('views', $root, 'Fields not requested are missing');
    }

    public function testTreeWithoutTokenReturnsNoData(): void
    {
        [$status, $body] = $this->postJson('/api/tree', array('entity' => 'PIM\\Folder'));

        $this->assertSame(401, $status, 'Since the stack switch (006-002-0003) the intended code — Symfony 4.4 fixes 000-000-0006 here');
        $this->assertArrayNotHasKey('data', $body);
    }

    // ── /api/tree2 ─────────────────────────────────────────────────────────────────────

    public function testTree2AttachesChildrenAsChildsAndCarriesParent(): void
    {
        [$status, $body] = $this->postJson('/api/tree2', array('entity' => 'PIM\\Folder'), $this->token());

        $this->assertSame(200, $status);
        $this->assertEnvelope($body); // 011-001-0002

        $root = $this->node($body['data'], $this->root, 'childs');
        $this->assertNotNull($root);
        $this->assertSame(array('id' => null), $root['parent'], 'The root has no parent');
        $this->assertSame(1, $root['sorting']);

        $child = $this->node($body['data'], $this->child, 'childs');
        $this->assertNotNull($child, 'The child hangs under childs — not treeChilds as with /api/tree');
        $this->assertSame(array('id' => $this->root), $child['parent']);
    }

    public function testTree2PassesThroughTheRawDatabaseValues(): void
    {
        // The hard difference from /api/tree: no serialisation via the type classes.
        [, $body] = $this->postJson('/api/tree2', array('entity' => 'PIM\\Folder'), $this->token());
        $root     = $this->node($body['data'], $this->root, 'childs');

        $this->assertIsString($root['created'],
            'tree2 returns the date as an SQL string, not as a group of four');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $root['created']);
        // A string, as on Contentfly 1.x under PHP 7.4 (000-000-0044). Characterised as the integer 1
        // in 008-001-0003 — that was PHP 8.1's pdo_mysql, not the framework's contract.
        $this->assertSame('1', $root['isActive'], 'Boolean values come through as the raw column string');
    }

    public function testTree2ReturnsAllScalarFields(): void
    {
        // Protects 012-005-0003: the column selection used to come from `showInList` — the
        // list position of the deleted user interface. Since then it is all scalar fields.
        [, $body] = $this->postJson('/api/tree2', array('entity' => 'PIM\\Folder'), $this->token());
        $root     = $this->node($body['data'], $this->root, 'childs');

        foreach (array('title', 'isActive', 'created', 'modified', 'views', 'isIntern', 'sorting') as $field) {
            $this->assertArrayHasKey($field, $root, "tree2 returns all scalar fields ($field)");
        }
    }

    public function testTree2QuotesColumnNamesAndHandlesTheReservedWordGroups(): void
    {
        // Regression protection from 012-005-0003: without backticks the query breaks as soon
        // as `groups` ends up in the selection — a reserved word in MySQL 8. PIM\Folder inherits
        // from BaseTree and thereby carries `users` and `groups`; a successful fetch is the
        // proof that quoting happens.
        [$status, $body] = $this->postJson('/api/tree2', array('entity' => 'PIM\\Folder'), $this->token());

        $this->assertSame(200, $status, 'Without quoting this would be an SQL syntax error');

        $root = $this->node($body['data'], $this->root, 'childs');
        $this->assertArrayHasKey('groups', $root, 'The reserved word is part of the selection');
        $this->assertArrayHasKey('users', $root);
    }

    public function testTree2WithoutTokenReturnsNoData(): void
    {
        [$status, $body] = $this->postJson('/api/tree2', array('entity' => 'PIM\\Folder'));

        $this->assertSame(401, $status, 'Since the stack switch (006-002-0003) the intended code — Symfony 4.4 fixes 000-000-0006 here');
        $this->assertArrayNotHasKey('data', $body);
    }
}
