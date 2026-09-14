<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * The LoginThrottle on the running login (013-001-0003).
 *
 * `LoginThrottleTest` measures the mechanics without HTTP. This is about the other half: that
 * it is actually wired into `/auth/login`, that it takes effect before the password check and that
 * its response reveals nothing about the identifier.
 *
 * THE STORAGE IS CLEARED BEFORE AND AFTER EVERY TEST. The throttle counts across requests —
 * without cleanup a test would carry its used-up attempts into the next one, and the
 * order of the suite would decide the result. Conversely, the twenty
 * failed attempts from the IP test must not throttle the rest of the suite, which logs in from
 * the same address.
 *
 * This requires the test run and the test server to be on the same machine — that is how
 * `tools/ci/prepare-test-environment.sh` sets up the environment, locally as well as in the pipeline. And that
 * `APP_CACHE_DRIVER` is set to `filesystem`, the default; otherwise the counter lives in apcu or
 * memcached and this directory is empty. Both are checked instead of assumed.
 */
class LoginThrottleApiTest extends IntegrationTestCase
{
    /** Five failed attempts per identifier and minute — as in LoginThrottle::TIERS_IDENTIFIER. */
    private const LIMIT_IDENTIFIER = 5;

    /** Twenty per IP and minute. */
    private const LIMIT_IP = 20;

    private const MESSAGE = 'Too many login attempts. Please try again later.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearThrottleStorage();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    // ── Per identifier ─────────────────────────────────────────────────────────────────

    public function testAfterFiveFailedAttemptsTheIdentifierIsThrottled(): void
    {
        for ($i = 1; $i <= self::LIMIT_IDENTIFIER; $i++) {
            [$status] = $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => 'wrong'.$i));
            $this->assertSame(401, $status, "Attempt $i must still be answered as a failed attempt");
        }

        [$status, $body, $headers] = $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => 'wrong'));

        $this->assertSame(429, $status);
        $this->assertSame(self::MESSAGE, $body['message'] ?? null);
        $this->assertArrayNotHasKey('token', $body);
        $this->assertNotNull($this->header($headers, 'Retry-After'), 'How long to wait is stated in the response');
    }

    /**
     * THE THROTTLE TAKES EFFECT BEFORE THE PASSWORD CHECK.
     *
     * Proven with the CORRECT password: Whoever is throttled gets 429 and no token,
     * even though the credentials are correct. If the throttle sat behind the check, a
     * 200 would come back here — and every rejected attempt would still cost an Argon2id run.
     */
    public function testAThrottledUserDoesNotGetThroughEvenWithTheCorrectPassword(): void
    {
        for ($i = 1; $i <= self::LIMIT_IDENTIFIER; $i++) {
            $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => 'wrong'.$i));
        }

        [$status, $body] = $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => $this->pass()));

        $this->assertSame(429, $status);
        $this->assertArrayNotHasKey('token', $body);
    }

    /**
     * The throttle must not be an oracle for which accounts exist.
     *
     * A made-up identifier is throttled exactly like a real one, with the same response. If it
     * were otherwise, an attacker would only have to count when throttling starts to harvest
     * the user list.
     */
    public function testTheThrottleDoesNotRevealWhetherTheIdentifierExists(): void
    {
        for ($i = 1; $i <= self::LIMIT_IDENTIFIER; $i++) {
            [$status] = $this->postJson('/auth/login', array('alias' => 'doesnotexist', 'pass' => 'whatever'.$i));
            $this->assertSame(401, $status);
        }
        [$statusUnknown, $unknown] = $this->postJson('/auth/login', array('alias' => 'doesnotexist', 'pass' => 'whatever'));

        for ($i = 1; $i <= self::LIMIT_IDENTIFIER; $i++) {
            $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => 'wrong'.$i));
        }
        [$statusReal, $real] = $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => 'wrong'));

        $this->assertSame(429, $statusUnknown, 'A made-up identifier is throttled too');
        $this->assertSame(429, $statusReal);
        $this->assertSame($real['message'] ?? null, $unknown['message'] ?? null, 'The same response for both');
    }

    // ── Per IP ─────────────────────────────────────────────────────────────────────────

    /**
     * Changing identifiers from the same address.
     *
     * Each identifier is tried only once, so the identifier axis never binds — throttling
     * happens here solely via the address. Exactly this case ran through completely unthrottled
     * under `CHECK_LOGIN_INTERVAL`, because the interval applied per user.
     */
    public function testChangingIdentifiersFromTheSameAddressAreThrottled(): void
    {
        for ($i = 1; $i <= self::LIMIT_IP; $i++) {
            [$status] = $this->postJson('/auth/login', array('alias' => 'nobody'.$i, 'pass' => 'whatever'));
            $this->assertSame(401, $status, "Attempt $i must still be answered as a failed attempt");
        }

        [$status, $body] = $this->postJson('/auth/login', array('alias' => 'someoneelse', 'pass' => 'whatever'));

        $this->assertSame(429, $status);
        $this->assertSame(self::MESSAGE, $body['message'] ?? null);
    }

    // ── Reset ──────────────────────────────────────────────────────────────────────────

    /**
     * A successful login clears the identifier's counter.
     *
     * Measured across the limit: Four failed attempts, then a successful login, then
     * four more failed attempts. Without a reset, the second round would at the latest be
     * at eight failed attempts and therefore throttled.
     */
    public function testASuccessfulLoginClearsTheCounter(): void
    {
        for ($i = 1; $i <= self::LIMIT_IDENTIFIER - 1; $i++) {
            $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => 'wrong'.$i));
        }

        [$status, $body] = $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => $this->pass()));
        $this->assertSame(200, $status);
        $this->assertArrayHasKey('token', $body);

        for ($i = 1; $i <= self::LIMIT_IDENTIFIER - 1; $i++) {
            [$status] = $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => 'wrong'.$i));
            $this->assertSame(401, $status, "After the reset attempt $i must be let through again");
        }

        [$status] = $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => $this->pass()));
        $this->assertSame(200, $status, 'And logging in still works afterwards');
    }

    // ── Cleanup ────────────────────────────────────────────────────────────────────────

    /**
     * Like the inherited version, but with a hard precondition.
     *
     * The base class cleans up silently when it can — for it, this is hygiene. Here it is
     * the precondition of the measurement: If the storage were elsewhere, this class would check against
     * a counter it does not know, and would be green without proving anything.
     */
    protected function clearThrottleStorage(): void
    {
        // The data directory OF THE APPLICATION, not the one of the suite (007-001-0005) — see
        // IntegrationTestCase::dataDir().
        $data = self::dataDir();

        if (!is_dir($data.'/cache')) {
            $this->markTestSkipped(
                'data/cache/ is not reachable from here — the test run and the test server are '
                .'apparently not on the same machine.'
            );
        }

        $this->removeDirectory($data.'/cache/login-throttle');
    }
}
