<?php
namespace Tests\Unit\Entity;

use Areanet\PIM\Classes\I18nPermission;
use Areanet\PIM\Entity\Group;
use PHPUnit\Framework\TestCase;

/**
 * Characterisation tests for the language permissions of a group.
 *
 * Deliberately a **unit test**: `langIsWritable()`, `langIsTranslatable()` and
 * `langisOnlyReadable()` depend on nothing but the group itself — neither database nor HTTP.
 * An integration test would run into nothing here anyway, because the template configures no
 * languages (`APP_LANGUAGES` is empty) and ships no concrete `BaseI18n` entity; `008-001-0005`
 * established that. A unit test still covers the logic.
 *
 * **The default is "allowed", not "forbidden".** Without language permissions and without an
 * entry for a language, all three methods return the permissive result. Whether that is
 * intended is not up for debate here — it is the current state.
 */
class GroupLanguagePermissionTest extends TestCase
{
    private function groupWith(?array $languagePermissions): Group
    {
        $group = new Group();

        if ($languagePermissions !== null) {
            $group->setLanguages($languagePermissions);
        }

        return $group;
    }

    // ── Without language permissions set, everything is allowed ────────────────────────

    public function testWithoutLanguagePermissionsEveryLanguageIsWritable(): void
    {
        $this->assertTrue($this->groupWith(null)->langIsWritable('de'));
        $this->assertTrue($this->groupWith(null)->langIsWritable('anything'));
    }

    public function testWithoutLanguagePermissionsEveryLanguageIsTranslatable(): void
    {
        $this->assertTrue($this->groupWith(null)->langIsTranslatable('de'));
    }

    public function testWithoutLanguagePermissionsNoLanguageIsOnlyReadable(): void
    {
        $this->assertFalse($this->groupWith(null)->langisOnlyReadable('de'),
            'langisOnlyReadable is the negation of the other two');
    }

    // ── A language without its own entry stays allowed ─────────────────────────────────

    public function testALanguageThatIsNotListedIsWritable(): void
    {
        // The surprising point: if language permissions are set but a language is not among
        // them, it counts as writable. The default is "allowed", not "forbidden" — whoever
        // wants to lock a language must list it explicitly.
        $group = $this->groupWith(array('de' => I18nPermission::IS_READABLE));

        $this->assertTrue($group->langIsWritable('en'),
            'en is not in the language permissions and is therefore writable');
    }

    // ── A listed entry locks ───────────────────────────────────────────────────────────

    public function testAListedLanguageIsNotWritable(): void
    {
        $group = $this->groupWith(array('de' => I18nPermission::IS_READABLE));

        $this->assertFalse($group->langIsWritable('de'));
    }

    public function testReadOnlyMeansNeitherWritableNorTranslatable(): void
    {
        $group = $this->groupWith(array('de' => I18nPermission::IS_READABLE));

        $this->assertFalse($group->langIsWritable('de'));
        $this->assertFalse($group->langIsTranslatable('de'));
        $this->assertTrue($group->langisOnlyReadable('de'));
    }

    public function testTranslatableIsNotWritableButTranslatable(): void
    {
        // The third level: translating yes, free writing no.
        $group = $this->groupWith(array('de' => I18nPermission::IS_TRANSLATABALE));

        $this->assertFalse($group->langIsWritable('de'));
        $this->assertTrue($group->langIsTranslatable('de'));
        $this->assertFalse($group->langisOnlyReadable('de'));
    }

    // ── Storage ────────────────────────────────────────────────────────────────────────

    public function testLanguagePermissionsAreStoredAsJsonAndReadBack(): void
    {
        $group = $this->groupWith(array('de' => I18nPermission::IS_READABLE));

        $this->assertSame(array('de' => I18nPermission::IS_READABLE), $group->getLanguages(),
            'setLanguages() encodes to JSON, getLanguages() decodes back');
    }

    public function testAnEmptyValueLeavesTheLanguagePermissionsUnset(): void
    {
        // setLanguages() ignores falsy values — so an empty array does NOT clear the
        // permissions, it leaves them untouched. Recorded, not judged.
        $group = new Group();
        $group->setLanguages(array('de' => I18nPermission::IS_READABLE));
        $group->setLanguages(array());

        $this->assertSame(array('de' => I18nPermission::IS_READABLE), $group->getLanguages(),
            'An empty array does not reset the language permissions');
    }
}
