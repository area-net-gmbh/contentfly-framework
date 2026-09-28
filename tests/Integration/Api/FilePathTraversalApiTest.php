<?php
namespace Tests\Integration\Api;

use Areanet\PIM\Entity\Permission;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Integration\IntegrationTestCase;

/**
 * `PIM\File.name` cannot be turned into a path (`015-000-0003`).
 *
 * THE FINDING, END TO END: the `name` column was writable like any other string —
 * `StringType::toDatabase()` stores what it is handed, and `UploadValidator` is not on that path.
 * `Api::getAll()` then built `getPath($file).'/'.$sizePrefix.$file->getName()`, and the size `org`
 * adds no prefix. So whoever held read and write on `PIM\File` — the same right an upload needs —
 * uploaded any file, set its name to `../../custom/config.php`, and read the database credentials
 * out of `/api/all` with `filedata: ["org"]`.
 *
 * Two layers are measured here, and they are separate on purpose:
 *
 * 1. `FileFieldGuard` keeps such a value out of the column — the write is refused with 400.
 * 2. `FilePath` proves the containment again at the read, for records written before today. The
 *    name is put into the database directly for that, because the API no longer accepts it.
 */
class FilePathTraversalApiTest extends IntegrationTestCase
{
    /** @return array<string, array{0: string}> */
    public static function escapingNames(): array
    {
        return array(
            'parent traversal'     => array('../../../custom/config.php'),
            'single parent'        => array('../config.php'),
            'absolute path'        => array('/etc/passwd'),
            'nested relative'      => array('sub/../../config.php'),
            'plain slash'          => array('a/b.txt'),
            'parent only'          => array('..'),
            'backslash'            => array('..\\..\\config.php'),
        );
    }

    #[DataProvider('escapingNames')]
    public function testANameWithAPathPartIsRefusedOnUpdate(string $name): void
    {
        [$token] = $this->fileUser();
        $file    = $this->uploadProbe($token);
        $before  = $this->fileColumn($file, 'name');

        [$status, $body] = $this->postJson('/api/update', array(
            'entity' => 'PIM\\File', 'id' => $file, 'data' => array('name' => $name),
        ), $token);

        $this->assertSame(400, $status, json_encode($body['errors'] ?? $body));
        $this->assertErrorEnvelope($body, 'contentfly_file_invalid_type');
        $this->assertSame($before, $this->fileColumn($file, 'name'), 'The stored name is untouched.');
    }

    #[DataProvider('escapingNames')]
    public function testANameWithAPathPartIsRefusedOnInsert(string $name): void
    {
        [$token] = $this->fileUser();

        [$status, $body] = $this->postJson('/api/insert', array(
            'entity' => 'PIM\\File', 'data' => array('name' => $name, 'type' => 'text/plain'),
        ), $token);

        $this->assertSame(400, $status, json_encode($body['errors'] ?? $body));
        $this->assertErrorEnvelope($body, 'contentfly_file_invalid_type');
    }

    /**
     * THE EXPLOIT ITSELF, against a record that already carries the name.
     *
     * The value goes into the table with SQL — that is the state of an installation that ran
     * before the guard existed, and it is the only way to reach the read path now. Without
     * `FilePath::within()` in `getAll()` the answer carries `custom/config.php`, base64-encoded.
     */
    public function testFiledataDoesNotServeAFileFromOutsideTheRecordsDirectory(): void
    {
        [$token] = $this->fileUser();
        $file    = $this->uploadProbe($token);

        /*
         * THREE LEVELS, NOT TWO. The record's directory is `<project>/data/files/<id>`, so
         * reaching `custom/` takes `../../../`. Measured against the unfixed code on 2026-09-28:
         * `../../` answers with nothing, `../../../` answers with 12.716 bytes of
         * `custom/config.php`. The task text named two levels — the payload is what counts, and
         * a test with the wrong one would have been green before the fix as well.
         */
        $this->pdo()->prepare('UPDATE pim_file SET name = :name WHERE id = :id')
                    ->execute(array('name' => '../../../custom/config.php', 'id' => $file));

        [$status, $body] = $this->postJson('/api/all', array(
            'entity' => 'PIM\\File', 'filedata' => array('org'),
        ), $token);

        $this->assertSame(200, $status, json_encode($body['errors'] ?? $body));

        $record = $this->recordFrom($body, $file);
        $this->assertNotNull($record, 'Precondition: the record is in the answer at all');
        $this->assertArrayNotHasKey('filedata', $record,
            'No bytes are delivered for a name that leaves the directory');

        // Belt and braces: whatever the answer carries, none of it decodes to the configuration.
        foreach ($this->base64Values($body) as $decoded) {
            $this->assertStringNotContainsString('DB_HOST', $decoded,
                'and no base64 field in the answer decodes to the configuration');
        }
    }

