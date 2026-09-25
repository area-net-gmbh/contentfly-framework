<?php
namespace Tests\Unit\Kernel;

use Areanet\PIM\Classes\Kernel\Application;
use Areanet\PIM\Classes\Kernel\Routing\RouteCollector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Two collections must not swallow each other when mounted (013-001-0005).
 *
 * `RouteCollector` numbers its routes **per provider**: The first route is called `login_0`,
 * no matter which provider it comes from. `RouteCollection::addCollection()` overwrites by
 * name — the collection mounted later displaced the earlier one, silently.
 *
 * **Measured on the running application:** 30 registered routes, 29 in the collection.
 * Missing were `POST /api/login` and `POST /api/logout`, displaced by their
 * namesakes under `/auth`. They were considered "dead routes pointing to non-existent
 * methods" — in truth they never reached the router.
 *
 * The test measures exactly that: same path, two mount points, both must arrive.
 */
class RouteNamesTest extends TestCase
{
    private function collection(string $path): RouteCollection
    {
        $collector = new RouteCollector();
        $collector->post($path, 'some.service:someAction');

        return $collector->collection();
    }

    public function testTwoMountPointsWithTheSamePathLoseNoRoute(): void
    {
        $app = new Application();
        $app->mount('/api',  $this->collection('/login'));
        $app->mount('/auth', $this->collection('/login'));

        $paths = array();
        foreach ($this->routes($app) as $route) {
            $paths[] = $route->getPath();
        }

        sort($paths);

        $this->assertSame(array('/api/login', '/auth/login'), $paths);
    }

    /**
     * The name carries the mount point — hence the uniqueness.
     *
     * Nobody else uses the names: There is no `url_generator` and no call that
     * refers to a route by name. They still have to be unique, otherwise mounting is a
     * gamble.
     */
    public function testTheRouteNameCarriesTheMountPoint(): void
    {
        $app = new Application();
        $app->mount('api/v1/example/', $this->collection('bootstrap'));

        $this->assertSame(array('api_v1_example_bootstrap_0'), array_keys($this->routes($app)->all()));
    }

    private function routes(Application $app): RouteCollection
    {
        $property = (new \ReflectionObject($app))->getProperty('routes');

        return $property->getValue($app);
    }
}
