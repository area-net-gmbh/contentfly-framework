<?php
namespace Tests\Unit\Kernel;

use Areanet\PIM\Controller\AuthController;
use PHPUnit\Framework\TestCase;

/**
 * How a LoginProvider is selected — **turned around with `013-004-0001`, not deleted.**
 *
 * The test had the same name before and checked something else: `AuthController::providerKlasse()`,
 * which resolved a name from the request to `Custom\Classes\<Name>`. It was created with
 * `013-001-0005` because the condition there was inverted (`substr($name, 7)` instead of the first
 * seven characters) and plugin providers therefore never worked.
 *
 * **The resolution itself is now gone**, and that was the point: Which class an application
 * instantiates is a decision of the operator and not of the caller. What remains is
 * the guarantee that it is gone — a test that records a removed mechanism is the
 * only way to notice when someone builds it back.
 */
class LoginProviderResolutionTest extends TestCase
{
    public function testResolutionByClassNameNoLongerExists(): void
    {
        $this->assertFalse(
            method_exists(AuthController::class, 'providerKlasse'),
            'providerKlasse() resolved a request parameter to a class — removed with 013-004-0001'
        );

        $this->assertFalse(
            method_exists(AuthController::class, 'getLoginProvider'),
            'getLoginProvider() instantiated that class — removed as well'
        );
    }

    /**
     * And the proof that the old mechanism does not secretly live on elsewhere: Nowhere in the tree
     * is a class name assembled from a request parameter any more.
     */
    public function testNoClassNameIsBuiltFromAParameterAnyMore(): void
    {
        $source = file_get_contents(CONTENTFLY_PROJECT_DIR.'/lib/contentfly/Controller/AuthController.php');

        $this->assertStringNotContainsString("'Custom\\Classes\\\\'", $source);
        $this->assertStringNotContainsString('class_exists(', $source);
    }
}
