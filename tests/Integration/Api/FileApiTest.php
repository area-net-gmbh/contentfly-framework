<?php
namespace Tests\Integration\Api;

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

        $this->assertSame('File uploaded', $response['message'] ?? null);
        $this->assertNotEmpty($response['data']['id'] ?? null);
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

        $this->assertNotSame('File uploaded', $response['message'] ?? null, 'Without a token no upload may succeed');
    }

    public function testOverwriteReplacesTheContentOfTheTarget(): void
    {
        // Both files carry the same name - that is a precondition, see next test.
        $source = $this->upload('same.txt', "new content\n", $this->token())['data']['id'];
        $target = $this->upload('same.txt', "old content\n", $this->token())['data']['id'];

        [, $response] = $this->postJson('/file/overwrite', array('sourceId' => $source, 'destId' => $target), $this->token());

        $this->assertSame('File overwritten', $response['message'] ?? null);

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
        $this->assertSame('contentfly_file_invalid_type', $body['message'] ?? null);
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

    // ── Helpers ────────────────────────────────────────────────────────────────────────

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
