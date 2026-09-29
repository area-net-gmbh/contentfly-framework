<?php
namespace Tests\Integration\Api;

use Areanet\PIM\Entity\Permission;
use Tests\Integration\ExtraServer;
use Tests\Integration\IntegrationTestCase;

/**
 * An uploaded file is delivered as data, never as markup the browser runs (`015-000-0020`).
 *
 * THE FINDING, in two halves. Without `FILE_ALLOWED_TYPES` — and null is the default —
 * `UploadValidator` blocks only what the SERVER would execute: `.php` and its relatives. Markup is
 * not in that list, because markup is not executed on the server; it runs in the browser, in this
 * application's origin. A user with upload rights stored `page.html`, or an SVG carrying a script,
 * and sent the link.
 *
 * And in readfile mode `/file/get` handed back the `Content-Type` **the uploading client had
 * stated**. Whoever uploaded therefore decided how every later reader would interpret the bytes.
 *
 * ── What is measured here, and what is not ────────────────────────────────────────────────
 *
 * This measures `/file/get`: the type now read from the bytes, `Content-Disposition: attachment`
 * for markup, `inline` for an image, and `nosniff` on both.
 *
 * The other delivery path — Apache serving `data/files/` straight from disk — is covered by the
 * `.htaccess` shipped in that directory, and **it cannot be measured here**: the suite runs
 * against PHP's built-in server, which knows nothing about `.htaccess`. `FileDeliveryRulesTest`
 * pins the content of that file instead, and says so plainly rather than pretending otherwise.
 *
 * ITS OWN SERVER, because `APP_FILE_MODE` defaults to `redirect` and the suite's server runs with
 * the default — which is the right default and the wrong one for measuring headers the
 * application sets.
 */
class FileDeliveryApiTest extends IntegrationTestCase
{
    private static ?ExtraServer $server = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (self::$baseUrl !== null) {
            self::$server = ExtraServer::start(array('APP_FILE_MODE' => 'readfile'));
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::$server?->stop();
        self::$server = null;

        parent::tearDownAfterClass();
    }

    /** The case from the task: HTML must not come back as a document. */
    public function testUploadedHtmlIsHandedOverAsADownload(): void
    {
        $headers = $this->deliver('page.html', '<html><body><script>alert(1)</script></body></html>');

        $this->assertStringContainsStringIgnoringCase('content-disposition: attachment', $headers);
        $this->assertStringContainsStringIgnoringCase('x-content-type-options: nosniff', $headers);
    }

    /** An SVG is markup too, and the one most often forgotten — it carries script. */
    public function testUploadedSvgIsHandedOverAsADownload(): void
    {
        $headers = $this->deliver(
            'drawing.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'
        );

        $this->assertStringContainsStringIgnoringCase('content-disposition: attachment', $headers);
        $this->assertStringContainsStringIgnoringCase('x-content-type-options: nosniff', $headers);
    }

    /**
     * The third acceptance criterion, as a test: an image stays inline.
     *
     * Being able to show a stored image in a browser tab is the ordinary use of a file store, and
     * an `<img>` is not a document — nothing in it runs in this origin. A fix that pushed every
     * file into a download would pass the two tests above and break the product.
     */
    public function testAnImageStaysInline(): void
    {
        $headers = $this->deliver('dot.gif', base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));

        $this->assertStringContainsStringIgnoringCase('content-disposition: inline', $headers);
        $this->assertStringContainsStringIgnoringCase('x-content-type-options: nosniff', $headers);
    }

    /**
     * And the type is read from the bytes, not from what the upload claimed.
     *
     * The upload states `text/plain` for every file in these helpers — the same claim the finding
     * is about. What comes back has to describe the content.
     */
    public function testTheContentTypeComesFromTheBytesNotFromTheUpload(): void
    {
        $headers = $this->deliver('claimed.html', '<html><body>hello</body></html>');

        $this->assertStringContainsStringIgnoringCase('content-type: text/html', $headers);
    }

    // ── helpers ────────────────────────────────────────────────────────────────────────────

    /**
     * Uploads a file on the extra server and returns the response headers of `/file/get`.
     */
    private function deliver(string $name, string $content): string
    {
        return $this->onServer(self::$server->url(), function () use ($name, $content) {
            [$token] = $this->createTestUser(array('PIM\\File' => array(
                'readable' => Permission::ALL,
                'writable' => Permission::ALL,
            )));

            $id = $this->upload($name, $content, $token);

            [, , $headers] = $this->get('/file/get/'.$id, $token);

            return $headers;
        });
    }

    /** @return string the id of the stored file */
    private function upload(string $name, string $content, string $token): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cf-delivery-');
        file_put_contents($tmp, $content);

        $ch = curl_init(self::$baseUrl.'/file/upload');
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            // text/plain for every file on purpose: that claim is what the finding is about.
            CURLOPT_POSTFIELDS     => array('file' => new \CURLFile($tmp, 'text/plain', $name)),
            CURLOPT_HTTPHEADER     => array('appcms-token: '.$token),
        ));
        $response = curl_exec($ch);
        unlink($tmp);

        $result = json_decode((string) $response, true) ?: array();
        $id     = $result['data']['id'] ?? null;

        if (!is_string($id) || $id === '') {
            $this->fail('The upload did not succeed: '.(string) $response);
        }

        $this->deleteAfterTest('pim_file', $id);

        return $id;
    }
}
