<?php
namespace Tests\Unit\Security;

use Areanet\PIM\Classes\Security\UserLoader;
use Areanet\PIM\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

/**
 * The UserLoader (013-002-0001).
 *
 * It is the counterpart to the TokenHandler: that one returns an identifier, this one turns it
 * into a user. It is tested against a repository test double — the query itself is one line of
 * Doctrine, the behaviour around it is the point.
 */
class UserLoaderTest extends TestCase
{
    private function loader(?User $found): UserLoader
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($found);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        return new UserLoader($em);
    }

    private function user(string $alias, bool $active = true): User
    {
        $user = new User();
        $user->setAlias($alias);
        $user->setIsActive($active);

        return $user;
    }

    public function testKnownUserIsLoaded(): void
    {
        $loaded = $this->loader($this->user('admin'))->loadUserByIdentifier('admin');

        $this->assertSame('admin', $loaded->getUserIdentifier());
    }

    public function testUnknownUserIsRejected(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->loader(null)->loadUserByIdentifier('doesnotexist');
    }

    /**
     * A locked user is treated like an unknown one, **with the same exception**.
     *
     * Not an oversight: the story requires that an invalid token fails indistinguishably. A
     * separate exception for "locked" would be an oracle for which accounts exist and which are
     * currently disabled.
     */
    public function testLockedUserIsRejectedLikeAnUnknownOne(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->loader($this->user('locked', false))->loadUserByIdentifier('locked');
    }

    public function testLoaderIsResponsibleForTheUserEntity(): void
    {
        $loader = $this->loader(null);

        $this->assertTrue($loader->supportsClass(User::class));
        $this->assertFalse($loader->supportsClass(\stdClass::class));
    }

    // ── The user as a Symfony user ─────────────────────────────────────────────────────

    public function testIdentifierIsTheAlias(): void
    {
        $this->assertSame('admin', $this->user('admin')->getUserIdentifier());
    }

    /**
     * **Only** what access control needs is mapped.
     *
     * `Permission`, `I18nPermission` and `Group` stay where they are — two permission models
     * running side by side drift apart.
     */
    public function testRolesOnlyMapAccessControl(): void
    {
        $regular = $this->user('editor');
        $this->assertSame(array('ROLE_USER'), $regular->getRoles());

        $admin = $this->user('admin');
        $admin->setIsAdmin(true);
        $this->assertSame(array('ROLE_USER', 'ROLE_ADMIN'), $admin->getRoles());
    }
}
