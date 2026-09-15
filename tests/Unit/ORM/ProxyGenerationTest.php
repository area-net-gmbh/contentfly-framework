<?php
namespace Tests\Unit\ORM;

use Areanet\PIM\Classes\Config;
use Areanet\PIM\Classes\ORM\ProxyGeneration;
use Doctrine\ORM\Proxy\ProxyFactory;
use PHPUnit\Framework\TestCase;

/**
 * How `APP_AUTOGENERATE_PROXIES` becomes Doctrine's proxy mode (000-000-0047).
 *
 * The bootstrap used to pass `(bool)` of the setting; its default `true` meant regenerating every proxy
 * on every request, and no other mode was reachable.
 */
class ProxyGenerationTest extends TestCase
{
    public function testTheDefaultWritesProxiesOnlyWhenMissingOrChanged(): void
    {
        $this->assertSame(ProxyFactory::AUTOGENERATE_FILE_NOT_EXISTS_OR_CHANGED, (new Config())->APP_AUTOGENERATE_PROXIES);
        $this->assertSame(ProxyFactory::AUTOGENERATE_FILE_NOT_EXISTS_OR_CHANGED, ProxyGeneration::mode((new Config())->APP_AUTOGENERATE_PROXIES));
    }

    public function testTrueAndFalseKeepTheirOldMeaning(): void
    {
        $this->assertSame(ProxyFactory::AUTOGENERATE_ALWAYS, ProxyGeneration::mode(true),
            'A project that set true explicitly keeps regenerating on every request');
        $this->assertSame(ProxyFactory::AUTOGENERATE_NEVER, ProxyGeneration::mode(false));
    }

    public function testEveryDoctrineModeIsPassedThrough(): void
    {
        foreach (array(0, 1, 2, 3, 4) as $mode) {
            $this->assertSame($mode, ProxyGeneration::mode($mode));
        }
    }

    public function testAnythingElseIsAConfigurationErrorNotACast(): void
    {
        foreach (array(5, -1, '4', 'yes', null, 1.0) as $invalid) {
            try {
                ProxyGeneration::mode($invalid);
                $this->fail('Accepted ' . var_export($invalid, true));
            } catch (\InvalidArgumentException $e) {
                $this->assertStringContainsString('APP_AUTOGENERATE_PROXIES', $e->getMessage());
            }
        }
    }
}
