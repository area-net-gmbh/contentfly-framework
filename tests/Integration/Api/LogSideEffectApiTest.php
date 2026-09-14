<?php
namespace Tests\Integration\Api;

use PDO;
use Tests\Integration\IntegrationTestCase;

/**
 * Characterisation tests for the log side effect of every write operation.
 *
 * Every insert, update and delete creates a row in `pim_log`. It is the only
 * side effect of the write side that **persists data without being visible in the
 * response** — and therefore the one most likely to disappear unnoticed during the kernel swap.
 *
 * `model_label` is the actual reason for this file: the field is filled from the entity's
 * `labelProperty`, and exactly that was on the removal list in `012-005-0002`. It was kept
 * because it is persisted here. These tests are the
 * proof that the decision holds.
 *
 * Reading happens via `pdo()`: there is no API endpoint that delivers log rows —
 * `PIM\Log` is on the exclusion list of `getDeleted()` (see `008-001-0004`).
 */
class LogSideEffectApiTest extends IntegrationTestCase
{
    /** @var array<int,string> Ids whose log rows are cleaned up at the end. */
    private array $observed = array();

    /** @return array<int, array{mode:string,model_name:string,model_label:?string}> */
    private function logRows(string $modelId): array
    {
        return $this->pdo()
            ->query('SELECT mode, model_name, model_label FROM pim_log
                     WHERE model_id = '.$this->pdo()->quote($modelId).' ORDER BY created, mode')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Creates a tag via the API and marks it for cleanup. */
    private function createTag(string $title): string
    {
        [$status, $body] = $this->postJson(
            '/api/insert',
            array('entity' => 'PIM\\Tag', 'data' => array('title' => $title)),
            $this->token()
        );

        $this->assertSame(200, $status, 'Precondition: creating succeeds');

        $this->deleteAfterTest('pim_tag', $body['id']);
        $this->observed[] = $body['id'];

        return $body['id'];
    }

    protected function tearDown(): void
    {
        // Only known here: log rows are created during the test, not before it.
        foreach ($this->observed as $modelId) {
            $this->pdo()->prepare('DELETE FROM pim_log WHERE model_id = :id')->execute(array('id' => $modelId));
        }

        $this->observed = array();

        parent::tearDown();
    }

    // ── The three modes of the lifecycle ───────────────────────────────────────────────

    public function testInsertWritesALogRowWithModeINS(): void
    {
        $id = $this->createTag('Log-INS');

        $rows = $this->logRows($id);

        $this->assertCount(1, $rows);
        $this->assertSame('INS', $rows[0]['mode']);
        $this->assertSame('PIM\\Tag', $rows[0]['model_name']);
    }

    public function testUpdateWritesALogRowWithModeUPT(): void
    {
        $id = $this->createTag('Log-UPT');

        $this->postJson(
            '/api/update',
            array('entity' => 'PIM\\Tag', 'id' => $id, 'data' => array('title' => 'Log-UPT-changed')),
            $this->token()
        );

        $modes = array_column($this->logRows($id), 'mode');
        $this->assertContains('UPT', $modes);
    }

    public function testDeleteWritesALogRowWithModeDEL(): void
    {
        $id = $this->createTag('Log-DEL');

        $this->postJson('/api/delete', array('entity' => 'PIM\\Tag', 'id' => $id), $this->token());

        $modes = array_column($this->logRows($id), 'mode');
        $this->assertContains('DEL', $modes);
    }

    public function testTheWholeLifecycleLeavesThreeRows(): void
    {
        $id = $this->createTag('Log-Cycle');
        $this->postJson('/api/update', array('entity' => 'PIM\\Tag', 'id' => $id, 'data' => array('title' => 'Log-Cycle-2')), $this->token());
        $this->postJson('/api/delete', array('entity' => 'PIM\\Tag', 'id' => $id), $this->token());

        $modes = array_column($this->logRows($id), 'mode');
        sort($modes);

        $this->assertSame(array('DEL', 'INS', 'UPT'), $modes,
            'Creating, changing and deleting leave exactly one row each');
    }

    public function testLogRowTimestampHasOnlySecondResolution(): void
    {
        // pim_log.created is a DATETIME without fractional seconds. A lifecycle that
        // completes within one second — the normal case with one API call each —
        // therefore leaves rows with identical timestamps, and from the log
        // alone it is then impossible to tell what happened first. See 000-000-0013.
        //
        // What is checked is the **resolution**, not the coincidence: a first version of this
        // test claimed that two consecutive calls carried the same timestamp.
        // That only holds as long as they do not cross a second boundary — the test was red
        // in roughly every thirtieth run.
        $id = $this->createTag('Timestamp-Sample');

        $timestamp = (string) $this->pdo()
            ->query('SELECT created FROM pim_log WHERE model_id = '.$this->pdo()->quote($id))
            ->fetchColumn();

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            $timestamp,
            'No fractional seconds — two operations within the same second cannot be '
            .'told apart'
        );
    }

    // ── model_label — the safeguard for the decision from 012-005-0002 ─────────────────

    public function testModelLabelIsFilledFromTheLabelProperty(): void
    {
        // PIM\Tag carries @PIM\Config(labelProperty="title"). Exactly this field was on the
        // removal list in 012-005-0002 and was kept because its value is written to the
        // database here. If labelProperty goes away, model_label stays empty —
        // and a log entry only says THAT something happened, not WITH WHAT.
        $id = $this->createTag('A meaningful title');

        $rows = $this->logRows($id);

        $this->assertSame('A meaningful title', $rows[0]['model_label'],
            'model_label comes from the entity\'s labelProperty — see 012-005-0002');
    }

    public function testModelLabelKeepsTheValueAtTheTimeOfTheOperation(): void
    {
        $id = $this->createTag('Title-before');
        $this->postJson(
            '/api/update',
            array('entity' => 'PIM\\Tag', 'id' => $id, 'data' => array('title' => 'Title-after')),
            $this->token()
        );

        $rows = $this->logRows($id);
        $byMode = array_column($rows, 'model_label', 'mode');

        $this->assertSame('Title-before', $byMode['INS']);
        $this->assertSame('Title-after', $byMode['UPT'],
            'Every row keeps the value that applied at the time of its operation');
    }

    // ── USERDEL ────────────────────────────────────────────────────────────────────────

    public function testRevokingAUserWritesAUSERDELRow(): void
    {
        // USERDEL is created when a user is removed from an object's users list
        // — the virtualjoin from Base that 012-005-0001 kept as data-relevant.
        $adminId = (string) $this->pdo()->query("SELECT id FROM pim_user WHERE alias = 'admin'")->fetchColumn();
        $id      = $this->createTag('Userdel-Sample');

        $this->postJson(
            '/api/update',
            array('entity' => 'PIM\\Tag', 'id' => $id, 'data' => array('users' => array(array('id' => $adminId)))),
            $this->token()
        );

        $this->postJson(
            '/api/update',
            array('entity' => 'PIM\\Tag', 'id' => $id, 'data' => array('users' => array())),
            $this->token()
        );

        $modes = array_column($this->logRows($id), 'mode');

        $this->assertContains('USERDEL', $modes,
            'Revoking a user is logged as a mode of its own');
    }

    // ── What is NOT logged ─────────────────────────────────────────────────────────────

    public function testARejectedWriteAttemptLeavesNoLogRow(): void
    {
        $id = $this->createTag('No-Log-Sample');
        $before = count($this->logRows($id));

        [$status] = $this->postJson(
            '/api/update',
            array('entity' => 'PIM\\Tag', 'id' => $id, 'data' => array('title' => 'Without-Token'))
        );

        $this->assertSame(401, $status,
            'Since 006-002-0003 the intended code — Symfony 4.4 fixes 000-000-0006 here');
        $this->assertCount($before, $this->logRows($id),
            'Without a token neither a change nor a log entry is created');
    }
}
