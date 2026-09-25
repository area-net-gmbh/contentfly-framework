<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * THE IMAGE PROCESSOR, FED OVER HTTP (000-000-0068).
 *
 * `Classes/File/Processing/Image` turns every uploaded JPEG, PNG and GIF into thumbnails — it is
 * the place where a stranger's file reaches an image decoder. The first coverage measurement found
 * it at 4 %: no test uploaded an image.
 *
 * Two halves. The first uploads real images and checks the thumbnails the installation's settings
 * ask for (`pim_list`: 200 × 200, cut; `pim_small`: 320 wide) — size and format, read back from
 * disk. The second uploads files that only pretend to be images and checks that each one fails
 * with a 4xx and leaves nothing behind: no record, no directory.
 *
 * WHAT THE SECOND HALF FOUND. Before 000-000-0068 a broken JPEG ended in 500 with the record and
 * the file already stored, and a PNG header claiming 50,000 × 50,000 pixels would have had GD
 * allocate ten gigabytes. The tests of that half were red before the fix.
 *
 * The images are drawn here with GD, not checked in: a fixture nobody can regenerate is a fixture
 * nobody dares to change.
 */
class ImageProcessingApiTest extends IntegrationTestCase
{
    /** @var list<string> Ids whose log rows tearDown() removes. */
    private array $logged = array();

    protected function setUp(): void
    {
        parent::setUp();

        if (!function_exists('imagecreatetruecolor')) {
            $this->fail('The tests draw their images with GD, and the framework needs it for thumbnails.');
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->logged as $id) {
            $this->pdo()->prepare('DELETE FROM pim_log WHERE model_id = :id')->execute(array('id' => $id));
        }
        $this->logged = array();

        parent::tearDown();
    }

    // ── Real images ────────────────────────────────────────────────────────────────────────

    public function testJpegGetsBothThumbnails(): void
    {
        $id = $this->uploadImage('photo.jpg', 'image/jpeg', $this->draw('jpeg', 800, 600));

        $this->assertThumbnail($id, 'pim_list-photo.jpg', 200, 200, IMAGETYPE_JPEG);
        $this->assertThumbnail($id, 'pim_small-photo.jpg', 320, 240, IMAGETYPE_JPEG);
    }

    public function testPngKeepsItsFormatAndTheAspectOfAPortrait(): void
    {
        $id = $this->uploadImage('portrait.png', 'image/png', $this->draw('png', 600, 800));

        $this->assertThumbnail($id, 'pim_list-portrait.png', 200, 200, IMAGETYPE_PNG);
        $this->assertThumbnail($id, 'pim_small-portrait.png', 320, 426, IMAGETYPE_PNG);
    }

    public function testGifGetsThumbnails(): void
    {
        $id = $this->uploadImage('anim.gif', 'image/gif', $this->draw('gif', 400, 300));

        $this->assertThumbnail($id, 'pim_small-anim.gif', 320, 240, IMAGETYPE_GIF);
    }

    public function testASmallImageIsNotEnlarged(): void
    {
        $id = $this->uploadImage('tiny.png', 'image/png', $this->draw('png', 100, 50));

        $this->assertThumbnail($id, 'pim_small-tiny.png', 100, 50, IMAGETYPE_PNG);
    }

    public function testTheUploadRecordsTheDimensions(): void
    {
        $id = $this->uploadImage('measured.jpg', 'image/jpeg', $this->draw('jpeg', 640, 480));

        $row = $this->pdo()->query('SELECT width, height FROM pim_file WHERE id = '.$this->pdo()->quote($id))->fetch(\PDO::FETCH_ASSOC);

        $this->assertSame(array('width' => 640, 'height' => 480), array_map('intval', $row));
    }

    public function testARequestedVariantIsGeneratedAndDelivered(): void
    {
        // /file/get renders a missing variant on request and redirects to it on disk.
        $id = $this->uploadImage('wide.jpg', 'image/jpeg', $this->draw('jpeg', 900, 600));

        [$status, , $headers] = $this->get("/file/get/$id/pim_small/2x/wide.jpg", $this->token());

        $this->assertSame(301, $status);
        $this->assertStringEndsWith("/data/files/$id/2x@pim_small-wide.jpg", (string) $this->header($headers, 'Location'));
        $this->assertThumbnail($id, '2x@pim_small-wide.jpg', 213, 142, IMAGETYPE_JPEG);
    }

