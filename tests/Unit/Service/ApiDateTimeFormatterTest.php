<?php
namespace Tests\Unit\Service;

use Custom\Classes\Service\Core\ApiDateTimeFormatter;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/**
 * The formatter defines what **every** timestamp of the API looks like. If its behaviour
 * changes, the API contract changes for all clients — that is why it is pinned down.
 */
class ApiDateTimeFormatterTest extends TestCase
{
    public function testFormatsAsIso8601WithMillisecondsAndOffset(): void
    {
        $moment = new DateTimeImmutable('2026-09-04 13:45:06.789', new DateTimeZone('UTC'));

        $this->assertSame('2026-09-04T13:45:06.789+00:00', ApiDateTimeFormatter::format($moment));
    }

    public function testConvertsEveryTimezoneToUtc(): void
    {
        // The same point in time, noted in Berlin summer time (UTC+2).
        $berlin = new DateTimeImmutable('2026-09-04 15:45:06.789', new DateTimeZone('Europe/Berlin'));

        $this->assertSame(
            '2026-09-04T13:45:06.789+00:00',
            ApiDateTimeFormatter::format($berlin),
            'A point in time must be delivered identically, regardless of the zone it was noted in'
        );
    }

    public function testAlsoAcceptsAMutableDateTime(): void
    {
        // Doctrine returns DateTime, not DateTimeImmutable - the formatter must accept both.
        $moment = new DateTime('2026-01-01 00:00:00.000', new DateTimeZone('UTC'));

        $this->assertSame('2026-01-01T00:00:00.000+00:00', ApiDateTimeFormatter::format($moment));
    }

    public function testDoesNotModifyThePassedPointInTime(): void
    {
        $moment = new DateTime('2026-09-04 15:45:06', new DateTimeZone('Europe/Berlin'));

        ApiDateTimeFormatter::format($moment);

        $this->assertSame(
            'Europe/Berlin',
            $moment->getTimezone()->getName(),
            'The conversion to UTC must not touch the passed object'
        );
    }

    public function testNullStaysNull(): void
    {
        // A missing point in time must not silently become "now" - otherwise a date that was
        // never set looks in the API like one that was set just now.
        $this->assertNull(ApiDateTimeFormatter::format(null));
    }

    public function testNowReturnsTheAgreedFormat(): void
    {
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}\+00:00$/',
            ApiDateTimeFormatter::now()
        );
    }
}
