<?php
namespace Tests\Unit\Kernel;

use PHPUnit\Framework\TestCase;

/**
 * The guard over the replacement of Silex, Pimple and knplabs (`009-001-0005`).
 *
 * Story `009-001` detached the project's own code from the types of these three packages so
 * that the kernel cut in `009-002` would be as small as possible. Without a check that would be a
 * snapshot. The same idea as `AutoloaderOverlapTest` from `006-004-0003`:
 * **A condition nobody checks is not a guarantee** — and that is exactly what the old
 * state slipped past for years.
 *
 * The test runs without a database and without HTTP: it reads files.
 *
 * **The list below is not an exception list meant to grow, but the work list for
 * `009-002`.** It shrinks to zero there. That is why this test also reports the reverse
 * case: If an entry becomes superfluous, it belongs out of the list. The same rule as for the
 * audit and the deprecation gate from `006-005`.
 */
class NoSilexTypesTest extends TestCase
{
    /**
     * The namespaces that are to disappear from the project's own code.
     *
     * All three belong to packages that `009-002` removes: `silex/silex` and the
     * `pimple/pimple` contained in it require the Symfony components at `^4.0`,
     * `knplabs/console-service-provider` caps `symfony/console` at `^4`.
     */
    private const NAMESPACES = array('Silex\\', 'Pimple\\', 'Knp\\');

    /**
     * Where the names may still appear — with the reason why.
     *
     * Every entry is a task for `009-002`, not a permanent exception.
     */
    /**
     * Where the names may still appear — **nowhere** (009-002-0006).
     *
     * Until the kernel cut there were five entries here, and each was a task for
     * `009-002`: the bootstrap with its four `register()` calls, the two seams
     * `Kernel\Application` and `Kernel\Command`, `InstallCommand::bootDoctrine()` and the
     * Pimple freeze test. All five are gone — `silex/silex`, `pimple/pimple` and
     * `knplabs/console-service-provider` are no longer in the tree.
     *
     * The list stays as an empty array, not as deleted machinery: The second test
     * below checks that no exception is left unused, and the first that no new
     * usage appears. Together they hold the state this epic established.
     *
     * @var array<string,string>
     */
    private const ALLOWED = array();

    /** Directories that belong to the project's own code. */
    private const TREES = array('lib/contentfly', 'custom', 'bin', 'tests');

    /**
     * This file itself.
     *
     * It has to name the searched-for names to be able to search for them — on the first run
     * it promptly reported itself. Deliberately **not** in `ALLOWED`: That holds the
     * work list for `009-002`, and the guard is not part of it. It stays once Silex is
     * gone, and makes sure it stays that way.
     */
    private const THIS_FILE = 'tests/Unit/Kernel/NoSilexTypesTest.php';

    public function testNoFileOutsideTheListNamesSilexPimpleOrKnp(): void
    {
        $hits = array();

        foreach ($this->phpFiles() as $path => $content) {
            if ($path === self::THIS_FILE || isset(self::ALLOWED[$path])) {
                continue;
            }

            foreach ($this->occurrences($content) as $line) {
                $hits[] = $path.': '.trim($line);
            }
        }

        $this->assertSame(array(), $hits,
            "These places name one of the replaced namespaces. Either swap them for the "
            ."project's own interface (see Areanet\\PIM\\Classes\\Kernel) or, if it "
            ."really is the kernel, add them to ALLOWED with a reason.");
    }

    public function testEveryAllowedEntryIsStillNeeded(): void
    {
        // The reverse direction. An exception that no longer covers anything looks like an
        // open construction site and is not one — it obscures the progress of 009-002.
        $superfluous = array();

        foreach (self::ALLOWED as $path => $reason) {
            $full = CONTENTFLY_PROJECT_DIR.'/'.$path;

            if (!is_file($full)) {
                $superfluous[] = $path.' (file no longer exists)';
                continue;
            }

            if ($this->occurrences(file_get_contents($full)) === array()) {
                $superfluous[] = $path.' (no longer names any of the namespaces)';
            }
        }

        $this->assertSame(array(), $superfluous,
            'These entries in ALLOWED are no longer needed and should be removed.');
    }

    /**
     * Occurrences in a file — comment lines do not count.
     *
     * The replacement is justified in comments in many places, and exactly there the old
     * name *has to* appear: A justification that is not allowed to name the replaced name
     * explains nothing. Code is what is meant.
     *
     * @return array<int,string>
     */
    private function occurrences(string $content): array
    {
        $hits = array();

        foreach (explode("\n", $content) as $line) {
            $blank = ltrim($line);

            if ($blank === '' || str_starts_with($blank, '*') || str_starts_with($blank, '//')
                || str_starts_with($blank, '/*') || str_starts_with($blank, '#')) {
                continue;
            }

            foreach (self::NAMESPACES as $namespace) {
                if (str_contains($line, $namespace)) {
                    $hits[] = $line;
                    break;
                }
            }
        }

        return $hits;
    }

    /** @return array<string,string> path relative to the project => content */
    private function phpFiles(): array
    {
        $files = array();

        foreach (self::TREES as $tree) {
            $root = CONTENTFLY_PROJECT_DIR.'/'.$tree;

            if (!is_dir($root)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $path = substr($file->getPathname(), strlen(CONTENTFLY_PROJECT_DIR) + 1);
                $files[$path] = file_get_contents($file->getPathname());
            }
        }

        return $files;
    }
}
