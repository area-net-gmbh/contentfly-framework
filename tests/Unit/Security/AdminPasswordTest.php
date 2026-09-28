<?php
namespace Tests\Unit\Security;

use Areanet\PIM\Classes\Helper;
use PHPUnit\Framework\TestCase;

/**
 * No password is hard-wired any more, and the generated one is worth something (`015-000-0002`).
 *
 * `Helper::install()` used to call `setPass('admin')` on every run. The value was a constant in
 * the source, so it was the same on every installation in the world — and `appcms:setup`
 * restored it even after someone had changed it.
 *
 * The behaviour of the commands is measured in `Tests\Integration\Command\SetupCommandTest`
 * against the real instance. What is held here is the other half: that the value cannot come
 * back into the source, and that what replaces it is not guessable.
 */
class AdminPasswordTest extends TestCase
{
    public function testTheGeneratedPasswordIsTwentyFourHexCharacters(): void
    {
        $password = Helper::generatePassword();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{24}$/', $password,
            '96 bits from random_bytes(), unambiguous to read out and to type');
    }

    public function testTwoGeneratedPasswordsDiffer(): void
    {
        $passwords = array();
        for ($i = 0; $i < 50; $i++) {
            $passwords[] = Helper::generatePassword();
        }

        $this->assertCount(50, array_unique($passwords),
            'A constant would pass every other test in this class');
    }

    /**
     * The source carries no admin password.
     *
     * Deliberately a text search and not a behaviour test: what has to be prevented is that
     * somebody puts the convenience back — in `Helper`, in a command, in a fixture. A behaviour
     * test only sees the paths it knows.
     */
    public function testNoSourceFileHardWiresAnAdminPassword(): void
    {
        $found = array();

        foreach ($this->sourceFiles() as $file) {
            foreach (file($file) as $number => $line) {
                // Comment lines are left out on purpose: the two places that were fixed explain
                // the old value in prose, and a guard that trips over its own explanation would
                // be removed rather than kept. What is searched for is code.
                if (preg_match('#^(\*|//|/\*|\*/)#', trim($line))) {
                    continue;
                }

                if (preg_match('/setPass\s*\(\s*[\'"]/', $line) || preg_match('/password\s*=\s*admin/i', $line)) {
                    $found[] = sprintf('%s:%d  %s', $this->relative($file), $number + 1, trim($line));
                }
            }
        }

        $this->assertSame(array(), $found, implode("\n", array_merge(
            array('A password is written into the source here — that is the finding of 015-000-0002:', ''),
            $found,
            array('', 'A password belongs in an option, an environment variable, or Helper::generatePassword().')
        )));
    }

    /** @return list<string> every PHP file of the framework */
    private function sourceFiles(): array
    {
        $root  = dirname(__DIR__, 3).'/lib/contentfly';
        $files = array();

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function relative(string $path): string
    {
        return str_replace(dirname(__DIR__, 3).'/', '', $path);
    }
}
