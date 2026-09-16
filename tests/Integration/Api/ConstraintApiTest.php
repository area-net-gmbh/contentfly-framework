<?php
namespace Tests\Integration\Api;

use PDO;
use Tests\Integration\IntegrationTestCase;

/**
 * Characterisation tests for the remaining side effects of the write side —
 * `unique` violations and the sorting of `BaseSortable` entities.
 *
 * Plus honest bookkeeping for two side effects that **have nothing to check today**: the
 * `encoded` encryption and the OneJoin cascade on delete. For both there is a test on the
 * *precondition* instead of on the effect — it fires as soon as someone creates a matching
 * entity, and thereby demands the missing proof instead of silently dropping it.
 *
 * The same pattern as with `excludeFromSync` and `i18n_universal` in story `008-001`. That it
 * keeps recurring is a finding in itself: the framework carries features whose only users were
 * the deleted UI or customer projects.
 */
class ConstraintApiTest extends IntegrationTestCase
{
    /** @var array<int,string> */
    private array $observed = array();

    private function createTag(string $title): array
    {
        [$status, $body] = $this->postJson(
            '/api/insert',
            array('entity' => 'PIM\\Tag', 'data' => array('title' => $title)),
            $this->token()
        );

        if ($status === 200) {
            // 011-001-0002: insert answers with the created object as payload; it carries the id.
            $this->deleteAfterTest('pim_tag', $body['data']['id']);
            $this->observed[] = $body['data']['id'];
        }

        return array($status, $body);
    }

    protected function tearDown(): void
    {
        foreach ($this->observed as $modelId) {
            $this->pdo()->prepare('DELETE FROM pim_log WHERE model_id = :id')->execute(array('id' => $modelId));
        }

        $this->observed = array();

        parent::tearDown();
    }

    private function schema(): array
    {
        [$status, $raw] = $this->get('/api/schema', $this->token());
        $this->assertSame(200, $status);

        return json_decode($raw, true)['data'];
    }

    // ── unique ─────────────────────────────────────────────────────────────────────────

    public function testUniqueViolationIsRejected(): void
    {
        // PIM\Tag.title carries both: @ORM\Column(unique=true) at database level and
        // @PIM\Config(unique=true) in the schema. Api checks the latter.
        $title = 'Unique-'.bin2hex(random_bytes(6));

        [$firstStatus] = $this->createTag($title);
        $this->assertSame(200, $firstStatus, 'The first one goes through');

        // Flipped with 009-003-0002, and the old assertion had named the wrong cause:
        // it attributed the 500 to the finding from 000-000-0006, i.e. the error chain.
        // In fact this line contained a constant that does not exist —
        // Messages::contentfly_general_record_already_exists instead of …_ressource_… —, and the
        // call died of "Undefined constant", not of the response handling. PHPStan
        // found it.
        [$secondStatus] = $this->createTag($title);
        $this->assertSame(409, $secondStatus,
            'A unique violation is a conflict, not a server error');
    }

    public function testUniqueViolationLeavesNoHalfWrittenRow(): void
    {
        // The API response alone says nothing about this — hence checked against the database.
        $title = 'Unique-'.bin2hex(random_bytes(6));

        $this->createTag($title);
        $this->createTag($title);

        $count = (int) $this->pdo()
            ->query('SELECT COUNT(*) FROM pim_tag WHERE title = '.$this->pdo()->quote($title))
            ->fetchColumn();

        $this->assertSame(1, $count,
            'After the failed second attempt exactly one row exists');
    }

    public function testSchemaExposesTheUniqueProperty(): void
    {
        $this->assertTrue($this->schema()['PIM\\Tag']['properties']['title']['unique'],
            'unique is one of the ten fields that 012-005-0002 kept');
    }

    // ── Sorting ────────────────────────────────────────────────────────────────────────

    public function testBaseSortableEntitiesAreMarkedAsSortable(): void
    {
        $settings = $this->schema()['PIM\\Option']['settings'];

        $this->assertTrue($settings['isSortable']);
        $this->assertSame('sorting', $settings['sortBy'],
            'Api::getSchema() sets this for BaseSortable, regardless of the annotation');
        $this->assertSame('ASC', $settings['sortOrder']);
    }

    public function testSortRestrictToIsTakenFromTheAnnotation(): void
    {
        // According to the finding from 008-001-0002, sortRestrictTo is the ONLY one of the three
        // sorting fields with a real reader in the framework (JoinBidirectionalType).
        // sortBy and sortOrder only appear in the schema and are applied by no reader —
        // decided with 000-000-0013 that it stays that way, and corrected accordingly in
        // an_project/docs/pim-annotationen-migration.md.
        $this->assertSame('group', $this->schema()['PIM\\Option']['settings']['sortRestrictTo'],
            'PIM\\Option carries @PIM\\Config(sortRestrictTo="group") — sorting runs '
            .'per option group, not globally');

        $this->assertNull($this->schema()['PIM\\Nav']['settings']['sortRestrictTo'],
            'PIM\\Nav does inherit from BaseSortable, but does not restrict');
    }

    // ── The two gaps ───────────────────────────────────────────────────────────────────

    public function testNoEntityUsesEncodedEncryption(): void
    {
        // encoded is one of the ten fields that 012-005-0002 kept, and it has real readers in
        // StringType/TextareaType. Only: **no entity sets it**, and
        // SECURITY_CIPHER_KEY defaults to null — StringType would throw even then.
        // Encryption cannot be triggered today.
        //
        // If someone sets the flag, this test fires. Then this is where the proof belongs
        // that the value is stored encrypted in the database and comes back as plain text via
        // the API — checked against the database, not against the response.
        $withFlag = array();

        foreach ($this->schema() as $entity => $entry) {
            if ($entity === '_hash' || !isset($entry['properties'])) {
                continue;
            }
            foreach ($entry['properties'] as $name => $config) {
                if (!empty($config['encoded'])) {
                    $withFlag[] = $entity.'.'.$name;
                }
            }
        }

        $this->assertSame(array(), $withFlag,
            'Today no entity uses encoded=true. If that changes, the proof of '
            .'encryption belongs in this test.');
    }

    public function testThereIsNoOnejoinPropertyForTheDeleteCascade(): void
    {
        // Api::delete() also removes joined objects of type onejoin. There is not a
        // single @ORM\OneToOne relation in the framework or in the template — the code path
        // has no trigger.
        //
        // If such a relation appears, this test fires. Then this is where the proof belongs
        // that the joined object disappears along with the parent object when it is deleted.
        $onejoins = array();

        foreach ($this->schema() as $entity => $entry) {
            if ($entity === '_hash' || !isset($entry['properties'])) {
                continue;
            }
            foreach ($entry['properties'] as $name => $config) {
                if (($config['type'] ?? null) === 'onejoin') {
                    $onejoins[] = $entity.'.'.$name;
                }
            }
        }

        $this->assertSame(array(), $onejoins,
            'Today there is no onejoin property. If that changes, the proof of '
            .'the delete cascade belongs in this test.');
    }
}