    /** A name without a path part keeps working — the guard must not cost the ordinary case. */
    public function testAnOrdinaryNameIsStillWritableAndStillDelivered(): void
    {
        [$token] = $this->fileUser();
        $file    = $this->uploadProbe($token);

        [$status, $body] = $this->postJson('/api/update', array(
            'entity' => 'PIM\\File', 'id' => $file, 'data' => array('name' => 'renamed-probe.txt'),
        ), $token);

        $this->assertSame(200, $status, json_encode($body['errors'] ?? $body));
        $this->assertSame('renamed-probe.txt', $this->fileColumn($file, 'name'));
    }

    /** `type` is a content type, not a path and not a header. */
    public function testATypeThatIsNotAContentTypeIsRefused(): void
    {
        [$token] = $this->fileUser();
        $file    = $this->uploadProbe($token);

        [$status, $body] = $this->postJson('/api/update', array(
            'entity' => 'PIM\\File', 'id' => $file, 'data' => array('type' => '../../etc/passwd'),
        ), $token);

        $this->assertSame(400, $status, json_encode($body['errors'] ?? $body));
        $this->assertErrorEnvelope($body, 'contentfly_file_invalid_type');
    }

    // ── helpers ────────────────────────────────────────────────────────────────────────────

    /** @return array{0:string,1:string,2:string} token, user id, group id */
    private function fileUser(): array
    {
        return $this->createTestUser(array('PIM\\File' => array(
            'readable' => Permission::ALL,
            'writable' => Permission::ALL,
        )));
    }

    /** Uploads a harmless file and returns its id. */
    private function uploadProbe(string $token): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cf-traversal-');
        file_put_contents($tmp, 'probe');

        $ch = curl_init(self::$baseUrl.'/file/upload');
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => array('file' => new \CURLFile($tmp, 'text/plain', 'probe.txt')),
            CURLOPT_HTTPHEADER     => array('appcms-token: '.$token),
        ));
        $response = curl_exec($ch);
        unlink($tmp);

        $result = json_decode((string) $response, true) ?: array();
        $id     = $result['data']['id'] ?? null;

        $this->assertIsString($id, 'Precondition: the upload succeeded — '.json_encode($result));
        $this->deleteAfterTest('pim_file', $id);

        return $id;
    }

    private function fileColumn(string $id, string $column): mixed
    {
        return $this->pdo()->query("SELECT `$column` FROM pim_file WHERE id = ".$this->pdo()->quote($id))->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    private function recordFrom(array $body, string $id): ?array
    {
        // `/api/all` groups by entity: data['PIM\\File'] is the list.
        foreach ($body['data']['PIM\\File'] ?? array() as $record) {
            if (is_array($record) && ($record['id'] ?? null) === $id) {
                return $record;
            }
        }

        return null;
    }

    /** @return list<string> every base64-looking string in the answer, decoded */
    private function base64Values(array $body): array
    {
        $decoded = array();

        array_walk_recursive($body, function ($value) use (&$decoded): void {
            if (is_string($value) && strlen($value) > 16) {
                $try = base64_decode($value, true);
                if ($try !== false) {
                    $decoded[] = $try;
                }
            }
        });

        return $decoded;
    }
}
