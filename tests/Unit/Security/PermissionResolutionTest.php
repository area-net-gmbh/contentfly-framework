<?php
namespace Tests\Unit\Security;

use Areanet\PIM\Classes\Permission;
use Areanet\PIM\Entity\Group;
use Areanet\PIM\Entity\Permission as PermissionRow;
use Areanet\PIM\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;

/**
 * SEVERAL ROWS FOR ONE ENTITY RESOLVE TO THE MOST RESTRICTIVE LEVEL (000-000-0088).
 *
 * Until then `Classes\Permission::is()` returned the first row matching the entity name. The
 * association has no ORDER BY, and with GUID ids MySQL returns a group's rows in id order — that
 * is, at random. Which of two rows counted was decided per group by chance: measured on 20 groups
 * with the same two rows, the one inserted first came back first in 5.
 *
 * Existing data has such pairs: every write of a group's permissions before `000-000-0070` added a
 * `PIM\Tag` row at ALL next to an explicit `PIM\Tag` entry. These tests pin the resolution
 * independently of the database, in both orders.
 */
class PermissionResolutionTest extends TestCase
{
    public function testASingleRowCountsAsItIs(): void
    {
        $user = $this->userWith(array($this->row('Core\\Example', PermissionRow::OWN)));

        $this->assertSame(PermissionRow::OWN, Permission::isReadable($user, 'Core\\Example'));
    }

    public function testAnUnknownEntityIsNotReadable(): void
    {
        $user = $this->userWith(array($this->row('Core\\Example', PermissionRow::ALL)));

        $this->assertFalse(Permission::isReadable($user, 'Core\\Other'));
    }

    public function testTheMostRestrictiveOfTwoRowsCountsInEitherOrder(): void
    {
        $all  = $this->row('PIM\\Tag', PermissionRow::ALL);
        $none = $this->row('PIM\\Tag', PermissionRow::NONE);

        $this->assertSame(PermissionRow::NONE, Permission::isReadable($this->userWith(array($all, $none)), 'PIM\\Tag'));
        $this->assertSame(PermissionRow::NONE, Permission::isReadable($this->userWith(array($none, $all)), 'PIM\\Tag'));
    }

    /**
     * The constants are not ordered by what they allow: NONE 0, OWN 1, ALL 2, GROUP 3. Comparing
     * the numbers would rank GROUP above ALL and hand out every record where a group was meant.
     */
    public function testGroupRanksBetweenOwnAndAll(): void
    {
        $own   = $this->row('Core\\Example', PermissionRow::OWN);
        $group = $this->row('Core\\Example', PermissionRow::GROUP);
        $all   = $this->row('Core\\Example', PermissionRow::ALL);

        $this->assertSame(PermissionRow::OWN, Permission::isReadable($this->userWith(array($group, $own)), 'Core\\Example'));
        $this->assertSame(PermissionRow::GROUP, Permission::isReadable($this->userWith(array($all, $group)), 'Core\\Example'));
    }

    public function testEachModeIsResolvedOnItsOwn(): void
    {
        $user = $this->userWith(array(
            $this->row('Core\\Example', PermissionRow::ALL, PermissionRow::NONE, PermissionRow::ALL),
            $this->row('Core\\Example', PermissionRow::OWN, PermissionRow::ALL, PermissionRow::ALL),
        ));

        $this->assertSame(PermissionRow::OWN, Permission::isReadable($user, 'Core\\Example'));
        $this->assertSame(PermissionRow::NONE, Permission::isWritable($user, 'Core\\Example'));
        $this->assertSame(PermissionRow::ALL, Permission::isDeletable($user, 'Core\\Example'));
    }

    /** @param list<PermissionRow> $rows */
    private function userWith(array $rows): User
    {
        $group = new Group();
        $group->setPermissions(new ArrayCollection($rows));

        $user = new User();
        $user->setGroup($group);

        return $user;
    }

    private function row(string $entity, int $readable, int $writable = PermissionRow::NONE, int $deletable = PermissionRow::NONE): PermissionRow
    {
        $row = new PermissionRow();
        $row->setEntityName($entity);
        $row->setReadable($readable);
        $row->setWritable($writable);
        $row->setDeletable($deletable);

        return $row;
    }
}
