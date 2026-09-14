<?php
namespace Tests\Unit\Kernel;

use Areanet\PIM\Classes\Kernel\Container;
use PHPUnit\Framework\TestCase;

/**
 * The container from `009-002-0002`, checked against exactly the promises it was
 * written for.
 *
 * It reproduces Pimple's contract because `custom/app.php` depends on it — and because an
 * existing project registers its services exactly this way. What is stated here is therefore
 * not an implementation detail but the interface to the project.
 */
class ContainerTest extends TestCase
{
    public function testAValueComesBackAsItWasStored(): void
    {
        $c = new Container();
        $c['number'] = 42;

        $this->assertSame(42, $c['number']);
    }

    public function testAClosureIsAFactoryAndOnlyRunsOnAccess(): void
    {
        // The core of the contract: registering executes nothing. custom/app.php is read during
        // bootstrap, long before a database is available — a factory that ran immediately
        // would access an EntityManager there that does not exist yet.
        $ran = false;

        $c = new Container();
        $c['service'] = function () use (&$ran) {
            $ran = true;

            return new \stdClass();
        };

        $this->assertFalse($ran, 'Registering alone does not execute the factory');

        $c['service'];

        $this->assertTrue($ran, 'The first access does');
    }

    public function testTheFactoryReceivesTheContainerAsArgument(): void
    {
        // This is how custom/app.php resolves dependencies:
        //     $app['my.service'] = function ($app) { return new X($app['orm.em']); };
        $c = new Container();
        $c['dependency'] = 'present';
        $c['service'] = function ($app) {
            return 'built with: '.$app['dependency'];
        };

        $this->assertSame('built with: present', $c['service']);
    }

    public function testAFactoryRunsExactlyOnce(): void
    {
        // This is what makes $app['orm.em'] the same EntityManager everywhere.
        $c = new Container();
        $c['object'] = function () {
            return new \stdClass();
        };

        $this->assertSame($c['object'], $c['object']);
    }

    public function testAnUnknownKeyThrowsInsteadOfReturningNull(): void
    {
        // Returning null would turn a typo into a silent error much later
        // on.
        $c = new Container();

        $this->expectException(\InvalidArgumentException::class);

        $c['doesnotexist'];
    }

    public function testIssetAndUnsetWorkAsExpected(): void
    {
        $c = new Container();
        $c['present'] = 1;

        $this->assertTrue(isset($c['present']));
        $this->assertFalse(isset($c['notpresent']));

        unset($c['present']);

        $this->assertFalse(isset($c['present']));
    }

    public function testAnEntryWithNullCountsAsPresent(): void
    {
        // bootstrap.php stores $app['auth.user'] = null and sets it later. If isset() returned
        // false for it, the distinction "not registered" versus "nobody logged in
        // yet" would be lost.
        $c = new Container();
        $c['auth.user'] = null;

        $this->assertTrue(isset($c['auth.user']));
        $this->assertNull($c['auth.user']);
    }

    // ── extend() and freezing ──────────────────────────────────────────────────────────

    public function testExtendWrapsTheOldFactory(): void
    {
        $c = new Container();
        $c['list'] = function () {
            return array('one');
        };

        $c->extend('list', function (array $old) {
            $old[] = 'two';

            return $old;
        });

        $this->assertSame(array('one', 'two'), $c['list']);
    }

    public function testExtendAfterTheFirstAccessThrows(): void
    {
        // The promise for which freezing was rebuilt in the first place. The
        // ConsoleManager extends the dispatcher via extend() and has to do so before
        // anyone reads it; 000-000-0006 stumbled over exactly that. A container
        // that silently allowed it would hide the error.
        $c = new Container();
        $c['dispatcher'] = function () {
            return new \stdClass();
        };

        $c['dispatcher'];

        $this->expectException(\RuntimeException::class);

        $c->extend('dispatcher', function ($old) {
            return $old;
        });
    }

    public function testExtendOnAValueThrows(): void
    {
        $c = new Container();
        $c['number'] = 42;

        $this->expectException(\InvalidArgumentException::class);

        $c->extend('number', function ($old) {
            return $old;
        });
    }

    public function testSettingAgainLiftsTheFreeze(): void
    {
        // Whoever replaces a definition starts over — otherwise a service would be fixed
        // forever after the first access, even for someone who deliberately swaps it.
        $c = new Container();
        $c['service'] = function () {
            return 'old';
        };

        $c['service'];

        $c['service'] = function () {
            return 'new';
        };

        $c->extend('service', function ($old) {
            return $old.'+extended';
        });

        $this->assertSame('new+extended', $c['service']);
    }

    public function testKeysReturnsTheKeysInRegistrationOrder(): void
    {
        $c = new Container();
        $c['first'] = 1;
        $c['second'] = 2;

        $this->assertSame(array('first', 'second'), $c->keys());
    }
}
