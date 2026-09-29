<?php
namespace Tests\Integration\Api;

use Areanet\PIM\Entity\Permission;
use Tests\Integration\IntegrationTestCase;

/**
 * `/file/overwrite` deletes, so it asks for the delete right (`015-000-0021`).
 *
 * THE FINDING. The endpoint MOVES: it renames the source's files into the destination's directory
 * and drops the source record with `$this->em->remove()`. Until this task it only checked
 * `Permission::isWritable` and the write ownership. `isDeletable` — which `Api::doDelete()` demands
 * for exactly this operation — was never asked, and no `DELETED` row was written to `pim_log`.
 *
 * The way was short. A group with `writable = ALL` and `deletable = NONE`, meant to edit files but
 * not delete them: upload a file of your own under the same name as the target, call
 * `/file/overwrite` with the target as `sourceId`. The target record is gone, every reference to
 * its id breaks, and its content lives on under your own id.
 *
 * ── Why two of these tests need no files on disk ──────────────────────────────────────────
 *
 * The rejection happens before anything is read or moved. Records written straight into the table
 * are therefore enough, and they keep the test from depending on the upload path it does not
 * check. The successful case does upload, because a deletion that is supposed to leave a log row
 * has to actually happen.
 */
class FileOverwriteRightsApiTest extends IntegrationTestCase
{
    /** The case from the task: may edit everything, may delete nothing. */
    public function testWithoutTheDeleteRightTheSourceSurvives(): void
    {
        [$source, $dest] = $this->twoFilesNamedAlike();

        [$token] = $this->createTestUser(array('PIM\\File' => array(
            'readable'  => Permission::ALL,
            'writable'  => Permission::ALL,
            'deletable' => Permission::NONE,
        )));

        [$status, $body] = $this->postJson(
            '/file/overwrite',
            array('sourceId' => $source, 'destId' => $dest),
            $token
        );

        $this->assertSame(403, $status, json_encode($body['errors'] ?? $body));
        $this->assertTrue($this->recordExists($source), 'the source record is still there');
        $this->assertTrue($this->recordExists($dest), 'and so is the destination');
    }

    /**
     * And with `deletable = OWN` on a source somebody else owns.
     *
     * The write right is `ALL` here, so the old check waved it through; the delete right is the
     * one that has to bite.
     */
    public function testWithOwnDeleteRightAForeignSourceSurvives(): void
    {
        [$source, $dest] = $this->twoFilesNamedAlike();

        [$token, $userId] = $this->createTestUser(array('PIM\\File' => array(
            'readable'  => Permission::ALL,
            'writable'  => Permission::ALL,
            'deletable' => Permission::OWN,
        )));

        // The destination is the caller's, the source is not — only the source is deleted.
        $this->own($dest, $userId);

        [$status, $body] = $this->postJson(
            '/file/overwrite',
            array('sourceId' => $source, 'destId' => $dest),
            $token
        );

        $this->assertSame(403, $status, json_encode($body['errors'] ?? $body));
        $this->assertTrue($this->recordExists($source), 'the foreign source record is still there');
    }

    /**
     * Whoever may delete still overwrites — and the deletion now leaves a trace.
     *
     * The second acceptance criterion. A file that vanishes without a row in `pim_log` is exactly
     * the gap somebody looking into broken references falls into: the record is gone and nothing
     * says who removed it or when.
     */
    public function testAPermittedOverwriteWritesADeletedLogRow(): void
    {
        $token = $this->login();
        $name  = 'overwrite-'.bin2hex(random_bytes(5)).'.txt';

        $source = $this->upload($name, 'source content', $token);
        $dest   = $this->upload($name, 'destination content', $token);

        [$status, $body] = $this->postJson(
            '/file/overwrite',
            array('sourceId' => $source, 'destId' => $dest),
            $token
        );

        $this->assertSame(200, $status, json_encode($body['errors'] ?? $body));
        $this->assertFalse($this->recordExists($source), 'the source is gone, as before');
        $this->assertTrue($this->recordExists($dest), 'the destination stays');

        $log = $this->logRowFor($source);

        $this->assertNotNull($log, 'the deletion left a row in pim_log');
        $this->assertSame('PIM\\File', $log['model_name']);
        $this->assertSame($name, $log['model_label'], 'and it names the file');
    }

    // ── helpers ────────────────────────────────────────────────────────────────────────────

    /**
     * Two file records sharing a name — the precondition the endpoint insists on.
     *
     * @return array{0:string,1:string} source id, destination id
     */
    private function twoFilesNamedAlike(): array
    {
        $name  = 'overwrite-'.bin2hex(random_bytes(5)).'.txt';
        $ids   = array();
        $write = $this->pdo()->prepare(
            'INSERT INTO pim_file (id, name, type, hash, size, created, modified, views, isIntern, isHidden)
             VALUES (:id, :name, :type, :hash, 0, NOW(), NOW(), 0, 0, 0)'
        );

        foreach (array('src', 'dst') as $which) {
            $id = 'fo-'.$which.'-'.bin2hex(random_bytes(5));
            $write->execute(array(
                'id'   => $id,
                'name' => $name,
                'type' => 'text/plain',
                'hash' => hash('sha256', $id),
            ));
            $this->deleteAfterTest('pim_file', $id);
            $ids[] = $id;
        }

        return $ids;
    }

    private function own(string $id, string $userId): void
    {
        $this->pdo()->prepare('UPDATE pim_file SET usercreated_id = ? WHERE id = ?')
            ->execute(array($userId, $id));
    }

    private function recordExists(string $id): bool
    {
        $statement = $this->pdo()->prepare('SELECT COUNT(*) FROM pim_file WHERE id = ?');
        $statement->execute(array($id));

        return (int) $statement->fetchColumn() > 0;
    }

    /** @return array<string,mixed>|null */
    private function logRowFor(string $modelId): ?array
    {
        /*
         * Filtered by mode, not just by id: the upload of this same record wrote an `INS` row a
         * moment earlier, and both carry the same second in `created`. Ordering by time would
         * pick whichever the storage engine returns first — the first version of this test did,
         * and read the insert row's empty label as a missing one.
         */
        $statement = $this->pdo()->prepare(
            'SELECT model_name, model_label, mode FROM pim_log WHERE model_id = ? AND mode = ?'
        );
        $statement->execute(array($modelId, 'DEL'));

        $row = $statement->fetch(\PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /** @return string the id of the stored file */
    private function upload(string $name, string $content, string $token): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cf-overwrite-');
        file_put_contents($tmp, $content);

        $ch = curl_init(self::$baseUrl.'/file/upload');
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => array('file' => new \CURLFile($tmp, 'text/plain', $name)),
            CURLOPT_HTTPHEADER     => array('appcms-token: '.$token),
        ));
        $response = curl_exec($ch);
        unlink($tmp);

        $id = json_decode((string) $response, true)['data']['id'] ?? null;

        if (!is_string($id) || $id === '') {
            $this->fail('The upload did not succeed: '.(string) $response);
        }

        $this->deleteAfterTest('pim_file', $id);

        return $id;
    }
}
