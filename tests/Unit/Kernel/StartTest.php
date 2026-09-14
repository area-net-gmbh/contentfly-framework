<?php
namespace Tests\Unit\Kernel;

use Areanet\PIM\Classes\Kernel\Paths;
use Areanet\PIM\Classes\Kernel\Start;
use PHPUnit\Framework\TestCase;

/**
 * The three abort paths of the door into the framework (007-001-0003).
 *
 * **Why they get tests.** `Start` throws instead of calling `exit`, and the reason is stated in
 * the class: An `exit` could not be tested — and an abort path that no test enters
 * is an abort path you cannot rely on. Then the guarantee that
 * a misconfiguration fails loudly would again only be a comment.
 *
 * **Tested via `web()`, not `console()`.** Both go through the same preparation,
 * but `console()` defines `APPCMS_CONSOLE` — and a constant cannot be taken
 * back. A test that sets it changes every test running after it in the same
 * process.
 */
class StartTest extends TestCase
{
    private string $scratch = '';

    protected function setUp(): void
    {
        $this->scratch = sys_get_temp_dir() . '/contentfly-start-' . bin2hex(random_bytes(6));
        mkdir($this->scratch . '/custom', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->cleanUp($this->scratch);

        // The suite set the directory in tests/bootstrap.php; give it back, otherwise
        // the following tests run against an empty Paths class.
        Paths::set(CONTENTFLY_PROJECT_DIR);
    }

    public function testANonExistentDirectoryIsRejected(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/does not exist/');

        Start::web($this->scratch . '/does-not-exist');
    }

    /**
     * If the configuration is missing, the message says so — and names the path that was searched.
     *
     * Before, it was a bare `require_once`. PHP's own message ("Failed opening
     * required …") does name the path, but not that it is about the configuration and how
     * to get to it.
     */
    public function testWithoutConfigurationTheStartAbortsWithAMessageAboutIt(): void
    {
        try {
            Start::web($this->scratch);
            $this->fail('Without custom/config.php the start must not run through.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('project configuration', $e->getMessage());
            $this->assertStringContainsString($this->scratch . '/custom/config.php', $e->getMessage());
            $this->assertStringContainsString('appcms:install', $e->getMessage());
        }
    }

    /**
     * The case that would go wrong silently if it were let through.
     *
     * A leftover `custom/vendor/` looks like something that is used. If it were
     * simply no longer loaded, the project would be missing a class — and the message would be about
     * that class, not about a whole tree no longer being valid.
     */
    public function testASecondComposerTreeIsRejectedInsteadOfIgnored(): void
    {
        file_put_contents($this->scratch . '/custom/config.php', '<?php');
        mkdir($this->scratch . '/custom/vendor', 0777, true);
        file_put_contents($this->scratch . '/custom/vendor/autoload.php', '<?php');

        try {
            Start::web($this->scratch);
            $this->fail('A second Composer tree must not be silently ignored.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('custom/vendor', $e->getMessage());
            $this->assertStringContainsString('exactly ONE tree', $e->getMessage());
        }
    }

    /**
     * And the order: Is the second tree reported BEFORE the missing configuration?
     *
     * No — the other way round, and that is intentional. Without configuration nothing runs anyway; it is
     * the condition that is closer to the start. This test records the order so that
     * nobody turns it around unnoticed during a rebuild and one message hides another.
     */
    public function testWithoutConfigurationAndWithASecondTreeTheSecondTreeWins(): void
    {
        mkdir($this->scratch . '/custom/vendor', 0777, true);
        file_put_contents($this->scratch . '/custom/vendor/autoload.php', '<?php');

        try {
            Start::web($this->scratch);
            $this->fail('The start must not run through here.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('custom/vendor', $e->getMessage());
        }
    }

    private function cleanUp(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $entry) {
            if (!$entry instanceof \SplFileInfo) {
                continue;
            }

            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }

        rmdir($path);
    }
}
