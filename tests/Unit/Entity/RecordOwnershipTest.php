<?php
namespace Tests\Unit\Entity;

use Areanet\PIM\Entity\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * `hasUserId()` answers about the `users` column, and about nothing else (`015-000-0009`).
 *
 * THE FINDING. The method ended with
 *
 *     return in_array($id, $ids) || $this->id == $id;
 *
 * and that second comparison put the CALLER'S user id next to the PRIMARY KEY OF THE RECORD —
 * two numbers from different tables with nothing to do with each other. With the installer's
 * default id strategy `auto` both are plain integers counted per table, so user 7 matched
 * record 7 in every entity.
 *
 * Every ownership check in the application rests on this one method, so each of them handed a
 * non-admin with OWN exactly one foreign record: `getSingle()`, `doUpdate()`, `doDelete()`, the
 * join, multijoin, checkbox, file and onejoin types, and `FileController`.
 *
 * `hasGroupId()` never had the clause, and neither has the SQL side: the `FIND_IN_SET` filters
 * of list, all and count look at `userCreated` and `users`. What is held here is that the two
 * sides now say the same thing.
 */
class RecordOwnershipTest extends TestCase
{
    /**
     * THE FINDING ITSELF. Before the fix this returned true.
     */
    public function testTheRecordsOwnIdIsNotAUser(): void
    {
        $record = new Tag();
        $record->setId('7');

        $this->assertFalse($record->hasUserId('7'),
            'A record whose id equals the caller user id is not thereby the caller\'s');
    }

    /** And it stays false when the column names somebody else. */
    public function testTheRecordsOwnIdIsNotAUserEvenWithOtherUsersListed(): void
    {
        $record = new Tag();
        $record->setId('7');
        $record->setUsers('3,9');

        $this->assertFalse($record->hasUserId('7'));
        $this->assertTrue($record->hasUserId('3'), 'while a listed user still matches');
        $this->assertTrue($record->hasUserId('9'));
    }

    /** The same with the id shape the guid strategy produces. */
    public function testTheSameHoldsForAUuidId(): void
    {
        $id     = '12dd32a0-ea65-4a0d-8869-5d08a6e48f8f';
        $record = new Tag();
        $record->setId($id);

        $this->assertFalse($record->hasUserId($id));
    }

    // ── what the column does say ───────────────────────────────────────────────────────────

    /** @return array<string, array{0: ?string, 1: mixed, 2: bool}> users column, caller, expected */
    public static function memberships(): array
    {
        return array(
            'single entry, match'        => array('5', '5', true),
            'single entry, no match'     => array('5', '6', false),
            'first of several'           => array('5,6,7', '5', true),
            'middle of several'          => array('5,6,7', '6', true),
            'last of several'            => array('5,6,7', '7', true),
            'none of several'            => array('5,6,7', '8', false),
            'column empty'               => array('', '5', false),
            'column null'                => array(null, '5', false),
            'caller as integer'          => array('5,6', 5, true),
            'uuid entry'                 => array('12dd32a0-ea65-4a0d-8869-5d08a6e48f8f', '12dd32a0-ea65-4a0d-8869-5d08a6e48f8f', true),
            'prefix is not a member'     => array('55', '5', false),
            'suffix is not a member'     => array('55', '5', false),
        );
    }

    #[DataProvider('memberships')]
    public function testTheColumnDecidesMembership(?string $users, mixed $caller, bool $expected): void
    {
        $record = new Tag();
        $record->setId('the-record');
        $record->setUsers($users);

        $this->assertSame($expected, $record->hasUserId($caller));
    }

    /**
     * An integer caller and a string column are the same value — `FIND_IN_SET` compares strings,
     * and making the comparison strict must not change that. Pinned because the strictness was
     * added in the same change and would otherwise be a silent narrowing.
     */
    public function testAnIntegerCallerMatchesAStringColumn(): void
    {
        $record = new Tag();
        $record->setUsers('5,6');

        $this->assertTrue($record->hasUserId(5));
        $this->assertTrue($record->hasUserId('5'));
        $this->assertFalse($record->hasUserId(7));
    }

    /** `hasGroupId()` is the yardstick — it never had the extra clause. */
    public function testGroupsBehaveTheSameWay(): void
    {
        $record = new Tag();
        $record->setId('7');
        $record->setGroups('3');

        $this->assertFalse($record->hasGroupId('7'), 'the record id is not a group either');
        $this->assertTrue($record->hasGroupId('3'));
    }
}
