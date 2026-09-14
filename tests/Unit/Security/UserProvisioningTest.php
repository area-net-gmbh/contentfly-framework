<?php
namespace Tests\Unit\Security;

use Areanet\PIM\Classes\Security\UserProvisioning;
use Areanet\PIM\Classes\Security\ExternalIdentity;
use Areanet\PIM\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

/**
 * Provisioning without a settable password (013-004-0002).
 *
 * **Finding A-6 is what this is directed against.** `createManagedUser()` called
 * `setPass($alias)` — the password was the user name. The only mitigation was the lock "only
 * authorisable via LoginManager"; every path that bypassed it was a trivial account takeover.
 */
class UserProvisioningTest extends TestCase
{
    /** @var list<object> */
    private array $persisted = array();

    private function provisioning(?User $existing): UserProvisioning
    {
        $this->persisted = array();

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($existing);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $em->method('persist')->willReturnCallback(function ($object) {
            $this->persisted[] = $object;
        });

        return new UserProvisioning($em);
    }

    // ── Creating ───────────────────────────────────────────────────────────────────────

    /**
     * **The core of the task.** A newly created user has no password — not a random one, but none
     * at all.
     */
    public function testNewUserHasALockedPassword(): void
    {
        $user = $this->provisioning(null)->findOrCreate('ldap', new ExternalIdentity('jdoe'));

        $this->assertTrue($user->isPasswordLocked());
        $this->assertSame(User::PASSWORD_LOCKED, $user->getPass());
    }

    /**
     * And the probe that finding A-6 describes: the user name as password does not match.
     */
    public function testUserNameDoesNotWorkAsPassword(): void
    {
        $user = $this->provisioning(null)->findOrCreate('ldap', new ExternalIdentity('jdoe'));

        $this->assertFalse($user->isPass('jdoe'));
        $this->assertFalse($user->isPass($user->getAlias()));
        $this->assertFalse($user->isPass(User::PASSWORD_LOCKED));
        $this->assertFalse($user->isPass(''));
    }

    /**
     * The identifier of the external system is stored readably — not in an MD5 prefix.
     */
    public function testIdentifierAndOriginAreStoredReadablyInSeparateFields(): void
    {
        $user = $this->provisioning(null)->findOrCreate('ldap', new ExternalIdentity('jdoe'));

        $this->assertSame('jdoe', $user->getExternalId());
        $this->assertSame('ldap', $user->getLoginManager());
        $this->assertSame('ldap:jdoe', $user->getAlias());
        $this->assertStringNotContainsString(md5('ldap'), (string) $user->getAlias());
    }

    /**
     * Two providers, the same identifier, two accounts.
     *
     * That is exactly what the MD5 prefix used to do — only unreadably.
     */
    public function testTwoProvidersWithTheSameIdentifierYieldTwoAccounts(): void
    {
        $one   = $this->provisioning(null)->findOrCreate('ldap', new ExternalIdentity('smith'));
        $other = $this->provisioning(null)->findOrCreate('saml', new ExternalIdentity('smith'));

        $this->assertNotSame($one->getAlias(), $other->getAlias());
        $this->assertSame('ldap:smith', $one->getAlias());
        $this->assertSame('saml:smith', $other->getAlias());
    }

    public function testNewUserGetsNoAdminRights(): void
    {
        $user = $this->provisioning(null)->findOrCreate('ldap', new ExternalIdentity('jdoe'));

        $this->assertFalse((bool) $user->getIsAdmin());
        $this->assertTrue((bool) $user->getIsActive());
    }

    // ── Finding again ──────────────────────────────────────────────────────────────────

    public function testExistingUserIsFoundAgain(): void
    {
        $existing = new User();
        $existing->setAlias('ldap:jdoe');
        $existing->setExternalId('jdoe');
        $existing->setLoginManager('ldap');

        $found = $this->provisioning($existing)->findOrCreate('ldap', new ExternalIdentity('jdoe'));

        $this->assertSame($existing, $found);
        $this->assertSame(array(), $this->persisted, 'Nothing created');
    }

    /**
     * An existing user whose password was set by a human is not locked.
     *
     * The case is not far-fetched: an administrator can assign a provider to an account that
     * already existed. The lock belongs to **creating**, not to logging in.
     */
    public function testExistingUserIsNotLockedRetroactively(): void
    {
        $existing = new User();
        $existing->setAlias('ldap:boss');
        $existing->setPass('a-real-password');

        $found = $this->provisioning($existing)->findOrCreate('ldap', new ExternalIdentity('boss'));

        $this->assertFalse($found->isPasswordLocked());
        $this->assertTrue($found->isPass('a-real-password'));
    }

    // ── Handling the locked hash ───────────────────────────────────────────────────────

    /**
     * A locked password is not rehashed — it is not supposed to become one.
     *
     * Without this check `needsRehash()` would take the asterisk for a legacy-format hash and the
     * login would try to replace it with the presented password.
     */
    public function testLockedPasswordIsNotRehashed(): void
    {
        $user = $this->provisioning(null)->findOrCreate('ldap', new ExternalIdentity('jdoe'));

        $this->assertFalse($user->needsRehash());
    }
}
