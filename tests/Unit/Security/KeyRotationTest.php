<?php
namespace Tests\Unit\Security;

use Areanet\PIM\Classes\Config;
use Areanet\PIM\Classes\Config\Factory;
use Areanet\PIM\Classes\Security\TokenHandler;
use Areanet\PIM\Classes\Security\JwtAccessToken;
use Areanet\PIM\Entity\RevokedToken;
use Areanet\PIM\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * A key rotation without forced logout (013-003-0004).
 *
 * A signing secret that cannot be rotated without ending all sessions does not get rotated —
 * and that makes a leak permanent. This test plays through the full rotation, in the order an
 * operator would perform it.
 */
class KeyRotationTest extends TestCase
{
    private const OLD_KEY = 'the-old-key-with-sufficient-length-32';
    private const NEW_KEY = 'the-new-key-with-sufficient-length-32';

    protected function tearDown(): void
    {
        Factory::getInstance()->setConfig(new Config());
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function configure(array $fields): void
    {
        $config = new Config();
        $config->SECURITY_JWT_TTL = 900;

        foreach ($fields as $name => $value) {
            $config->$name = $value;
        }

        Factory::getInstance()->setConfig($config);
    }

    private function user(): User
    {
        $user = new User();
        $user->setAlias('admin');

        return $user;
    }

    /** An EntityManager that only knows the (empty) revocation list. */
    private function em(): EntityManagerInterface
    {
        $revocationList = $this->createMock(EntityRepository::class);
        $revocationList->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(
            function (string $class) use ($revocationList) {
                if ($class === RevokedToken::class) {
                    return $revocationList;
                }

                throw new \LogicException('Only the JWT branch is measured here');
            }
        );

        return $em;
    }

    private function isValid(string $token): bool
    {
        try {
            (new TokenHandler($this->em()))->getUserBadgeFrom($token);

            return true;
        } catch (AuthenticationException) {
            return false;
        }
    }

    // ── The full rotation ──────────────────────────────────────────────────────────────

    /**
     * **The proof this is about.** Three states, in the order of a real rotation.
     */
    public function testRotationKeepsRunningSessionsAlive(): void
    {
        // 1. Before: one key, one token.
        $this->configure(array('SECURITY_JWT_SECRET' => self::OLD_KEY, 'SECURITY_JWT_KEY_ID' => 'k1'));
        $oldToken = JwtAccessToken::issue($this->user())['token'];

        $this->assertTrue($this->isValid($oldToken));

        // 2. The rotation: the current value moves to PREVIOUS, a new one arrives.
        $this->configure(array(
            'SECURITY_JWT_SECRET'          => self::NEW_KEY,
            'SECURITY_JWT_KEY_ID'          => 'k2',
            'SECURITY_JWT_SECRET_PREVIOUS' => self::OLD_KEY,
            'SECURITY_JWT_KEY_ID_PREVIOUS' => 'k1',
        ));

        $this->assertTrue($this->isValid($oldToken), 'Nobody has to log in again');

        $newToken = JwtAccessToken::issue($this->user())['token'];
        $this->assertTrue($this->isValid($newToken));
        $this->assertSame('k2', $this->header($newToken)['kid'], 'Signing uses the new key');

        // 3. After the longest access JWT has expired: the old key can go.
        $this->configure(array('SECURITY_JWT_SECRET' => self::NEW_KEY, 'SECURITY_JWT_KEY_ID' => 'k2'));

        $this->assertFalse($this->isValid($oldToken), 'Now the old token fails');
        $this->assertTrue($this->isValid($newToken), 'The new one does not');
    }

    public function testKeyIdIsInTheHeader(): void
    {
        $this->configure(array('SECURITY_JWT_SECRET' => self::NEW_KEY, 'SECURITY_JWT_KEY_ID' => 'key-2026-09'));

        $header = $this->header(JwtAccessToken::issue($this->user())['token']);

        $this->assertSame('key-2026-09', $header['kid']);
    }

    /**
     * A token with an unknown key id is rejected — as indistinguishably as everything else.
     */
    public function testUnknownKeyIdIsRejected(): void
    {
        $this->configure(array('SECURITY_JWT_SECRET' => self::OLD_KEY, 'SECURITY_JWT_KEY_ID' => 'k1'));
        $token = JwtAccessToken::issue($this->user())['token'];

        // The same key, but the application no longer knows the key id k1.
        $this->configure(array('SECURITY_JWT_SECRET' => self::OLD_KEY, 'SECURITY_JWT_KEY_ID' => 'k9'));

        $this->assertFalse($this->isValid($token));
    }

    // ── Misconfiguration fails loudly ──────────────────────────────────────────────────

    /**
     * Two identical key ids are rejected.
     *
     * Otherwise one would overwrite the other in the array, and the application would silently
     * accept only one of the two keys — in the middle of a rotation, the worst possible moment for
     * a silent surprise.
     */
    public function testTwoIdenticalKeyIdsAreRejected(): void
    {
        $this->configure(array(
            'SECURITY_JWT_SECRET'          => self::NEW_KEY,
            'SECURITY_JWT_KEY_ID'          => 'k1',
            'SECURITY_JWT_SECRET_PREVIOUS' => self::OLD_KEY,
            'SECURITY_JWT_KEY_ID_PREVIOUS' => 'k1',
        ));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/must differ/');

        JwtAccessToken::verificationKeys();
    }

    public function testPreviousKeyWithoutKeyIdIsRejected(): void
    {
        $this->configure(array(
            'SECURITY_JWT_SECRET'          => self::NEW_KEY,
            'SECURITY_JWT_KEY_ID'          => 'k2',
            'SECURITY_JWT_SECRET_PREVIOUS' => self::OLD_KEY,
        ));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/SECURITY_JWT_KEY_ID_PREVIOUS/');

        JwtAccessToken::verificationKeys();
    }

    /**
     * A misconfiguration must **not** look like an invalid token.
     *
     * If the handler caught it as well, the application would answer every request with "invalid
     * token", and the operator would look for the error in their clients.
     */
    public function testMisconfigurationFailsLoudlyInsteadOfRejecting(): void
    {
        $this->configure(array('SECURITY_JWT_SECRET' => self::OLD_KEY, 'SECURITY_JWT_KEY_ID' => 'k1'));
        $token = JwtAccessToken::issue($this->user())['token'];

        $this->configure(array(
            'SECURITY_JWT_SECRET'          => self::NEW_KEY,
            'SECURITY_JWT_KEY_ID'          => 'k1',
            'SECURITY_JWT_SECRET_PREVIOUS' => self::OLD_KEY,
            'SECURITY_JWT_KEY_ID_PREVIOUS' => 'k1',
        ));

        $this->expectException(\RuntimeException::class);

        (new TokenHandler($this->em()))->getUserBadgeFrom($token);
    }

    /** @return array<string, mixed> */
    private function header(string $token): array
    {
        return json_decode(base64_decode(strtr(explode('.', $token)[0], '-_', '+/')), true);
    }
}
