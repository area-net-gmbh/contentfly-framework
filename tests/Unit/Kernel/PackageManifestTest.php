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
     * The version is still stated in two places — the guard moved, it did not go away
     * (`011-002-0003`).
     *
     * IT USED TO COMPARE `lib/contentfly/composer.json` WITH `version.php`. That field is gone:
     * Composer discards a tag whose `composer.json` declares a different version than the tag,
     * which made every pre-release tag invisible and the delivery path unprovable before a
     * release. The tag is now the single source of the DELIVERED version.
     *
     * What remains is the development tree, which still needs a stable version for its `path`
     * repository — it lives in the root manifest under `repositories[].options.versions`, a
     * statement about this checkout rather than about the package. Two places again, so the same
     * question again: do they agree?
     *
     * `tools/ci/paket-veroeffentlichen.sh` asks the third one at publishing time, against the tag.
     */
    public function testTheVersionOfThePathRepositoryMatchesVersionPhp(): void
    {
        $root = json_decode(file_get_contents(dirname(__DIR__, 3) . '/composer.json'), true);

        $versionen = null;
        foreach ($root['repositories'] as $repository) {
            if (($repository['type'] ?? null) === 'path' && ($repository['url'] ?? null) === 'lib/contentfly') {
                $versionen = $repository['options']['versions'] ?? null;
            }
        }

        $this->assertIsArray($versionen,
            "The path repository for lib/contentfly has no options.versions.\n"
            ."Without it Composer derives the version of the path package from the Git branch, and\n"
            ."the resolution of \"areanet/contentfly\": \"^2.0\" would depend on what the branch\n"
            ."happens to be called.");

        $this->assertSame(
            APP_VERSION,
            $versionen['areanet/contentfly'] ?? null,
            "options.versions in composer.json and APP_VERSION in lib/contentfly/version.php\n"
            ."have diverged. Whoever changes one changes both."
        );
    }

    /**
     * And the field must NOT come back.
     *
     * Adding it again looks harmless — it is the obvious answer to "how does this package know its
     * version". It would silently break every pre-release tag, and the symptom appears far away:
     * Composer reports the package as `dev-master` only, with no word about the tag it dropped.
     */
    public function testThePackageManifestDeclaresNoVersion(): void
    {
        $this->assertArrayNotHasKey('version', $this->packageManifest(),
            "lib/contentfly/composer.json declares a version again.\n"
            ."Composer discards every tag whose composer.json names a DIFFERENT version than the\n"
            ."tag itself — measured on 2026-09-17: with the field, v2.0.0-rc1 disappeared without\n"
            ."a message. The delivered version comes from the tag; see 011-002-0003."
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
