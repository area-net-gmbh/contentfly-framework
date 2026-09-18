<?php
namespace Tests\Integration\Api;

use Areanet\PIM\Entity\Permission;
use Tests\Integration\IntegrationTestCase;

/**
 * Characterisation tests for the file API — the three actions that `012-003-0001` classified
 * as API functions: upload, delivery, overwrite.
 *
 * **Characterisation means: record what is.** Including the questionable parts. These tests
 * are meant to fire during the kernel switch (epic 009) when behaviour changes — they do not
 * describe what the API should look like.
 *
 * Preconditions (otherwise skipped):
 * - `CONTENTFLY_TEST_BASE_URL` points to a running, installed instance
 * - `CONTENTFLY_TEST_ADMIN_PASS` is the password of the user `admin`
 *
 * See `tests/README.md`.
 */
class FileApiTest extends IntegrationTestCase
{

    public function testLoginReturnsAToken(): void
    {
        $this->assertNotEmpty($this->token());
        $this->assertMatchesRegularExpression('/^[0-9a-f]{128}$/', $this->token(), 'The token is 64 bytes as hex');
    }

    public function testUploadCreatesAFileAndReturnsItsId(): void
    {
        $response = $this->upload('sample.txt', "hello contentfly\n", $this->token());

        // 011-001-0004: `message` is gone — the 200 says it. `data` keeps its meaning: before it
        // was the payload beside the sentence, now it is the payload alone.
        $this->assertNotEmpty($this->assertEnvelope($response)['id'] ?? null);
    }

    public function testUploadedFileIsStoredByteIdenticalOnDisk(): void
    {
        $content  = "line one\nline two\n";
        $response = $this->upload('roundtrip.txt', $content, $this->token());

        $path = self::dataDir().'/files/'.$response['data']['id'].'/roundtrip.txt';

        $this->assertFileExists($path, 'The upload stores the file under data/files/<id>/<name>');
        $this->assertSame($content, file_get_contents($path), 'The content must be stored byte-identical');
    }

    /**
     * Delivery responds with a **redirect** to the path under `data/files/`,
     * not with the file content. Under Apache the `.htaccess` then takes over and serves existing
     * files directly; under the test server `tests/router.php` does that.
     *
     * **Since `000-000-0006` the target is fixed.** Before that, `bootstrap-web.php` derived
     * `Config::WEB_ROOT` from `$_SERVER['PHP_SELF']` on every request; under the
     * built-in PHP server this produced `/index.php/file/get/data/files/…` — a path
     * into nowhere. That is why only THAT a redirect happens could be checked here, not WHERE TO.
     * Now the mount point comes from the configuration, and `testDeliveryReturnsTheContent()`
     * follows the redirect all the way to the file.
     */
    public function testDeliveryRespondsWithRedirectToTheFile(): void
    {
        $response = $this->upload('delivered.txt', "visible\n", $this->token());

        [$status, , $headers] = $this->get('/file/get/'.$response['data']['id']);
        $location = $this->header($headers, 'Location');

        // 301, not 302: getAction() calls `$this->app->redirect($redirectUri, 301)` — like that since
        // the initial import, and documented like that in the README. The assertion was set to 302
        // until 000-000-0019; see the comment on testDeliveryRequiresNoToken.
        $this->assertSame(301, $status);
        $this->assertSame(
            '/data/files/'.$response['data']['id'].'/delivered.txt',
            (string) $location,
            'Absolute from WEB_ROOT, no longer derived from PHP_SELF — 000-000-0006'
        );
    }

