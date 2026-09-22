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
 * Until 000-000-0078 a translation with an id could not be added on that server at all: the read
 * of the main language cleared the whole entity manager, the logged-in user with it.
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

    private function createTranslation(string $id, string $lang, string $title, ?string $code = null, ?string $related = null): void
    {
        $this->pdo()->prepare(
            'INSERT INTO example_i18n (id, lang, title, code, related_id, related_lang, created, modified, views, isIntern)
             VALUES (:id, :lang, :title, :code, :related, :relatedLang, NOW(), NOW(), 0, 0)'
        )->execute(array(
            'id'          => $id,
            'lang'        => $lang,
            'title'       => $title,
            'code'        => $code,
            'related'     => $related,
            'relatedLang' => $related !== null ? $lang : null,
        ));

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

        $id = $data['id'] ?? $body['data']['id'] ?? null;
        if ($id !== null) {
            $this->deleteAfterTest('example_i18n', $id);
            $this->pdo()->prepare('DELETE FROM pim_log WHERE model_id = ?')->execute(array($id));
        }

        return array($status, $body);
    }

    /** @return array<string,mixed>|false */
    private function row(string $id, string $lang): array|false
    {
        $statement = $this->pdo()->prepare('SELECT code, related_id, related_lang FROM example_i18n WHERE id = ? AND lang = ?');
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
     * Inverted with 000-000-0078. It recorded a 500: getSingle(…, clearEM: true) called
     * `$this->em->clear($entityFullName)`, which since ORM 3 clears the whole entity manager.
     */
    public function testANewTranslationTakesTheUniversalFieldsOfTheMainLanguage(): void
    {
        $id = 'ml-'.bin2hex(random_bytes(6));
        $this->createTranslation($id, 'de', 'German', 'CODE-'.$id);

        [$status, $body] = $this->insert(array('id' => $id, 'title' => 'English'), 'en');

        $this->assertSame(200, $status, json_encode($body));
        $this->assertSame('CODE-'.$id, $this->row($id, 'en')['code'],
            'The translation did not send `code` and has the value of the German record');
        $this->assertSame('CODE-'.$id, $this->row($id, 'de')['code'], 'The German record stays as it was');
    }

    public function testAUniversalValueSentWithTheTranslationIsKept(): void
    {
        $id = 'ml-'.bin2hex(random_bytes(6));
        $this->createTranslation($id, 'de', 'German', 'OLD');

        [$status, $body] = $this->insert(array('id' => $id, 'title' => 'English', 'code' => 'NEW'), 'en');

        $this->assertSame(200, $status, json_encode($body));
        $this->assertSame('NEW', $this->row($id, 'en')['code'], 'What is sent wins over the main language');
        $this->assertSame('NEW', $this->row($id, 'de')['code'], 'and, being universal, reaches the main language too');
    }

    public function testAJoinTakenFromTheMainLanguagePointsToTheTranslationOfItsTarget(): void
    {
        $target = 'ml-'.bin2hex(random_bytes(6));
        $source = 'ml-'.bin2hex(random_bytes(6));

        $this->createTranslation($target, 'de', 'Target (de)');
        $this->createTranslation($target, 'en', 'Target');
        $this->createTranslation($source, 'de', 'Source (de)', null, $target);

        [$status, $body] = $this->insert(array('id' => $source, 'title' => 'Source'), 'en');

        $this->assertSame(200, $status, json_encode($body));
        $this->assertSame(array('code' => null, 'related_id' => $target, 'related_lang' => 'en'), $this->row($source, 'en'),
            'The same target, in the language being written — not the German row (000-000-0025)');
    }

    public function testWithoutARecordInTheMainLanguageNothingIsTaken(): void
    {
        $id = 'ml-'.bin2hex(random_bytes(6));

        [$status, $body] = $this->insert(array('id' => $id, 'title' => 'English only'), 'en');

        $this->assertSame(200, $status, json_encode($body));
        $this->assertSame(array('code' => null, 'related_id' => null, 'related_lang' => null), $this->row($id, 'en'));
    }

    public function testANewRecordWithoutAnIdTakesNothing(): void
    {
        [$status, $body] = $this->insert(array('title' => 'Brand new'), 'en');

        $this->assertSame(200, $status, json_encode($body));
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
