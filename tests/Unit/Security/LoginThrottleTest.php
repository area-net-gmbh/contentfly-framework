<?php
namespace Tests\Unit\Security;

use Areanet\PIM\Classes\Security\LoginThrottle;
use Areanet\PIM\Controller\AuthController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * The LoginThrottle, without HTTP (013-001-0003).
 *
 * What is covered here is the mechanics: two axes, increasing delay, reset on only one of them.
 * That it is actually wired into the login is measured by `LoginThrottleApiTest` against a
 * running instance.
 *
 * The storage is an `ArrayAdapter` — every test gets a fresh one, there is nothing to clean up,
 * and time plays no role: no test waits for a window to pass.
 */
class LoginThrottleTest extends TestCase
{
    /** Five failed attempts per identifier per minute. */
    private const IDENTIFIER_LIMIT = 5;

    /** Twenty per IP per minute — wider, because many people sit behind one address. */
    private const IP_LIMIT = 20;

    private function throttle(): LoginThrottle
    {
        return new LoginThrottle(new ArrayAdapter());
    }

    // ── The identifier axis ────────────────────────────────────────────────────────────

    public function testNothingIsThrottledInitially(): void
    {
        $this->assertNull($this->throttle()->retryAfter('admin', '10.0.0.1'));
    }

    public function testAfterTheLimitTheLoginIsThrottled(): void
    {
        $throttle = $this->throttle();

        for ($i = 1; $i <= self::IDENTIFIER_LIMIT - 1; $i++) {
            $throttle->recordFailure('admin', '10.0.0.1');
            $this->assertNull($throttle->retryAfter('admin', '10.0.0.1'), "still free after $i failed attempts");
        }

        $throttle->recordFailure('admin', '10.0.0.1');

        $this->assertNotNull($throttle->retryAfter('admin', '10.0.0.1'));
    }

    /**
     * The identifier axis applies independently of the address — otherwise it could be bypassed
     * by switching addresses, and that is exactly what botnets are for.
     */
    public function testTheIdentifierIsAlsoThrottledFromAnotherAddress(): void
    {
        $throttle = $this->throttle();

        for ($i = 1; $i <= self::IDENTIFIER_LIMIT; $i++) {
            $throttle->recordFailure('admin', '10.0.0.'.$i);
        }

        $this->assertNotNull($throttle->retryAfter('admin', '198.51.100.99'));
    }

    /**
     * Upper and lower case do not multiply the limit.
     */
    public function testTheSpellingOfTheIdentifierBypassesNothing(): void
    {
        $throttle = $this->throttle();

        for ($i = 1; $i <= self::IDENTIFIER_LIMIT; $i++) {
            $throttle->recordFailure('admin', '10.0.0.'.$i);
        }

        $this->assertNotNull($throttle->retryAfter('ADMIN', '198.51.100.99'));
    }

    public function testAnotherIdentifierStaysFree(): void
    {
        $throttle = $this->throttle();

        for ($i = 1; $i <= self::IDENTIFIER_LIMIT; $i++) {
            $throttle->recordFailure('admin', '10.0.0.1');
        }

        $this->assertNull($throttle->retryAfter('editor', '10.0.0.1'));
    }

    // ── The IP axis ────────────────────────────────────────────────────────────────────

    /**
     * Changing identifiers from one address: the identifier axis sees each one only once, the IP
     * axis counts them all.
     */
    public function testChangingIdentifiersFromOneAddressAreThrottled(): void
    {
        $throttle = $this->throttle();

        for ($i = 1; $i <= self::IP_LIMIT; $i++) {
            $throttle->recordFailure('nobody'.$i, '10.0.0.1');
        }

        $this->assertNotNull($throttle->retryAfter('someoneelse', '10.0.0.1'));
        $this->assertNull($throttle->retryAfter('someoneelse', '198.51.100.99'), 'Another address stays free');
    }

    // ── Reset ──────────────────────────────────────────────────────────────────────────

    public function testASuccessfulLoginClearsTheIdentifierCounter(): void
    {
        $throttle = $this->throttle();

        for ($i = 1; $i <= self::IDENTIFIER_LIMIT; $i++) {
            $throttle->recordFailure('admin', '10.0.0.1');
        }
        $this->assertNotNull($throttle->retryAfter('admin', '10.0.0.1'));

        $throttle->reset('admin');

        $this->assertNull($throttle->retryAfter('admin', '10.0.0.1'));
    }

    /**
     * The address counter stays in place.
     *
     * Otherwise a single valid account — the attacker's own — would be enough to unlock
     * themselves again after every block.
     */
    public function testTheAddressCounterStaysInPlace(): void
    {
        $throttle = $this->throttle();

        for ($i = 1; $i <= self::IP_LIMIT; $i++) {
            $throttle->recordFailure('nobody'.$i, '10.0.0.1');
        }

        $throttle->reset('someoneelse');

        $this->assertNotNull($throttle->retryAfter('someoneelse', '10.0.0.1'));
    }

    // ── The check consumes nothing ─────────────────────────────────────────────────────

    /**
     * If `retryAfter()` counted by itself, every rejected attempt would extend the lock — the
     * block would never expire, and an attacker could lock someone else's account permanently by
     * continuing to run against the closed door.
     */
    public function testTheCheckConsumesNothing(): void
    {
        $throttle = $this->throttle();

        for ($i = 1; $i <= self::IDENTIFIER_LIMIT - 1; $i++) {
            $throttle->recordFailure('admin', '10.0.0.1');
        }

        for ($i = 1; $i <= 50; $i++) {
            $this->assertNull($throttle->retryAfter('admin', '10.0.0.1'));
        }
    }

    // ── Increasing delay ───────────────────────────────────────────────────────────────

    /**
     * Three windows stacked: one minute, a quarter of an hour, one hour.
     *
     * Only the identifier axis is measured — every failed attempt comes from a different
     * address, so the IP axis does not count along and report the longer of the two wait times.
     */
    public function testTheWaitTimeGrowsWithPersistence(): void
    {
        $throttle = $this->throttle();

        for ($i = 1; $i <= 5; $i++) {
            $throttle->recordFailure('admin', '10.0.'.$i.'.1');
        }
        $firstStage = $throttle->retryAfter('admin', null);

        for ($i = 6; $i <= 20; $i++) {
            $throttle->recordFailure('admin', '10.0.'.$i.'.1');
        }
        $secondStage = $throttle->retryAfter('admin', null);

        for ($i = 21; $i <= 50; $i++) {
            $throttle->recordFailure('admin', '10.0.'.$i.'.1');
        }
        $thirdStage = $throttle->retryAfter('admin', null);

        $this->assertNotNull($firstStage);
        $this->assertNotNull($secondStage);
        $this->assertNotNull($thirdStage);
        $this->assertGreaterThan($firstStage, $secondStage);
        $this->assertGreaterThan($secondStage, $thirdStage);
    }

    // ── What was removed ───────────────────────────────────────────────────────────────

    /**
     * `CHECK_LOGIN_INTERVAL` was a `false` constant — the branch behind it never ran.
     *
     * The test lives here and not as a comment, because a dead branch left in place looks like an
     * existing safeguard on the next read.
     */
    public function testTheOldIntervalConstantsNoLongerExist(): void
    {
        $constants = (new \ReflectionClass(AuthController::class))->getConstants();

        $this->assertArrayNotHasKey('CHECK_LOGIN_INTERVAL', $constants);
        $this->assertArrayNotHasKey('MIN_LOGIN_INTERVAL', $constants);
    }
}
