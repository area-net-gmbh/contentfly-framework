<?php
namespace Tests\Integration\Api;

use Tests\Integration\ExtraServer;
use Tests\Integration\IntegrationTestCase;

/**
 * What a new translation takes from the main language (000-000-0075).
 *
 * `doInsert()` fills the `i18n_universal` fields a translation does not send from the record in
 * the main language — the first entry of `APP_LANGUAGES`. The template configures no languages,
 * so the suite's server has no main language and never reaches this path. This class runs its own
 * server with `APP_LANGUAGES=de,en`.
 *
 * What it found is the current state, not the intended one: on that server a translation with an
 * id cannot be added at all — see testAddingATranslationFailsWhileLanguagesAreConfigured().
 */
class MainLanguageApiTest extends IntegrationTestCase
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

    private function createTranslation(string $id, string $lang, string $title, ?string $code = null): void
    {
        $this->pdo()->prepare(
            'INSERT INTO example_i18n (id, lang, title, code, created, modified, views, isIntern)
             VALUES (:id, :lang, :title, :code, NOW(), NOW(), 0, 0)'
        )->execute(array('id' => $id, 'lang' => $lang, 'title' => $title, 'code' => $code));

        // Deleting by id removes every language of the record.
        $this->deleteAfterTest('example_i18n', $id);
    }

    /** Inserts through the extra server and returns status and body. */
    private function insert(array $data, string $lang): array
    {
        [$status, $body] = $this->onServer(self::$server->url(), fn () => $this->postJson('/api/insert', array(
            'entity' => 'Core\\ExampleI18n',
            'lang'   => $lang,
            'data'   => $data,
        ), $this->login()));

        if (isset($data['id'])) {
            $this->deleteAfterTest('example_i18n', $data['id']);
            $this->pdo()->prepare('DELETE FROM pim_log WHERE model_id = ?')->execute(array($data['id']));
        }

        return array($status, $body);
    }

    /** @return array<string,mixed>|false */
    private function row(string $id, string $lang): array|false
    {
        $statement = $this->pdo()->prepare('SELECT code FROM example_i18n WHERE id = ? AND lang = ?');
        $statement->execute(array($id, $lang));

        return $statement->fetch(\PDO::FETCH_ASSOC);
    }

    public function testTheServerHasAMainLanguage(): void
    {
        [$status, $raw] = $this->onServer(self::$server->url(), fn () => $this->get('/api/schema', $this->login()));

        $this->assertSame(200, $status);
        $this->assertSame(array('de', 'en'), json_decode($raw, true)['meta']['frontend']['languages'],
            'Precondition: APP_LANGUAGES reaches the application — de is the main language');
    }

    /**
     * Current state, and a finding: with a main language, adding a translation fails.
     *
     * Before it copies the universal fields, doInsert() reads the record in the main language
     * through getSingle(…, clearEM: true), and that calls `$this->em->clear($entityFullName)` —
     * the partial clear of Doctrine ORM 2. ORM 3 (epic 010) dropped the argument: `clear()` takes
     * none, PHP ignores the extra one, and the WHOLE entity manager is cleared. The logged-in user
     * goes with it, and the flush then finds `userCreated` pointing to a user it does not know.
     *
     * The path that should copy `code` from the German record is therefore not reached, and a
     * project with languages cannot add a translation to an existing record at all.
     */
    public function testAddingATranslationFailsWhileLanguagesAreConfigured(): void
    {
        $id = 'ml-'.bin2hex(random_bytes(6));
        $this->createTranslation($id, 'de', 'German', 'CODE-'.$id);

        [$status, $body] = $this->insert(array('id' => $id, 'title' => 'English'), 'en');

        $this->assertSame(500, $status, 'Current state: the insert of the translation fails');
        $this->assertErrorEnvelope($body);
        $this->assertFalse($this->row($id, 'en'), 'and writes no row');
        $this->assertSame('CODE-'.$id, $this->row($id, 'de')['code'], 'The German record stays as it was');
    }

    public function testItFailsWithoutARecordInTheMainLanguageAsWell(): void
    {
        // Not the copying fails, the clearing before it: it runs whenever the translation carries
        // an id, whether a German record exists or not.
        $id = 'ml-'.bin2hex(random_bytes(6));

        [$status] = $this->insert(array('id' => $id, 'title' => 'English only'), 'en');

        $this->assertSame(500, $status, 'Current state');
        $this->assertFalse($this->row($id, 'en'));
    }

    public function testANewRecordWithoutAnIdIsNotAffected(): void
    {
        [$status, $body] = $this->insert(array('title' => 'Brand new'), 'en');

        $this->assertSame(200, $status, json_encode($body));
        $this->deleteAfterTest('example_i18n', $body['data']['id']);
        $this->pdo()->prepare('DELETE FROM pim_log WHERE model_id = ?')->execute(array($body['data']['id']));

        $this->assertNull($this->row($body['data']['id'], 'en')['code'], 'Without an id there is no record to take anything from');
    }

    public function testTheMainLanguageItselfTakesNothing(): void
    {
        $id = 'ml-'.bin2hex(random_bytes(6));
        $this->createTranslation($id, 'en', 'English', 'FROM-EN');

        [$status, $body] = $this->insert(array('id' => $id, 'title' => 'German'), 'de');

        $this->assertSame(200, $status, json_encode($body));
        $this->assertNull($this->row($id, 'de')['code'],
            'Only a translation inherits; the main language is the source, not a copy');
    }
}
