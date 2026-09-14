<?php
namespace Tests\Unit\Security;

use Areanet\PIM\Classes\Security\TrustedProxies;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Trusted proxies — the precondition for the per-IP throttle hitting the right party
 * (013-001-0003).
 *
 * Until this task, `setTrustedProxies()` was not called anywhere in the whole tree. These tests
 * pin down both: that nothing happens without configuration, and that with configuration the
 * forwarded address applies.
 *
 * THE SETTING IS GLOBAL — it is attached to the `Request` class, not to an instance. The previous
 * state is therefore saved in `setUp()` and restored in `tearDown()`; otherwise every test in this
 * file would carry its setting into all subsequent ones.
 */
class TrustedProxiesTest extends TestCase
{
    /** @var list<string> */
    private array $previousProxies = array();

    private int $previousHeaderSet = 0;

    protected function setUp(): void
    {
        $this->previousProxies   = Request::getTrustedProxies();
        $this->previousHeaderSet = Request::getTrustedHeaderSet();
    }

    protected function tearDown(): void
    {
        Request::setTrustedProxies($this->previousProxies, $this->previousHeaderSet);
    }

    // ── Without configuration ──────────────────────────────────────────────────────────

    /**
     * The most important test in this file: whoever configures nothing gets the previous behaviour.
     */
    public function testWithoutSettingNothingIsApplied(): void
    {
        $this->assertFalse(TrustedProxies::apply(array(), 'x-forwarded'));
        $this->assertFalse(TrustedProxies::apply('', 'x-forwarded'));
        $this->assertFalse(TrustedProxies::apply(null, 'x-forwarded'));

        $this->assertSame(array(), Request::getTrustedProxies());
    }

    // ── The setting from the configuration ─────────────────────────────────────────────

    public function testArrayIsTakenOver(): void
    {
        $this->assertSame(
            array('10.0.0.0/8', '192.168.1.5'),
            TrustedProxies::list(array('10.0.0.0/8', ' 192.168.1.5 '))
        );
    }

    /**
     * The second notation exists because such a setting often comes from an environment variable
     * and can only exist there as a string.
     */
    public function testCommaSeparatedStringIsSplit(): void
    {
        $this->assertSame(
            array('10.0.0.0/8', '192.168.1.5', 'REMOTE_ADDR'),
            TrustedProxies::list('10.0.0.0/8, 192.168.1.5 ,REMOTE_ADDR')
        );
    }

    public function testEmptyEntriesAreDropped(): void
    {
        $this->assertSame(array('10.0.0.1'), TrustedProxies::list('  ,10.0.0.1,  ,'));
    }

    // ── The header set ─────────────────────────────────────────────────────────────────

    public function testDefaultIsTheNarrowOne(): void
    {
        $set = TrustedProxies::headerSet('x-forwarded');

        $this->assertSame(Request::HEADER_X_FORWARDED_FOR, $set & Request::HEADER_X_FORWARDED_FOR);
        $this->assertSame(0, $set & Request::HEADER_FORWARDED, 'Forwarded is only read on request');
    }

    public function testForwardedSwitchesOver(): void
    {
        $set = TrustedProxies::headerSet('forwarded');

        $this->assertSame(Request::HEADER_FORWARDED, $set);
        $this->assertSame(0, $set & Request::HEADER_X_FORWARDED_FOR, 'Not both at the same time');
    }

    /**
     * Rejected instead of silently falling back to the default — the same rule as for the dropped
     * cache driver `apc` (010-002-0002). Otherwise a typo would be a configuration that appears to
     * take effect and does not.
     */
    public function testUnknownHeaderSetIsRejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/APP_TRUSTED_HEADERS/');

        TrustedProxies::headerSet('x-forwaded');
    }

    // ── The effect ─────────────────────────────────────────────────────────────────────

    /**
     * The actual proof: the same request, once without and once with a trusted proxy.
     *
     * Without the setting, the caller's address is that of the proxy — exactly that would have made
     * the per-IP throttle worthless, because it would then have hit the proxy and thus all users
     * behind it.
     */
    public function testWithTrustedProxyTheForwardedAddressApplies(): void
    {
        $build = static function (): Request {
            return new Request(
                array(), array(), array(), array(), array(),
                array(
                    'REMOTE_ADDR'          => '10.0.0.1',
                    'HTTP_X_FORWARDED_FOR' => '203.0.113.7',
                )
            );
        };

        Request::setTrustedProxies(array(), $this->previousHeaderSet);
        $this->assertSame('10.0.0.1', $build()->getClientIp(), 'Without the setting the next hop counts');

        TrustedProxies::apply(array('10.0.0.1'), 'x-forwarded');
        $this->assertSame('203.0.113.7', $build()->getClientIp(), 'With the setting the caller counts');
    }

    /**
     * A header from a sender that is NOT trusted changes nothing.
     *
     * Otherwise a self-set `X-Forwarded-For` would suffice to steer the per-IP throttle to a
     * different bucket on every attempt.
     */
    public function testUntrustedSenderCannotSetTheAddress(): void
    {
        TrustedProxies::apply(array('10.0.0.1'), 'x-forwarded');

        $request = new Request(
            array(), array(), array(), array(), array(),
            array(
                'REMOTE_ADDR'          => '198.51.100.4',
                'HTTP_X_FORWARDED_FOR' => '203.0.113.7',
            )
        );

        $this->assertSame('198.51.100.4', $request->getClientIp());
    }
}
