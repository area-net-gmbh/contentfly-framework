<?php
namespace Tests\Integration\Api;

use Areanet\PIM\Entity\Permission;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Integration\IntegrationTestCase;

/**
 * A re-upload is a write on somebody's record (`015-000-0006`).
 *
 * THE FINDING. `uploadAction` checked `Permission::isWritable()` and threw the return value
 * away. That value says WHICH level a user holds — `OWN`, `GROUP` or `ALL` — and every other
 * write path on `PIM\File` narrows by it: `overwriteAction` calls `assertFileWritable()`,
 * `Api::doUpdate()` does the same by hand. The upload did not.
 *
 * So a user with `writable = OWN` read the id of a foreign file out of a `/file/get` URL,
 * uploaded a replacement under that id, and the victim's file was gone: a publicly linked image
 * or PDF had different content, the thumbnails were rebuilt from it — and `userCreated` named
 * the caller, which made the change invisible to the very rule that should have stopped it.
 *
 * Both halves are measured here, and the second one matters on its own: an ownership check that
 * runs while the attacker is already recorded as the owner is no check.
 */
class UploadOwnershipApiTest extends IntegrationTestCase
{
    /** @return array<string, array{0: int}> */
    public static function narrowedLevels(): array
    {
        return array(
            'OWN'   => array(Permission::OWN),
            'GROUP' => array(Permission::GROUP),
        );
    }

    #[DataProvider('narrowedLevels')]
    public function testANarrowedUserCannotReplaceAForeignFile(int $level): void
    {
        [$victimToken]   = $this->fileUser(Permission::ALL);
        [$attackerToken] = $this->fileUser($level);

        $file = $this->upload($victimToken, 'the original', 'victim.txt');

        $before = array(
            'hash'        => $this->fileColumn($file, 'hash'),
            'size'        => $this->fileColumn($file, 'size'),
            'userCreated' => $this->fileColumn($file, 'usercreated_id'),
        );

        [$status] = $this->uploadWithStatus($attackerToken, 'REPLACED BY THE ATTACKER', 'victim.txt', $file);

        $this->assertSame(403, $status, 'The upload onto a foreign record is refused');
        $this->assertSame($before['hash'], $this->fileColumn($file, 'hash'), 'and the stored hash is untouched');
        $this->assertSame($before['size'], $this->fileColumn($file, 'size'));
        $this->assertSame($before['userCreated'], $this->fileColumn($file, 'usercreated_id'),
            'and the record still belongs to whoever created it');
        $this->assertSame('the original', $this->storedContent($file), 'and the bytes on disk are the original');
    }

    /**
     * The owner's own re-upload keeps working — that is what the endpoint is for.
     */
    public function testTheOwnerCanStillReplaceHisOwnFile(): void
    {
        [$token] = $this->fileUser(Permission::OWN);

        $file   = $this->upload($token, 'first version', 'mine.txt');
        $before = $this->fileColumn($file, 'usercreated_id');

        [$status] = $this->uploadWithStatus($token, 'second version', 'mine.txt', $file);

        $this->assertSame(200, $status);
        $this->assertSame('second version', $this->storedContent($file));
        $this->assertSame($before, $this->fileColumn($file, 'usercreated_id'),
            'and the creator is unchanged — it was the same person anyway');
    }

    /** With `ALL` a re-upload onto a foreign record is allowed; that level says so. */
    public function testWithAllAForeignFileMayStillBeReplaced(): void
    {
        [$victimToken] = $this->fileUser(Permission::ALL);
        [$otherToken]  = $this->fileUser(Permission::ALL);

        $file = $this->upload($victimToken, 'the original', 'shared.txt');

        [$status] = $this->uploadWithStatus($otherToken, 'a new version', 'shared.txt', $file);

        $this->assertSame(200, $status);
        $this->assertSame('a new version', $this->storedContent($file));
    }

    /**
     * THE SECOND HALF, AND IT IS A FINDING OF ITS OWN.
     *
     * `setUserCreated()` ran on every pass, on a found record as on a new one. Even where the
     * re-upload is allowed — `ALL`, above — the record must not change hands: ownership is what
     * every `OWN` rule in this application is decided by, and a write that rewrites it silently
     * removes the basis of the next check.
     */
    public function testAReUploadNeverChangesWhoCreatedTheRecord(): void
    {
        [$victimToken, $victimId] = $this->fileUser(Permission::ALL);
        [$otherToken]             = $this->fileUser(Permission::ALL);

        $file = $this->upload($victimToken, 'the original', 'shared.txt');
        $this->assertSame($victimId, $this->fileColumn($file, 'usercreated_id'), 'Precondition: the victim created it');

        [$status] = $this->uploadWithStatus($otherToken, 'a new version', 'shared.txt', $file);

        $this->assertSame(200, $status);
        $this->assertSame($victimId, $this->fileColumn($file, 'usercreated_id'),
            'The creator stayed the creator — this is the half that made the attack invisible');
    }

    // ── helpers ────────────────────────────────────────────────────────────────────────────

    /** @return array{0:string,1:string,2:string} token, user id, group id */
    private function fileUser(int $writable): array
    {
        return $this->createTestUser(array('PIM\\File' => array(
            'readable'  => Permission::ALL,
            'writable'  => $writable,
            'deletable' => Permission::ALL,
        )));
    }

    private function upload(string $token, string $content, string $name): string
    {
        [$status, $body] = $this->uploadWithStatus($token, $content, $name, null);

        $this->assertSame(200, $status, 'Precondition: the upload succeeded — '.json_encode($body));
        $this->assertIsString($body['data']['id'] ?? null);

        return $body['data']['id'];
    }

    /** @return array{0:int,1:array} */
    private function uploadWithStatus(string $token, string $content, string $name, ?string $id): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cf-own-');
        file_put_contents($tmp, $content);

        $fields = array('file' => new \CURLFile($tmp, 'text/plain', $name));
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

    private function fileColumn(string $id, string $column): mixed
    {
        return $this->pdo()->query("SELECT `$column` FROM pim_file WHERE id = ".$this->pdo()->quote($id))->fetchColumn();
    }

    /** What is actually on disk — a status code alone does not say whether the bytes changed. */
    private function storedContent(string $id): string
    {
        $name = (string) $this->fileColumn($id, 'name');
        $path = $this->applicationDir().'/data/files/'.$id.'/'.$name;

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