    public function testAResponsiveSettingWritesBothVariantsOnUpload(): void
    {
        $this->thumbnailSetting('t_resp', array('width' => 300, 'isResponsive' => 1));

        $id = $this->uploadImage('resp.jpg', 'image/jpeg', $this->draw('jpeg', 900, 600));

        // Two thirds and one third of the thumbnail, rounded. Until 000-000-0068 the fraction was
        // cut off (199 instead of 200) — with a deprecation notice per call in the server log.
        $this->assertThumbnail($id, 't_resp-resp.jpg', 300, 200, IMAGETYPE_JPEG);
        $this->assertThumbnail($id, '2x@t_resp-resp.jpg', 200, 133, IMAGETYPE_JPEG);
        $this->assertThumbnail($id, '1x@t_resp-resp.jpg', 100, 67, IMAGETYPE_JPEG);
    }

    public function testForceJpegTurnsAPngThumbnailIntoAJpeg(): void
    {
        $this->thumbnailSetting('t_jpeg', array('width' => 100, 'forceJpeg' => 1, 'backgroundColor' => '#ffffff'));

        $id = $this->uploadImage('logo.png', 'image/png', $this->draw('png', 400, 200));

        $this->assertThumbnail($id, 't_jpeg-logo.jpg', 100, 50, IMAGETYPE_JPEG);
    }

    // ── Files that only pretend ────────────────────────────────────────────────────────────

    public function testTextSentAsJpegIsRejected(): void
    {
        $this->assertRejectedWithoutLeftovers('fake.jpg', 'image/jpeg', "not an image at all\n", 415);
    }

    public function testJpegHeaderFollowedByGarbageIsRejected(): void
    {
        // Red before 000-000-0068: 500, with record and file stored.
        $this->assertRejectedWithoutLeftovers('broken.jpg', 'image/jpeg', "\xFF\xD8\xFF\xE0".str_repeat('garbage', 50), 415);
    }

    public function testPngSentAsJpegIsRejected(): void
    {
        // The type picks the decoder. A PNG declared as JPEG went to imagecreatefromjpeg().
        $this->assertRejectedWithoutLeftovers('disguised.jpg', 'image/jpeg', $this->draw('png', 50, 50), 415);
    }

    public function testAnImageClaimingTooManyPixelsIsRejectedBeforeDecoding(): void
    {
        // A valid PNG header that claims 50,000 × 50,000 pixels: a few dozen bytes on disk, ten
        // gigabytes for GD. FILE_IMAGE_MAX_PIXELS stops it from the header alone.
        $header = pack('NN', 50000, 50000)."\x08\x02\x00\x00\x00";
        $png    = "\x89PNG\r\n\x1a\n"
                .pack('N', 13).'IHDR'.$header.pack('N', crc32('IHDR'.$header))
                .pack('N', 0).'IEND'.pack('N', crc32('IEND'));

        $this->assertRejectedWithoutLeftovers('bomb.png', 'image/png', $png, 413);
    }

    public function testAValidHeaderWithABrokenBodyFailsInTheProcessor(): void
    {
        // The header passes every check in UploadValidator; only decoding finds the damage. That
        // is the path through Image::execute() and FileController::discardUpload().
        $png   = $this->draw('png', 60, 40);
        $start = strpos($png, 'IDAT') + 4;
        $png   = substr($png, 0, $start).str_repeat("\x00", 16).substr($png, $start + 16);

        $this->assertNotFalse(getimagesize('data://image/png;base64,'.base64_encode($png)),
            'Precondition: the header is still readable');

        $this->assertRejectedWithoutLeftovers('corrupt-body.png', 'image/png', $png, 415);
    }

    public function testAFileWithoutImageProcessingIsNotTouchedByTheImageChecks(): void
    {
        [$status, $body] = $this->upload('notes.txt', 'text/plain', "plain text\n");

        $this->assertSame(200, $status, 'A .txt stays storable without configuration');
        $this->register($body['data']['id']);
    }

