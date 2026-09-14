<?php
namespace Tests\Unit\Security;

use Areanet\PIM\Classes\Security\ExternalIdentity;
use Areanet\PIM\Classes\Security\LdapProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Ldap\Adapter\CollectionInterface;
use Symfony\Component\Ldap\Adapter\QueryInterface;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Exception\ConnectionException;
use Symfony\Component\Ldap\LdapInterface;

/**
 * The LDAP provider (013-005-0001).
 *
 * **Limitation, explicitly:** tested against an `LdapInterface` test double, not against a
 * running directory. That measures the own logic — bind order, escaping of the filter, what goes
 * into the `ExternalIdentity` — and **not** that a real bind against an Active Directory works.
 * Decided on 2026-09-11: an OpenLDAP service in the test environment would be out of proportion
 * to what it would additionally prove.
 */
class LdapProviderTest extends TestCase
{
    /** All calls the provider made to the directory — in their order. */
    private array $calls = array();

    private function settings(array $overrides = array()): array
    {
        return $overrides + array(
            'base_dn'          => 'OU=Users,DC=example,DC=invalid',
            'filter'           => '(sAMAccountName={identifier})',
            'group_attribute' => 'memberOf',
            'search_dn'        => 'CN=service,DC=example,DC=invalid',
            'search_password'  => 'service-secret',
        );
    }

    /**
     * A directory test double.
     *
     * @param list<Entry>|null $results       null = the search throws
     * @param list<string>     $bindFailsFor  DNs whose bind fails
     */
    private function ldap(?array $results, array $bindFailsFor = array()): LdapInterface
    {
        $this->calls = array();
        $calls = &$this->calls;

        $collection = $this->createMock(CollectionInterface::class);
        $collection->method('count')->willReturn($results === null ? 0 : count($results));
        $collection->method('offsetGet')->willReturnCallback(
            static fn ($i) => $results[$i] ?? null
        );

        $query = $this->createMock(QueryInterface::class);
        if ($results === null) {
            $query->method('execute')->willThrowException(new ConnectionException('Directory unreachable'));
        } else {
            $query->method('execute')->willReturn($collection);
        }

        $ldap = $this->createMock(LdapInterface::class);
        $ldap->method('bind')->willReturnCallback(
            static function (?string $dn = null, ?string $pass = null) use (&$calls, $bindFailsFor) {
                $calls[] = array('bind', $dn, $pass);

                if (in_array((string) $dn, $bindFailsFor, true)) {
                    throw new ConnectionException('Bind rejected');
                }
            }
        );
        /*
         * `strtr()` and not `str_replace()` with arrays.
         *
         * The first draft used `str_replace`, and that processes the pairs ONE AFTER ANOTHER: the
         * last rule (`\\` -> `\\5c`) escaped once more the backslashes the previous ones had just
         * inserted — `\\28` became `\\5c28`. A bug in the test double, not in the provider, but it
         * would have made the escaping test useless. `strtr()` replaces in a single pass.
         */
        $ldap->method('escape')->willReturnCallback(
            static fn (string $value) => strtr($value, array('\\' => '\\5c', '*' => '\\2a', '(' => '\\28', ')' => '\\29'))
        );
        $ldap->method('query')->willReturnCallback(
            static function (string $dn, string $filter) use (&$calls, $query) {
                $calls[] = array('query', $dn, $filter);

                return $query;
            }
        );

        return $ldap;
    }

    private function entry(array $attributes = array('memberOf' => array('CN=Editorial,DC=example,DC=invalid'))): Entry
    {
        return new Entry('CN=John Doe,OU=Users,DC=example,DC=invalid', $attributes);
    }

    private function request(?string $alias = 'jdoe', ?string $pass = 'secret'): Request
    {
        $data = array();
        if ($alias !== null) { $data['alias'] = $alias; }
        if ($pass !== null)  { $data['pass']  = $pass; }

        return new Request(array(), $data);
    }

    // ── The successful path ────────────────────────────────────────────────────────────

    public function testASuccessfulLoginReturnsIdentifierAndGroups(): void
    {
        $provider = new LdapProvider($this->ldap(array($this->entry())), $this->settings());

        $external = $provider->authenticate($this->request());

        $this->assertInstanceOf(ExternalIdentity::class, $external);
        $this->assertSame('jdoe', $external->identifier);
        $this->assertSame(array('CN=Editorial,DC=example,DC=invalid'), $external->groups);
    }

    /**
     * **Search, then bind** — and with the DN from the directory, not with an assembled one.
     *
     * The direct bind would do without a service account, but only works as long as all users
     * sit flat in one OU. In Active Directory they do not, and login uses `sAMAccountName`, which
     * does not even appear in the DN.
     */
    public function testTheProviderBindsTwiceAndSearchesInBetween(): void
    {
        $provider = new LdapProvider($this->ldap(array($this->entry())), $this->settings());
        $provider->authenticate($this->request());

        $this->assertSame('bind', $this->calls[0][0]);
        $this->assertSame('CN=service,DC=example,DC=invalid', $this->calls[0][1], 'First the service account');

        $this->assertSame('query', $this->calls[1][0]);
        $this->assertSame('OU=Users,DC=example,DC=invalid', $this->calls[1][1]);

        $this->assertSame('bind', $this->calls[2][0]);
        $this->assertSame('CN=John Doe,OU=Users,DC=example,DC=invalid', $this->calls[2][1],
            'Then the DN from the directory');
        $this->assertSame('secret', $this->calls[2][2]);
    }

