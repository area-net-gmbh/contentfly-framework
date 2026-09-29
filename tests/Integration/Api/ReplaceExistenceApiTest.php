<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * `/api/replace` no longer says whether a record exists (`015-000-0022`).
 *
 * THE FINDING. `replaceAction` took `entity` and `id` from the body and asked the repository
 * straight away, without any right being checked. It then branched: `/api/update` when the record
 * exists, `/api/insert` when it does not. Both sub-requests do check the rights — but with
 * DIFFERENT messages, `contentfly_general_access_denied` on one path and
 * `contentfly_general_permission_denied` on the other.
 *
 * So the refusal answered the question the caller was not allowed to ask. Every logged-in user
 * could enumerate, for an entity they may not read, which ids exist — and with the default id
 * strategy `auto`, which counts up, how many records there are. The answer never carried content;
 * it did not have to.
 *
 * WHAT IS MEASURED is that the two cases are indistinguishable: same status, same error code. Not
 * merely that each is refused — they were both refused before, and that was exactly the problem.
 */
class ReplaceExistenceApiTest extends IntegrationTestCase
{
    /** The finding: the two answers must not differ in any part a caller can see. */
    public function testAnExistingAndAMissingIdAreAnsweredAlike(): void
    {
        [$token] = $this->createTestUser(array());  // no rights on anything

        $existing = $this->anExampleRecord();

        [$statusExisting, $bodyExisting] = $this->replace($existing, $token);
        [$statusMissing, $bodyMissing]   = $this->replace('does-not-exist-'.bin2hex(random_bytes(6)), $token);

        $this->assertSame($statusMissing, $statusExisting, 'The status must not depend on existence');
        $this->assertSame(
            $this->errorCode($bodyMissing),
            $this->errorCode($bodyExisting),
            'and neither must the error code — that difference WAS the oracle'
        );
    }

    /** And the refusal is a refusal, not a 500 out of an undefined array key. */
    public function testTheRefusalIsAnOrdinaryAccessDenied(): void
    {
        [$token] = $this->createTestUser(array());

        [$status, $body] = $this->replace($this->anExampleRecord(), $token);

        $this->assertSame(403, $status, json_encode($body['errors'] ?? $body));
        $this->assertErrorEnvelope($body, 'contentfly_general_permission_denied');
    }

    /**
     * An entity that does not exist in the schema is refused too, and says so.
     *
     * Before the check moved to the front, `$schema[$entityName]` was read for whatever the
     * caller sent — an undefined key, and the answer came out of PHP rather than the application.
     */
    public function testAnUnknownEntityIsRefusedCleanly(): void
    {
        [$token] = $this->createTestUser(array());

        [$status, $body] = $this->postJson('/api/replace', array(
            'entity' => 'Core\\NoSuchEntityAtAll',
            'id'     => 'whatever',
            'data'   => array('name' => 'x'),
        ), $token);

        $this->assertNotSame(500, $status, json_encode($body['errors'] ?? $body));
        $this->assertContains($status, array(403, 404), 'A refusal or a not-found, not a crash');
    }

    // ── helpers ────────────────────────────────────────────────────────────────────────────

    /** @return array{0:int,1:array} */
    private function replace(string $id, string $token): array
    {
        return $this->postJson('/api/replace', array(
            'entity' => 'Core\\Example',
            'id'     => $id,
            'data'   => array('name' => 'replaced-'.bin2hex(random_bytes(4))),
        ), $token);
    }

    /** A record of the template's example entity, created past the API. */
    private function anExampleRecord(): string
    {
        $id = 'rep-'.bin2hex(random_bytes(6));

        $this->pdo()->prepare(
            'INSERT INTO example_entity (id, name, created, modified, views, isIntern)
             VALUES (:id, :name, NOW(), NOW(), 0, 0)'
        )->execute(array('id' => $id, 'name' => 'existing-'.bin2hex(random_bytes(4))));

        $this->deleteAfterTest('example_entity', $id);

        return $id;
    }

    private function errorCode(array $body): ?string
    {
        return $body['errors']['code'] ?? ($body['errors'][0]['code'] ?? null);
    }
}