    // ── helpers ────────────────────────────────────────────────────────────────────────────

    /** An image in the given GD format, drawn with a gradient so that the encoder has content. */
    private function draw(string $format, int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        for ($x = 0; $x < $width; $x += 10) {
            imagefilledrectangle($image, $x, 0, $x + 9, $height, imagecolorallocate($image, $x % 256, 80, 160));
        }

        ob_start();
        match ($format) {
            'jpeg' => imagejpeg($image),
            'png'  => imagepng($image),
            'gif'  => imagegif($image),
        };

        return (string) ob_get_clean();
    }

    /** @return array{0:int,1:array} */
    private function upload(string $name, string $type, string $content): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cf-image-');
        file_put_contents($tmp, $content);

        $ch = curl_init(self::$baseUrl.'/file/upload');
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => array('file' => new \CURLFile($tmp, $type, $name)),
            CURLOPT_HTTPHEADER     => array('appcms-token: '.$this->token()),
        ));
        $raw    = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        unlink($tmp);

        return array($status, json_decode($raw, true) ?: array());
    }

    private function uploadImage(string $name, string $type, string $content): string
    {
        [$status, $body] = $this->upload($name, $type, $content);
        $this->assertSame(200, $status, 'Precondition: the upload succeeds — '.json_encode($body['errors'] ?? null));

        $id = $body['data']['id'];
        $this->register($id);

        return $id;
    }

    /** An additional thumbnail setting; the processor reads the settings on every request. */
    private function thumbnailSetting(string $alias, array $fields): void
    {
        $id = 'tts-'.bin2hex(random_bytes(6));

        $this->pdo()->prepare(
            'INSERT INTO pim_thumbnail_setting (id, alias, width, height, percent, doCut, forceJpeg, isResponsive,
                                                backgroundColor, created, modified, isIntern)
             VALUES (:id, :alias, :width, :height, :percent, :doCut, :forceJpeg, :isResponsive, :bg, NOW(), NOW(), 0)'
        )->execute(array(
            'id'           => $id,
            'alias'        => $alias,
            'width'        => $fields['width'] ?? null,
            'height'       => $fields['height'] ?? null,
            'percent'      => $fields['percent'] ?? null,
            'doCut'        => $fields['doCut'] ?? 0,
            'forceJpeg'    => $fields['forceJpeg'] ?? 0,
            'isResponsive' => $fields['isResponsive'] ?? 0,
            'bg'           => $fields['backgroundColor'] ?? null,
        ));

        $this->deleteAfterTest('pim_thumbnail_setting', $id);
    }

    private function register(string $id): void
    {
        $this->deleteAfterTest('pim_file', $id);
        $this->deleteDirectoryAfterTest(self::dataDir().'/files/'.$id);
        $this->logged[] = $id;
    }

    private function assertThumbnail(string $id, string $file, int $width, int $height, int $type): void
    {
        $path = self::dataDir().'/files/'.$id.'/'.$file;
        $this->assertFileExists($path, "The thumbnail $file is written next to the original");

        $info = getimagesize($path);
        $this->assertSame(array($width, $height, $type), array($info[0], $info[1], $info[2]), $file);
    }

    private function assertRejectedWithoutLeftovers(string $name, string $type, string $content, int $expected): void
    {
        $files       = (int) $this->pdo()->query('SELECT COUNT(*) FROM pim_file')->fetchColumn();
        $directories = count(glob(self::dataDir().'/files/*', GLOB_ONLYDIR));

        [$status, $body] = $this->upload($name, $type, $content);

        if (isset($body['data']['id'])) {
            $this->register($body['data']['id']);
        }

        $this->assertSame($expected, $status, json_encode($body['errors'] ?? $body));
        $this->assertErrorEnvelope($body);
        $this->assertSame($files, (int) $this->pdo()->query('SELECT COUNT(*) FROM pim_file')->fetchColumn(),
            'No record of the rejected upload stays');
        $this->assertCount($directories, glob(self::dataDir().'/files/*', GLOB_ONLYDIR),
            'No directory of the rejected upload stays');
    }
}
