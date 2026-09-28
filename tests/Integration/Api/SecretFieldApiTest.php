<?php
namespace Tests\Integration\Api;

use Areanet\PIM\Entity\Permission;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Integration\IntegrationTestCase;

/**
 * The hit count does not answer what the output refuses (`015-000-0012`).
 *
 * THE FINDING. `where.fulltext` appended `<field> LIKE '%value%'` for every string field of the
 * entity. On `PIM\User` that includes `pass` and `salt`. `toValueObject()` hides both from the
 * output — the filter ran over them all the same, and `meta.totalItems` said whether the pattern
 * matched.
 *
 * With `{"alias":"admin","fulltext":"<prefix><character>"}` a whole hash could be read character
 * by character, in about 64 x 16 requests. `%` and `_` were not escaped either, so a match could
 * be bound to a position — `_____x` asks whether the sixth character is an `x`. Accounts that
 * have not logged in since `013-001-0001` still carry a SHA-256 hash and its hex salt, and that
 * one cracks offline at GPU speed.
 *
 * WHAT IS MEASURED is the difference between a right and a wrong guess. A filter that still ran
 * but always returned the same count would also pass — and would be just as good a fix; what
 * must not survive is the difference.
 */
class SecretFieldApiTest extends IntegrationTestCase
{
    /**
     * THE ORACLE ITSELF.
     *
     * The test knows the stored hash — it reads it from the database — and asks the API once
     * with a real prefix of it and once with one that cannot match. Before the fix the two
     * answers differ, and that difference is the whole attack.
     */
    #[DataProvider('secretColumns')]
    public function testTheHitCountDoesNotDependOnTheSecret(string $column): void
    {
        [$token, $victim] = $this->readerAndVictim();

        $secret = (string) $this->userColumn($victim, $column);
        $this->assertNotSame('', $secret, 'Precondition: the column has a value to leak');

        $right = $this->countWithFulltext($token, $victim, substr($secret, 0, 6));
        $wrong = $this->countWithFulltext($token, $victim, 'zzzzzz-not-in-any-hash');

        $this->assertSame($wrong, $right,
            'A correct prefix of `'.$column.'` must not be distinguishable from a wrong one');
    }

    /** @return array<string, array{0: string}> */
    public static function secretColumns(): array
    {
        return array(
            'pass' => array('pass'),
            'salt' => array('salt'),
        );
    }

    /**
     * The wildcards belong to the query, not to the value — otherwise a match can be bound to a
     * position, which is what makes the oracle practical rather than merely possible.
     */
    public function testWildcardsInTheValueAreEscaped(): void
    {
        [$token, $victim] = $this->readerAndVictim();

        $alias = (string) $this->userColumn($victim, 'alias');

        $this->assertSame(1, $this->countWithFulltext($token, $victim, $alias),
            'Precondition: the alias is found by fulltext');
        $this->assertSame(0, $this->countWithFulltext($token, $victim, '%'),
            'A lone % matches everything as a wildcard and nothing as a character');
        $this->assertSame(0, $this->countWithFulltext($token, $victim, str_repeat('_', 3)),
            'and neither does a row of underscores');
    }

    /** A secret cannot be filtered on directly either. */
    #[DataProvider('secretColumns')]
    public function testASecretIsNotAFilter(string $column): void
    {
        [$token, $victim] = $this->readerAndVictim();

        $secret = (string) $this->userColumn($victim, $column);

        $all       = $this->countWithWhere($token, array('id' => $victim));
        $withValue = $this->countWithWhere($token, array('id' => $victim, $column => $secret));
        $withOther = $this->countWithWhere($token, array('id' => $victim, $column => 'not-the-value'));

        $this->assertSame($all, $withValue, 'The filter is ignored, not honoured');
        $this->assertSame($all, $withOther, 'and it is ignored for a wrong value in the same way');
    }

    /** Sorting and grouping by a secret are answered like a field that does not exist. */
    #[DataProvider('secretColumns')]
    public function testASecretCannotBeSortedBy(string $column): void
    {
        [$token] = $this->readerAndVictim();

        [$status, $body] = $this->postJson('/api/list', array(
            'entity' => 'PIM\\User', 'order' => array($column => 'ASC'),
        ), $token);

        $this->assertSame(400, $status, json_encode($body['errors'] ?? $body));
        $this->assertErrorEnvelope($body, 'contentfly_general_unknown_property');

        [$unknownStatus, $unknownBody] = $this->postJson('/api/list', array(
            'entity' => 'PIM\\User', 'order' => array('no-such-column' => 'ASC'),
        ), $token);

        $this->assertSame($unknownStatus, $status, 'and indistinguishable from a typo');
        $this->assertSame(
            $this->assertErrorEnvelope($unknownBody)['code'],
            $this->assertErrorEnvelope($body)['code']
        );
    }

    #[DataProvider('secretColumns')]
    public function testASecretCannotBeGroupedBy(string $column): void
    {
        [$token] = $this->readerAndVictim();

        [$status, $body] = $this->postJson('/api/list', array(
            'entity' => 'PIM\\User', 'groupBy' => $column,
        ), $token);

        $this->assertSame(400, $status, json_encode($body['errors'] ?? $body));
        $this->assertErrorEnvelope($body, 'contentfly_general_unknown_property');
    }

    /** An ordinary fulltext search keeps working — the guard must not cost the normal case. */
    public function testFulltextStillFindsAnOrdinaryField(): void
    {
        [$token, $victim] = $this->readerAndVictim();

        $alias = (string) $this->userColumn($victim, 'alias');

        $this->assertSame(1, $this->countWithFulltext($token, $victim, substr($alias, 0, 12)));
        $this->assertSame(0, $this->countWithFulltext($token, $victim, 'no-such-alias-fragment'));
    }

    // ── helpers ────────────────────────────────────────────────────────────────────────────

    /** @return array{0:string,1:string} a token that may read users, and a victim's id */
    private function readerAndVictim(): array
    {
        [$token]    = $this->createTestUser(array('PIM\\User' => array(
            'readable' => Permission::ALL,
            'writable' => Permission::OWN,
        )));
        [, $victim] = $this->createTestUser();

        return array($token, $victim);
    }

    /**
     * `totalItems` for a fulltext search narrowed to one record.
     *
     * The `id` narrows it so the count answers one question only: does the pattern match THIS
     * record. That is exactly the shape the attack uses.
     */
    private function countWithFulltext(string $token, string $id, string $fulltext): int
    {
        [$status, $body] = $this->postJson('/api/list', array(
            'entity' => 'PIM\\User',
            'where'  => array('id' => $id, 'fulltext' => $fulltext),
        ), $token);

        $this->assertSame(200, $status, json_encode($body['errors'] ?? $body));

        return (int) ($body['meta']['totalItems'] ?? -1);
    }

    private function countWithWhere(string $token, array $where): int
    {
        [$status, $body] = $this->postJson('/api/list', array(
            'entity' => 'PIM\\User', 'where' => $where,
        ), $token);

        $this->assertSame(200, $status, json_encode($body['errors'] ?? $body));

        return (int) ($body['meta']['totalItems'] ?? -1);
    }

    private function userColumn(string $id, string $column): mixed
    {
        return $this->pdo()->query("SELECT `$column` FROM pim_user WHERE id = ".$this->pdo()->quote($id))->fetchColumn();
    }
}
