<?php
namespace Tests\Unit\Kernel;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The document root serves `index.php` and the delivered files — nothing else (`015-000-0004`).
 *
 * WHY A TEXT CHECK AND NOT A REQUEST. The rules live in `.htaccess` and are enforced by Apache.
 * The suite runs against PHP's built-in server, which has no `.htaccess` at all, and CI has no
 * Apache either. What can be held here is therefore that the rules are present and that they
 * deny rather than rewrite — not that Apache obeys them.
 *
 * THAT IS WORTH HAVING ANYWAY: the file is edited by hand, it has no syntax anyone runs, and a
 * rule deleted by accident leaves no trace. The second layer — `bin/console.php` refusing a web
 * SAPI itself — is what actually protects, and it is measured in `ConsoleSapiTest` and
 * `Tests\Integration\ConsoleOverHttpTest`.
 *
 * Whoever loosens a rule here changes this test with it, and then the reason stands in the diff.
 */
class DocumentRootTest extends TestCase
{
    /** @return array<string, array{0: string}> directories that are code, config or working data */
    public static function deniedDirectories(): array
    {
        return array(
            'bin — the console'          => array('bin'),
            'custom — the credentials'   => array('custom'),
            'lib — the framework'        => array('lib'),
            'vendor — the dependencies'  => array('vendor'),
            'plugins'                    => array('plugins'),
            'tools'                      => array('tools'),
            'tests'                      => array('tests'),
        );
    }

    #[DataProvider('deniedDirectories')]
    public function testTheDirectoryIsDeniedFromTheWeb(string $directory): void
    {
        $rules = $this->denyRules();

        $covered = false;
        foreach ($rules as $rule) {
            if (str_contains($rule, $directory)) {
                $covered = true;
                break;
            }
        }

        $this->assertTrue($covered, sprintf(
            "No deny rule in .htaccess covers `%s/`.\nThe rules found were:\n%s",
            $directory,
            implode("\n", $rules)
        ));
    }

    /** `data/files/` is delivered — `FileController` redirects to it. The rest of `data/` is not. */
    public function testOnlyTheDeliveredFilesUnderDataStayReachable(): void
    {
        $htaccess = $this->htaccess();

        $this->assertStringContainsString('!(^|/)data/files/', $htaccess,
            'The exception for the delivered files is what keeps /file/get working');
        $this->assertMatchesRegularExpression('#RewriteRule \(\^\|/\)data/ - \[F,L\]#', $htaccess,
            'and everything else under data/ — cache, import, temp — is denied');
    }

    /** Denied, not rewritten: a rewrite would hide a misconfiguration behind the API's 404. */
    public function testTheRulesDenyRatherThanRewrite(): void
    {
        foreach ($this->denyRules() as $rule) {
            $this->assertStringContainsString('[F,L]', $rule,
                "A rule that does not end in [F,L] lets the request continue:\n".$rule);
        }
    }

    /** The rule that routes everything else to index.php must stay, and stay last. */
    public function testTheApplicationIsStillReachable(): void
    {
        $htaccess = $this->htaccess();

        $this->assertStringContainsString('RewriteRule ^ index.php [QSA,L]', $htaccess);

        $lastDeny = 0;
        foreach ($this->denyRules() as $rule) {
            $lastDeny = max($lastDeny, (int) strpos($htaccess, $rule));
        }

        $this->assertGreaterThan(strpos($htaccess, 'RewriteRule ^(bin|'), strpos($htaccess, 'RewriteRule ^ index.php'),
            'The catch-all comes after the deny rules — before them it would swallow every request');
        $this->assertGreaterThan($lastDeny, strpos($htaccess, 'RewriteRule ^ index.php'));
    }

    // ── helpers ────────────────────────────────────────────────────────────────────────────

    private function htaccess(): string
    {
        $path = dirname(__DIR__, 3).'/.htaccess';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /** @return list<string> every line that denies something */
    private function denyRules(): array
    {
        $rules = array();
        foreach (explode("\n", $this->htaccess()) as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'RewriteRule') && str_contains($line, '[F,L]')) {
                $rules[] = $line;
            }
        }

        $this->assertNotEmpty($rules, '.htaccess carries no deny rule at all');

        return $rules;
    }
}
