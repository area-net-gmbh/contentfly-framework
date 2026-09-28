<?php
namespace Tests\Integration\Api;

use Areanet\PIM\Entity\Permission;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Integration\ExtraServer;
use Tests\Integration\IntegrationTestCase;

/**
 * A language restriction is not one keystroke wide (`015-000-0011`).
 *
 * THE FINDING. `lang` went from the request into two places that read it differently: the rights
 * check `Group::langIsWritable()` looked it up as an array key — case-sensitively — and an
 * unknown key counted as unrestricted, while the record was selected with `a.lang = :lang` under
 * `utf8mb3_unicode_ci`, which ignores case and trailing spaces.
 *
 * So a group limited to `{"en": "readable"}` wrote English content by asking for `EN`, `En` or
 * `en ` — the check saw an unconfigured language and waved it through, the query hit the `en` row
 * all the same.
 *
 * ITS OWN SERVER, because the template configures no languages and the suite's server therefore
 * never reaches this path — the same reason `MainLanguageApiTest` starts one. `Core\ExampleI18n`
 * is the concrete i18n entity of the template.
 *
 * WHAT IS MEASURED IS THE ROW, not the status code alone: a rejection that still wrote would pass
 * a status assertion.
 */
class LanguageRightsApiTest extends IntegrationTestCase
{
    private static ?ExtraServer $server = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (self::$baseUrl !== null) {
            self::$server = ExtraServer::start(array('APP_LANGUAGES' => 'de,en'));
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::$server?->stop();
        self::$server = null;

        parent::tearDownAfterClass();
    }

    /** The spellings the collation treats as `en`. */
    public static function spellingsOfEnglish(): array
    {
        return array(
            'as configured'  => array('en'),
            'upper case'     => array('EN'),
            'mixed case'     => array('En'),
            'trailing space' => array('en '),
            'leading space'  => array(' en'),
        );
    }

    #[DataProvider('spellingsOfEnglish')]
    public function testARestrictedGroupCannotUpdateEnglishInAnySpelling(string $lang): void
    {
        [$id, $before] = $this->englishRecord();

        [$status, $body] = $this->asRestrictedUser(fn (string $token) => $this->postJson('/api/update', array(
            'entity' => 'Core\\ExampleI18n', 'id' => $id, 'lang' => $lang,
            'data'   => array('title' => 'changed through '.$lang),
        ), $token));

        $this->assertNotSame(200, $status, 'The write is refused — '.json_encode($body['errors'] ?? $body));
        $this->assertSame($before, $this->title($id, 'en'), 'and the English row is unchanged');
    }

    #[DataProvider('spellingsOfEnglish')]
    public function testARestrictedGroupCannotDeleteEnglishInAnySpelling(string $lang): void
    {
        [$id, $before] = $this->englishRecord();

        [$status, $body] = $this->asRestrictedUser(fn (string $token) => $this->postJson('/api/delete', array(
            'entity' => 'Core\\ExampleI18n', 'id' => $id, 'lang' => $lang,
        ), $token));

        $this->assertNotSame(200, $status, 'The delete is refused — '.json_encode($body['errors'] ?? $body));
        $this->assertSame($before, $this->title($id, 'en'), 'and the English row is still there');
    }

    /**
     * A configured language the group does NOT restrict stays writable — the map lists
     * restrictions, not permissions, and this task must not turn that around.
     */
    #[DataProvider('spellingsOfGerman')]
    public function testAnUnrestrictedLanguageStaysWritable(string $lang): void
    {
        $id = 'lr-'.bin2hex(random_bytes(6));

        [$status, $body] = $this->asRestrictedUser(fn (string $token) => $this->postJson('/api/insert', array(
            'entity' => 'Core\\ExampleI18n', 'lang' => $lang,
            'data'   => array('id' => $id, 'title' => 'deutsch-'.bin2hex(random_bytes(4))),
        ), $token));

        $this->deleteAfterTest('example_i18n', $id);

        $this->assertSame(200, $status, 'German is not restricted — '.json_encode($body['errors'] ?? $body));
    }

    /** @return array<string, array{0: string}> */
    public static function spellingsOfGerman(): array
    {
        return array(
            'as configured'  => array('de'),
            'upper case'     => array('DE'),
            'trailing space' => array('de '),
        );
    }

    /** A value that names no configured language is a mistake in the request, not a free pass. */
    public function testAnUnknownLanguageIsRefusedWithFourHundred(): void
    {
        [$status, $body] = $this->asRestrictedUser(fn (string $token) => $this->postJson('/api/insert', array(
            'entity' => 'Core\\ExampleI18n', 'lang' => 'fr',
            'data'   => array('title' => 'francais-'.bin2hex(random_bytes(4))),
        ), $token));

        $this->assertSame(400, $status, json_encode($body['errors'] ?? $body));
        $this->assertErrorEnvelope($body, 'contentfly_general_invalid_params');
    }

    // ── helpers ────────────────────────────────────────────────────────────────────────────

    /**
     * Runs the call on the extra server as a user whose group may only read English.
     *
     * The login has to happen on that server too — the token is issued there.
     *
     * @return array{0:int,1:array}
     */
    private function asRestrictedUser(callable $call): array
    {
        return $this->onServer(self::$server->url(), function () use ($call) {
            [$token] = $this->createTestUser(
                array('Core\\ExampleI18n' => array(
                    'readable'  => Permission::ALL,
                    'writable'  => Permission::ALL,
                    'deletable' => Permission::ALL,
                )),
                array('languages' => json_encode(array('en' => 'readable')))
            );

            return $call($token);
        });
    }

    /**
     * An English record that somebody else created.
     *
     * @return array{0:string,1:string} id and current English title
     */
    private function englishRecord(): array
    {
        $id    = 'lr-'.bin2hex(random_bytes(6));
        $title = 'english-'.bin2hex(random_bytes(4));

        $this->pdo()->prepare(
            'INSERT INTO example_i18n (id, lang, title, created, modified, views, isIntern)
             VALUES (:id, :lang, :title, NOW(), NOW(), 0, 0)'
        )->execute(array('id' => $id, 'lang' => 'en', 'title' => $title));

        $this->deleteAfterTest('example_i18n', $id);

        return array($id, $title);
    }

    private function title(string $id, string $lang): string
    {
        $statement = $this->pdo()->prepare('SELECT title FROM example_i18n WHERE id = ? AND lang = ?');
        $statement->execute(array($id, $lang));

        return (string) $statement->fetchColumn();
    }
}
