<?php
namespace Tests\Unit\Security;

use Areanet\PIM\Classes\Config;
use Areanet\PIM\Classes\Config\Factory;
use Areanet\PIM\Classes\Security\TokenHandler;
use Areanet\PIM\Classes\Security\JwtAccessToken;
use Areanet\PIM\Entity\Group;
use Areanet\PIM\Entity\RevokedToken;
use Areanet\PIM\Entity\Token;
use Areanet\PIM\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * The branching TokenHandler (013-002-0003).
 *
 * Symfony allows exactly one `token_handler` per firewall and ships no chaining. The branching is
 * therefore our own code — and thus something that deserves to be tested.
 */
class TokenHandlerTest extends TestCase
{
    private const SECRET = 'test-secret-only-for-this-test-run';

    /**
     * At least 32 bytes — php-jwt 7 rejects a shorter secret for HS256, already when signing
     * ("Provided key is too short"). In the first draft of this test there was a 21-byte value
     * here, and the library did not accept it.
     */
    private const FOREIGN_SECRET = 'another-secret-with-sufficient-length';

    protected function setUp(): void
    {
        $config = new Config();
        $config->SECURITY_JWT_SECRET     = self::SECRET;
        $config->APP_TOKEN_TIMEOUT       = 1800;
        $config->APP_CHECK_TOKEN_TIMEOUT = true;

        Factory::getInstance()->setConfig($config);
    }

    /**
     * The configuration is a singleton — whatever this test sets would otherwise stay in place.
     *
     * It is reset to the default values and not to "nothing": the factory has no way to remove
     * anything, and a `default` entry pointing to `null` would be worse than one with the default
     * values.
     */
    protected function tearDown(): void
    {
        Factory::getInstance()->setConfig(new Config());
    }

    // ── Test doubles ───────────────────────────────────────────────────────────────────

    /** An EntityManager that returns a token row. */
    private function em(?Token $row, ?int $flushes = null): EntityManagerInterface
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($row);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        if ($flushes !== null) {
            $em->expects($this->exactly($flushes))->method('flush');
        }

