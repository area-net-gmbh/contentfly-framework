<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * **This test has been turned around (007-001-0003) — it has not been deleted.**
 *
 * ## What it guarded until then
 * The decision from `006-004-0001`: two Composer trees, root first, `custom/` supplementary;
 * for a shared PSR-4 prefix the root wins. The condition for that was that the trees do not
 * overlap — and it was violated for years without anyone noticing: `psr/log` was loaded in
 * 1.1.3 and 3.0.2 at the same time in the process, plus `symfony/polyfill-ctype` and
 * `-mbstring` in incompatible versions and a hand-copied `PHPMailer\PHPMailer\` that was in
 * no `installed.json`. It only surfaced when `006-001-0002` counted out both trees.
 *
 * ## Why the condition no longer applies
 * With `007-001` the framework becomes a library package. A project then has **one** tree,
 * in which Contentfly sits as a dependency — there are no longer two trees between which a
 * precedence would have to be settled. **The replacement is stronger than the guarantee it
 * supersedes:** The old rule kept an overlap *away* as long as this test checked the condition.
 * Composer *refuses* incompatible constraints while resolving. The `psr/log` case can no
 * longer arise.
 *
 * ## What it guards now
 * That it stays that way. A test that guarded a condition that has gone away guards afterwards
 * that it **stays** gone — otherwise the second tree returns without anyone making the
 * decision of `007-001-0001` again.
 *
 * Deleting it would have been the wrong thing: The case it uncovered is the most expensive
 * single finding from epic `006`, and the memory of it belongs in the place where someone
 * would undo what fixed it.
 */
class AutoloaderOverlapTest extends TestCase
{
    private const ROOT = __DIR__ . '/../..';

    public function testTheRootTreeExistsAndIsNotEmpty(): void
    {
        $vendor = self::ROOT . '/vendor';

        self::assertDirectoryExists(
            $vendor,
            'Nothing runs without vendor/ — "composer install" is missing (an_project/docs/runbook.md, step 1).'
        );

        $psr4 = $vendor . '/composer/autoload_psr4.php';

        self::assertFileExists($psr4, 'The autoloader of the root tree is missing.');

        /** @var array<string,mixed> $map */
        $map = require $psr4;

        self::assertNotEmpty(
            $map,
            'The PSR-4 map of the root tree is empty. Then the tests below check nothing '
            .'and would be green for the wrong reason.'
        );

        self::assertArrayHasKey(
            'Areanet\\PIM\\',
            $map,
            'The framework is not registered in the project\'s autoloader — that is exactly '
            .'the state 007-001 establishes: Contentfly sits IN the project\'s tree.'
        );
    }

    /**
     * No second tree under `custom/`.
     *
     * What is checked is the directory on disk, not the manifest: A
     * `custom/composer.json` without `require` does no harm, an installed
     * `custom/vendor/autoload.php` does — it would look as if it were used.
     */
    public function testThereIsNoSecondComposerTree(): void
    {
        self::assertFileDoesNotExist(
            self::ROOT . '/custom/vendor/autoload.php',
            "There is a Composer tree under custom/vendor/ again.\n\n"
            ."Since 007-001 a project has exactly one: the framework is a dependency\n"
            ."inside it, not a second tree next to it. Whatever lies here is not loaded — and silently\n"
            ."leaving around what is not loaded is the mistake this rule is\n"
            ."built against.\n\n"
            ."The decision is in an_project/docs/architecture.md (Key decisions,\n"
            ."2026-09-11); Start::assertNoSecondVendorTree() rejects the same state at runtime."
        );
    }

    /**
     * What may load an autoloader, and why.
     *
     * @var array<string,string> file → reason
     */
    private const ALLOWED = array(
        'Classes/Plugin.php' =>
            'A plugin brings its own vendor/ tree, and initComposer() loads it. '
            .'That is NOT the framework\'s autoloader but that of a part of the project '
            .'— the framework loads it on its behalf. This test noticed it while being '
            .'turned around (007-001-0003): The decision from 007-001-0001 spoke of "one tree '
            .'per project" and had not considered plugins. Whether it stays that way is decided by '
            .'007-001-0004 together with the question of what becomes of plugins/.',
    );

    /**
     * And the framework no longer loads its own autoloader.
     *
     * That is the other half of `007-001-0003` and the reason why the package can sit in
     * `vendor/` at all: A package is loaded by the autoloader, it does not load it.
     * Without this test the reversal would remain a matter of a comment.
     *
     * **What is searched for is a `require`/`include`, not the word.** A line that merely CHECKS
     * an autoloader path — `Start::assertNoSecondVendorTree()` does exactly that — loads nothing;
     * reporting it would mean turning the check against itself.
     */
    public function testTheFrameworkLoadsNoAutoloader(): void
    {
        $found = array();
        $root  = self::ROOT . '/lib/contentfly';

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

        foreach ($iterator as $entry) {
            if (!$entry instanceof \SplFileInfo || $entry->getExtension() !== 'php') {
                continue;
            }

            foreach (explode("\n", (string) file_get_contents($entry->getPathname())) as $number => $line) {
                $trimmed = ltrim($line);

                if ($trimmed === '' || str_starts_with($trimmed, '*')
                    || str_starts_with($trimmed, '//') || str_starts_with($trimmed, '/*')) {
                    continue;
                }

                if (preg_match('/\b(require|include)(_once)?\b.*autoload\.php/', $line) !== 1) {
                    continue;
                }

                $relative = substr($entry->getPathname(), strlen($root) + 1);

                if (isset(self::ALLOWED[$relative])) {
                    continue;
                }

                $found[] = sprintf('%s:%d — %s', $relative, $number + 1, trim($line));
            }
        }

        self::assertSame(array(), $found, implode("\n", array_merge(
            array(
                'The framework loads an autoloader. That means it cannot sit as a package under',
                'vendor/: To find this file, you would need the autoloader that',
                'it only loads itself (007-001-0003).',
                '',
            ),
            $found,
            array('', 'The entry point loads it and then calls Classes\\Kernel\\Start.')
        )));
    }

    /**
     * And the exception list watches itself — like the gates from `006-005`.
     */
    public function testEveryExceptionIsStillNeeded(): void
    {
        $dead = array();

        foreach (array_keys(self::ALLOWED) as $relative) {
            $path = self::ROOT . '/lib/contentfly/' . $relative;

            if (!is_file($path)) {
                $dead[] = sprintf('%s — the file no longer exists.', $relative);
                continue;
            }

            if (preg_match('/\b(require|include)(_once)?\b.*autoload\.php/', (string) file_get_contents($path)) !== 1) {
                $dead[] = sprintf('%s — no autoloader is loaded there any more.', $relative);
            }
        }

        self::assertSame(array(), $dead, implode("\n", array_merge(
            array('These exceptions no longer match anything and should be removed from ALLOWED:', ''),
            $dead
        )));
    }
}
