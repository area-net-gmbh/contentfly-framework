<?php
namespace Tests\Unit;

use Areanet\PIM\Classes\Config;
use Areanet\PIM\Classes\Config\Factory;
use Areanet\PIM\Classes\Language;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * `lang` enters the application in one shape, through one place (`015-000-0011`).
 *
 * THE FINDING. The parameter went from the request into two places that read it differently: the
 * rights check looked it up as an ARRAY KEY, so case-sensitively, and an unknown key counted as
 * unrestricted; the query then compared `a.lang = :lang` under `utf8mb3_unicode_ci`, which
 * ignores case and trailing spaces. `EN`, `En` and `en ` passed the check as "no restriction" and
 * hit the `en` row all the same.
 *
 * `ApiController` read the parameter in twelve places. The source check at the end of this class
 * is the part that keeps the fix: normalising eleven of them would look exactly like normalising
 * all of them, right up to the day somebody uses the twelfth.
 */
class LanguageEdgeTest extends TestCase
{
    protected function setUp(): void
    {
        $config = new Config();
        $config->APP_LANGUAGES = array('de', 'en');
        Factory::getInstance()->setConfig($config);
    }

    protected function tearDown(): void
    {
        Factory::getInstance()->setConfig(new Config());
    }

    /** @return array<string, array{0: mixed, 1: ?string}> */
    public static function values(): array
    {
        return array(
            'as configured'      => array('en', 'en'),
            'upper case'         => array('EN', 'en'),
            'mixed case'         => array('En', 'en'),
            'trailing space'     => array('en ', 'en'),
            'leading space'      => array(' en', 'en'),
            'both'               => array('  EN  ', 'en'),
            'empty'              => array('', null),
            'only spaces'        => array('   ', null),
            'null'               => array(null, null),
            'array'              => array(array('en'), null),
        );
    }

    #[DataProvider('values')]
    public function testNormalisation(mixed $raw, ?string $expected): void
    {
        $this->assertSame($expected, Language::normalise($raw));
    }

    /**
     * The three spellings of the finding all end up as the one the collation would match.
     */
    public function testTheVariantsOfTheFindingCollapseIntoOne(): void
    {
        foreach (array('EN', 'En', 'en ', ' en', 'eN') as $variant) {
            $this->assertSame('en', Language::normalise($variant), $variant.' is the same language');
        }
    }

    public function testOnlyConfiguredLanguagesCount(): void
    {
        $this->assertTrue(Language::isConfigured('en'));
        $this->assertTrue(Language::isConfigured('de'));
        $this->assertFalse(Language::isConfigured('fr'), 'not configured');
        $this->assertFalse(Language::isConfigured(null));
        $this->assertFalse(Language::isConfigured('EN'), 'the check compares the normalised form');
    }

    /** The configuration's own spelling is normalised too — otherwise the comparison fails on it. */
    public function testTheConfigurationIsNormalisedAsWell(): void
    {
        $config = new Config();
        $config->APP_LANGUAGES = array('DE', ' En ');
        Factory::getInstance()->setConfig($config);

        $this->assertSame(array('de', 'en'), Language::configured());
        $this->assertTrue(Language::isConfigured('en'));
        $this->assertTrue(Language::isConfigured('de'));
    }

    /**
     * WITHOUT CONFIGURED LANGUAGES EVERY VALUE COUNTS AS KNOWN, and that is deliberate: an
     * installation with i18n entities and no `APP_LANGUAGES` would otherwise be unable to write
     * anything at all. This task narrows a rights check that was too wide; it does not close
     * i18n for installations that never configured it.
     */
    public function testWithoutConfiguredLanguagesNothingIsRefused(): void
    {
        $config = new Config();
        $config->APP_LANGUAGES = array();
        Factory::getInstance()->setConfig($config);

        $this->assertTrue(Language::isConfigured('anything'));
        $this->assertTrue(Language::isConfigured('en'));
    }

    // ── the edge itself ────────────────────────────────────────────────────────────────────

    /**
     * No controller reads `lang` out of the request on its own any more.
     *
     * A text check, deliberately: what has to be prevented is a thirteenth entry point written
     * next year. A behaviour test only sees the paths it knows.
     */
    public function testNoControllerReadsTheParameterRaw(): void
    {
        $found = array();

        foreach ($this->controllerFiles() as $file) {
            foreach (file($file) as $number => $line) {
                if (preg_match('/request->(request|query)->(all\(\)\[|get\()\s*[\'"]lang[\'"]/', $line)) {
                    $found[] = sprintf('%s:%d  %s', $this->relative($file), $number + 1, trim($line));
                }
            }
        }

        $this->assertSame(array(), $found, implode("\n", array_merge(
            array(
                'A controller reads `lang` straight from the request — that is the finding of',
                '015-000-0011, and one unnormalised entry point is enough for it:',
                '',
            ),
            $found,
            array('', 'Use Language::fromRequest($request).')
        )));
    }

    /** @return list<string> */
    private function controllerFiles(): array
    {
        $root  = dirname(__DIR__, 2).'/lib/contentfly/Controller';
        $files = array();

        foreach (new \DirectoryIterator($root) as $entry) {
            if ($entry->isFile() && $entry->getExtension() === 'php') {
                $files[] = $entry->getPathname();
            }
        }

        sort($files);
        $this->assertNotEmpty($files, 'Precondition: there are controllers to check');

        return $files;
    }

    private function relative(string $path): string
    {
        return str_replace(dirname(__DIR__, 2).'/', '', $path);
    }
}