        return $em;
    }

    /**
     * An EntityManager that answers every access to `pim_token` with an exception.
     *
     * This way "the JWT branch does not touch `pim_token`" is measured instead of claimed: if it
     * does access it, an exception is thrown here that the handler does not catch.
     *
     * **Updated with `013-003-0003`:** the revocation list lives in its own table, and the JWT
     * branch reads it on every request. That is the price of revocation, and it is a **read access
     * to a small table** as opposed to the write access to `pim_token` that was the reason for the
     * whole rework. The test double therefore distinguishes by table instead of throwing across
     * the board — otherwise the test would no longer measure what it claims to measure.
     *
     * @param list<RevokedToken> $revocations
     */
    private function emThatThrows(array $revocations = array()): EntityManagerInterface
    {
        $revocationList = $this->createMock(EntityRepository::class);
        $revocationList->method('findOneBy')->willReturnCallback(
            static function (array $criteria) use ($revocations) {
                foreach ($revocations as $revocation) {
                    if ($revocation->getJti() === ($criteria['jti'] ?? null)) {
                        return $revocation;
                    }
                }

                return null;
            }
        );

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(
            function (string $class) use ($revocationList) {
                if ($class === RevokedToken::class) {
                    return $revocationList;
                }

                throw new \LogicException('The JWT branch must not touch pim_token');
            }
        );
        $em->method('flush')->willThrowException(new \LogicException('The JWT branch must not write'));

        return $em;
    }

    private function user(string $alias = 'admin', bool $active = true): User
    {
        $user = new User();
        $user->setAlias($alias);
        $user->setIsActive($active);

        return $user;
    }

    private function row(User $user, ?\DateTime $modified = null, ?string $referrer = null): Token
    {
        $row = new Token();
        $row->setUser($user);
        $row->setReferrer($referrer);
        $row->setModified($modified ?? new \DateTime());

        return $row;
    }

    private function jwt(array $claims): string
    {
        return JWT::encode(
            $claims + array(
                'iss' => JwtAccessToken::ISSUER,
                'exp' => time() + 600,
                'jti' => bin2hex(random_bytes(16)),
            ),
            self::SECRET,
            'HS256',
            // The key id is mandatory since 013-003-0004: `JWT::decode()` selects the key by it,
            // and a token without `kid` is rejected.
            JwtAccessToken::keyId()
        );
    }

    // ── The opaque branch ──────────────────────────────────────────────────────────────

    public function testOpaqueTokenReturnsItsUser(): void
    {
        $user    = $this->user();
        $handler = new TokenHandler($this->em($this->row($user)));

        $badge = $handler->getUserBadgeFrom('some-opaque-token');

        $this->assertSame('admin', $badge->getUserIdentifier());
        $this->assertSame($user, $badge->getUser(), 'The user is already available — no second query');
    }

    public function testUnknownOpaqueTokenIsRejected(): void
    {
        $handler = new TokenHandler($this->em(null));

        $this->expectException(AuthenticationException::class);
        $handler->getUserBadgeFrom('doesnotexist');
    }

    public function testLockedUserIsRejected(): void
    {
        $handler = new TokenHandler($this->em($this->row($this->user('locked', false))));

        $this->expectException(AuthenticationException::class);
        $handler->getUserBadgeFrom('some-opaque-token');
    }

    public function testExpiredTokenIsRejected(): void
    {
        $old     = new \DateTime('-1 day');
        $handler = new TokenHandler($this->em($this->row($this->user(), $old)));

        $this->expectException(AuthenticationException::class);
        $handler->getUserBadgeFrom('expired');
    }

    /**
     * A token with a `referrer` is an API token and does not expire — as in `checkToken()`.
     */
    public function testReferrerTokenDoesNotExpire(): void
    {
        $old      = new \DateTime('-1 year');
        $handler  = new TokenHandler($this->em($this->row($this->user(), $old, 'https://example.invalid')));

        $this->assertSame('admin', $handler->getUserBadgeFrom('api-token')->getUserIdentifier());
    }

    /**
     * The timeout comes from the group if the user has one — in minutes, not seconds.
     */
    public function testGroupTimeoutOverridesConfiguredTimeout(): void
    {
        $group = new Group();
        $group->setTokenTimeout(1);

        $user = $this->user();
        $user->setGroup($group);

        $handler = new TokenHandler($this->em($this->row($user, new \DateTime('-2 minutes'))));

        $this->expectException(AuthenticationException::class);
        $handler->getUserBadgeFrom('opaque-token');
    }

    /**
     * The sliding-expiration write: a valid opaque token writes `modified` back.
     */
    public function testOpaqueBranchWritesModifiedBack(): void
    {
        $row     = $this->row($this->user(), new \DateTime('-10 minutes'));
        $before  = $row->getModified()->getTimestamp();
        $handler = new TokenHandler($this->em($row, 1));

        $handler->getUserBadgeFrom('opaque-token');

        $this->assertGreaterThan($before, $row->getModified()->getTimestamp());
    }

    public function testResolvedTokenRemainsRetrievable(): void
    {
        $row     = $this->row($this->user());
        $handler = new TokenHandler($this->em($row));

        $handler->getUserBadgeFrom('opaque-token');

        $this->assertSame($row, $handler->lastToken(), '$app[\'auth.token\'] needs the row');
    }

    // ── The JWT branch ─────────────────────────────────────────────────────────────────

    public function testJwtReturnsItsUser(): void
    {
        $handler = new TokenHandler($this->emThatThrows());

        $badge = $handler->getUserBadgeFrom($this->jwt(array('sub' => 'admin')));

        $this->assertSame('admin', $badge->getUserIdentifier());
    }

    /**
     * **The proof this is about:** the JWT branch does not touch `pim_token`.
     *
     * Measured, not claimed — the EntityManager throws on every access. This also removes the
     * sliding-expiration write that the opaque branch performs on *every* request.
     */
    public function testJwtBranchDoesNotTouchTheTokenTable(): void
    {
        $handler = new TokenHandler($this->emThatThrows());

        $handler->getUserBadgeFrom($this->jwt(array('sub' => 'admin')));

        $this->assertNull($handler->lastToken(), 'There is no row in the JWT branch');
    }

    /**
     * The badge comes back **without** its own loader — the user is fetched by the `UserLoader`
     * that the authenticator knows.
     */
    public function testJwtBadgeLeavesLoadingToTheUserLoader(): void
    {
        $handler = new TokenHandler($this->emThatThrows());

        $badge = $handler->getUserBadgeFrom($this->jwt(array('sub' => 'admin')));

        $this->assertNull($badge->getUserLoader());
    }

    public function testExpiredJwtIsRejected(): void
    {
        $expired = JWT::encode(array('sub' => 'admin', 'iss' => JwtAccessToken::ISSUER, 'exp' => time() - 10), self::SECRET, 'HS256', JwtAccessToken::keyId());
        $handler = new TokenHandler($this->emThatThrows());

        $this->expectException(AuthenticationException::class);
        $handler->getUserBadgeFrom($expired);
    }

    public function testTamperedJwtIsRejected(): void
    {
        $genuine  = $this->jwt(array('sub' => 'admin'));
        $tampered = substr($genuine, 0, -3).'aaa';
        $handler  = new TokenHandler($this->emThatThrows());

        $this->expectException(AuthenticationException::class);
        $handler->getUserBadgeFrom($tampered);
    }

    public function testJwtWithForeignSecretIsRejected(): void
    {
        $foreign = JWT::encode(array('sub' => 'admin', 'iss' => JwtAccessToken::ISSUER, 'exp' => time() + 600), self::FOREIGN_SECRET, 'HS256', JwtAccessToken::keyId());
        $handler = new TokenHandler($this->emThatThrows());

        $this->expectException(AuthenticationException::class);
        $handler->getUserBadgeFrom($foreign);
    }

    public function testJwtWithoutSubIsRejected(): void
    {
        $handler = new TokenHandler($this->emThatThrows());

        $this->expectException(AuthenticationException::class);
        $handler->getUserBadgeFrom($this->jwt(array()));
    }

    /**
     * Without a configured secret the JWT branch rejects, it does **not** skip.
     *
     * A branch that switches itself off for lack of configuration is not a check.
     */
    public function testWithoutSecretTheJwtBranchRejects(): void
    {
        $token = $this->jwt(array('sub' => 'admin'));

        $config = new Config();
        $config->SECURITY_JWT_SECRET = null;
        Factory::getInstance()->setConfig($config);

        $handler = new TokenHandler($this->emThatThrows());

        $this->expectException(AuthenticationException::class);
        $handler->getUserBadgeFrom($token);
    }

    /**
     * A secret that is too short is rejected, not waved through.
     *
     * php-jwt 7 requires at least 32 bytes for HS256 and throws a `DomainException` otherwise —
     * discovered while writing these tests. The handler catches it along with everything else; an
     * operator with a secret that is too short therefore does not get a half-working system, but
     * none at all. That is the right direction: HS256 with a short secret is guessable.
     */
    public function testTooShortSecretIsRejected(): void
    {
        $token = $this->jwt(array('sub' => 'admin'));

        $config = new Config();
        $config->SECURITY_JWT_SECRET = 'too-short';
        Factory::getInstance()->setConfig($config);

        $handler = new TokenHandler($this->emThatThrows());

        $this->expectException(AuthenticationException::class);
        $handler->getUserBadgeFrom($token);
    }

    /**
     * A foreign issuer is rejected (013-003-0001).
     *
     * The library checks signature and expiry, not the `iss`. Without our own check, any token
     * signed with the same secret would be valid here — including one that an entirely different
     * application issued for an entirely different purpose.
     */
    public function testTokenWithForeignIssuerIsRejected(): void
    {
        $foreign = JWT::encode(
            array('sub' => 'admin', 'iss' => 'another-application', 'exp' => time() + 600),
            self::SECRET,
            'HS256',
            JwtAccessToken::keyId()
        );
        $handler = new TokenHandler($this->emThatThrows());

        $this->expectException(AuthenticationException::class);
        $handler->getUserBadgeFrom($foreign);
    }

    public function testTokenWithoutIssuerIsRejected(): void
    {
        $withoutIssuer = JWT::encode(array('sub' => 'admin', 'exp' => time() + 600), self::SECRET, 'HS256', JwtAccessToken::keyId());
        $handler = new TokenHandler($this->emThatThrows());

        $this->expectException(AuthenticationException::class);
        $handler->getUserBadgeFrom($withoutIssuer);
    }

    /**
     * **A refresh token is not a JwtAccessToken (013-003-0001).**
     *
     * It is an ordinary row in `pim_token`, and until then this branch accepted any row. A refresh
     * token lives longer than an access JWT — that is its purpose — and without this check it would
     * be a long-lived master key for the entire API.
     */
    public function testRefreshTokenDoesNotOpenTheApi(): void
    {
        $row = $this->row($this->user());
        $row->setPurpose(Token::PURPOSE_REFRESH);

        $handler = new TokenHandler($this->em($row));

        $this->expectException(AuthenticationException::class);
        $handler->getUserBadgeFrom('a-refresh-token');
    }

    // ── The revocation list (013-003-0003) ─────────────────────────────────────────────

    /**
     * A revoked token is rejected **even though it is still valid**.
     *
     * That is the whole purpose of the list: otherwise a stateless token cannot be recalled as long
     * as its `exp` lies in the future.
     */
    public function testRevokedTokenIsRejectedEvenThoughStillValid(): void
    {
        $handler = new TokenHandler($this->emThatThrows());
        $token   = $this->jwt(array('sub' => 'admin'));

        // At first it is valid.
        $this->assertSame('admin', $handler->getUserBadgeFrom($token)->getUserIdentifier());

        $jti        = $handler->lastClaims()['jti'];
        $revocation = new RevokedToken();
        $revocation->setJti($jti);
        $revocation->setExpiresAt(new \DateTime('+10 minutes'));

        $revoked = new TokenHandler($this->emThatThrows(array($revocation)));

        $this->expectException(AuthenticationException::class);
        $revoked->getUserBadgeFrom($token);
    }

    public function testUnrelatedRevocationDoesNotAffectThisToken(): void
    {
        $foreign = new RevokedToken();
        $foreign->setJti('an-entirely-different-jti');
        $foreign->setExpiresAt(new \DateTime('+10 minutes'));

        $handler = new TokenHandler($this->emThatThrows(array($foreign)));

        $this->assertSame('admin', $handler->getUserBadgeFrom($this->jwt(array('sub' => 'admin')))->getUserIdentifier());
    }

    /**
     * Without a `jti` a token could not be revoked — so it is not accepted in the first place.
     */
    public function testTokenWithoutJtiIsRejected(): void
    {
        $withoutJti = JWT::encode(
            array('sub' => 'admin', 'iss' => JwtAccessToken::ISSUER, 'exp' => time() + 600),
            self::SECRET,
            'HS256',
            JwtAccessToken::keyId()
        );
        $handler = new TokenHandler($this->emThatThrows());

        $this->expectException(AuthenticationException::class);
        $handler->getUserBadgeFrom($withoutJti);
    }

    public function testClaimsRemainRetrievableForLogout(): void
    {
        $handler = new TokenHandler($this->emThatThrows());
        $handler->getUserBadgeFrom($this->jwt(array('sub' => 'admin')));

        $claims = $handler->lastClaims();

        $this->assertIsArray($claims);
        $this->assertArrayHasKey('jti', $claims);
        $this->assertArrayHasKey('exp', $claims);
    }

    public function testAfterOpaqueTokenThereAreNoClaims(): void
    {
        $handler = new TokenHandler($this->em($this->row($this->user())));
        $handler->getUserBadgeFrom('opaque-token');

        $this->assertNull($handler->lastClaims());
    }

    // ── The branching itself ───────────────────────────────────────────────────────────

    /**
     * The JOSE header has a say too, not just the two dots.
     *
     * An opaque token that a project chose itself via `addToken` may contain dots —
     * `pim_token.token` accepts any string. If the shape alone decided, it would end up in the JWT
     * branch and be rejected, even though it is in the database.
     */
    public function testOpaqueTokenWithDotsEndsUpInOpaqueBranch(): void
    {
        $user    = $this->user();
        $handler = new TokenHandler($this->em($this->row($user)));

        $badge = $handler->getUserBadgeFrom('project.api.token');

        $this->assertSame('admin', $badge->getUserIdentifier());
        $this->assertNotNull($handler->lastToken(), 'It was looked up in the table');
    }

    // ── Failing indistinguishably ──────────────────────────────────────────────────────

    /**
     * **Both branches fail with the same exception and the same message.**
     *
     * Different messages reveal which kind of token is expected, and thus which one an attacker
     * has to build. The test holds the responses against each other instead of checking each one
     * against an expected text — that way it also fails if someone changes *both*, but only one of
     * them.
     */
    public function testBothBranchesFailIndistinguishably(): void
    {
        $fromOpaque = null;
        try {
            (new TokenHandler($this->em(null)))->getUserBadgeFrom('doesnotexist');
        } catch (AuthenticationException $e) {
            $fromOpaque = $e;
        }

        $fromJwt = null;
        try {
            (new TokenHandler($this->emThatThrows()))->getUserBadgeFrom(
                JWT::encode(array('sub' => 'admin', 'iss' => JwtAccessToken::ISSUER, 'exp' => time() + 600), self::FOREIGN_SECRET, 'HS256', JwtAccessToken::keyId())
            );
        } catch (AuthenticationException $e) {
            $fromJwt = $e;
        }

        $this->assertNotNull($fromOpaque);
        $this->assertNotNull($fromJwt);
        $this->assertSame($fromOpaque::class, $fromJwt::class, 'the same exception');
        $this->assertSame($fromOpaque->getMessage(), $fromJwt->getMessage(), 'the same message');
        $this->assertSame($fromOpaque->getMessageKey(), $fromJwt->getMessageKey(), 'the same message key');
    }
}
