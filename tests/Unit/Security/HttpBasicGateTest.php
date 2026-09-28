<?php
namespace Tests\Unit\Security;

use Areanet\PIM\Classes\Security\HttpBasicGate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * One correct value used to be enough (`015-000-0007`).
 *
 * The gate in `bootstrap-web.php` read
 *
 *     if ($user != $expectedUser && $password != $expectedPassword)
 *
 * and refused only when **both** were wrong. `Basic base64("staging:anything")` therefore walked
 * past a gate whose user is `staging`, and `base64("anyone:the-real-password")` did too. Behind
 * it sit `/auth/login`, `/auth/refresh`, `/api/config`, `/file/get/*` and every custom route with
 * `isSecure = false`.
 *
 * The two rows marked below are the ones that used to pass. They are the finding; everything
 * else in this class keeps the rest of the door honest.
 */
class HttpBasicGateTest extends TestCase
{
    private const USER = 'staging';
    private const PASS = 'the-real-password';

    /** @return array<string, array{0: ?string, 1: ?string, 2: bool}> */
    public static function attempts(): array
    {
        return array(
            'both correct'                      => array(self::USER, self::PASS, true),
            'right user, wrong password'        => array(self::USER, 'anything', false),      // walked through
            'wrong user, right password'        => array('anyone', self::PASS, false),        // walked through
            'both wrong'                        => array('anyone', 'anything', false),
            'right user, empty password'        => array(self::USER, '', false),
            'empty user, right password'        => array('', self::PASS, false),
            'both empty'                        => array('', '', false),
            'user null'                         => array(null, self::PASS, false),
            'password null'                     => array(self::USER, null, false),
            'both null'                         => array(null, null, false),
            'password with trailing space'      => array(self::USER, self::PASS.' ', false),
            'user differing in case'            => array('Staging', self::PASS, false),
        );
    }

    #[DataProvider('attempts')]
    public function testOnlyBothValuesTogetherOpenTheGate(?string $user, ?string $password, bool $expected): void
    {
        $this->assertSame($expected, HttpBasicGate::isAuthorised($user, $password, self::USER, self::PASS));
    }

    /**
     * FAIL CLOSED. A gate with a user but no password admitted anybody who knew the user name —
     * the same finding once more. Now it admits nobody, and the register entry says so, because
     * it locks out an installation that upgrades with half a configuration.
     *
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public static function halfConfigurations(): array
    {
        return array(
            'password missing' => array(self::USER, null),
            'password empty'   => array(self::USER, ''),
            'user missing'     => array(null, self::PASS),
            'user empty'       => array('', self::PASS),
            'nothing set'      => array(null, null),
        );
    }

    #[DataProvider('halfConfigurations')]
    public function testAHalfConfiguredGateLetsNobodyThrough(?string $expectedUser, ?string $expectedPassword): void
    {
        $this->assertFalse(HttpBasicGate::isAuthorised(self::USER, self::PASS, $expectedUser, $expectedPassword));
        $this->assertFalse(HttpBasicGate::isAuthorised('', '', $expectedUser, $expectedPassword));
        $this->assertFalse(HttpBasicGate::isAuthorised(null, null, $expectedUser, $expectedPassword));
    }

    // ── the header ─────────────────────────────────────────────────────────────────────────

    public function testACompleteHeaderIsTakenApart(): void
    {
        $this->assertSame(
            array('staging', 'the-real-password'),
            HttpBasicGate::credentials('Basic '.base64_encode('staging:the-real-password'))
        );
    }

    /** A password may contain colons — the split takes the first one only. */
    public function testAPasswordMayContainColons(): void
    {
        $this->assertSame(
            array('staging', 'a:b:c'),
            HttpBasicGate::credentials('Basic '.base64_encode('staging:a:b:c'))
        );
    }

    /** The scheme is matched case-insensitively, as RFC 7617 requires. */
    public function testTheSchemeMayBeWrittenInAnyCase(): void
    {
        $this->assertSame(
            array('staging', 'x'),
            HttpBasicGate::credentials('bAsIc '.base64_encode('staging:x'))
        );
    }

    /** @return array<string, array{0: ?string}> */
    public static function headersThatAreNoPair(): array
    {
        return array(
            'no header'          => array(null),
            'empty'             => array(''),
            'another scheme'    => array('Bearer '.'abcdefgh'),
            'no payload'        => array('Basic '),
            'not base64'        => array('Basic !!!not-base64!!!'),
            'no colon'          => array('Basic '.\base64_encode('staging')),
            'scheme only'       => array('Basic'),
        );
    }

    /**
     * A payload without a colon is not a pair with an empty password — it is not a pair at all.
     * `explode(':')` on it used to yield a single element, and `list()` read the second as null
     * while warning about it.
     */
    #[DataProvider('headersThatAreNoPair')]
    public function testAnythingThatIsNoPairYieldsNothing(?string $header): void
    {
        $this->assertSame(array(null, null), HttpBasicGate::credentials($header));
    }

    /** And nothing that yields no pair opens the gate. */
    #[DataProvider('headersThatAreNoPair')]
    public function testAnythingThatIsNoPairIsRefused(?string $header): void
    {
        [$user, $password] = HttpBasicGate::credentials($header);

        $this->assertFalse(HttpBasicGate::isAuthorised($user, $password, self::USER, self::PASS));
    }

    /** The whole way through, as `bootstrap-web.php` walks it. */
    public function testTheCorrectHeaderOpensTheGate(): void
    {
        [$user, $password] = HttpBasicGate::credentials('Basic '.base64_encode(self::USER.':'.self::PASS));

        $this->assertTrue(HttpBasicGate::isAuthorised($user, $password, self::USER, self::PASS));
    }
}