    /**
     * Delivery end-to-end: upload, follow the redirect, compare the content.
     *
     * **This was not checkable until `000-000-0006`**, and that is the reason this
     * test exists: a test net that does not cover delivery leaves exactly the function unchecked
     * during the kernel switch (epic `009`) that every client needs. The redirect itself
     * says nothing about that — it can look formally correct and still point into nowhere,
     * and that is exactly what it did.
     */
    public function testDeliveryReturnsTheContent(): void
    {
        $content  = "first line\nsecond line\n";
        $response = $this->upload('e2e.txt', $content, $this->token());

        $ch = curl_init(self::$baseUrl.'/file/get/'.$response['data']['id']);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
        ));
        $body   = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->assertSame(200, $status, 'Following the redirect yields the file, not a stray target');
        $this->assertSame($content, $body, 'Byte-identical to what was uploaded');
    }

    /**
     * **Why 301 is here and 302 was here for a while** (`000-000-0019`).
     *
     * `006-002-0006` changed both delivery assertions from 301 to 302, as
     * "adjust the test expectation to the new stack". That was a misdiagnosis: at that
     * point the upload was already broken by the `UploadedFile` break, `data.id` came back
     * as `null`, and the call went to `/file/get/` **without an id**. There it is
     * not delivery that responds, but the WEB_ROOT redirect to `/` — with 302.
     *
     * So what was measured was a symptom of the upload defect, and it was pinned down as new
     * Symfony 4.4 behaviour. `getAction()` still calls `redirect($redirectUri, 301)`, since the
     * initial import; the README describes it the same way.
     *
     * The case illustrates the rule from `an_project/docs/technical.md`: a
     * test adjustment is a change of behaviour and needs a justification. If it is
     * made to turn a red test green, it pins down the defect.
     */
    public function testDeliveryRequiresNoToken(): void
    {
        // Deliberately so: getAction in FileControllerProvider is NOT attached to checkAuth.
        // Whoever knows the ID gets the file. This is current behaviour, not a proposal.
        $response = $this->upload('public.txt', "visible\n", $this->token());

        [$status] = $this->get('/file/get/'.$response['data']['id']);

        $this->assertSame(301, $status, 'Without a token the request is not rejected but redirected');
    }

    /**
     * An unknown ID ends with **404**.
     *
     * For a while the test recorded 500, because the debug exception handler caught the
     * `FileNotFoundException` before the application's handler. The stack switch
     * (`006-002-0003`) fixed that, `000-000-0006` the remaining half on the
     * API side.
     */
    public function testUnknownIdReturnsNoFile(): void
    {
        [$status] = $this->get('/file/get/00000000-0000-0000-0000-000000000000');

        $this->assertNotSame(200, $status);
        $this->assertSame(404, $status,
            'Since 006-002-0003 the intended code — Symfony 4.4 fixes 000-000-0006 here');
    }

    public function testUploadWithoutTokenIsRejected(): void
    {
        $response = $this->upload('forbidden.txt', "no\n", null);

        /*
         * 011-001-0004: no file in the payload — `data` is null and the answer carries `errors`.
         *
         * `code` is null here, and that is right: the rejection is Symfony's AccessDeniedHttpException
         * from the route guard, not a Contentfly exception with a Messages key. It has no stable
         * identifier, so the envelope does not invent one.
         */
        $this->assertNull($this->assertErrorEnvelope($response)['code']);
    }

    public function testOverwriteReplacesTheContentOfTheTarget(): void
    {
        // Both files carry the same name - that is a precondition, see next test.
        $source = $this->upload('same.txt', "new content\n", $this->token())['data']['id'];
        $target = $this->upload('same.txt', "old content\n", $this->token())['data']['id'];

        [, $response] = $this->postJson('/file/overwrite', array('sourceId' => $source, 'destId' => $target), $this->token());

        // 011-001-0004: the two ids ARE the answer; `message` said the same thing a second time.
        $this->assertSame(array('sourceId' => $source, 'destId' => $target), $this->assertEnvelope($response));

        $targetPath = self::dataDir().'/files/'.$target.'/same.txt';
        $this->assertFileExists($targetPath);
        $this->assertSame(
            "new content\n",
            file_get_contents($targetPath),
            'After overwriting, the target carries the content of the source'
        );

        $this->assertDirectoryDoesNotExist(
            self::dataDir().'/files/'.$source,
            'The source is moved, not copied - its directory disappears'
        );
    }

    /**
     * Overwriting requires source and target to carry **the same file name**
     * (`FileController::overwriteAction()`). Otherwise it aborts with a FileNotFoundException
     * - a misleading message for a name check, but it is current
     * behaviour and clients can rely on it.
     */
    public function testOverwriteRequiresIdenticalFileNames(): void
    {
        $source = $this->upload('one.txt', "new content\n", $this->token())['data']['id'];
        $target = $this->upload('two.txt', "old content\n", $this->token())['data']['id'];

        $this->postJson('/file/overwrite', array('sourceId' => $source, 'destId' => $target), $this->token());

        $this->assertSame(
            "old content\n",
            file_get_contents(self::dataDir().'/files/'.$target.'/two.txt'),
            'With different names the target stays untouched'
        );
    }

    /**
     * **The predicted case happened and is fixed** (`000-000-0019`).
     *
     * Until then this test recorded that the upload works through the raw `$_FILES` array path
     * and carried the warning that the upload accepted a raw `$_FILES` array instead of an
     * `UploadedFile`. That worked **by accident**: PHP 8.1 adds the key `full_path` to `$_FILES`,
     * the detection in HttpFoundation 3.4 (`FileBag::$fileKeys`)
     * compares the keys exactly, fails on it and passed the raw array through —
     * exactly what `uploadAction()` expected with `$file['name']`.
     *
     * With `006-002-0003` (Symfony 3.4 -> 4.4) an `UploadedFile` arrived there, and the
     * array access became a fatal error. Literally at the predicted spot.
     * Since then `uploadAction()` reads the four values via the `UploadedFile` API.
     *
     * **The test itself is unchanged** — the same two assertions as before. Only
     * name and comment described a mechanism that no longer exists. What it
     * checks is the assertion at which the break became visible back then: that the
     * reported file size comes through.
     */
    public function testUploadTakesOverTheReportedFileSize(): void
    {
        $response = $this->upload('image.txt', str_repeat('x', 1024), $this->token());

        $this->assertNotEmpty($response['data']['id'] ?? null);
        $this->assertSame(1024, $response['data']['size'] ?? null, 'The size comes from the client\'s declaration');
    }

    // ── What must never be stored (000-000-0038) ───────────────────────────────────────

    /**
     * The measurement from the task, as a test.
     *
     * Until 000-000-0038 this upload was stored as `data/files/<id>/probe-upload.php`, and requesting
     * it answered `EXECUTED-42`. The test does not request anything: it checks that the file never
     * reaches the disk and no row reaches the table — a file that is not there cannot be executed.
     *
     * @dataProvider executableNames
     */
    public function testAnExecutableUploadIsRejectedAndLeavesNothingBehind(string $name): void
    {
        $this->token();

        $rowsBefore  = (int) $this->pdo()->query('SELECT COUNT(*) FROM pim_file')->fetchColumn();
        $dirsBefore  = $this->fileDirectories();

        [$status, $body] = $this->uploadWithStatus($name, '<?php echo "EXECUTED-" . (6*7);', $this->token());

        $this->assertSame(415, $status, "$name is rejected as an unsupported type");
        $this->assertErrorEnvelope($body, 'contentfly_file_invalid_type'); // 011-001-0003
        $this->assertSame($rowsBefore, (int) $this->pdo()->query('SELECT COUNT(*) FROM pim_file')->fetchColumn(),
            'No row in pim_file');
        $this->assertSame($dirsBefore, $this->fileDirectories(), 'No directory under data/files/');
    }

    /** @return iterable<string, array{string}> */
    public static function executableNames(): iterable
    {
        yield 'the name from the measurement' => array('probe-upload.php');
        yield 'php hidden in a middle segment' => array('shell.php.jpg');
        yield 'server configuration'          => array('.htaccess');
    }

    // ── The size limit (000-000-0042) ──────────────────────────────────────────────────

    /**
     * The limit the test server runs with — tools/ci/prepare-test-environment.sh passes it as
     * APP_FILE_MAX_UPLOAD_SIZE. 1 MiB, below PHP's default upload_max_filesize of 2M, so the
     * application's check is what answers and not the server's.
     */
    private const TEST_SERVER_MAX_UPLOAD_SIZE = 1048576;

    public function testAnUploadOverTheLimitIsRejectedAndLeavesNothingBehind(): void
    {
        $token      = $this->token();
        $rowsBefore = (int) $this->pdo()->query('SELECT COUNT(*) FROM pim_file')->fetchColumn();
        $dirsBefore = $this->fileDirectories();

        [$status, $body] = $this->uploadWithStatus('large.txt', str_repeat('a', self::TEST_SERVER_MAX_UPLOAD_SIZE + 1), $token);

        $this->assertSame(413, $status, 'One byte over FILE_MAX_UPLOAD_SIZE');
        // 011-001-0003: `message` became `code`, and `message_value` moved into `context` — a
        // fixed key instead of one that only appeared for a ContentflyException.
        $entry = $this->assertErrorEnvelope($body, 'contentfly_file_too_large');
        $this->assertSame(self::TEST_SERVER_MAX_UPLOAD_SIZE, $entry['context']['value'], 'The limit is named');
        $this->assertSame($rowsBefore, (int) $this->pdo()->query('SELECT COUNT(*) FROM pim_file')->fetchColumn(), 'No row in pim_file');
        $this->assertSame($dirsBefore, $this->fileDirectories(), 'No directory under data/files/');
    }

    public function testAnUploadAtTheLimitIsStored(): void
    {
        [$status, $body] = $this->uploadWithStatus('exact.txt', str_repeat('a', self::TEST_SERVER_MAX_UPLOAD_SIZE), $this->token());

        $this->assertSame(200, $status, json_encode($body));
        $this->cleanUpUploadedFile($body['data']['id'] ?? null);
    }

    public function testAnUploadOverPhpsOwnLimitIs413NotAMissingFile(): void
    {
        $phpLimit = $this->bytes((string) ini_get('upload_max_filesize'));
        $postLimit = $this->bytes((string) ini_get('post_max_size'));

        // Same PHP binary as the test server; the upload must pass post_max_size to reach $_FILES.
        if ($phpLimit === 0 || $phpLimit + 4096 >= $postLimit) {
            $this->markTestSkipped('upload_max_filesize is not below post_max_size on this PHP.');
        }

        [$status, $body] = $this->uploadWithStatus('huge.bin', str_repeat('a', $phpLimit + 1024), $this->token());

        $this->assertSame(413, $status, 'PHP rejected the file; before 000-000-0042 this answered 400 missing params');
        $this->assertErrorEnvelope($body, 'contentfly_file_too_large'); // 011-001-0003
    }

    private function bytes(string $iniValue): int
    {
        $value = (int) $iniValue;

        return match (strtolower(substr(trim($iniValue), -1))) {
            'g'     => $value * 1024 ** 3,
            'm'     => $value * 1024 ** 2,
            'k'     => $value * 1024,
            default => $value,
        };
    }

    // ── Helpers ────────────────────────────────────────────────────────────────────────

    // ── Overwrite and ownership (000-000-0060) ─────────────────────────────────────────
    //
    // Overwriting used to check only the write right on PIM\File, not whose files source and
    // target are. With writable = OWN a user replaced the content of any file carrying the same
    // name — and made a foreign source disappear, because the source is moved, not copied.

    public function testWithLevelOwnAForeignTargetIsNotOverwritten(): void
    {
        [$token] = $this->fileUser(Permission::OWN);

        $source = $this->upload('owned.txt', "intruder\n", $token)['data']['id'];
        $target = $this->upload('owned.txt', "original\n", $this->token())['data']['id'];

        [$status] = $this->postJson('/file/overwrite', array('sourceId' => $source, 'destId' => $target), $token);

        $this->assertSame(403, $status, 'The target belongs to the admin');
        $this->assertSame("original\n", file_get_contents(self::dataDir().'/files/'.$target.'/owned.txt'),
            'The target on disk is unchanged');
        $this->assertDirectoryExists(self::dataDir().'/files/'.$source, 'Nothing was moved');
    }

    public function testWithLevelOwnAForeignSourceIsNotMovedAway(): void
    {
        [$token] = $this->fileUser(Permission::OWN);

        $source = $this->upload('owned.txt', "foreign\n", $this->token())['data']['id'];
        $target = $this->upload('owned.txt', "mine\n", $token)['data']['id'];

        [$status] = $this->postJson('/file/overwrite', array('sourceId' => $source, 'destId' => $target), $token);

        $this->assertSame(403, $status, 'The source belongs to the admin — overwriting would delete it');
        $this->assertDirectoryExists(self::dataDir().'/files/'.$source, 'The foreign source is still there');
        $this->assertSame("mine\n", file_get_contents(self::dataDir().'/files/'.$target.'/owned.txt'));
    }

    public function testWithLevelOwnTheOwnFilesAreOverwritten(): void
    {
        [$token] = $this->fileUser(Permission::OWN);

        $source = $this->upload('owned.txt', "new\n", $token)['data']['id'];
        $target = $this->upload('owned.txt', "old\n", $token)['data']['id'];

        [$status] = $this->postJson('/file/overwrite', array('sourceId' => $source, 'destId' => $target), $token);

        $this->assertSame(200, $status);
        $this->assertSame("new\n", file_get_contents(self::dataDir().'/files/'.$target.'/owned.txt'));
    }

    public function testWithLevelGroupAFileSharedWithTheGroupIsOverwritten(): void
    {
        [$token, , $groupId] = $this->fileUser(Permission::GROUP);

        $source   = $this->upload('shared.txt', "new\n", $token)['data']['id'];
        $shared   = $this->upload('shared.txt', "old\n", $this->token())['data']['id'];
        $unshared = $this->upload('shared.txt', "untouched\n", $this->token())['data']['id'];

        $this->pdo()->prepare('UPDATE pim_file SET `groups` = :grp WHERE id = :id')
             ->execute(array('grp' => $groupId, 'id' => $shared));

        [$statusUnshared] = $this->postJson('/file/overwrite', array('sourceId' => $source, 'destId' => $unshared), $token);
        [$statusShared]   = $this->postJson('/file/overwrite', array('sourceId' => $source, 'destId' => $shared), $token);

        $this->assertSame(403, $statusUnshared, 'Without a group relation the file stays out of reach');
        $this->assertSame("untouched\n", file_get_contents(self::dataDir().'/files/'.$unshared.'/shared.txt'));

        $this->assertSame(200, $statusShared, 'The own group is listed in groups');
        $this->assertSame("new\n", file_get_contents(self::dataDir().'/files/'.$shared.'/shared.txt'));
    }

    /**
     * A user who may read every file and write on the given level.
     *
     * @return array{0:string,1:string,2:string} token, user id, group id
     */
    private function fileUser(int $writable): array
    {
        return $this->createTestUser(array('PIM\\File' => array(
            'readable' => Permission::ALL,
            'writable' => $writable,
        )));
    }

    private function upload(string $name, string $content, ?string $token): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cf-test-');
        file_put_contents($tmp, $content);

        $ch = curl_init(self::$baseUrl.'/file/upload');
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => array('file' => new \CURLFile($tmp, 'text/plain', $name)),
            CURLOPT_HTTPHEADER     => $token ? array('appcms-token: '.$token) : array(),
        ));
        $response = curl_exec($ch);
        curl_close($ch);
        unlink($tmp);

        $result = json_decode((string) $response, true) ?: array();

        $this->cleanUpUploadedFile($result['data']['id'] ?? null);

        return $result;
    }

    /**
     * Like upload(), but with the status code — a rejection is only a rejection with its code.
     *
     * @return array{0:int,1:array}
     */
    private function uploadWithStatus(string $name, string $content, ?string $token): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cf-test-');
        file_put_contents($tmp, $content);

        $ch = curl_init(self::$baseUrl.'/file/upload');
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => array('file' => new \CURLFile($tmp, 'text/plain', $name)),
            CURLOPT_HTTPHEADER     => $token ? array('appcms-token: '.$token) : array(),
        ));
        $response = curl_exec($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        unlink($tmp);

        $result = json_decode((string) $response, true) ?: array();

        $this->cleanUpUploadedFile($result['data']['id'] ?? null);

        return array($status, $result);
    }

    /** @return list<string> */
    private function fileDirectories(): array
    {
        $entries = array_values(array_diff((array) scandir(self::dataDir().'/files'), array('.', '..', '.gitkeep')));
        sort($entries);

        return $entries;
    }

    /**
     * Registers everything a successful upload leaves behind — the file row, the log rows
     * created along the way and the directory under `data/files/`.
     *
     * Without this the test environment grew by 8 files and 17 database rows with every run
     * (task `000-000-0008`). A rejected upload has no id — then there is
     * nothing to register.
     */
    private function cleanUpUploadedFile(?string $id): void
    {
        if ($id === null) {
            return;
        }

        foreach ($this->pdo()->query('SELECT id FROM pim_log WHERE model_id = '.$this->pdo()->quote($id))->fetchAll(\PDO::FETCH_COLUMN) as $logId) {
            $this->deleteAfterTest('pim_log', $logId);
        }

        $this->deleteAfterTest('pim_file', $id);
        $this->deleteDirectoryAfterTest(self::dataDir().'/files/'.$id);
    }
}
