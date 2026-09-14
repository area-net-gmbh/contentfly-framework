<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * Characterisation tests for the framework's only ManyToMany path: `PIM\File.tags`.
 *
 * **Why this file is created in the middle of epic `006` and not in `008`:** `doctrine/orm` is
 * currently on a fork of its own — `area-net-gmbh/doctrine2`, branch **`bugfix-many2many`**,
 * as of 2018-08-07. Story `006-002` switches to a release, and nobody can say whether the
 * fix of this fork was merged there (`006-001-0003`).
 *
 * The fork is named after *this* path. Without tests on it, a possible break would mix
 * with four further major jumps — Symfony 3.4→4.4, DBAL 2.6→2.13, annotations 1.8→1.14,
 * uuid 3→4 — and could no longer be attributed.
 *
 * ## Not to be confused with the documented gap
 * `an_project/docs/technical.md` lists the "write check" of `MultijoinType` as a gap. That is
 * a **permission** path in the `acceptFrom` branch, which cannot be triggered because there is
 * no `acceptFrom` anywhere in the tree — recorded in
 * `WritePermissionApiTest::testTheWriteCheckInMultijoinTypeCannotBeTriggered()`.
 *
 * This file checks something else: the **ORM behaviour**. Creating, reading,
 * changing, deleting links. That can very well be triggered, and it is what the fork is named after.
 *
 * ## The entity
 * ```php
 * // lib/contentfly/Entity/File.php:81
 * @ORM\ManyToMany(targetEntity="Areanet\PIM\Entity\Tag")
 * @ORM\JoinTable(name="pim_file_tags", joinColumns={@ORM\JoinColumn(onDelete="CASCADE")})
 * @PIM\Config(isFilterable=true)
 * ```
 *
 * **The state of the join table is checked directly via SQL, not only via the
 * API response.** An ORM switch can make the response look right and still fill the table
 * wrongly — exactly the kind of bug this fork is supposed to have fixed once.
 *
 * While writing, one point emerged that **cannot** be read from the entity:
 * the annotation sets `onDelete="CASCADE"` only on the `joinColumns` (`file_id`), but Doctrine
 * creates it on **both** foreign keys. The schema is symmetric, the annotation
 * describes it asymmetrically — anyone reading only the annotation expects orphaned rows that
 * do not exist.
 */
class ManyToManyApiTest extends IntegrationTestCase
{
    private const ENTITY = 'PIM\\File';

    /** @var array<int,string> Ids whose log and link rows tearDown() removes. */
    private array $idsToCleanUp = array();

    /**
     * Removes what `deleteAfterTest()` does not reach, and then hands over to the base.
     *
     * Two things slip through: the rows in `pim_file_tags` (they have no `id` of their own,
     * but the base deletes by `id`) and the log rows (they are stored under `model_id`,
     * and the decisive ones are only created **after** registration — every `/api/update`
     * writes one).
     *
     * Without this, `pim_log` grew by three rows with every run. Exactly the backlog that
     * `000-000-0008` was written against.
     */
    protected function tearDown(): void
    {
        foreach ($this->idsToCleanUp as $id) {
            $this->pdo()->prepare('DELETE FROM pim_file_tags WHERE file_id = :id')->execute(array('id' => $id));
            $this->pdo()->prepare('DELETE FROM pim_log WHERE model_id = :id')->execute(array('id' => $id));
        }

        $this->idsToCleanUp = array();

        parent::tearDown();
    }

    /**
     * Creates a tag **bypassing the API**.
     *
     * As in epic `008`: a read test whose precondition runs through the write path it
     * checks itself loses its significance.
     */
    private function tag(string $title): string
    {
        $id = 'm2m-t-'.bin2hex(random_bytes(5));

        $this->pdo()->prepare(
            'INSERT INTO pim_tag (id, title, created, modified, views, isIntern)
             VALUES (:id, :title, NOW(), NOW(), 0, 0)'
        )->execute(array('id' => $id, 'title' => $title));

        $this->deleteAfterTest('pim_tag', $id);
        $this->idsToCleanUp[] = $id;

        return $id;
    }

    /**
     * Creates a file row — without an upload.
     *
     * `/api/insert` is ruled out: `pim_file` requires `hash` and `type` as NOT NULL, and the
     * insert path does not fill them. Files are otherwise created via `/file/upload`, but the
     * upload path has nothing to do with ManyToMany and would only bring its own sources of error
     * (see `FileApiTest`, where it is characterised).
     */
    private function file(string $name = 'm2m.txt'): string
    {
        $id = 'm2m-f-'.bin2hex(random_bytes(5));

        $this->pdo()->prepare(
            'INSERT INTO pim_file (id, name, type, hash, size, created, modified, views, isIntern)
             VALUES (:id, :name, :type, :hash, 5, NOW(), NOW(), 0, 0)'
        )->execute(array(
            'id'   => $id,
            'name' => $name,
            'type' => 'text/plain',
            'hash' => bin2hex(random_bytes(8)),
        ));

        $this->deleteAfterTest('pim_file', $id);
        $this->idsToCleanUp[] = $id;

        return $id;
    }

