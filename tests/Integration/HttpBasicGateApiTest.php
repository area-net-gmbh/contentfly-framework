<?php
namespace Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * The HTTP Basic gate, against a server that really has it on (`015-000-0007`).
 *
 * `HttpBasicGateTest` measures the decision; this class measures the door. The suite's own server
 * runs without the gate — production is where it is switched on —, so this one starts its own
 * with `APP_HTTP_AUTH_USER` and `APP_HTTP_AUTH_PASS` set, exactly as the shipped
 * `custom/config.php` reads them.
 *
 * THE TWO CASES THAT MATTER are the right user with a wrong password and a wrong user with the
 * right password. The check refused only when both were wrong, so either of them walked straight
 * through — to `/auth/login`, `/api/config`, `/file/get/*` and every custom route with
 * `isSecure = false`.
 */
class HttpBasicGateApiTest extends IntegrationTestCase
{
    private const USER = 'staging-only';
    private const PASS = 'ci-only-basic-password';

    private static ?ExtraServer $server = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (self::$baseUrl !== null) {
            self::$server = ExtraServer::start(array(
                'APP_HTTP_AUTH_USER' => self::USER,
                'APP_HTTP_AUTH_PASS' => self::PASS,
            ));
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::$server?->stop();
        self::$server = null;

        parent::tearDownAfterClass();
    }

    /** @return array<string, array{0: ?string, 1: ?string}> */
    public static function refusedCredentials(): array
    {
        return array(
            'right user, wrong password' => array(self::USER, 'not-the-password'),
            'wrong user, right password' => array('not-the-user', self::PASS),
            'both wrong'                 => array('not-the-user', 'not-the-password'),
            'right user, no password'    => array(self::USER, ''),
            'no user, right password'    => array('', self::PASS),
            'nothing at all'             => array(null, null),
        );
    }

    #[DataProvider('refusedCredentials')]
    public function testTheGateRefuses(?string $user, ?string $password): void
    {
        [$status, $body] = $this->through('/api/config', $user, $password);

        $this->assertSame(401, $status, "The gate answered instead of the application:\n".$body);
        $this->assertStringNotContainsString('version', $body, 'and no answer of the application leaked');
    }

    public function testTheGateOpensForBothCorrectValues(): void
    {
        [$status, $body] = $this->through('/api/config', self::USER, self::PASS);

        $this->assertSame(200, $status, $body);
        $this->assertStringContainsString('version', $body, 'and the application answered');
    }

    /**
     * The routes behind the gate are the ones that need no token — that is what made the finding
     * worth its severity. `/auth/login` is the one that hands out tokens.
     */
    #[DataProvider('protectedPaths')]
    public function testEveryOpenRouteSitsBehindTheGate(string $path): void
    {
        [$status] = $this->through($path, self::USER, 'not-the-password');

        $this->assertSame(401, $status, $path.' is reachable with half a credential');
    }

    /** @return array<string, array{0: string}> */
    public static function protectedPaths(): array
    {
        return array(
            '/api/config'  => array('/api/config'),
            '/auth/login'  => array('/auth/login'),
            '/'            => array('/'),
        );
    }

    /** A malformed header is refused, not taken apart into halves. */
    public function testAHeaderWithoutAColonIsRefused(): void
    {
        [$status] = $this->throughRaw('/api/config', 'Basic '.base64_encode(self::USER));

        $this->assertSame(401, $status);
    }

    public function testAnotherSchemeIsRefused(): void
    {
        [$status] = $this->throughRaw('/api/config', 'Bearer '.base64_encode(self::USER.':'.self::PASS));

        $this->assertSame(401, $status);
    }

    // ── helpers ────────────────────────────────────────────────────────────────────────────

    /** @return array{0:int,1:string} */
    private function through(string $path, ?string $user, ?string $password): array
    {
        if ($user === null && $password === null) {
            return $this->throughRaw($path, null);
        }

        return $this->throughRaw($path, 'Basic '.base64_encode($user.':'.$password));
    }

    /** @return array{0:int,1:string} status and body */
    private function throughRaw(string $path, ?string $authorization): array
    {
        $ch = curl_init(self::$server->url().$path);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $authorization === null ? array() : array('Authorization: '.$authorization),
        ));

        $body   = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return array($status, $body);
    }
}
