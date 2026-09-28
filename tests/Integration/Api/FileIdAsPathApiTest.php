<?php
namespace Tests\Integration\Api;

use Areanet\PIM\Entity\Permission;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Integration\IntegrationTestCase;

/**
 * A file's id does not become a path (`015-000-0005`).
 *
 * THE FINDING, IN TWO DIRECTIONS. The id of a `PIM\File` came out of the request — `/file/upload`
 * takes it from the body, `/api/insert` from `data.id` — and with `DB_GUID_STRATEGY`, the shipped
 * default, the column is a free string. `FileSystem::getPath()` builds `data/files/<id>` from it:
 *
 *  - **writing:** `move_uploaded_file()` put the upload wherever the id pointed. `data/cache` is
 *    a reachable target, and that is where `Api::getSchema()` hands a file to `unserialize()`
 *    when `APP_ENABLE_SCHEMA_CACHE` is on.
 *  - **deleting:** `Api::doDelete()` unlinks every regular file in that directory and removes it;
 *    `FileController::overwriteAction()` does the same to the target record's directory.
 *
 * What is measured here is the file system, not the status code alone. A rejection that still
 * wrote the file would pass a status assertion.
 */
class FileIdAsPathApiTest extends IntegrationTestCase
{
    /** @return array<string, array{0: string}> */
    public static function idsThatAreNotIds(): array
    {
        return array(
            'parent into cache' => array('../cache/probe'),
            'parent only'       => array('..'),
            'nested parent'     => array('../../custom'),
            'plain slash'       => array('a/b'),
            'absolute'          => array('/tmp/contentfly-probe'),
            'backslash'         => array('..\\cache'),
            'not a uuid'        => array('probe-not-a-uuid'),
        );
    }

    #[DataProvider('idsThatAreNotIds')]
    public function testUploadRefusesAnIdThatIsAPath(string $id): void
    {
        [$token] = $this->fileUser();

        [$status, $body] = $this->upload($id, $token);

        $this->assertSame(400, $status, json_encode($body['errors'] ?? $body));
        $this->assertErrorEnvelope($body, 'contentfly_general_invalid_params');
        $this->assertNoTraceAt($id);
    }

    #[DataProvider('idsThatAreNotIds')]
    public function testInsertRefusesAnIdThatIsAPath(string $id): void
    {
        [$token] = $this->fileUser();

        [$status, $body] = $this->postJson('/api/insert', array(
            'entity' => 'PIM\\File', 'data' => array('id' => $id, 'name' => 'probe.txt', 'type' => 'text/plain'),
        ), $token);

        $this->assertSame(400, $status, json_encode($body['errors'] ?? $body));
        $this->assertErrorEnvelope($body, 'contentfly_file_invalid_type');
        $this->assertNoTraceAt($id);
    }

    /**
     * THE DELETE DIRECTION, against a record that already carries such an id.
     *
     * Written with SQL, because the API refuses it now — and this is the state of an installation
     * that ran before the guard existed. `data/cache` is filled with a marker beforehand: without
     * the containment in `getPath()`, `/api/delete` empties that directory and removes it.
     */
    public function testDeleteDoesNotEmptyADirectoryOutsideDataFiles(): void
    {
        [$token] = $this->fileUser(Permission::ALL);

        $marker = $this->applicationDir().'/data/cache/probe-015-000-0005.txt';
        file_put_contents($marker, 'do not delete me');

        $id = '../cache';
        $this->pdo()->prepare(
            "INSERT INTO pim_file (id, name, type, hash, size, isIntern, views, created, modified)
             VALUES (:id, 'probe.txt', 'text/plain', '', 5, 0, 0, NOW(), NOW())"
        )->execute(array('id' => $id));

        try {
            [$status] = $this->postJson('/api/delete', array('entity' => 'PIM\\File', 'id' => $id), $token);

            $this->assertNotSame(200, $status, 'The delete does not go through for such a record');
            $this->assertFileExists($marker, 'and data/cache is untouched — this is the finding');
            $this->assertDirectoryExists($this->applicationDir().'/data/cache');
        } finally {
            @unlink($marker);
            $this->pdo()->prepare('DELETE FROM pim_file WHERE id = :id')->execute(array('id' => $id));
        }
    }

    /** An ordinary upload keeps working — the guard must not cost the normal case. */
    public function testAnUploadWithoutAnIdStillWorks(): void
    {
        [$token] = $this->fileUser();

        [$status, $body] = $this->upload(null, $token);

        $this->assertSame(200, $status, json_encode($body['errors'] ?? $body));
        $this->assertIsString($body['data']['id'] ?? null);
    }