    /** Inserts links directly into the table. */
    private function link(string $fileId, string ...$tagIds): void
    {
        $stmt = $this->pdo()->prepare('INSERT INTO pim_file_tags (file_id, tag_id) VALUES (:f, :t)');

        foreach ($tagIds as $tagId) {
            $stmt->execute(array('f' => $fileId, 't' => $tagId));
        }
    }

    /** The tag ids stored in `pim_file_tags` for this file — sorted. */
    private function links(string $fileId): array
    {
        $stmt = $this->pdo()->prepare('SELECT tag_id FROM pim_file_tags WHERE file_id = :f ORDER BY tag_id');
        $stmt->execute(array('f' => $fileId));

        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Clears the links of a file.
     *
     * `tearDown()` does that anyway; the calls in the test body are placed where cleanup should
     * happen **before** an assertion — then a failure message does not depend on whether
     * the cleanup succeeded beforehand.
     */
    private function clearLinks(string $fileId): void
    {
        $this->pdo()->prepare('DELETE FROM pim_file_tags WHERE file_id = :f')->execute(array('f' => $fileId));
    }

    // ── Reading ────────────────────────────────────────────────────────────────────────

    public function testLinkedFileReturnsItsTagsAsFullObjects(): void
    {
        // Remarkable and therefore recorded: the response contains not only the ids,
        // but every tag as a complete object — with created, modified, views, users,
        // groups. A client that only needs the mapping gets the whole record.
        $file  = $this->file();
        $alpha = $this->tag('M2M-Alpha');
        $beta  = $this->tag('M2M-Beta');
        $this->link($file, $alpha, $beta);

        [$status, $body] = $this->postJson(
            '/api/single',
            array('entity' => self::ENTITY, 'id' => $file),
            $this->token()
        );

        $this->assertSame(200, $status);

        // Compared sorted: the order in which the tags come back guarantees
        // nothing — neither the entity nor the query specifies one. A test that
        // relies on it is a test that eventually turns red for no reason.
        $tags = $body['data']['tags'];
        $this->assertCount(2, $tags);

        $ids = array_column($tags, 'id');
        sort($ids);
        $expected = array($alpha, $beta);
        sort($expected);
        $this->assertSame($expected, $ids);

        $titles = array_column($tags, 'title');
        sort($titles);
        $this->assertSame(array('M2M-Alpha', 'M2M-Beta'), $titles);
        $this->assertArrayHasKey('created', $tags[0], 'Full object, not just the id');

        $this->clearLinks($file);
    }

    public function testFileWithoutTagsReturnsAnEmptyList(): void
    {
        $file = $this->file();

        [, $body] = $this->postJson(
            '/api/single',
            array('entity' => self::ENTITY, 'id' => $file),
            $this->token()
        );

        $this->assertSame(array(), $body['data']['tags'],
            'Empty list, not null — the difference matters to a client');
    }

    public function testSchemaDescribesTheJoinTable(): void
    {
        // The client learns from the schema what the relation physically looks like. After the
        // Doctrine switch this must hold unchanged — otherwise clients that rely on it
        // break.
        [$status, $raw] = $this->get('/api/schema', $this->token());
        $this->assertSame(200, $status);

        $tags = json_decode($raw, true)['data'][self::ENTITY]['properties']['tags'];

        $this->assertSame('multijoin', $tags['type']);
        $this->assertSame('Areanet\\PIM\\Entity\\Tag', $tags['accept']);
        $this->assertSame('pim_file_tags', $tags['foreign']);
        $this->assertSame('file_id', $tags['dbfield']);
        $this->assertSame('tag_id', $tags['dbfield_foreign']);
        $this->assertTrue($tags['isFilterable']);
    }

    // ── Writing ────────────────────────────────────────────────────────────────────────

    public function testTagsCanBeSetViaUpdate(): void
    {
        $file  = $this->file();
        $alpha = $this->tag('M2M-Set-A');
        $beta  = $this->tag('M2M-Set-B');

        [$status] = $this->postJson(
            '/api/update',
            array('entity' => self::ENTITY, 'id' => $file, 'data' => array('tags' => array($alpha, $beta))),
            $this->token()
        );

        $expected = array($alpha, $beta);
        sort($expected);

        $this->assertSame(200, $status);
        $this->assertSame($expected, $this->links($file),
            'The rows really are in pim_file_tags — checked via SQL, not via the response');

        $this->clearLinks($file);
    }

    public function testNewSetReplacesTheOldOneCompletely(): void
    {
        // The point where an ORM switch can go wrong: update means REPLACE, not
        // ADD. Whoever wants to remove beta sends the set without beta — and the row
        // must disappear, not remain.
        $file  = $this->file();
        $alpha = $this->tag('M2M-Replace-A');
        $beta  = $this->tag('M2M-Replace-B');
        $this->link($file, $alpha, $beta);

        [$status] = $this->postJson(
            '/api/update',
            array('entity' => self::ENTITY, 'id' => $file, 'data' => array('tags' => array($alpha))),
            $this->token()
        );

        $this->assertSame(200, $status);
        $this->assertSame(array($alpha), $this->links($file),
            'beta is gone — the set was replaced, not extended');

        $this->clearLinks($file);
    }

    public function testEmptySetRemovesAllLinks(): void
    {
        $file = $this->file();
        $this->link($file, $this->tag('M2M-Clear-A'), $this->tag('M2M-Clear-B'));

        [$status] = $this->postJson(
            '/api/update',
            array('entity' => self::ENTITY, 'id' => $file, 'data' => array('tags' => array())),
            $this->token()
        );

        $this->assertSame(200, $status);
        $this->assertSame(array(), $this->links($file));
    }

    // ── Deleting ───────────────────────────────────────────────────────────────────────

    public function testLinksDisappearTogetherWithTheFile(): void
    {
        // The JoinColumn carries onDelete="CASCADE". Whether the links are removed by the database
        // (foreign key) or by the ORM cannot be distinguished from the
        // outside — and does not matter for the contract either. What matters is that no
        // orphaned rows remain.
        $file = $this->file();
        $this->link($file, $this->tag('M2M-Cascade-A'), $this->tag('M2M-Cascade-B'));

        $this->assertCount(2, $this->links($file), 'Precondition');

        [$status] = $this->postJson(
            '/api/delete',
            array('entity' => self::ENTITY, 'id' => $file),
            $this->token()
        );

        $this->assertSame(200, $status);
        $this->assertSame(array(), $this->links($file),
            'No orphaned rows in pim_file_tags');

    }

    public function testDeletingTheTagAlsoCleansUpTheLink(): void
    {
        // The opposite direction — and it cleans up as well, contrary to the expectation when
        // this test was written. The annotation sets onDelete="CASCADE" only on the
        // joinColumns (file_id); but Doctrine creates it on BOTH foreign keys:
        //
        //   CONSTRAINT FK_…46F22BC   FOREIGN KEY (file_id) REFERENCES pim_file (id) ON DELETE CASCADE
        //   CONSTRAINT FK_…DD1FDCE8  FOREIGN KEY (tag_id)  REFERENCES pim_tag  (id) ON DELETE CASCADE
        //
        // So the schema is symmetric, although the annotation describes it asymmetrically.
        // Good — there are no orphaned rows. Recorded because it can NOT be read from the
        // entity: anyone reading only the annotation expects the opposite.
        $file = $this->file();
        $tag  = $this->tag('M2M-Tag-deleted');
        $this->link($file, $tag);

        $this->assertSame(array($tag), $this->links($file), 'Precondition');

        [$status] = $this->postJson(
            '/api/delete',
            array('entity' => 'PIM\\Tag', 'id' => $tag),
            $this->token()
        );

        $remaining = $this->links($file);

        $this->assertSame(200, $status, 'Deleting the tag succeeds');
        $this->assertSame(array(), $remaining,
            'No orphaned row — the foreign key on tag_id cascades as well');
    }

    // ── Filtering ──────────────────────────────────────────────────────────────────────

    public function testFilteringByTagsWorks(): void
    {
        // isFilterable=true is in the schema; here is the proof that it also takes effect. The
        // filter goes through the join table — exactly the kind of query that an
        // ORM switch could generate differently.
        $wanted    = $this->file('m2m-wanted.txt');
        $unwanted  = $this->file('m2m-unwanted.txt');
        $tag       = $this->tag('M2M-Filter');
        $otherTag  = $this->tag('M2M-Filter-Other');

        $this->link($wanted, $tag);
        $this->link($unwanted, $otherTag);

        [$status, $body] = $this->postJson(
            '/api/list',
            array('entity' => self::ENTITY, 'where' => array('tags' => $tag)),
            $this->token()
        );

        $ids = array_column($body['data'], 'id');

        $this->clearLinks($wanted);
        $this->clearLinks($unwanted);

        $this->assertSame(200, $status);
        $this->assertContains($wanted, $ids, 'The linked file is found');
        $this->assertNotContains($unwanted, $ids, 'The differently linked one is not');
    }
}