    public function testWithoutAServiceAccountTheSearchIsAnonymous(): void
    {
        $provider = new LdapProvider(
            $this->ldap(array($this->entry())),
            $this->settings(array('search_dn' => null, 'search_password' => null))
        );
        $provider->authenticate($this->request());

        $this->assertSame(array('bind', null, null), $this->calls[0]);
    }

    // ── The empty password ─────────────────────────────────────────────────────────────

    /**
     * **The most important guarantee of this class.**
     *
     * LDAP knows the "unauthenticated bind": a bind with a valid DN and an empty password counts
     * as successful — it means "I do not want to log in", not "the password is correct".
     * Whoever reads that as a login lets in anyone whose identifier they know.
     *
     * The test therefore checks both: that it is rejected, **and** that the directory was not
     * even asked.
     */
    public function testAnEmptyPasswordIsRejectedWithoutAskingTheDirectory(): void
    {
        $provider = new LdapProvider($this->ldap(array($this->entry())), $this->settings());

        $this->assertNull($provider->authenticate($this->request('jdoe', '')));
        $this->assertSame(array(), $this->calls, 'Not a single call to the directory');
    }

    public function testWithoutAnIdentifierTheLoginIsRejected(): void
    {
        $provider = new LdapProvider($this->ldap(array($this->entry())), $this->settings());

        $this->assertNull($provider->authenticate($this->request(null, 'secret')));
        $this->assertNull($provider->authenticate($this->request('   ', 'secret')));
        $this->assertSame(array(), $this->calls);
    }

    // ── The escaping ───────────────────────────────────────────────────────────────────

    /**
     * Without escaping, an identifier like `admin)(|(objectClass=*` rewrites the filter — LDAP
     * injection, the same pattern as SQL injection and just as old.
     */
    public function testTheIdentifierIsEscapedIntoTheFilter(): void
    {
        $provider = new LdapProvider($this->ldap(array($this->entry())), $this->settings());
        $provider->authenticate($this->request('admin)(|(objectClass=*', 'secret'));

        $filter = $this->calls[1][2];

        $this->assertStringNotContainsString('(|(objectClass=', $filter, 'The filter is not bent');
        $this->assertStringContainsString('\\28', $filter, 'The parenthesis is escaped');
    }

    // ── Rejections, all alike ──────────────────────────────────────────────────────────

    public function testAnUnknownIdentifierIsRejected(): void
    {
        $provider = new LdapProvider($this->ldap(array()), $this->settings());

        $this->assertNull($provider->authenticate($this->request()));
    }

    /**
     * Two results mean the filter is not unique. Guessing which one was meant would be the worst
     * of all answers.
     */
    public function testTwoResultsAreRejected(): void
    {
        $provider = new LdapProvider($this->ldap(array($this->entry(), $this->entry())), $this->settings());

        $this->assertNull($provider->authenticate($this->request()));
    }

    public function testAWrongPasswordIsRejected(): void
    {
        $ldap = $this->ldap(
            array($this->entry()),
            array('CN=John Doe,OU=Users,DC=example,DC=invalid')
        );

        $this->assertNull((new LdapProvider($ldap, $this->settings()))->authenticate($this->request()));
    }

    public function testAnUnreachableDirectoryIsRejected(): void
    {
        $provider = new LdapProvider($this->ldap(null), $this->settings());

        $this->assertNull($provider->authenticate($this->request()));
    }

    public function testARejectedServiceAccountIsRejected(): void
    {
        $ldap = $this->ldap(array($this->entry()), array('CN=service,DC=example,DC=invalid'));

        $this->assertNull((new LdapProvider($ldap, $this->settings()))->authenticate($this->request()));
    }

    // ── Groups ─────────────────────────────────────────────────────────────────────────

    public function testWithoutAGroupAttributeNoGroupsArrive(): void
    {
        $provider = new LdapProvider($this->ldap(array($this->entry(array()))), $this->settings());

        $external = $provider->authenticate($this->request());

        $this->assertInstanceOf(ExternalIdentity::class, $external);
        $this->assertSame(array(), $external->groups);
    }

    /**
     * What the directory delivers is passed on **unchanged**. It is mapped by `GroupMapping`
     * (`013-004-0003`) — rewriting anything here would mean having the mapping in two places.
     */
    public function testTheGroupsArePassedOnUnchanged(): void
    {
        $raw = array('CN=Editorial,OU=Groups,DC=example,DC=invalid', 'CN=Everyone,DC=example,DC=invalid');
        $provider = new LdapProvider($this->ldap(array($this->entry(array('memberOf' => $raw)))), $this->settings());

        $this->assertSame($raw, $provider->authenticate($this->request())->groups);
    }
}
