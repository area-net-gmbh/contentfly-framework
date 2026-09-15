<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * No `use` import points to a class that does not exist (`000-000-0037`).
 *
 * **Why this needs a check.** An import loads nothing. `use Doctrine\Common\Persistence\…` sat in
 * `Classes/Api.php` for years after `doctrine/persistence` 2.0 had removed the namespace, and every
 * `@throws` that used it pointed nowhere. PHPStan does not report it: an unused import is no error,
 * and a docblock is not checked against the autoloader. When the task measured the whole tree,
 * one known case turned into five imports in four files.
 *
 * **What is checked:** every namespace-level `use` in `lib/contentfly/`, `custom/` and `bin/` has to
 * resolve to a class, interface, trait or enum. Read with the tokenizer, not with a pattern — a
 * closure's `use (…)`, a trait `use` inside a class and an import inside a string are not imports.
 *
 * **A namespace import is allowed** (`use Doctrine\ORM\Mapping as ORM;`). It is the one kind of
 * import that is not supposed to be a class; whether the namespace exists is checked against the PSR-4
 * prefixes of the Composer autoloader. Whether it is USED is not this test's question — an unused
 * import of something that exists makes no false statement.
 *
 * `tests/` is not scanned: fixtures there contain imports on purpose, inside strings and in files
 * that describe a state before a migration.
 */
class DeadImportTest extends TestCase
{
    private const DIRECTORIES = array('lib/contentfly', 'custom', 'bin');

    public function testNoImportPointsToAMissingClass(): void
    {
        $dead = array();

        foreach (self::DIRECTORIES as $directory) {
            foreach ($this->phpFiles(CONTENTFLY_PROJECT_DIR . '/' . $directory) as $file) {
                foreach ($this->deadImports((string) file_get_contents($file)) as $import) {
                    $dead[] = substr($file, strlen(CONTENTFLY_PROJECT_DIR) + 1) . ': ' . $import;
                }
            }
        }

        $this->assertSame(array(), $dead, implode("\n", array_merge(
            array('These imports resolve to no class, interface, trait or enum (000-000-0037):', ''),
            $dead,
            array('', 'Correct the name, or remove the import if nothing uses it.')
        )));
    }

    /**
     * The counter-check: the scan has to find a dead import — otherwise a green run above would
     * prove nothing. And it must not report what only looks like one.
     */
    public function testTheScanReportsADeadImportAndNothingElse(): void
    {
        $source = <<<'PHP'
<?php
namespace Example;

use Doctrine\Common\Persistence\Mapping\MappingException;
use Doctrine\ORM\Mapping as ORM;
use Areanet\PIM\Classes\Annotations as PIM;
use Symfony\Component\HttpFoundation\Request;
use function sprintf;

$handler = function (\Throwable $e) use($app) {
    return 404;
};

#[ORM\Entity]
class Probe
{
    use SomeTrait;

    public function run(): void
    {
        $text = 'use Not\A\Real\Import;';
        $closure = function () use ($text) {
            return $text;
        };
    }
}
PHP;

        $this->assertSame(
            array('Doctrine\Common\Persistence\Mapping\MappingException'),
            $this->deadImports($source)
        );
    }

    /**
     * The namespace-level imports of a file that resolve to nothing.
     *
     * @return list<string>
     */
    private function deadImports(string $source): array
    {
        $dead = array();

        foreach ($this->imports($source) as $name) {
            if (class_exists($name) || interface_exists($name) || trait_exists($name) || enum_exists($name)) {
                continue;
            }

            if ($this->namespaceExists($name)) {
                continue;
            }

            $dead[] = $name;
        }

        return $dead;
    }

    /**
     * The names in namespace-level `use` statements. Skips `use function` and `use const`, every
     * `use` inside braces (trait imports, closures in methods) and a closure's `use (…)` at the top
     * level of a script.
     *
     * @return list<string>
     */
    private function imports(string $source): array
    {
        $tokens  = \PhpToken::tokenize($source);
        $imports = array();
        $depth   = 0;
        $count   = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if ($token->text === '{' || $token->is(array(T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES))) {
                $depth++;
                continue;
            }

            if ($token->text === '}') {
                $depth--;
                continue;
            }

            if ($depth !== 0 || !$token->is(T_USE)) {
                continue;
            }

            // A closure's `use (…)` at the top level of a script — `bootstrap-web.php` has one.
            $next = $i + 1;
            while ($next < $count && $tokens[$next]->is(T_WHITESPACE)) {
                $next++;
            }
            if ($next < $count && $tokens[$next]->text === '(') {
                continue;
            }

            $statement = '';
            for ($i++; $i < $count && $tokens[$i]->text !== ';'; $i++) {
                $statement .= $tokens[$i]->text;
            }

            $statement = trim($statement);

            if (preg_match('/^(function|const)\s/', $statement)) {
                continue;
            }

            foreach (explode(',', $statement) as $part) {
                if (preg_match('/^\s*\\\\?([\w\\\\]+)(?:\s+as\s+(\w+))?\s*$/', $part, $match)) {
                    $imports[] = $match[1];
                }
            }
        }

        return $imports;
    }

    /**
     * Whether a namespace maps to an existing directory under a PSR-4 prefix of the autoloader.
     */
    private function namespaceExists(string $name): bool
    {
        $namespace = trim($name, '\\') . '\\';

        foreach (\Composer\Autoload\ClassLoader::getRegisteredLoaders() as $loader) {
            foreach ($loader->getPrefixesPsr4() as $prefix => $directories) {
                if (strncmp($namespace, $prefix, strlen($prefix)) !== 0) {
                    continue;
                }

                $relative = str_replace('\\', '/', substr($namespace, strlen($prefix)));

                foreach ($directories as $directory) {
                    if (is_dir(rtrim($directory, '/') . '/' . $relative)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /** @return list<string> */
    private function phpFiles(string $directory): array
    {
        $files = array();

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $entry) {
            if ($entry instanceof \SplFileInfo && $entry->getExtension() === 'php') {
                $files[] = $entry->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
