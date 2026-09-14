<?php
namespace Tests\Unit\Security;

use Areanet\PIM\Classes\Security\TokenAuthenticator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\AccessToken\AccessTokenExtractorInterface;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * The driver that runs Symfony's `access_token` authenticator without a firewall (013-002-0001).
 *
 * It is tested against test doubles for handler and extractor. That is half the purpose of this
 * task: the driver has to be in place and measurable **before** there is a real handler — that
 * one only arrives with `013-002-0003`.
 */
class TokenAuthenticatorTest extends TestCase
{
    /** An extractor that always returns the same value — or none. */
    private function extractor(?string $token): AccessTokenExtractorInterface
    {
        return new class($token) implements AccessTokenExtractorInterface {
            public function __construct(private ?string $token) {}

            public function extractAccessToken(Request $request): ?string
            {
                return $this->token;
            }
        };
    }

    /** A handler that returns an identifier — or throws the given exception. */
    private function handler(?string $identifier, ?AuthenticationException $error = null): AccessTokenHandlerInterface
    {
        return new class($identifier, $error) implements AccessTokenHandlerInterface {
            public function __construct(private ?string $identifier, private ?AuthenticationException $error) {}

            public function getUserBadgeFrom(string $accessToken): UserBadge
            {
                if ($this->error !== null) {
                    throw $this->error;
                }

                return new UserBadge(
                    (string) $this->identifier,
                    fn (string $identifier) => new InMemoryUser($identifier, null)
                );
            }
        };
    }

    public function testValidTokenReturnsTheUser(): void
    {
        $driver = new TokenAuthenticator($this->handler('admin'), $this->extractor('some-token'));

        $user = $driver->user(new Request());

        $this->assertNotNull($user);
        $this->assertSame('admin', $user->getUserIdentifier());
    }

    public function testWithoutTokenTheDriverDoesNotApply(): void
    {
        $driver = new TokenAuthenticator($this->handler('admin'), $this->extractor(null));

        $this->assertNull($driver->user(new Request()));
    }

    /**
     * The reason for `supports() === false` instead of `!supports()`.
     *
     * `AccessTokenAuthenticator::supports()` returns **null** when a token is present — the marker
     * for "maybe, decide later". Only `false` means "no token at all". A `!supports()` would treat
     * both cases the same and reject every request; this test fails exactly then.
     */
    public function testPresentTokenIsNotRejectedPrematurely(): void
    {
        $driver = new TokenAuthenticator($this->handler('editor'), $this->extractor('token'));

        $this->assertNotNull($driver->user(new Request()));
    }

    /**
     * Every failure looks the same: `null`.
     *
     * No token, unknown identifier, locked user, expired or tampered token — the caller only
     * learns that it was not enough. Whoever distinguishes by cause here tells them which kind of
     * token is expected and which accounts exist.
     */
    public function testEveryFailureLooksTheSame(): void
    {
        $cases = array(
            'unknown identifier' => new UserNotFoundException(),
            'expired'            => new CustomUserMessageAuthenticationException('expired'),
            'tampered'           => new CustomUserMessageAuthenticationException('wrong signature'),
        );

        foreach ($cases as $name => $error) {
            $driver = new TokenAuthenticator($this->handler(null, $error), $this->extractor('token'));

            $this->assertNull($driver->user(new Request()), $name.' must fail like everything else');
        }
    }
}
