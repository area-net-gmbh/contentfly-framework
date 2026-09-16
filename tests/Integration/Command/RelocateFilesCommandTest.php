<?php
namespace Tests\Integration\Command;

use Areanet\PIM\Command\RelocateFilesCommand;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\TestCase;
use Tests\Integration\IntegrationTestCase;

/**
 * The move from the Contentfly 1.x file layout (000-000-0041).
 *
 * Against a table of its own with the 1.x column `path` — the suite's `pim_file` is already on
 * Contentfly 2 and has none — and a directory of its own. The same pattern as ReencryptCommandTest.
 */
class RelocateFilesCommandTest extends TestCase
{
    private const TABLE = 'probe_relocate_file';

    /** @var \Doctrine\DBAL\Connection */
    private $db;

    private string $files = '';

    protected function setUp(): void
    {
        $credentials = IntegrationTestCase::dbCredentials();

        $this->db = DriverManager::getConnection(array(
            'driver'   => 'pdo_mysql',
            'host'     => $credentials['host'],
            'port'     => (int) $credentials['port'],
            'dbname'   => $credentials['name'],
            'user'     => $credentials['user'],
            'password' => $credentials['pass'],
        ));

        $this->db->executeStatement('DROP TABLE IF EXISTS ' . self::TABLE);
        $this->db->executeStatement('CREATE TABLE ' . self::TABLE . ' (id VARCHAR(255) PRIMARY KEY, path VARCHAR(255) NULL)');

        $this->files = sys_get_temp_dir() . '/contentfly-relocate-' . bin2hex(random_bytes(6));
        mkdir($this->files, 0777, true);
    }

    protected function tearDown(): void
    {
        if ($this->db) {
            $this->db->executeStatement('DROP TABLE IF EXISTS ' . self::TABLE);
        }

        $this->removeTree($this->files);
    }

    public function testFilesMoveWithTheirThumbnailsAndASecondRunFindsThemInPlace(): void
    {
        $this->folder('2026/06/aaa', array('photo.jpg' => 'original', 'thumb-photo.jpg' => 'thumbnail'));
        $this->folder('2026/06/bbb', array('doc.pdf' => 'pdf'));
        $this->folder('ccc', array('new.txt' => 'stored by Contentfly 2'));
        $this->row('ccc', '');
        $this->row('aaa', '2026/06/');
        $this->row('bbb', '2026/06');

        $dry = $this->relocate(true);
        $this->assertSame(2, $dry['moved'], 'The dry run counts what it would move');
        $this->assertDirectoryExists($this->files . '/2026/06/aaa', 'but moves nothing');
        $this->assertDirectoryDoesNotExist($this->files . '/aaa');

        $real = $this->relocate(false);
        $this->assertSame(array(2, 1, 0), array($real['moved'], $real['without_path'], $real['missing']));
        $this->assertSame('original', file_get_contents($this->files . '/aaa/photo.jpg'));
        $this->assertSame('thumbnail', file_get_contents($this->files . '/aaa/thumb-photo.jpg'), 'Thumbnails move with the folder');
        $this->assertSame('pdf', file_get_contents($this->files . '/bbb/doc.pdf'), 'A path without trailing slash works too');
        $this->assertDirectoryDoesNotExist($this->files . '/2026', 'The emptied date folders are removed');
        $this->assertSame('stored by Contentfly 2', file_get_contents($this->files . '/ccc/new.txt'));

        $again = $this->relocate(false);
        $this->assertSame(array(0, 2), array($again['moved'], $again['in_place']), 'A second run changes nothing');
    }

    public function testMissingFoldersConflictsAndForeignPathsAreReportedAndLeftAlone(): void
    {
        $this->row('gone', '2025/01/');

        $this->folder('2025/02/twice', array('a.txt' => 'old'));
        $this->folder('twice', array('a.txt' => 'new'));
        $this->row('twice', '2025/02/');

        mkdir($this->files . '/../outside-' . basename($this->files), 0777, true);
        $this->row('escape', '../outside-' . basename($this->files) . '/');

        $result = $this->relocate(false);

        $this->assertSame(array(1, 1, 1, 0), array($result['missing'], $result['conflicts'], $result['rejected'], $result['moved']));
        $this->assertSame('old', file_get_contents($this->files . '/2025/02/twice/a.txt'), 'A conflict is not resolved by overwriting');
        $this->assertSame('new', file_get_contents($this->files . '/twice/a.txt'));
        $this->assertCount(3, $result['problems']);

        rmdir($this->files . '/../outside-' . basename($this->files));
    }

    public function testWithoutThePathColumnThereIsNothingToDo(): void
    {
        $this->db->executeStatement('ALTER TABLE ' . self::TABLE . ' DROP COLUMN path');

        $this->assertTrue($this->relocate(false)['without_column']);
    }

    private function relocate(bool $dryRun): array
    {
        return (new RelocateFilesCommand())->relocate($this->db, $this->files, $dryRun, self::TABLE);
    }

    private function row(string $id, ?string $path): void
    {
        $this->db->insert(self::TABLE, array('id' => $id, 'path' => $path));
    }

    /** @param array<string,string> $contents */
    private function folder(string $directory, array $contents): void
    {
        mkdir($this->files . '/' . $directory, 0777, true);
        foreach ($contents as $name => $content) {
            file_put_contents($this->files . '/' . $directory . '/' . $name, $content);
        }
    }

    private function removeTree(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($directory);
    }
}
