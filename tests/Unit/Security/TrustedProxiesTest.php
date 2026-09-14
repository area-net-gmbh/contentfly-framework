<?php
namespace Tests\Unit\Security;

use Areanet\PIM\Classes\Security\TrustedProxies;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Vertraute Proxies — die Voraussetzung dafuer, dass die Bremse pro IP den Richtigen trifft
 * (013-001-0003).
 *
 * `setTrustedProxies()` wurde bis zu diesem Task im ganzen Baum nirgends gerufen. Diese Tests
 * halten beides fest: dass ohne Konfiguration nichts geschieht, und dass mit Konfiguration die
 * weitergereichte Adresse gilt.
 *
 * DIE ANGABE IST GLOBAL — sie haengt an der Klasse `Request`, nicht an einer Instanz. Der
 * vorherige Stand wird deshalb in `setUp()` gesichert und in `tearDown()` zurueckgestellt;
 * andernfalls truege jeder Test dieser Datei seine Einstellung in alle folgenden.
 */
class TrustedProxiesTest extends TestCase
{
    /** @var list<string> */
    private array $vorherProxies = array();

    private int $vorherHeaderSatz = 0;

    protected function setUp(): void
    {
        $this->vorherProxies    = Request::getTrustedProxies();
        $this->vorherHeaderSatz = Request::getTrustedHeaderSet();
    }

    protected function tearDown(): void
    {
        Request::setTrustedProxies($this->vorherProxies, $this->vorherHeaderSatz);
    }

    // ── Ohne Konfiguration ─────────────────────────────────────────────────────────────

    /**
     * Der wichtigste Test der Datei: Wer nichts eintraegt, bekommt das Verhalten von vorher.
     */
    public function testOhneAngabeWirdNichtsGesetzt(): void
    {
        $this->assertFalse(TrustedProxies::apply(array(), 'x-forwarded'));
        $this->assertFalse(TrustedProxies::apply('', 'x-forwarded'));
        $this->assertFalse(TrustedProxies::apply(null, 'x-forwarded'));

        $this->assertSame(array(), Request::getTrustedProxies());
    }

    // ── Die Angabe aus der Konfiguration ───────────────────────────────────────────────

    public function testEinArrayWirdUebernommen(): void
    {
        $this->assertSame(
            array('10.0.0.0/8', '192.168.1.5'),
            TrustedProxies::list(array('10.0.0.0/8', ' 192.168.1.5 '))
        );
    }

    /**
     * Die zweite Schreibweise gibt es, weil eine solche Angabe oft aus einer Umgebungsvariablen
     * kommt und dort nur als Zeichenkette existieren kann.
     */
    public function testEineZeichenketteMitKommasWirdZerlegt(): void
    {
        $this->assertSame(
            array('10.0.0.0/8', '192.168.1.5', 'REMOTE_ADDR'),
            TrustedProxies::list('10.0.0.0/8, 192.168.1.5 ,REMOTE_ADDR')
        );
    }

    public function testLeereEintraegeFallenWeg(): void
    {
        $this->assertSame(array('10.0.0.1'), TrustedProxies::list('  ,10.0.0.1,  ,'));
    }

    // ── Der Headersatz ─────────────────────────────────────────────────────────────────

    public function testDieVorgabeIstDieEnge(): void
    {
        $satz = TrustedProxies::headerSet('x-forwarded');

        $this->assertSame(Request::HEADER_X_FORWARDED_FOR, $satz & Request::HEADER_X_FORWARDED_FOR);
        $this->assertSame(0, $satz & Request::HEADER_FORWARDED, 'Forwarded wird nur auf Ansage gelesen');
    }

    public function testForwardedSchaltetUm(): void
    {
        $satz = TrustedProxies::headerSet('forwarded');

        $this->assertSame(Request::HEADER_FORWARDED, $satz);
        $this->assertSame(0, $satz & Request::HEADER_X_FORWARDED_FOR, 'Nicht beide gleichzeitig');
    }

    /**
     * Abgewiesen statt stillschweigend auf die Vorgabe zurueckgefuehrt — dieselbe Regel wie beim
     * entfallenen Cache-Treiber `apc` (010-002-0002). Ein Tippfehler waere sonst eine
     * Konfiguration, die zu wirken scheint und nicht wirkt.
     */
    public function testEinUnbekannterHeaderSatzWirdAbgewiesen(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/APP_TRUSTED_HEADERS/');

        TrustedProxies::headerSet('x-forwaded');
    }

    // ── Die Wirkung ────────────────────────────────────────────────────────────────────

    /**
     * Der eigentliche Nachweis: Dieselbe Anfrage, einmal ohne und einmal mit vertrautem Proxy.
     *
     * Ohne Angabe ist die Adresse des Aufrufers die des Proxys — genau das haette die Bremse
     * pro IP wertlos gemacht, weil sie dann den Proxy getroffen haette und damit alle Benutzer
     * dahinter.
     */
    public function testMitVertrautemProxyGiltDieWeitergereichteAdresse(): void
    {
        $bauen = static function (): Request {
            return new Request(
                array(), array(), array(), array(), array(),
                array(
                    'REMOTE_ADDR'          => '10.0.0.1',
                    'HTTP_X_FORWARDED_FOR' => '203.0.113.7',
                )
            );
        };

        Request::setTrustedProxies(array(), $this->vorherHeaderSatz);
        $this->assertSame('10.0.0.1', $bauen()->getClientIp(), 'Ohne Angabe zaehlt der naechste Hop');

        TrustedProxies::apply(array('10.0.0.1'), 'x-forwarded');
        $this->assertSame('203.0.113.7', $bauen()->getClientIp(), 'Mit Angabe zaehlt der Aufrufer');
    }

    /**
     * Ein Header von einem Absender, dem NICHT vertraut wird, aendert nichts.
     *
     * Sonst genuegte ein selbstgesetztes `X-Forwarded-For`, um die Bremse pro IP bei jedem
     * Versuch auf einen anderen Eimer zu lenken.
     */
    public function testEinNichtVertrauterAbsenderKannDieAdresseNichtSetzen(): void
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
