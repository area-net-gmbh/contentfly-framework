<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * Field names from the request must name a property of the entity (000-000-0063).
 *
 * Values have always been bound. The NAMES were not: four places put a field name — or a sort
 * direction — from the request straight into DQL. That is DQL injection: Doctrine parses the
 * expression, so there are no stacked statements, but a subquery in a condition can read other
 * entities, password hashes in `PIM\User` included, one true/false answer at a time.
 *
 * What each place does with an unknown name is decided per place:
 *
 * | Place | Unknown name |
 * |---|---|
 * | `where` of `/api/single` | **rejected** — a filter that is silently dropped returns a DIFFERENT record |
 * | `order` of `/api/list` | **rejected**, and the direction is `ASC` or `DESC` only |
 * | `groupBy` of `/api/list` | **rejected** |
 * | `properties` of `/api/tree` | **dropped** — exactly what `/api/list` has always done with its `properties` |
 *
 * The first test of each pair uses a name that carries DQL; the second shows that a legitimate
 * call is unaffected.
 */
class FieldNameApiTest extends IntegrationTestCase
{
    private const HOSTILE = 'id IS NOT NULL OR 1';

    private string $tag = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tag = 'fn-'.bin2hex(random_bytes(6));

        $this->pdo()->prepare(
            'INSERT INTO pim_tag (id, title, created, modified, views, isIntern) VALUES (:id, :title, NOW(), NOW(), 0, 0)'
        )->execute(array('id' => $this->tag, 'title' => "Field name $this->tag"));

        $this->deleteAfterTest('pim_tag', $this->tag);
    }

    // ── where of /api/single ───────────────────────────────────────────────────────────

    public function testSingleRejectsAWhereFieldThatIsNoProperty(): void
    {
        [$status, $body] = $this->postJson('/api/single', array(
            'entity' => 'PIM\\Tag',
            'where'  => array(self::HOSTILE => 'x'),
        ), $this->token());

        $this->assertSame(400, $status, 'A name that is not a property is the client\'s mistake');
        $this->assertErrorEnvelope($body, 'contentfly_general_unknown_property');
    }

    public function testSingleStillFindsByAPropertyInWhere(): void
    {
        [$status, $body] = $this->postJson('/api/single', array(
            'entity' => 'PIM\\Tag',
            'where'  => array('title' => "Field name $this->tag"),
        ), $this->token());

        $this->assertSame(200, $status);
        $this->assertSame($this->tag, $this->assertEnvelope($body)['id']);
    }

    // ── order of /api/list ─────────────────────────────────────────────────────────────

    public function testListRejectsAnOrderFieldThatIsNoProperty(): void
    {
        [$status, $body] = $this->postJson('/api/list', array(
            'entity' => 'PIM\\Tag',
            'order'  => array(self::HOSTILE => 'ASC'),
        ), $this->token());

        $this->assertSame(400, $status);
        $this->assertErrorEnvelope($body, 'contentfly_general_unknown_property');
    }

    public function testListRejectsASortDirectionOtherThanAscOrDesc(): void
    {
        [$status, $body] = $this->postJson('/api/list', array(
            'entity' => 'PIM\\Tag',
            'order'  => array('title' => 'ASC, '.self::HOSTILE),
        ), $this->token());

        $this->assertSame(400, $status);
        $this->assertErrorEnvelope($body);
    }

    public function testListStillSortsByAPropertyInEitherCase(): void
    {
        // `id` and `created` too: a client that sorts by a base field must keep working.
        foreach (array('title' => 'ASC', 'id' => 'desc', 'created' => 'Asc') as $field => $direction) {
            [$status] = $this->postJson('/api/list', array(
                'entity' => 'PIM\\Tag',
                'order'  => array($field => $direction),
            ), $this->token());

            $this->assertSame(200, $status, "order $field $direction");
        }
    }

    // ── groupBy of /api/list ───────────────────────────────────────────────────────────

    public function testListRejectsAGroupByFieldThatIsNoProperty(): void
    {
        [$status, $body] = $this->postJson('/api/list', array(
            'entity'  => 'PIM\\Tag',
            'groupBy' => self::HOSTILE,
        ), $this->token());

        $this->assertSame(400, $status);
        $this->assertErrorEnvelope($body, 'contentfly_general_unknown_property');
    }

    // ── properties of /api/tree ────────────────────────────────────────────────────────

    public function testTreeDropsPropertiesThatAreNoProperty(): void
    {
        $folder = 'fn-f-'.bin2hex(random_bytes(6));

        $this->pdo()->prepare(
            'INSERT INTO pim_tree (id, sorting, isActive, created, modified, views, isIntern, dtype)
             VALUES (:id, 1, 1, NOW(), NOW(), 0, 0, \'folder\')'
        )->execute(array('id' => $folder));
        $this->pdo()->prepare('INSERT INTO pim_folder (id, title) VALUES (:id, :title)')
             ->execute(array('id' => $folder, 'title' => 'Field name folder'));
        $this->deleteAfterTest('pim_tree', $folder);
        $this->deleteAfterTest('pim_folder', $folder);

        [$status, $body] = $this->postJson('/api/tree', array(
            'entity'     => 'PIM\\Folder',
            'properties' => array('title', self::HOSTILE),
        ), $this->token());

        $this->assertSame(200, $status, 'Dropped like /api/list drops them, not an error');

        $node = current(array_filter($this->assertEnvelope($body), fn (array $n): bool => $n['id'] === $folder));
        $this->assertNotFalse($node, 'The folder is in the tree');
        $this->assertSame('Field name folder', $node['title'], 'The known property is still delivered');
    }
}
