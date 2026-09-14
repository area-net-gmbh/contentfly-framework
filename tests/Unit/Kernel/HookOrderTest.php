<?php
namespace Tests\Unit\Kernel;

use Areanet\PIM\Classes\Kernel\Application;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The **effective** order of the before and after hooks (`009-002-0004`).
 *
 * `an_project/docs/technical.md` states it as a rule:
 *
 * > Silex takes a priority argument for `before()`/`after()`. Where it was set, it was
 * > intentional — when porting to Symfony listeners, the effective order must be proven,
 * > not the registration order guessed.
 *
 * That is exactly what is here. The tests register hooks in one order and expect them in
 * another; if the priority had no effect, they would come in registration order
 * and the test would be red.
 */
class HookOrderTest extends TestCase
{
    private function application(): Application
    {
        $app = new Application();

        // A route, so that the kernel gets as far as the controller at all.
        $app->options('/{anything}', static fn (): Response => new JsonResponse(null, 204))
            ->assert('anything', '.*');

        return $app;
    }

    private function runRequest(Application $app): Response
    {
        return $app->handle(Request::create('/arbitrary', 'OPTIONS'));
    }

    public function testAHigherPriorityRunsEarlierRegardlessOfRegistration(): void
    {
        $order = array();
        $app   = $this->application();

        // Deliberately registered in the wrong order.
        $app->before(function () use (&$order) { $order[] = 'low'; }, -100);
        $app->before(function () use (&$order) { $order[] = 'high'; }, 128);
        $app->before(function () use (&$order) { $order[] = 'middle'; }, 0);

        $this->runRequest($app);

        $this->assertSame(array('high', 'middle', 'low'), $order);
    }

    public function testEqualPriorityRunsInRegistrationOrder(): void
    {
        $order = array();
        $app   = $this->application();

        $app->before(function () use (&$order) { $order[] = 'first'; });
        $app->before(function () use (&$order) { $order[] = 'second'; });

        $this->runRequest($app);

        $this->assertSame(array('first', 'second'), $order);
    }

    public function testABeforeHookReturningAResponseAborts(): void
    {
        // The documented way to block a request — custom/app.php describes it.
        $order = array();
        $app   = $this->application();

        $app->before(function () use (&$order) {
            $order[] = 'blocks';

            return new JsonResponse(array('locked' => true), 423);
        }, 100);

        $app->before(function () use (&$order) { $order[] = 'afterwards'; }, 0);

        $response = $this->runRequest($app);

        $this->assertSame(423, $response->getStatusCode());
        $this->assertSame(array('blocks'), $order, 'The second hook no longer runs');
    }

    public function testTheBeforeHookReceivesRequestAndApplication(): void
    {
        $seen = array();
        $app  = $this->application();

        $app->before(function ($request, $application) use (&$seen) {
            $seen = array(get_class($request), $application instanceof Application);
        });

        $this->runRequest($app);

        $this->assertSame(array(Request::class, true), $seen);
    }

    public function testAHookDoesNotPreventLaterCommandRegistration(): void
    {
        /*
         * THE SEQUENCE THAT TRIGGERED THE BUG (009-004-0004).
         *
         * `custom/app.php` does both: it registers middleware and then console commands.
         * If `before()` accessed `$app['dispatcher']` directly, the service would be
         * frozen afterwards, and the ConsoleManager — which has to extend it via `extend()` —
         * would get:
         *
         *     RuntimeException: The service "dispatcher" has already been read …
         *
         * It only surfaced when the template in 009-004-0001 actually followed its own
         * documented path. Silex deferred the registration before boot; this got lost in the
         * rebuild because no test covered this sequence.
         */
        $app = new Application();
        $app['orm.em'] = null;

        $app->before(static function (): void {});

        $manager = new \Areanet\PIM\Classes\Manager\ConsoleManager($app);
        $manager->addCommand(new class extends \Areanet\PIM\Classes\Command\CustomCommand {});

        $this->assertCount(
            1,
            $app['dispatcher']->getListeners(\Areanet\PIM\Classes\Kernel\ConsoleEvents::INIT),
            'The command is registered even though a before hook was registered first'
        );
    }

    public function testAfterHooksRunByPriorityAndSeeTheResponse(): void
    {
        $order = array();
        $app   = $this->application();

        $app->after(function ($request, Response $response) use (&$order) {
            $order[] = 'late';
            $response->headers->set('X-Late', '1');
        }, -50);

        $app->after(function ($request, Response $response) use (&$order) {
            $order[] = 'early';
            $response->headers->set('X-Early', '1');
        }, 50);

        $response = $this->runRequest($app);

        $this->assertSame(array('early', 'late'), $order);
        $this->assertSame('1', $response->headers->get('X-Early'));
        $this->assertSame('1', $response->headers->get('X-Late'));
    }
}
