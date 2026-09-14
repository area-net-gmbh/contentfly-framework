<?php
namespace Tests\Unit\Kernel;

use Areanet\PIM\Classes\Kernel\Paths;
use PHPUnit\Framework\TestCase;

/**
 * The guard against the return of `ROOT_DIR` (007-001-0002).
 *
 * ## The occasion
 * `lib/contentfly/bootstrap.php` computed the project directory from the location of the framework:
 * `const ROOT_DIR = __DIR__ . '/../..'`. That is correct as long as the framework sits under `lib/` in
 * the project — and becomes wrong as soon as it sits as a package under `vendor/`.
 *
 * **Wrong in the silent way:** The computed path then does not exist, but it IS a valid path.
 * `file_exists()` returns `false`, and the resulting message is about a missing file
 * instead of a wrong root. Whoever includes the package for the first time looks in the
 * wrong place.
 *
 * ## Why the second part is needed
 * The class alone is not enough. It only helps as long as nobody writes another
 * `__DIR__` jump out of `lib/contentfly/` next to it — and that is the most obvious way
 * to get "quickly" to a file in the project. The second part of this test searches for it.
 */
class PathsTest extends TestCase
{
    /**
     * A jump upwards that leaves the package is allowed — but only for the package.
     *
     * `Paths::package()` does exactly that with `dirname(__DIR__, 4)`, and that is correct: A file
     * may find its own package. It just must not conclude from that where the PROJECT is.
     * The entry is therefore here and not as an exception in the search pattern.
     *
     * @var array<string,string> file → why the jump is correct there
     */
    private const ALLOWED = array(
        'Classes/Kernel/Paths.php' =>
            'Paths::package() derives the framework directory from its own location. That '
            .'is the one place where that is correct — and the reason why it is a separate '
            .'method instead of an expression in twenty places.',
    );

    protected function tearDown(): void
    {
        // The suite set the value in tests/bootstrap.php; whoever removes it here gives
        // it back, otherwise the following tests run against an empty class.
        Paths::set(CONTENTFLY_PROJECT_DIR);
    }

    public function testEveryAccessThrowsWithoutASetDirectory(): void
    {
        Paths::reset();

        $this->assertFalse(Paths::isSet());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/project directory has not been set/');

        Paths::project();
    }

    /**
     * The difference this task is about.
     *
     * A directory that does not exist is rejected when SETTING it — not only on the first
     * access to a file below it. Otherwise the message would again be about the file.
     */
    public function testANonExistentDirectoryIsRejectedWhenSet(): void
    {
        Paths::reset();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/does not exist/');

        Paths::set(CONTENTFLY_PROJECT_DIR . '/this-directory-does-not-exist');
    }

    public function testThePathsDependOnThePassedDirectory(): void
    {
        Paths::reset();
        Paths::set(CONTENTFLY_PROJECT_DIR);

        $this->assertSame(realpath(CONTENTFLY_PROJECT_DIR), Paths::project());
        $this->assertSame(Paths::project() . '/custom',  Paths::custom());
        $this->assertSame(Paths::project() . '/data',    Paths::data());
        $this->assertSame(Paths::project() . '/plugins', Paths::plugins());
    }

    /**
     * The package directory is independent of the project — that has to be checked too.
     *
     * If both were the same, nothing would have changed from the old state; it would just look
     * different. Today they coincide because the framework still sits in the project; the guarantee
     * is that `package()` manages WITHOUT a set project directory.
     */
    public function testThePackageDirectoryNeedsNoProject(): void
    {
        Paths::reset();

        $this->assertDirectoryExists(Paths::package());
        $this->assertFileExists(Paths::package() . '/bootstrap.php');
    }

    /**
     * No `__DIR__` jump out of `lib/contentfly/` — except the named ones.
     *
     * Both are searched for: `__DIR__ . '/..'` in any spelling and `dirname(__DIR__)`
     * with or without depth. Both leave the file's directory, and both were the way
     * `ROOT_DIR` came about.
     */
    public function testNoJumpOutOfTheFramework(): void
    {
        $suspicious = array();

        foreach ($this->frameworkFiles() as $relative => $path) {
            if (isset(self::ALLOWED[$relative])) {
                continue;
            }

            foreach (explode("\n", (string) file_get_contents($path)) as $number => $line) {
                if ($this->isComment($line)) {
                    continue;
                }

                if (preg_match('/__DIR__\s*\.\s*[\'"]\s*\/\.\./', $line) === 1
                    || preg_match('/dirname\s*\(\s*__DIR__/', $line) === 1) {
                    $suspicious[] = sprintf('%s:%d — %s', $relative, $number + 1, trim($line));
                }
            }
        }

        $this->assertSame(array(), $suspicious, implode("\n", array_merge(
            array(
                'These places in the framework compute a path outside their own',
                'directory. That is exactly how ROOT_DIR came about, and exactly that goes wrong',
                'as soon as the framework sits as a package under vendor/ (007-001-0002):',
                '',
            ),
            $suspicious,
            array(
                '',
                'Whoever needs the project directory uses Paths::project() (custom(), data(),',
                'plugins()); whoever needs the package, Paths::package().',
            )
        )));
    }

    /**
     * And the exception list watches itself — as with the gates from `006-005`.
     */
    public function testEveryExceptionIsStillNeeded(): void
    {
        $files = $this->frameworkFiles();
        $dead  = array();

        foreach (array_keys(self::ALLOWED) as $relative) {
            if (!isset($files[$relative])) {
                $dead[] = sprintf('%s — the file no longer exists.', $relative);
                continue;
            }

            $content = (string) file_get_contents($files[$relative]);

            if (preg_match('/__DIR__\s*\.\s*[\'"]\s*\/\.\./', $content) !== 1
                && preg_match('/dirname\s*\(\s*__DIR__/', $content) !== 1) {
                $dead[] = sprintf('%s — there is no jump there any more.', $relative);
            }
        }

        $this->assertSame(array(), $dead, implode("\n", array_merge(
            array('These exceptions no longer match anything and should be removed from ALLOWED:', ''),
            $dead
        )));
    }

    /**
     * @return array<string,string> path relative to lib/contentfly/ → full path
     */
    private function frameworkFiles(): array
    {
        $root  = Paths::package();
        $files = array();

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        foreach ($iterator as $entry) {
            if (!$entry instanceof \SplFileInfo || $entry->getExtension() !== 'php') {
                continue;
            }

            $files[substr($entry->getPathname(), strlen($root) + 1)] = $entry->getPathname();
        }

        $this->assertNotEmpty($files, 'There is no PHP file under lib/contentfly/ — then this test checks nothing.');

        return $files;
    }

    private function isComment(string $line): bool
    {
        $trimmed = ltrim($line);

        return $trimmed === ''
            || str_starts_with($trimmed, '*')
            || str_starts_with($trimmed, '//')
            || str_starts_with($trimmed, '/*');
    }
}
