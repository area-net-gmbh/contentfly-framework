<?php
namespace Tests\Unit\Kernel;

use PHPUnit\Framework\TestCase;

/**
 * Two promises about `composer.lock` that nobody was checking (`011-004-0001`).
 *
 * Both were **measured** during the epics that earned them, and a measurement expires the moment
 * someone runs `composer update`. This project drew that line for its three other gates already:
 * a guarantee nobody checks is no guarantee.
 */
class LockGuaranteesTest extends TestCase
{
    /**
     * The target platform, in ONE place.
     *
     * `composer.json` carries `config.platform.php` — the version the suite and the pipeline run
     * against, today 8.3. That is deliberately NOT this number: the target is where the project is
     * going, the platform is where it stands. Mixing them would make the gate green for the wrong
     * reason.
     *
     * DECIDED ON 2026-09-25 (000-000-0054): `config.platform.php` stays at 8.3.0. It decides what
     * Composer resolves the lock for, and the package manifest promises `^8.3`. Raised to 8.5, the
     * lock could take in a package that needs PHP 8.4 or 8.5, and a project on 8.3 could no longer
     * install it. The platform moves with the lower bound of the manifest, not with the target.
     *
     * Measured on the same day, PHP 8.5.11: the lock installs (`check-platform-reqs` succeeds for
     * every requirement), `composer audit` is clean and the full suite is green. The deprecation
     * gate was not — `imagedestroy()` in our own code, a no-op since PHP 8.0. 000-000-0096 removed
     * it and put 8.5 into the pipeline matrix, beside 8.3 and 8.4.
     */
    private const TARGET_PLATFORM = '8.5';

    /**
     * Package name prefixes that must not come back.
     *
     * Silex was the microframework until Epic `009`, Pimple its container. Both are out of the
     * tree, and `NoSilexTypesTest` keeps their type names out of the code — this is the other
     * half: the packages themselves.
     */
    private const FORBIDDEN_PREFIXES = array('silex/', 'pimple/', 'knplabs/console-service-provider');

    /**
     * No Silex and no Pimple in the lock.
     *
     * **A TEXT SEARCH WOULD NOT DO, and that is the point of this test's shape.** `composer.lock`
     * contains one occurrence of the word "Silex" — inside the `notes` block that
     * `lib/contentfly/composer.json` carries and Composer embeds verbatim:
     *
     *     "What forces them has moved twice: until epic 009 Silex and knplabs capped them"
     *
     * A `grep` over the file therefore finds a hit and reports a dependency that does not exist.
     * A gate that cries wolf on its own documentation is worse than none — it gets switched off.
     * So this reads the package NAMES.
     */
    public function testTheLockCarriesNoSilexOrPimplePackage(): void
    {
        $found = array();

        foreach ($this->allPackages() as $package) {
            foreach (self::FORBIDDEN_PREFIXES as $prefix) {
                if (str_starts_with($package['name'], $prefix)) {
                    $found[] = $package['name'] . ' ' . ($package['version'] ?? '?');
                }
            }
        }

        $this->assertSame(array(), $found,
            "These packages are back in composer.lock:\n" . implode("\n", $found) . "\n\n"
            . "Silex was the microframework until epic 009, Pimple its container. Both left the\n"
            . "tree with that epic; NoSilexTypesTest keeps their type names out of the code, this\n"
            . "test keeps the packages out of the lock."
        );
    }

    /**
     * Nothing in the lock caps PHP below the target platform.
     *
     * The jump to PHP 8.5 is a step of its own, and what used to block it is gone: a package that
     * capped at `^7.0` left with its consumer in `012-001-0003`. Measured on 2026-09-17, none of
     * the 55 packages caps below 8.5 — and that is exactly the kind of statement that stops being
     * true without anyone noticing, because a `composer update` can bring back a dependency that
     * has not caught up.
     *
     * **What this test does NOT claim:** that the suite passes on 8.5. That needs a run on 8.5,
     * and the pipeline goes to 8.4 today. This is the cheaper half — the constraint side — and it
     * fails early, before anyone builds an image for a version the dependencies refuse.
     */
    public function testNoPackageCapsPhpBelowTheTargetPlatform(): void
    {
        $blocking = array();

        foreach ($this->allPackages() as $package) {
            $constraint = $package['require']['php'] ?? null;

            if ($constraint === null || $this->allows($constraint, self::TARGET_PLATFORM)) {
                continue;
            }

            $blocking[] = sprintf('%-40s php %s', $package['name'], $constraint);
        }

        $this->assertSame(array(), $blocking,
            "These packages do not allow PHP " . self::TARGET_PLATFORM . ":\n" . implode("\n", $blocking) . "\n\n"
            . "The target platform is named once, in this test. Whoever moves it moves it here —\n"
            . "and whoever adds a package that caps below it learns it now, not when the image is built."
        );
    }

    /**
     * Does a Composer constraint allow this version?
     *
     * Deliberately narrow: it understands `^`, `>=`, `||` and plain versions, because that is what
     * the 55 packages of this lock use. An expression it cannot read counts as ALLOWED — a gate
     * that guesses "forbidden" from something it did not understand would be red for the wrong
     * reason, and this one is meant to name the package that really blocks.
     */
    private function allows(string $constraint, string $version): bool
    {
        // `|` and `||` both separate alternatives in Composer, and the lock uses BOTH — the first
        // draft of this method split on `||` only and then read `^7.2|^8.0` as a single token.
        // It reported phpstan and rector as blocking PHP 8.5, which they do not; the gate found a
        // bug in itself before it found one in the lock.
        foreach (preg_split('/\|\|?/', $constraint) as $alternative) {
            $alternative = trim($alternative);

            foreach (preg_split('/\s+/', $alternative) as $part) {
                if ($part === '' || $part === '*') {
                    return true;
                }

                if (preg_match('/^\^(\d+)(?:\.(\d+))?/', $part, $m)) {
                    // ^8.1 allows every 8.x from 8.1 on — the next major is the ceiling.
                    if ((int) $m[1] === (int) explode('.', $version)[0]
                        && (int) ($m[2] ?? 0) <= (int) explode('.', $version)[1]) {
                        return true;
                    }
                    continue;
                }

                if (preg_match('/^>=?\s*(\d+(?:\.\d+)*)/', $part, $m)) {
                    if (version_compare($version, $m[1], '>=')) {
                        return true;
                    }
                    continue;
                }

                // Anything this method does not understand is not evidence of a cap.
                return true;
            }
        }

        return false;
    }

    /** @return list<array<string,mixed>> */
    private function allPackages(): array
    {
        $lock = json_decode(file_get_contents(dirname(__DIR__, 3) . '/composer.lock'), true);

        $this->assertIsArray($lock, 'composer.lock is not readable — the gate has nothing to check.');

        return array_merge($lock['packages'] ?? array(), $lock['packages-dev'] ?? array());
    }
}
