<?php
namespace Tests\Unit\Security;

use Areanet\PIM\Classes\Security\LoginProviderRegistry;
use Areanet\PIM\Classes\Security\LoginProvider;
use Areanet\PIM\Classes\Security\ExternalIdentity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * The allowlist of LoginProviders (013-004-0001).
 *
 * It replaces resolving a class name from the request. The difference is not cosmetic: "the
 * caller says what gets loaded" becomes "the caller chooses from what the operator has
 * approved".
 */
class LoginProviderRegistryTest extends TestCase
{
    private function provider(?string $identifier = 'external-1'): LoginProvider
    {
        return new class($identifier) implements LoginProvider {
            public function __construct(private ?string $identifier) {}

            public function authenticate(Request $request): ?ExternalIdentity
            {
                return $this->identifier === null ? null : new ExternalIdentity($this->identifier);
            }
        };
    }

    public function testARegisteredNameReturnsItsProvider(): void
    {
        $registry = new LoginProviderRegistry();
        $registry->register('ldap', $this->provider());

        $this->assertTrue($registry->has('ldap'));
        $this->assertInstanceOf(LoginProvider::class, $registry->get('ldap'));
    }

    /**
     * **The core:** a name nobody registered does not exist — and a class name is such a name.
     */
    public function testAnUnregisteredNameReturnsNothing(): void
    {
        $registry = new LoginProviderRegistry();
        $registry->register('ldap', $this->provider());

        $this->assertNull($registry->get('saml'));
        $this->assertNull($registry->get('Custom\\Classes\\LoginManager\\Example'));
        $this->assertNull($registry->get('Plugins\\Auth\\Ldap'));
        $this->assertNull($registry->get(null));
    }

    /**
     * An empty registry is the default state: as long as nothing is registered, there is no way
     * around the password check.
     */
    public function testAnEmptyRegistryLetsNobodyThrough(): void
    {
        $this->assertSame(array(), (new LoginProviderRegistry())->names());
        $this->assertNull((new LoginProviderRegistry())->get('ldap'));
    }

    public function testUpperAndLowerCaseDoNotMatter(): void
    {
        $registry = new LoginProviderRegistry();
        $registry->register('LDAP', $this->provider());

        $this->assertNotNull($registry->get('ldap'));
        $this->assertNotNull($registry->get(' Ldap '));
    }

    /**
     * A second entry under the same name is rejected.
     *
     * Overwriting silently would mean that the order of two lines in `custom/app.php` decides
     * which external system is checked against. Nobody notices that until it does the wrong
     * thing.
     */
    public function testASecondEntryUnderTheSameNameIsRejected(): void
    {
        $registry = new LoginProviderRegistry();
        $registry->register('ldap', $this->provider());

        $this->expectException(\LogicException::class);
        $registry->register('ldap', $this->provider());
    }

    /**
     * The entry is lazy: a provider may open a connection to an external system, and that must
     * not happen on every request.
     */
    public function testAnEntryIsOnlyBuiltWhenRetrieved(): void
    {
        $built = 0;

        $registry = new LoginProviderRegistry();
        $registry->register('ldap', function () use (&$built) {
            $built++;

            return $this->provider();
        });

        $this->assertSame(0, $built, 'Not built yet');

        $registry->get('ldap');
        $registry->get('ldap');

        $this->assertSame(1, $built, 'Built once, the same one afterwards');
    }

    public function testAClosureThatReturnsNoProviderIsRejected(): void
    {
        $registry = new LoginProviderRegistry();
        $registry->register('broken', fn () => new \stdClass());

        $this->expectException(\LogicException::class);
        $registry->get('broken');
    }

    // ── The ExternalIdentity ───────────────────────────────────────────────────────────────

    public function testAnExternalIdentityWithoutAnIdentifierDoesNotExist(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ExternalIdentity('   ');
    }

    public function testAnExternalIdentityCarriesWhatTheExternalSystemSays(): void
    {
        $identity = new ExternalIdentity('jdoe', array('Editorial'), array('mail' => 'm@example.invalid'));

        $this->assertSame('jdoe', $identity->identifier);
        $this->assertSame(array('Editorial'), $identity->groups);
        $this->assertSame('m@example.invalid', $identity->attributes['mail']);
    }
}
