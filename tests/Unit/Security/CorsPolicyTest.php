<?php
namespace Tests\Unit\Security;

use Areanet\PIM\Classes\Security\CorsPolicy;
use PHPUnit\Framework\TestCase;

/**
 * Which origin a response may name (000-000-0039). The headers themselves: CorsApiTest.
 */
class CorsPolicyTest extends TestCase
{
    public function testWithoutConfigurationNoOriginIsAllowed(): void
    {
        foreach (array(null, '', array()) as $configured) {
            $this->assertNull(CorsPolicy::allowedOrigin('https://evil.example', $configured));
        }
    }

    public function testOnlyAConfiguredOriginIsNamedAndExactlyThatOne(): void
    {
        $configured = array('https://app.example.com', 'capacitor://localhost');

        $this->assertSame('https://app.example.com', CorsPolicy::allowedOrigin('https://app.example.com', $configured));
        $this->assertSame('capacitor://localhost', CorsPolicy::allowedOrigin('capacitor://localhost', $configured));
        $this->assertNull(CorsPolicy::allowedOrigin('https://evil.example', $configured), 'A foreign origin');
        $this->assertNull(CorsPolicy::allowedOrigin('https://app.example.com.evil.example', $configured), 'No prefix match');
        $this->assertNull(CorsPolicy::allowedOrigin('http://app.example.com', $configured), 'The scheme belongs to the origin');
        $this->assertNull(CorsPolicy::allowedOrigin(null, $configured), 'No Origin header, nothing to name');
    }

    public function testACommaSeparatedStringIsAList(): void
    {
        $configured = ' https://app.example.com/ , http://localhost ';

        $this->assertSame('http://localhost', CorsPolicy::allowedOrigin('http://localhost', $configured));
        $this->assertSame('https://app.example.com', CorsPolicy::allowedOrigin('https://app.example.com', $configured),
            'A trailing slash in the configuration does not matter');
    }

    public function testAStarIsAnExplicitChoice(): void
    {
        $this->assertSame('*', CorsPolicy::allowedOrigin('https://anyone.example', '*'));
    }
}
