<?php
namespace Tests\Unit\File;

use Areanet\PIM\Classes\Config;
use Areanet\PIM\Classes\Config\Factory;
use Areanet\PIM\Classes\Exceptions\ContentflyException;
use Areanet\PIM\Classes\File\UploadValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * What may be stored, and under which name (000-000-0038).
 *
 * The endpoint is covered by FileApiTest; this test covers the rules one by one, including the
 * optional whitelist, which the suite's installation does not configure.
 */
class UploadValidatorTest extends TestCase
{
    /** @var list<string> */
    private array $temporary = array();

    protected function setUp(): void
    {
        Factory::getInstance()->setConfig(new Config());
    }

    protected function tearDown(): void
    {
        foreach ($this->temporary as $path) {
            @unlink($path);
        }

        Factory::getInstance()->setConfig(new Config());
    }

    /** @return iterable<string, array{string}> */
    public static function executableNames(): iterable
    {
        yield 'plain php'                 => array('probe-upload.php');
        yield 'upper case'                => array('PROBE.PHP');
        yield 'php in a middle segment'   => array('shell.php.jpg');
        yield 'phtml'                     => array('page.phtml');
        yield 'phar'                      => array('archive.phar');
        yield 'server configuration'      => array('.htaccess');
        yield 'php configuration'         => array('.user.ini');
        yield 'hidden behind a directory' => array('../../data/files/x.php');
    }

    /** @dataProvider executableNames */
    public function testAnExecutableNameIsRejected(string $name): void
    {
        try {
            (new UploadValidator())->validate($this->upload($name, '<?php echo "EXECUTED-" . (6*7);'));
            $this->fail("$name must be rejected");
        } catch (ContentflyException $e) {
            $this->assertSame(415, $e->getCode());
            $this->assertSame('contentfly_file_invalid_type', $e->getMessage());
        }
    }

    public function testWithoutAWhitelistAnOrdinaryFileIsAcceptedAsBefore(): void
    {
        $result = (new UploadValidator())->validate($this->upload('Report 2026.txt', "hello\n", 'text/plain'));

        $this->assertSame(array('name' => 'report-2026.txt', 'type' => 'text/plain'), $result,
            'The client type stays as long as no whitelist applies');
    }

    public function testTheStoredNameIsBuiltByTheFramework(): void
    {
        $validator = new UploadValidator();

        $this->assertSame('x.txt', $validator->validate($this->upload('../../etc/x.txt', 'a'))['name'], 'No directory parts');
        $this->assertSame('file.txt', $validator->validate($this->upload('.txt', 'a'))['name'], 'Never a dotfile');
        $this->assertSame('readme', $validator->validate($this->upload('README', 'a'))['name'], 'A name without extension stays without one');
        $this->assertSame('résumé-draft.pdf', $validator->validate($this->upload('Résumé Draft.PDF', 'a'))['name'], 'Letters are kept, the extension is lower case');
    }

    public function testWithAWhitelistTheTypeComesFromTheContent(): void
    {
        $config = new Config();
        $config->FILE_ALLOWED_TYPES = array('png' => array('image/png'), 'txt' => array('text/plain'));
        Factory::getInstance()->setConfig($config);

        $validator = new UploadValidator();
        $png       = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');

        $this->assertSame('image/png', $validator->validate($this->upload('pixel.png', $png, 'text/html'))['type'],
            'Detected from the content, not the client type');

        foreach (array(array('fake.png', 'not an image'), array('doc.pdf', '%PDF-1.4')) as [$name, $content]) {
            try {
                $validator->validate($this->upload($name, $content, 'image/png'));
                $this->fail("$name must be rejected by the whitelist");
            } catch (ContentflyException $e) {
                $this->assertSame(415, $e->getCode());
            }
        }
    }

    public function testTheWhitelistCannotReopenTheFloor(): void
    {
        $config = new Config();
        $config->FILE_ALLOWED_TYPES = array('php' => array('text/x-php', 'text/plain'));
        Factory::getInstance()->setConfig($config);

        $this->expectException(ContentflyException::class);
        $this->expectExceptionCode(415);

        (new UploadValidator())->validate($this->upload('probe.php', '<?php echo 1;', 'text/plain'));
    }

    public function testAStoredNameIsJudgedByTheSameFloor(): void
    {
        $validator = new UploadValidator();

        $this->assertTrue($validator->isAcceptableName('report.pdf'));
        $this->assertFalse($validator->isAcceptableName('probe-upload.php'));
        $this->assertFalse($validator->isAcceptableName('shell.php.jpg'));
    }

    public function testWithoutAFileTheRequestIsIncomplete(): void
    {
        $this->expectException(ContentflyException::class);
        $this->expectExceptionCode(400);

        (new UploadValidator())->validate(null);
    }

    private function upload(string $name, string $content, string $type = 'application/octet-stream'): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'cf-upload-');
        file_put_contents($path, $content);
        $this->temporary[] = $path;

        return new UploadedFile($path, $name, $type, null, true);
    }

    // ── The size limit (000-000-0042) ──────────────────────────────────────────────────

    public function testWithoutALimitALargeFileIsAccepted(): void
    {
        $result = (new UploadValidator())->validate($this->uploadedFileOfSize('report.txt', 3 * 1024 * 1024));

        $this->assertSame('report.txt', $result['name']);
    }

    public function testAFileOverTheLimitIsRejectedWith413AndTheLimitAsValue(): void
    {
        $this->configure('FILE_MAX_UPLOAD_SIZE', 1024);

        (new UploadValidator())->validate($this->uploadedFileOfSize('exact.txt', 1024));

        try {
            (new UploadValidator())->validate($this->uploadedFileOfSize('large.txt', 1025));
            $this->fail('A file one byte over the limit was accepted');
        } catch (ContentflyException $e) {
            $this->assertSame(413, $e->getCode());
            $this->assertSame('contentfly_file_too_large', $e->getMessage());
            $this->assertSame(1024, $e->getValue());
        }
    }

    public function testTheLimitFromTheEnvironmentIsANumericString(): void
    {
        $this->configure('FILE_MAX_UPLOAD_SIZE', '1024');

        $this->expectExceptionCode(413);
        (new UploadValidator())->validate($this->uploadedFileOfSize('large.txt', 2048));
    }

    public function testAnInvalidLimitIsAConfigurationErrorNotNoLimit(): void
    {
        $this->configure('FILE_MAX_UPLOAD_SIZE', '20MB');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('FILE_MAX_UPLOAD_SIZE must be a positive number of bytes or null');
        (new UploadValidator())->validate($this->uploadedFileOfSize('small.txt', 10));
    }

    public function testPhpsOwnLimitIsReportedAs413NotAsAMissingFile(): void
    {
        $file = new UploadedFile('/nonexistent', 'huge.bin', 'application/octet-stream', UPLOAD_ERR_INI_SIZE, true);

        try {
            (new UploadValidator())->validate($file);
            $this->fail('An upload over upload_max_filesize was not rejected');
        } catch (ContentflyException $e) {
            $this->assertSame(413, $e->getCode());
            $this->assertSame('contentfly_file_too_large', $e->getMessage());
        }
    }

    private function configure(string $key, mixed $value): void
    {
        $config       = new Config();
        $config->$key = $value;
        Factory::getInstance()->setConfig($config);
    }

    private function uploadedFileOfSize(string $name, int $bytes): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'cf-size-');
        file_put_contents($path, str_repeat('a', $bytes));
        $this->temporary[] = $path;

        return new UploadedFile($path, $name, 'text/plain', null, true);
    }

}
