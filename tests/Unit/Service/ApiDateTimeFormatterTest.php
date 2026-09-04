<?php
namespace Tests\Unit\Service;

use Custom\Classes\Service\Core\ApiDateTimeFormatter;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/**
 * Der Formatter legt fest, wie **jeder** Zeitstempel der API aussieht. Wenn sich sein Verhalten
 * ändert, ändert sich der API-Vertrag für alle Clients — deshalb ist er festgenagelt.
 */
class ApiDateTimeFormatterTest extends TestCase
{
    public function testFormatiertInIso8601MitMillisekundenUndOffset(): void
    {
        $moment = new DateTimeImmutable('2026-09-04 13:45:06.789', new DateTimeZone('UTC'));

        $this->assertSame('2026-09-04T13:45:06.789+00:00', ApiDateTimeFormatter::format($moment));
    }

    public function testRechnetJedeZeitzoneNachUtcUm(): void
    {
        // Derselbe Zeitpunkt, in Berliner Sommerzeit notiert (UTC+2).
        $berlin = new DateTimeImmutable('2026-09-04 15:45:06.789', new DateTimeZone('Europe/Berlin'));

        $this->assertSame(
            '2026-09-04T13:45:06.789+00:00',
            ApiDateTimeFormatter::format($berlin),
            'Ein Zeitpunkt muss unabhängig von der Zone, in der er notiert wurde, gleich ausgeliefert werden'
        );
    }

    public function testAkzeptiertAuchEinVeraenderlichesDateTime(): void
    {
        // Doctrine liefert DateTime, nicht DateTimeImmutable - der Formatter muss beides nehmen.
        $moment = new DateTime('2026-01-01 00:00:00.000', new DateTimeZone('UTC'));

        $this->assertSame('2026-01-01T00:00:00.000+00:00', ApiDateTimeFormatter::format($moment));
    }

    public function testVeraendertDenUebergebenenZeitpunktNicht(): void
    {
        $moment = new DateTime('2026-09-04 15:45:06', new DateTimeZone('Europe/Berlin'));

        ApiDateTimeFormatter::format($moment);

        $this->assertSame(
            'Europe/Berlin',
            $moment->getTimezone()->getName(),
            'Die Umrechnung nach UTC darf das übergebene Objekt nicht anfassen'
        );
    }

    public function testNullBleibtNull(): void
    {
        // Ein fehlender Zeitpunkt darf nicht stillschweigend zu "jetzt" werden - sonst sieht
        // ein nie gesetztes Datum in der API aus wie ein gerade eben gesetztes.
        $this->assertNull(ApiDateTimeFormatter::format(null));
    }

    public function testNowLiefertDasVereinbarteFormat(): void
    {
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}\+00:00$/',
            ApiDateTimeFormatter::now()
        );
    }
}
