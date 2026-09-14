<?php
namespace Tests\Unit\Manager;

use Areanet\PIM\Classes\Command\CustomCommand;
use Areanet\PIM\Classes\Controller\Provider\Base\CustomControllerProvider;
use Areanet\PIM\Classes\Manager\ConsoleManager;
use Areanet\PIM\Classes\Manager\RouteManager;
use Areanet\PIM\Classes\Kernel\ConsoleEvents;
use PHPUnit\Framework\TestCase;
use Areanet\PIM\Classes\Kernel\Application;

/**
 * Characterisation tests for `RouteManager` and `ConsoleManager`.
 *
 * Both depend on nothing but a `Kernel\Application` object — no EntityManager, no database,
 * no HTTP. Hence unit tests: they run on every checkout, even without a container.
 *
 * A project registers its routes and console commands through these two managers. What they
 * promise to the outside must be reproduced identically by the Symfony kernel from Epic `009`.
 */
class RouteAndConsoleManagerTest extends TestCase
{
    private function app(): Application
    {
        $app = new Application();
        $app['orm.em'] = null;

        return $app;
    }

    // ── RouteManager ───────────────────────────────────────────────────────────────────

    public function testMountReturnsTheProviderForChaining(): void
    {
        // custom/app.php uses exactly that:
        //     $controllerProvider->mount('api/v1/example/', …)->post('/bootstrap', false, …)
        $manager = new RouteManager($this->app());

        $provider = $manager->mount('api/v1/example/', '\Custom\Controller\Core\ExampleController');

        $this->assertInstanceOf(CustomControllerProvider::class, $provider);
        $this->assertSame($provider, $provider->post('/sample', false, 'sampleAction'),
            'The route methods return the provider so that routes can be chained');
    }

    public function testTwoMountsOnTheSamePathOverwriteEachOther(): void
    {
        // The path is the key in the internal array — there is no collision check. A project
        // that mounts the same path twice silently loses the first controller.
        //
        // Observed through a spy on Application::mount(), not through Silex' route
        // internals: the promise of the RouteManager is "bindRoutes() passes every collected
        // mount on to the application" — exactly that is measured here.
        $app     = new SpyApplication();
        $manager = new RouteManager($app);

        $manager->mount('duplicate/', '\Custom\Controller\Core\ExampleController');
        $manager->mount('duplicate/', '\Custom\Controller\Core\ExampleController');
        $manager->bindRoutes();

        $this->assertSame(array('duplicate/'), $app->mountedPaths,
            'Two mounts on the same path result in one bound mount, not two');
    }

    public function testBindRoutesPassesOnEveryCollectedMount(): void
    {
        $app     = new SpyApplication();
        $manager = new RouteManager($app);

        $manager->mount('first/',  '\Custom\Controller\Core\ExampleController');
        $manager->mount('second/', '\Custom\Controller\Core\ExampleController');

        $this->assertSame(array(), $app->mountedPaths, 'mount() does not bind yet');

        $manager->bindRoutes();

        $this->assertSame(array('first/', 'second/'), $app->mountedPaths,
            'Only bindRoutes() binds — and it does so in the order of registration');
    }

    public function testWithoutMountBindRoutesBindsNothing(): void
    {
        $app = new SpyApplication();
        (new RouteManager($app))->bindRoutes();

        $this->assertSame(array(), $app->mountedPaths);
    }

    // ── ConsoleManager ─────────────────────────────────────────────────────────────────

    public function testAddCommandMustRunBeforeTheFirstAccessToTheDispatcher(): void
    {
        // A finding that came up while writing this test: addCommand() uses
        // $app->extend('dispatcher', …). The container freezes a service as soon as it has
        // been read — whoever touches the dispatcher beforehand gets an exception on the next
        // addCommand().
        //
        // Until 009-002-0002 that was Pimple's FrozenServiceException; since then the own
        // container throws a RuntimeException. The behaviour is the same and rebuilt on
        // purpose — the ordering constraint is real, and a container that silently allowed
        // it would hide the error.
        //
        // In the real flow this is no problem: custom/app.php runs before the first request
        // uses the dispatcher. But it is an ordering constraint that is documented nowhere —
        // and the Symfony kernel from Epic 009 will have to either reproduce or resolve it.
        $app     = $this->app();
        $manager = new ConsoleManager($app);

        $app['dispatcher']; // read once — freezes the service

        $this->expectException(\RuntimeException::class);

        $manager->addCommand(new class extends CustomCommand {});
    }

    public function testAddCommandAttachesAListenerToTheDispatcher(): void
    {
        // The manager does not register the command immediately, but attaches a listener
        // to ConsoleEvents::INIT — so the command is only registered once the console
        // actually starts.
        $app     = $this->app();
        $manager = new ConsoleManager($app);

        $manager->addCommand(new class extends CustomCommand {});

        $this->assertCount(1, $app['dispatcher']->getListeners(ConsoleEvents::INIT));
    }

    public function testSeveralCommandsResultInSeveralListeners(): void
    {
        $app     = $this->app();
        $manager = new ConsoleManager($app);

        $manager->addCommand(new class extends CustomCommand {});
        $manager->addCommand(new class extends CustomCommand {});

        $this->assertCount(2, $app['dispatcher']->getListeners(ConsoleEvents::INIT),
            'Unlike the RouteManager, there is no key here under which something could '
            .'be overwritten');
    }

    public function testCustomCommandPrefixesTheNameWithCustom(): void
    {
        // The base class sets the name to `custom:<name>`, so that project commands never
        // collide with those of the framework. That is the contract custom/app.php describes
        // in its comment.
        $command = new class extends CustomCommand {};
        $command->setName('example');

        $this->assertSame('custom:example', $command->getName());
    }
}

/**
 * Application that remembers which paths were mounted.
 *
 * A spy instead of a stub: the RouteManager promises to pass every collected mount on to the
 * application — that can be observed directly here, without querying the kernel's internal
 * route structure.
 */
class SpyApplication extends Application
{
    /** @var array<int,string> */
    public array $mountedPaths = array();

    public function mount($prefix, $controllers): Application
    {
        $this->mountedPaths[] = $prefix;

        return $this;
    }
}
