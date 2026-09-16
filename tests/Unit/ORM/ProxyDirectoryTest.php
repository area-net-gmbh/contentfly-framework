<?php
namespace Tests\Unit\ORM;

use Areanet\PIM\Classes\ORM\ProxyDirectory;
use PHPUnit\Framework\TestCase;

/**
 * One proxy directory per framework and ORM version (000-000-0049).
 *
 * Before, every version wrote into `data/cache/doctrine`, and since 000-000-0047 a file there is only
 * replaced when it is older than its entity — a proxy of the previous installation is regularly younger.
 * Measured on the data of the existing project UFP: the ORM 2 proxy was loaded and the request died.
 */
class ProxyDirectoryTest extends TestCase
{
    public function testTheSameVersionsGiveTheSameDirectoryAndOtherVersionsAnother(): void
    {
        $current = array('areanet/contentfly' => '2.0.0@abc123', 'doctrine/orm' => '3.7.0@def456');

        $this->assertSame(ProxyDirectory::fingerprint($current), ProxyDirectory::fingerprint($current),
            'The same installation keeps its directory — otherwise every request would generate anew');

        foreach (array(
            'a newer framework' => array('areanet/contentfly' => '2.1.0@aaa111', 'doctrine/orm' => '3.7.0@def456'),
            'the same version from another commit' => array('areanet/contentfly' => '2.0.0@zzz999', 'doctrine/orm' => '3.7.0@def456'),
            'a newer ORM' => array('areanet/contentfly' => '2.0.0@abc123', 'doctrine/orm' => '3.8.0@fff000'),
        ) as $case => $other) {
            $this->assertNotSame(ProxyDirectory::fingerprint($current), ProxyDirectory::fingerprint($other), $case);
        }
    }

    public function testThePathLiesUnderDataCacheProxiesAndNotInTheOldDirectory(): void
    {
        $path = ProxyDirectory::path('/var/www/html/data/');

        $this->assertMatchesRegularExpression('#^/var/www/html/data/cache/proxies/[0-9a-f]{12}$#', $path);
        $this->assertStringNotContainsString('cache/doctrine', $path,
            'data/cache/doctrine is where Contentfly 1.x and every earlier version wrote');
    }

    public function testTheIdentifierOfThisInstallationIsReadable(): void
    {
        $this->assertMatchesRegularExpression('/^[0-9a-f]{12}$/', ProxyDirectory::identifier());
    }
}
