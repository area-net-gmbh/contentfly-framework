<?php
namespace Tests\Unit\Kernel;

use Areanet\PIM\Classes\Kernel\Application;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Die **effektive** Reihenfolge der before- und after-Hooks (`009-002-0004`).
 *
 * `an_project/docs/technical.md` sagt es als Regel:
 *
 * > Silex nimmt bei `before()`/`after()` ein Prioritätsargument. Wo es gesetzt war, war es
 * > Absicht — beim Portieren auf Symfony-Listener muss die effektive Reihenfolge nachgewiesen
 * > werden, nicht die Registrierungsreihenfolge geraten.
 *
 * Genau das steht hier. Die Tests registrieren Hooks in einer Reihenfolge und erwarten sie in
 * einer anderen; wäre die Priorität wirkungslos, kämen sie in der Registrierungsreihenfolge
 * und der Test wäre rot.
 */
class HookReihenfolgeTest extends TestCase
{
    private function anwendung(): Application
    {
        $app = new Application();

        // Eine Route, damit der Kernel überhaupt bis zum Controller kommt.
        $app->options('/{anything}', static fn (): Response => new JsonResponse(null, 204))
            ->assert('anything', '.*');

        return $app;
    }

    private function lauf(Application $app): Response
    {
        return $app->handle(Request::create('/beliebig', 'OPTIONS'));
    }

    public function testEineHoeherePrioritaetLaeuftFrueherUnabhaengigVonDerRegistrierung(): void
    {
        $reihe = array();
        $app   = $this->anwendung();

        // Absichtlich in der falschen Reihenfolge registriert.
        $app->before(function () use (&$reihe) { $reihe[] = 'niedrig'; }, -100);
        $app->before(function () use (&$reihe) { $reihe[] = 'hoch'; }, 128);
        $app->before(function () use (&$reihe) { $reihe[] = 'mitte'; }, 0);

        $this->lauf($app);

        $this->assertSame(array('hoch', 'mitte', 'niedrig'), $reihe);
    }

    public function testGleichePrioritaetLaeuftInRegistrierungsreihenfolge(): void
    {
        $reihe = array();
        $app   = $this->anwendung();

        $app->before(function () use (&$reihe) { $reihe[] = 'erster'; });
        $app->before(function () use (&$reihe) { $reihe[] = 'zweiter'; });

        $this->lauf($app);

        $this->assertSame(array('erster', 'zweiter'), $reihe);
    }

    public function testEinBeforeHookDerEineResponseZurueckgibtBrichtAb(): void
    {
        // Der dokumentierte Weg, einen Request zu blockieren — custom/app.php beschreibt ihn.
        $reihe = array();
        $app   = $this->anwendung();

        $app->before(function () use (&$reihe) {
            $reihe[] = 'blockt';

            return new JsonResponse(array('gesperrt' => true), 423);
        }, 100);

        $app->before(function () use (&$reihe) { $reihe[] = 'danach'; }, 0);

        $antwort = $this->lauf($app);

        $this->assertSame(423, $antwort->getStatusCode());
        $this->assertSame(array('blockt'), $reihe, 'Der zweite Hook laeuft nicht mehr');
    }

    public function testDerBeforeHookBekommtRequestUndAnwendung(): void
    {
        $gesehen = array();
        $app     = $this->anwendung();

        $app->before(function ($request, $anwendung) use (&$gesehen) {
            $gesehen = array(get_class($request), $anwendung instanceof Application);
        });

        $this->lauf($app);

        $this->assertSame(array(Request::class, true), $gesehen);
    }

    public function testAfterHooksLaufenNachPrioritaetUndSehenDieAntwort(): void
    {
        $reihe = array();
        $app   = $this->anwendung();

        $app->after(function ($request, Response $response) use (&$reihe) {
            $reihe[] = 'spaet';
            $response->headers->set('X-Spaet', '1');
        }, -50);

        $app->after(function ($request, Response $response) use (&$reihe) {
            $reihe[] = 'frueh';
            $response->headers->set('X-Frueh', '1');
        }, 50);

        $antwort = $this->lauf($app);

        $this->assertSame(array('frueh', 'spaet'), $reihe);
        $this->assertSame('1', $antwort->headers->get('X-Frueh'));
        $this->assertSame('1', $antwort->headers->get('X-Spaet'));
    }
}