    /** And so does one with a real UUID, which is what a client that assigns ids sends. */
    public function testAnUploadWithAUuidStillWorks(): void
    {
        [$token] = $this->fileUser();
        $id      = sprintf('%s-%s-4%s-a%s-%s',
            bin2hex(random_bytes(4)), bin2hex(random_bytes(2)), substr(bin2hex(random_bytes(2)), 1),
            substr(bin2hex(random_bytes(2)), 1), bin2hex(random_bytes(6)));

        [$status, $body] = $this->upload($id, $token);

        $this->assertSame(200, $status, json_encode($body['errors'] ?? $body));
        $this->assertSame($id, $body['data']['id'] ?? null);
        $this->assertDirectoryExists($this->applicationDir().'/data/files/'.$id);
    }

    // ── helpers ────────────────────────────────────────────────────────────────────────────

    /** @return array{0:string,1:string,2:string} */
    private function fileUser(int $writable = Permission::ALL): array
    {
        return $this->createTestUser(array('PIM\\File' => array(
            'readable' => Permission::ALL,
            'writable' => $writable,
            'deletable' => Permission::ALL,
        )));
    }

    /** @return array{0:int,1:array} */
    private function upload(?string $id, string $token): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cf-id-');
        file_put_contents($tmp, 'probe');

        $fields = array('file' => new \CURLFile($tmp, 'text/plain', $this->probeName()));
        if ($id !== null) {
            $fields['id'] = $id;
        }

        $ch = curl_init(self::$baseUrl.'/file/upload');
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => $fields,
            CURLOPT_HTTPHEADER     => array('appcms-token: '.$token),
        ));
        $response = curl_exec($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unlink($tmp);

        $body = json_decode((string) $response, true) ?: array();

        if (isset($body['data']['id'])) {
            $this->deleteAfterTest('pim_file', $body['data']['id']);
        }

        return array($status, $body);
    }

    /**
     * The attack left no trace where the id pointed (000-000-0104).
     *
     * THIS USED TO COMPARE THE WHOLE `data/` TREE before and after the call — every path under
     * `cache/`, `import/` and `temp/` —, and it was flaky for it: `data/cache` also holds
     * Doctrine's `proxies/`, `query/`, `metadata/` and the `login-throttle/`. Any request may
     * write there in passing — a proxy on first use of an entity, a throttle entry from the
     * login inside `createTestUser()` — so the snapshot changed for reasons the test is not
     * about. Measured in the pipeline of #91: `test: PHP 8.4` red, `8.3` and `8.5` green, same
     * revision.
     *
     * WHAT IS MEASURED NOW IS THE TRACE, and it is two different things depending on the id:
     *
     *   the file       `move_uploaded_file()` would put the upload into the directory the id
     *                  points at. That marker works for every id, including the ones whose
     *                  target is a directory that legitimately exists — `..` resolves to
     *                  `data/`, `../../custom` to the project's `custom/`. Its name is unique
     *                  per run: a leftover from an earlier run — this working copy had one,
     *                  from before the fix — would otherwise fail a test that is measuring
     *                  something else.
     *   the directory  `getPath()` would `mkdir()` the target. Only checked when it was not
     *                  there before the call, because an existing one says nothing.
     *
     * The first version of this fix asserted the target itself must not exist. Ten runs in a
     * row said otherwise within seconds — `..` is `data/`, and `data/` is supposed to be there.
     */
    private function assertNoTraceAt(string $id): void
    {
        $target = $this->resolve($this->applicationDir().'/data/files/'.$id);

        $this->assertFileDoesNotExist($target.'/'.$this->probeName(),
            'The upload was written outside data/files, at '.$target);

        if (!in_array($target, $this->directoriesThatMayExist(), true)) {
            $this->assertDirectoryDoesNotExist($target,
                'A directory was created outside data/files, at '.$target);
        }
    }

    private ?string $probe = null;

    /** The uploaded file's name — unique per run, so no leftover can answer for this one. */
    private function probeName(): string
    {
        if ($this->probe === null) {
            $this->probe = 'probe-'.bin2hex(random_bytes(6)).'.txt';
        }

        return $this->probe;
    }

    /**
     * Targets that are a legitimate directory of the project, so their existence proves nothing.
     *
     * Named rather than probed: a directory the call itself created would be indistinguishable
     * from one that was already there if this were measured at run time.
     *
     * @return list<string>
     */
    private function directoriesThatMayExist(): array
    {
        $root = $this->applicationDir();

        return array(
            $root.'/data',
            $root.'/data/cache',
            $root.'/custom',
            $root,
        );
    }

    /**
     * A path with its `.` and `..` segments folded away, without touching the file system.
     *
     * `realpath()` returns false for a path that does not exist, which is precisely the case
     * under test — it could not tell "not there" from "cannot say".
     */
    private function resolve(string $path): string
    {
        $absolute = str_starts_with($path, '/');
        $parts    = array();

        foreach (preg_split('#[/\\\\]+#', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }

            if ($part === '..') {
                array_pop($parts);
                continue;
            }

            $parts[] = $part;
        }

        return ($absolute ? '/' : '').implode('/', $parts);
    }
}
