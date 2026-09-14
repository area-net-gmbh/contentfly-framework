<?php
namespace Tests\Unit\Security;

use Areanet\PIM\Classes\Config;
use Areanet\PIM\Classes\Config\Factory;
use Areanet\PIM\Classes\Security\JwtAccessToken;
use Areanet\PIM\Entity\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\Framework\TestCase;

/**
 * The claim set and the issuing (013-003-0001).
 *
 * Until this task the `TokenHandler` could verify JWTs, but nobody issued any — so there was no
 * set to verify against either. These tests record what an issued token carries, and above
 * all **what it does not**.
 */
class JwtAccessTokenTest extends TestCase
{
    private const SECRET = 'test-secret-with-at-least-32-bytes-length';

    protected function setUp(): void
    {
        $config = new Config();
        $config->SECURITY_JWT_SECRET = self::SECRET;
        $config->SECURITY_JWT_TTL    = 900;

        Factory::getInstance()->setConfig($config);
    }

    protected function tearDown(): void
    {
        Factory::getInstance()->setConfig(new Config());
    }

    private function user(string $alias = 'admin', bool $admin = false): User
    {
        $user = new User();
        $user->setAlias($alias);
        $user->setIsAdmin($admin);

        return $user;
    }

    private function claims(string $token): array
    {
        return (array) JWT::decode($token, new Key(self::SECRET, JwtAccessToken::ALGORITHM));
    }

    // ── The claim set ──────────────────────────────────────────────────────────────────

    public function testATokenCarriesExactlyTheFiveDefinedClaims(): void
    {
        $names = array_keys($this->claims(JwtAccessToken::issue($this->user())['token']));
        sort($names);

        $expected = JwtAccessToken::CLAIMS;
        sort($expected);

        $this->assertSame($expected, $names, 'Exactly these five — a sixth one is caught here');
    }

    /**
     * **The most important test in this file.**
     *
     * Roles, groups and permissions can change while the token is valid. If they were in it, a
     * change of rights would only take effect after it expires — the classic mistake when moving
     * to stateless tokens, and one that only shows when someone's right is revoked and it does
     * not take effect.
     *
     * The forbidden names deliberately include the German spellings `rolle` and `gruppe`.
     */
    public function testNoPermissionAndNoRoleIsInTheToken(): void
    {
        $admin = $this->user('boss', true);

        $raw = JwtAccessToken::issue($admin)['token'];

        $this->assertStringNotContainsStringIgnoringCase('ROLE_', base64_decode(strtr(explode('.', $raw)[1], '-_', '+/')));

        foreach (array('roles', 'rolle', 'group', 'gruppe', 'permissions', 'isAdmin') as $forbidden) {
            $this->assertArrayNotHasKey($forbidden, $this->claims($raw), $forbidden.' does not belong in the token');
        }
    }

    public function testTheIdentifierIsInSub(): void
    {
        $claims = $this->claims(JwtAccessToken::issue($this->user('editor'))['token']);

        $this->assertSame('editor', $claims['sub']);
    }

    public function testTheIssuerIsInIss(): void
    {
        $claims = $this->claims(JwtAccessToken::issue($this->user())['token']);

        $this->assertSame(JwtAccessToken::ISSUER, $claims['iss']);
    }

    /**
     * The `jti` is the identifier of this **one** token — the handle for revocation in
     * `013-003-0003`. Two tokens of the same user carry different ones.
     */
    public function testEveryTokenCarriesItsOwnJti(): void
    {
        $user = $this->user();

        $one = JwtAccessToken::issue($user);
        $two = JwtAccessToken::issue($user);

        $this->assertNotSame($one['jti'], $two['jti']);
        $this->assertSame($one['jti'], $this->claims($one['token'])['jti']);
    }

    // ── Lifetime ───────────────────────────────────────────────────────────────────────

    public function testTheLifetimeComesFromTheConfiguration(): void
    {
        $config = new Config();
        $config->SECURITY_JWT_SECRET = self::SECRET;
        $config->SECURITY_JWT_TTL    = 300;
        Factory::getInstance()->setConfig($config);

        $access = JwtAccessToken::issue($this->user());

        $this->assertEqualsWithDelta(time() + 300, $access['exp'], 2);
    }

    /**
     * A nonsensical value falls back to the default instead of building a token without expiry.
     */
    public function testANonsensicalLifetimeFallsBackToTheDefault(): void
    {
        $config = new Config();
        $config->SECURITY_JWT_SECRET = self::SECRET;
        $config->SECURITY_JWT_TTL    = 0;
        Factory::getInstance()->setConfig($config);

        $this->assertSame(900, JwtAccessToken::ttl());
    }

    // ── Without a secret ───────────────────────────────────────────────────────────────

    public function testWithoutASecretNothingIsConfigured(): void
    {
        Factory::getInstance()->setConfig(new Config());

        $this->assertFalse(JwtAccessToken::isConfigured());
    }

    /**
     * Thrown, not continued with a substitute.
     *
     * A default key stored in the code would be no key, and an application that silently does
     * something other than what was asked is worse than one that stops.
     */
    public function testWithoutASecretNoTokenIsIssued(): void
    {
        Factory::getInstance()->setConfig(new Config());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/SECURITY_JWT_SECRET/');

        JwtAccessToken::issue($this->user());
    }
}
