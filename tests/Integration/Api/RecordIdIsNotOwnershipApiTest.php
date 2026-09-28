<?php
namespace Tests\Integration\Api;

use Areanet\PIM\Entity\Permission;
use Tests\Integration\IntegrationTestCase;

/**
 * A record whose id equals the caller's user id is not the caller's (`015-000-0009`).
 *
 * THE FINDING, END TO END. `Base::hasUserId()` ended with `|| $this->id == $id` — the caller's
 * user id compared against the record's primary key. With the installer's default id strategy
 * `auto` both are plain integers counted per table, so user 7 owned record 7 in every entity.
 *
 * Reproduced here without relying on that strategy: the probe record is given the id the test
 * user already has. The column is a string under either strategy, so the mechanism is the same
 * and the test does not need an installation of its own. That also makes the point that the
 * finding is not limited to `auto` — any installation where a record id happens to equal a user
 * id was affected, and ids may be assigned by a client.
 *
 * Read, change and delete are measured separately, because each goes through a different check
 * (`getSingle()`, `doUpdate()`, `doDelete()`) and all three rested on the same method.
 */
class RecordIdIsNotOwnershipApiTest extends IntegrationTestCase
{
    public function testAForeignRecordCarryingTheOwnUserIdCannotBeRead(): void
    {
        [$token, $userId] = $this->userWithOwnRights();
        $this->foreignTagWithId($userId);

        [$status, $body] = $this->postJson('/api/single', array('entity' => 'PIM\\Tag', 'id' => $userId), $token);

        $this->assertSame(403, $status, json_encode($body['errors'] ?? $body));
        $this->assertErrorEnvelope($body, 'contentfly_general_access_denied');
    }

    public function testAForeignRecordCarryingTheOwnUserIdCannotBeChanged(): void
    {
        [$token, $userId] = $this->userWithOwnRights();
        $this->foreignTagWithId($userId);
        $before = $this->tagTitle($userId);

        [$status, $body] = $this->postJson('/api/update', array(
            'entity' => 'PIM\\Tag', 'id' => $userId, 'data' => array('title' => 'taken over'),
        ), $token);

        $this->assertSame(403, $status, json_encode($body['errors'] ?? $body));
        $this->assertSame($before, $this->tagTitle($userId), 'and the record is untouched');
    }

    public function testAForeignRecordCarryingTheOwnUserIdCannotBeDeleted(): void
    {
        [$token, $userId] = $this->userWithOwnRights();
        $this->foreignTagWithId($userId);
        $before = $this->tagTitle($userId);

        [$status, $body] = $this->postJson('/api/delete', array('entity' => 'PIM\\Tag', 'id' => $userId), $token);

        $this->assertSame(403, $status, json_encode($body['errors'] ?? $body));
        $this->assertSame($before, $this->tagTitle($userId), 'and the record is still there');
    }

    /**
     * The other direction, so the rule is not simply "nothing works": a record the user created
     * is still theirs, and one that names them in `users` is too.
     */
    public function testTheOwnRecordsAreStillReachable(): void
    {
        [$token, $userId] = $this->userWithOwnRights();

        [$status, $created] = $this->postJson('/api/insert', array(
            'entity' => 'PIM\\Tag', 'data' => array('title' => 'mine-'.bin2hex(random_bytes(4))),
        ), $token);
        $this->assertSame(200, $status, json_encode($created['errors'] ?? $created));

        $mine = $created['data']['id'];
        $this->deleteAfterTest('pim_tag', $mine);

        $this->assertSame(200, $this->postJson('/api/single', array('entity' => 'PIM\\Tag', 'id' => $mine), $token)[0],
            'A record the user created stays readable');

        $shared = $this->foreignTagWithId('shared-'.bin2hex(random_bytes(4)), $userId);

        $this->assertSame(200, $this->postJson('/api/single', array('entity' => 'PIM\\Tag', 'id' => $shared), $token)[0],
            'and so does one that names the user in `users`');
    }

    /**
     * THE CASE THAT USED TO DEPEND ON THE BUG.
     *
     * A user reading and writing their own `PIM\User` record with OWN worked only because the
     * record's id and the caller's user id are the same number by definition — through exactly
     * the clause that was the finding. `getSingle()` now names that case for itself, the way
     * `doUpdate()` already did.
     */
    public function testTheOwnUserRecordIsStillReadableAndWritable(): void
    {
        [$token, $userId] = $this->createTestUser(array('PIM\\User' => array(
            'readable' => Permission::OWN,
            'writable' => Permission::OWN,
        )));

        $this->assertSame(200, $this->postJson('/api/single', array('entity' => 'PIM\\User', 'id' => $userId), $token)[0],
            'Reading the own account');

        [$status, $body] = $this->postJson('/api/update', array(
            'entity' => 'PIM\\User', 'id' => $userId, 'data' => array('alias' => 'renamed-'.bin2hex(random_bytes(4))),
        ), $token);

        $this->assertSame(200, $status, 'and writing it — '.json_encode($body['errors'] ?? $body));
    }

    // ── helpers ────────────────────────────────────────────────────────────────────────────

    /** @return array{0:string,1:string} token and user id */
    private function userWithOwnRights(): array
    {
        [$token, $userId] = $this->createTestUser(array('PIM\\Tag' => array(
            'readable'  => Permission::OWN,
            'writable'  => Permission::OWN,
            'deletable' => Permission::OWN,
        )));

        return array($token, $userId);
    }

    /**
     * A tag created by somebody else, under the given id.
     *
     * `usercreated_id` deliberately stays null — that is "created by somebody who is not the
     * caller", and it keeps the row independent of any other account in the database.
     */
    private function foreignTagWithId(string $id, ?string $forUser = null): string
    {
        $this->pdo()->prepare(
            'INSERT INTO pim_tag (id, title, users, isIntern, views, created, modified)
             VALUES (:id, :title, :users, 0, 0, NOW(), NOW())'
        )->execute(array('id' => $id, 'title' => 'foreign-'.bin2hex(random_bytes(6)), 'users' => $forUser));

        $this->deleteAfterTest('pim_tag', $id);

        return $id;
    }

    private function tagTitle(string $id): string
    {
        return (string) $this->pdo()->query('SELECT title FROM pim_tag WHERE id = '.$this->pdo()->quote($id))->fetchColumn();
    }
}
