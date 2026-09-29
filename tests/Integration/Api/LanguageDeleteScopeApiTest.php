<?php
namespace Tests\Integration\Api;

use Areanet\PIM\Entity\Permission;
use Tests\Integration\ExtraServer;
use Tests\Integration\IntegrationTestCase;

/**
 * A delete takes every language row — so every language row is checked first (`015-000-0016`).
 *
 * THE FINDING. `Api::doDelete()` checked ownership and `I18nPermission` for the ONE language from
 * the request, and then wiped the rest of the record with a single statement:
 *
 *     DELETE FROM … e WHERE e.id = :id AND NOT e.lang = :lang
 *
 * Whom those rows belonged to, and what the caller was allowed to do in those languages, nobody
 * asked. A translator with full rights on `en` and read-only on the main language deleted her
 * `en` variant, and the protected main-language row went with it.
 *
 * WHAT IS MEASURED IS THE ROWS, not the status code. A refusal that still deleted would pass a
 * status assertion, and a fix that refuses everything would pass it too — hence the last two
 * tests, which insist that a caller who MAY delete every language still gets all of them in one
 * call.
 *
 * NOTHING IS DELETED when one row fails, and the tests pin that rather than a partial delete:
 * files, the log entry and the onejoins are removed BEFORE that statement runs, so leaving the
 * other rows behind would leave them pointing at things that are gone.
 *
 * ITS OWN SERVER, because the template configures no languages and the suite's server therefore
 * never reaches this path — the same reason `LanguageRightsApiTest` starts one.
 * `Core\ExampleI18n` is the concrete i18n entity of the template.
 */
class LanguageDeleteScopeApiTest extends IntegrationTestCase
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

    // ── The finding ────────────────────────────────────────────────────────────────────────

    /**
     * The case from the task: read-only on the main language, full rights on English.
     *
     * Deleting the English variant must not take the German row with it.
     */
    public function testDeletingEnglishDoesNotTakeTheProtectedGermanRowWithIt(): void
    {
        [$id] = $this->recordInBothLanguages();

        [$status, $body] = $this->onServer(self::$server->url(), function () use ($id) {
            [$token] = $this->createTestUser(
                array('Core\\ExampleI18n' => array(
                    'readable'  => Permission::ALL,
                    'writable'  => Permission::ALL,
                    'deletable' => Permission::ALL,
                )),
                array('languages' => json_encode(array('de' => 'readable')))
            );

            return $this->postJson('/api/delete', array(
                'entity' => 'Core\\ExampleI18n', 'id' => $id, 'lang' => 'en',
            ), $token);
        });

        $this->assertNotSame(200, $status, 'The delete is refused — '.json_encode($body['errors'] ?? $body));
        $this->assertTrue($this->rowExists($id, 'de'), 'the protected German row is still there');
        $this->assertTrue($this->rowExists($id, 'en'), 'and nothing at all was deleted');
    }

    /**
     * The ownership half of the same finding.
     *
     * `deletable = OWN` and no language restriction at all: the caller owns the English row, the
     * German one belongs to somebody else. The English delete must not reach across.
     */
    public function testDeletingAnOwnedRowDoesNotTakeAForeignLanguageRowWithIt(): void
    {
        [$id] = $this->recordInBothLanguages();

        [$status, $body] = $this->onServer(self::$server->url(), function () use ($id) {
            [$token, $userId] = $this->createTestUser(
                array('Core\\ExampleI18n' => array(
                    'readable'  => Permission::ALL,
                    'writable'  => Permission::ALL,
                    'deletable' => Permission::OWN,
                ))
            );

            $this->own($id, 'en', $userId);

            return $this->postJson('/api/delete', array(
                'entity' => 'Core\\ExampleI18n', 'id' => $id, 'lang' => 'en',
            ), $token);
        });

        $this->assertNotSame(200, $status, 'The delete is refused — '.json_encode($body['errors'] ?? $body));
        $this->assertTrue($this->rowExists($id, 'de'), 'the foreign German row is still there');
        $this->assertTrue($this->rowExists($id, 'en'), 'and nothing at all was deleted');
    }

    // ── And the other direction, which the task states just as plainly ─────────────────────

    /** An admin deletes the record as a whole, in one call, as before. */
    public function testAnAdminStillDeletesEveryLanguageInOneCall(): void
    {
        [$id] = $this->recordInBothLanguages();

        [$status, $body] = $this->onServer(self::$server->url(), function () use ($id) {
            return $this->postJson('/api/delete', array(
                'entity' => 'Core\\ExampleI18n', 'id' => $id, 'lang' => 'en',
            ), $this->login());
        });

        $this->assertSame(200, $status, json_encode($body['errors'] ?? $body));
        $this->assertFalse($this->rowExists($id, 'en'), 'the English row is gone');
        $this->assertFalse($this->rowExists($id, 'de'), 'and the German one with it');
    }

    /**
     * And so does a NON-admin who may delete every language.
     *
     * The admin path short-circuits `I18nPermission` before it looks at anything, so it alone
     * would not notice a check that refuses every ordinary user.
     */
    public function testAnUnrestrictedUserStillDeletesEveryLanguageInOneCall(): void
    {
        [$id] = $this->recordInBothLanguages();

        [$status, $body] = $this->onServer(self::$server->url(), function () use ($id) {
            [$token] = $this->createTestUser(
                array('Core\\ExampleI18n' => array(
                    'readable'  => Permission::ALL,
                    'writable'  => Permission::ALL,
                    'deletable' => Permission::ALL,
                ))
            );

            return $this->postJson('/api/delete', array(
                'entity' => 'Core\\ExampleI18n', 'id' => $id, 'lang' => 'en',
            ), $token);
        });

        $this->assertSame(200, $status, json_encode($body['errors'] ?? $body));
        $this->assertFalse($this->rowExists($id, 'en'), 'the English row is gone');
        $this->assertFalse($this->rowExists($id, 'de'), 'and the German one with it');
    }

    // ── helpers ────────────────────────────────────────────────────────────────────────────

    /**
     * A record that exists in both languages and belongs to nobody in particular.
     *
     * @return array{0:string}
     */
    private function recordInBothLanguages(): array
    {
        $id = 'lds-'.bin2hex(random_bytes(6));

        $insert = $this->pdo()->prepare(
            'INSERT INTO example_i18n (id, lang, title, created, modified, views, isIntern)
             VALUES (:id, :lang, :title, NOW(), NOW(), 0, 0)'
        );

        foreach (array('de', 'en') as $lang) {
            $insert->execute(array('id' => $id, 'lang' => $lang, 'title' => $lang.'-'.bin2hex(random_bytes(4))));
        }

        $this->deleteAfterTest('example_i18n', $id);

        return array($id);
    }

    /** Hands one language row to a user, so that `deletable = OWN` lets them at it. */
    private function own(string $id, string $lang, string $userId): void
    {
        $this->pdo()
            ->prepare('UPDATE example_i18n SET usercreated_id = ? WHERE id = ? AND lang = ?')
            ->execute(array($userId, $id, $lang));
    }

    private function rowExists(string $id, string $lang): bool
    {
        $statement = $this->pdo()->prepare('SELECT COUNT(*) FROM example_i18n WHERE id = ? AND lang = ?');
        $statement->execute(array($id, $lang));

        return (int) $statement->fetchColumn() > 0;
    }
}
