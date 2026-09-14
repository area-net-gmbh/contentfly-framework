<?php
namespace Tests\Unit\Security;

use Areanet\PIM\Classes\Config;
use Areanet\PIM\Classes\Config\Factory;
use Areanet\PIM\Classes\Security\ExternalIdentity;
use Areanet\PIM\Classes\Security\GroupMapping;
use Areanet\PIM\Entity\Group;
use Areanet\PIM\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

/**
 * What the external system says, mapped onto Contentfly groups (013-004-0003).
 *
 * Before, `createManagedUser($alias, $group, $isAdmin)` took both as arguments — every project
 * decided on its own how to get from "the user is in CN=Editorial" to a Contentfly group, and
 * the result lived in project code nobody reads any more.
 */
class GroupMappingTest extends TestCase
{
    protected function tearDown(): void
    {
        Factory::getInstance()->setConfig(new Config());
    }

    /** @param array<string, mixed> $mapping */
    private function configure(array $mapping): void
    {
        $config = new Config();
        $config->SECURITY_PROVIDER_GROUPS = $mapping;

        Factory::getInstance()->setConfig($config);
    }

    /** @param array<string, Group> $groups */
    private function mapping(array $groups = array()): GroupMapping
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturnCallback(
            static fn (array $criteria) => $groups[$criteria['name'] ?? ''] ?? null
        );

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        return new GroupMapping($em);
    }

    private function group(string $name): Group
    {
        $group = new Group();
        $group->setName($name);

        return $group;
    }

    private function user(): User
    {
        $user = new User();
        $user->setAlias('ldap:jdoe');

        return $user;
    }

    // ── Matches ────────────────────────────────────────────────────────────────────────

    public function testAMappedExternalGroupSetsTheContentflyGroup(): void
    {
        $editors = $this->group('Editors');
        $this->configure(array('ldap' => array('groups' => array('CN=Editorial' => 'Editors'))));

        $user = $this->user();
        $this->mapping(array('Editors' => $editors))
            ->apply('ldap', new ExternalIdentity('jdoe', array('CN=Editorial')), $user);

        $this->assertSame($editors, $user->getGroup());
    }

    public function testAnAdminGroupSetsTheAdminFlag(): void
    {
        $this->configure(array('ldap' => array('admin' => array('CN=Admins'))));

        $user = $this->user();
        $this->mapping()->apply('ldap', new ExternalIdentity('boss', array('CN=Admins')), $user);

        $this->assertTrue($user->getIsAdmin());
    }

    /**
     * **The order is a decision.**
     *
     * A user can be in several external groups; Contentfly knows exactly one group per user.
     * Which one wins is stated in the configuration and not left to the whims of a hash table.
     */
    public function testWithSeveralMatchesTheFirstEntryWins(): void
    {
        $first  = $this->group('First');
        $second = $this->group('Second');

        $this->configure(array('ldap' => array('groups' => array(
            'CN=A' => 'First',
            'CN=B' => 'Second',
        ))));

        $user = $this->user();
        $this->mapping(array('First' => $first, 'Second' => $second))
            ->apply('ldap', new ExternalIdentity('m', array('CN=B', 'CN=A')), $user);

        $this->assertSame($first, $user->getGroup(), 'The order of the configuration decides');
    }

    // ── No match ───────────────────────────────────────────────────────────────────────

    /**
     * **When in doubt, no rights.** A mapping that grants rights when in doubt points the wrong
     * way: the external system is supposed to justify rights, not their absence.
     */
    public function testWithoutAMatchThereAreNoAdminRights(): void
    {
        $this->configure(array('ldap' => array('admin' => array('CN=Admins'))));

        $user = $this->user();
        $user->setIsAdmin(true);

        $this->mapping()->apply('ldap', new ExternalIdentity('m', array('CN=Interns')), $user);

        $this->assertFalse($user->getIsAdmin(), 'Once an administrator is not always an administrator');
    }

    public function testWithoutAMatchTheDefaultApplies(): void
    {
        $guests = $this->group('Guests');
        $this->configure(array('ldap' => array(
            'groups' => array('CN=Editorial' => 'Editors'),
            'default' => 'Guests',
        )));

        $user = $this->user();
        $this->mapping(array('Guests' => $guests))
            ->apply('ldap', new ExternalIdentity('m', array('CN=Others')), $user);

        $this->assertSame($guests, $user->getGroup());
    }

    /**
     * Without a match and without a default the group is cleared, not left in place.
     *
     * Otherwise someone would keep the rights of a group the external system removed them from.
     */
    public function testWithoutAMatchAndWithoutADefaultTheGroupIsCleared(): void
    {
        $this->configure(array('ldap' => array('groups' => array('CN=Editorial' => 'Editors'))));

        $user = $this->user();
        $user->setGroup($this->group('Editors'));

        $this->mapping()->apply('ldap', new ExternalIdentity('m', array()), $user);

        $this->assertNull($user->getGroup());
    }

    /**
     * Without an entry for this provider nothing happens at all — no clearing either.
     *
     * Whoever configures no mapping manages the groups by hand, and then a login must not take
     * them away.
     */
    public function testWithoutAnEntryForTheProviderNothingHappens(): void
    {
        $editors = $this->group('Editors');
        $this->configure(array('saml' => array('groups' => array('X' => 'Y'))));

        $user = $this->user();
        $user->setGroup($editors);
        $user->setIsAdmin(true);

        $this->mapping()->apply('ldap', new ExternalIdentity('m', array('X')), $user);

        $this->assertSame($editors, $user->getGroup());
        $this->assertTrue($user->getIsAdmin());
    }

    // ── Misconfiguration ───────────────────────────────────────────────────────────────

    /**
     * A mapping onto a group that does not exist aborts the login.
     *
     * Ignoring it silently would mean: the user gets in and has different rights than intended
     * — and nobody finds out why.
     */
    public function testAnUnknownTargetGroupFailsLoudly(): void
    {
        $this->configure(array('ldap' => array('groups' => array('CN=Editorial' => 'DoesNotExist'))));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/DoesNotExist/');

        $this->mapping()->apply('ldap', new ExternalIdentity('m', array('CN=Editorial')), $this->user());
    }

    // ── Changes take effect on the next login ──────────────────────────────────────────

    /**
     * The same user, logged in twice, with a different mapping.
     *
     * This is exactly why roles and groups are **not** in the JWT (`013-003-0001`) — there they
     * would be frozen until expiry.
     */
    public function testAChangedMappingTakesEffectOnTheNextLogin(): void
    {
        $editors = $this->group('Editors');
        $guests  = $this->group('Guests');

        $this->configure(array('ldap' => array(
            'groups' => array('CN=Editorial' => 'Editors', 'CN=External' => 'Guests'),
            'admin'   => array('CN=Admins'),
        )));

        $mapping = $this->mapping(array('Editors' => $editors, 'Guests' => $guests));
        $user    = $this->user();

        $mapping->apply('ldap', new ExternalIdentity('m', array('CN=Editorial', 'CN=Admins')), $user);
        $this->assertSame($editors, $user->getGroup());
        $this->assertTrue($user->getIsAdmin());

        $mapping->apply('ldap', new ExternalIdentity('m', array('CN=External')), $user);
        $this->assertSame($guests, $user->getGroup());
        $this->assertFalse($user->getIsAdmin());
    }
}
