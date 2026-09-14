<?php
namespace Tests\Unit\Kernel;

use Areanet\PIM\Classes\Kernel\Paths;
use PHPUnit\Framework\TestCase;

/**
 * The package boundary, checked against the manifest (007-001-0004).
 *
 * ## Why this is checked and not just written down
 * Since `007-001-0004` there are two manifests: `lib/contentfly/composer.json` describes the
 * library package `areanet/contentfly`, the manifest in the root directory describes the
 * project. **The boundary between the two is a decision, not a property of the files** —
 * and a decision nobody checks holds exactly until the next `composer require` in
 * the wrong place.
 *
 * The most expensive finding from epic `006` was of this kind: The condition the
 * autoloader decision depended on was violated for years because nobody checked it.
 */
class PackageManifestTest extends TestCase
{
    /**
     * The version is stated in two places — so it has to be checked that it is the same.
     *
     * `lib/contentfly/version.php` carries `APP_VERSION`, the manifest a `version` field. The
     * field is needed for a `path` repository: Without it Composer derives the version from the
     * Git branch, and resolution would then depend on what the branch happens to be called.
     *
     * The manifest promises exactly that — this test keeps the promise.
     */
    public function testTheVersionInTheManifestMatchesVersionPhp(): void
    {
        $manifest = $this->packageManifest();

        $this->assertArrayHasKey('version', $manifest,
            'Without a version field Composer derives the version of the path package from the Git branch.');

        $this->assertSame(
            APP_VERSION,
            $manifest['version'],
            "version in lib/contentfly/composer.json and APP_VERSION in lib/contentfly/version.php\n"
            ."have diverged. Whoever changes one changes both."
        );
    }

    /**
     * The package carries exactly one namespace: its own.
     *
     * Until `007-001-0004` **one** manifest carried three — `Areanet\PIM\`, `Custom\` and `Plugins\`.
     * Exactly this mixing made updating impossible: Whoever wanted a new framework version
     * could only get it by overwriting the tree in which their own code also lived.
     */
    public function testThePackageCarriesOnlyTheFrameworkNamespace(): void
    {
        $manifest = $this->packageManifest();

        $this->assertSame(
            array('Areanet\\PIM\\'),
            array_keys($manifest['autoload']['psr-4'] ?? array()),
            'The library package may only carry its own namespace.'
        );

        $this->assertSame('library', $manifest['type'] ?? null);
        $this->assertSame('areanet/contentfly', $manifest['name'] ?? null);
    }

    /**
     * And the project carries its own — but not the framework's.
     *
     * If `Areanet\PIM\` were also here, there would be two ways to the same classes, and which
     * one wins would be decided by the load order. That is the case `006-004-0001`
     * was built against, just one level higher.
     */
    public function testTheProjectDoesNotCarryTheFrameworkNamespace(): void
    {
        $manifest = $this->projectManifest();
        $psr4     = $manifest['autoload']['psr-4'] ?? array();

        $this->assertArrayNotHasKey('Areanet\\PIM\\', $psr4,
            'The framework comes via the package, not via a second autoload entry.');

        $this->assertArrayHasKey('areanet/contentfly', $manifest['require'] ?? array(),
            'The project must list the framework as a dependency.');
    }

    /**
     * A library package does not ship any tools.
     *
     * Whoever includes a library inherits its `require` entries — but PHPUnit, PHPStan and
     * Rector belong in the repo in which development happens, not in every installation that uses
     * the framework.
     */
    public function testThePackageShipsNoTools(): void
    {
        $manifest = $this->packageManifest();

        $this->assertArrayNotHasKey('require-dev', $manifest,
            'The framework\'s require-dev belongs in the manifest of the development repo.');

        $this->assertArrayNotHasKey('platform', $manifest['config'] ?? array(),
            'A library must not prescribe a platform version to its consumer.');
    }

    /**
     * And it really is where Paths::package() points.
     *
     * Without this check the test above could be green for the wrong reason — for instance because it
     * reads a manifest that is not the package's at all.
     */
    public function testTheManifestIsInThePackageRoot(): void
    {
        $this->assertFileExists(Paths::package() . '/composer.json');
        $this->assertFileExists(Paths::package() . '/version.php');
        $this->assertFileExists(Paths::package() . '/bootstrap.php');
    }

    /** @return array<string,mixed> */
    private function packageManifest(): array
    {
        return $this->read(Paths::package() . '/composer.json');
    }

    /** @return array<string,mixed> */
    private function projectManifest(): array
    {
        return $this->read(Paths::project() . '/composer.json');
    }

    /** @return array<string,mixed> */
    private function read(string $path): array
    {
        $this->assertFileExists($path);

        $data = json_decode((string) file_get_contents($path), true);

        $this->assertIsArray($data, sprintf('%s is not valid JSON.', $path));

        return $data;
    }
}
