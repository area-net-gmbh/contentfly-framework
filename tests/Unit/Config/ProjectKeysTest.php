<?php
namespace Tests\Unit\Config;

use Areanet\PIM\Classes\Config;
use Areanet\PIM\Classes\Config\Adapter;
use Areanet\PIM\Classes\Config\Factory;
use PHPUnit\Framework\TestCase;

/**
 * A project may set keys of its own on Config (000-000-0040).
 *
 * Since PHP 8.2 an undeclared property is deprecated. At the existing project UFP that produced 35
 * deprecations while custom/config.php ran — before display_errors was set, so in the response body.
 * The test turns every deprecation into a failure while it sets a key the framework does not declare.
 */
class ProjectKeysTest extends TestCase
{
    protected function tearDown(): void
    {
        Factory::getInstance()->setConfig(new Config());
    }

    public function testAProjectKeyRaisesNoDeprecationAndIsReadBack(): void
    {
        $deprecations = array();
        set_error_handler(static function (int $level, string $message) use (&$deprecations): bool {
            $deprecations[] = $message;
            return true;
        }, E_DEPRECATED | E_USER_DEPRECATED);

        try {
            $config = new Config();
            $config->CUSTOM_SMTP_HOST = 'mail.example.test';
            Factory::getInstance()->setConfig($config);
        } finally {
            restore_error_handler();
        }

        $this->assertSame(array(), $deprecations, 'Setting a project key must not raise a deprecation');
        $this->assertSame('mail.example.test', Adapter::getConfig()->CUSTOM_SMTP_HOST);
    }

    public function testTheAllowanceIsDeclaredOnTheClass(): void
    {
        $this->assertNotEmpty(
            (new \ReflectionClass(Config::class))->getAttributes(\AllowDynamicProperties::class),
            'Config carries #[AllowDynamicProperties] — removing it breaks every project with own keys'
        );
    }
}
