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
 * Charakterisierungstests für `RouteManager` und `ConsoleManager`.
 *
 * Beide hängen allein an einem `Kernel\Application`-Objekt — kein EntityManager, keine
 * Datenbank, kein HTTP. Deshalb Unit-Tests: Sie laufen auf jedem Checkout, auch ohne
 * Container.
 *
 * Über diese beiden Manager registriert ein Projekt seine Routen und Console-Commands. Was
 * sie nach außen zusagen, muss der Symfony-Kernel aus Epic `009` identisch reproduzieren.
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

    public function testMountLiefertDenProviderZumWeiterverketten(): void
    {
        // custom/app.php nutzt genau das:
        //     $controllerProvider->mount('api/v1/example/', …)->post('/bootstrap', false, …)
        $manager = new RouteManager($this->app());

        $provider = $manager->mount('api/v1/beispiel/', '\Custom\Controller\Core\ExampleController');

        $this->assertInstanceOf(CustomControllerProvider::class, $provider);
        $this->assertSame($provider, $provider->post('/probe', false, 'probeAction'),
            'Die Routen-Methoden geben den Provider zurueck, damit sich Routen verketten lassen');
    }

    public function testZweiMountsAufDenselbenPfadUeberschreibenSich(): void
    {
        // Der Pfad ist der Schluessel im internen Array — es gibt keine Kollisionspruefung.
        // Ein Projekt, das zweimal denselben Pfad mountet, verliert den ersten Controller
        // stillschweigend.
        //
        // Beobachtet wird ueber einen Spion auf Application::mount(), nicht ueber Silex'
        // Routen-Interna: Die Zusage des RouteManagers lautet "bindRoutes() reicht jeden
        // gesammelten Mount an die Anwendung weiter" — genau das wird hier gemessen.
        $app     = new SpionApplication();
        $manager = new RouteManager($app);

        $manager->mount('doppelt/', '\Custom\Controller\Core\ExampleController');
        $manager->mount('doppelt/', '\Custom\Controller\Core\ExampleController');
        $manager->bindRoutes();

        $this->assertSame(array('doppelt/'), $app->gemountetePfade,
            'Zwei Mounts auf denselben Pfad ergeben einen gebundenen Mount, nicht zwei');
    }

    public function testBindRoutesReichtJedenGesammeltenMountWeiter(): void
    {
        $app     = new SpionApplication();
        $manager = new RouteManager($app);

        $manager->mount('erster/',  '\Custom\Controller\Core\ExampleController');
        $manager->mount('zweiter/', '\Custom\Controller\Core\ExampleController');

        $this->assertSame(array(), $app->gemountetePfade, 'mount() bindet noch nicht');

        $manager->bindRoutes();

        $this->assertSame(array('erster/', 'zweiter/'), $app->gemountetePfade,
            'Erst bindRoutes() bindet — und zwar in der Reihenfolge der Registrierung');
    }

    public function testOhneMountBindetBindRoutesNichts(): void
    {
        $app = new SpionApplication();
        (new RouteManager($app))->bindRoutes();

        $this->assertSame(array(), $app->gemountetePfade);
    }

    // ── ConsoleManager ─────────────────────────────────────────────────────────────────

    public function testAddCommandMussVorDemErstenZugriffAufDenDispatcherLaufen(): void
    {
        // Ein Befund, der beim Schreiben dieses Tests auffiel: addCommand() nutzt
        // $app->extend('dispatcher', …). Der Container friert einen Dienst ein, sobald er
        // ausgelesen wurde — wer den Dispatcher vorher anfasst, bekommt beim naechsten
        // addCommand() eine Ausnahme.
        //
        // Bis 009-002-0002 war das Pimples FrozenServiceException; seither wirft der eigene
        // Container eine RuntimeException. Das Verhalten ist dasselbe und mit Absicht
        // nachgebaut — die Reihenfolgebedingung ist echt, und ein Container, der sie
        // stillschweigend erlaubte, wuerde den Fehler verstecken.
        //
        // Im echten Ablauf ist das kein Problem: custom/app.php laeuft, bevor der erste
        // Request den Dispatcher benutzt. Aber es ist eine Reihenfolgebedingung, die
        // nirgends dokumentiert steht — und der Symfony-Kernel aus Epic 009 wird sie
        // entweder reproduzieren oder aufloesen muessen.
        $app     = $this->app();
        $manager = new ConsoleManager($app);

        $app['dispatcher']; // einmal auslesen — friert den Service ein

        $this->expectException(\RuntimeException::class);

        $manager->addCommand(new class extends CustomCommand {});
    }

    public function testAddCommandHaengtEinenListenerAnDenDispatcher(): void
    {
        // Der Manager registriert den Command nicht sofort, sondern haengt einen Listener
        // auf ConsoleEvents::INIT — der Command wird also erst angemeldet, wenn die Console
        // tatsaechlich startet.
        $app     = $this->app();
        $manager = new ConsoleManager($app);

        $manager->addCommand(new class extends CustomCommand {});

        $this->assertCount(1, $app['dispatcher']->getListeners(ConsoleEvents::INIT));
    }

    public function testMehrereCommandsErgebenMehrereListener(): void
    {
        $app     = $this->app();
        $manager = new ConsoleManager($app);

        $manager->addCommand(new class extends CustomCommand {});
        $manager->addCommand(new class extends CustomCommand {});

        $this->assertCount(2, $app['dispatcher']->getListeners(ConsoleEvents::INIT),
            'Anders als beim RouteManager gibt es hier keinen Schluessel, unter dem sich '
            .'etwas ueberschreiben koennte');
    }

    public function testCustomCommandPraefigiertDenNamenMitCustom(): void
    {
        // Die Basisklasse stellt den Namen auf `custom:<name>`, damit Projekt-Commands nie
        // mit denen des Frameworks kollidieren. Das ist der Vertrag, den custom/app.php
        // in seinem Kommentar beschreibt.
        $command = new class extends CustomCommand {};
        $command->setName('beispiel');

        $this->assertSame('custom:beispiel', $command->getName());
    }
}

/**
 * Anwendung, die sich merkt, welche Pfade gemountet wurden.
 *
 * Ein Spion statt einer Attrappe: Der RouteManager sagt zu, jeden gesammelten Mount an die
 * Anwendung weiterzureichen — das laesst sich hier direkt beobachten, ohne die interne
 * Routen-Struktur des Kernels zu befragen.
 */
class SpionApplication extends Application
{
    /** @var array<int,string> */
    public array $gemountetePfade = array();

    public function mount($prefix, $controllers): Application
    {
        $this->gemountetePfade[] = $prefix;

        return $this;
    }
}
