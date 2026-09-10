<?php
namespace Tests\Unit\Kernel;

use Areanet\PIM\Classes\Kernel\Application;
use Areanet\PIM\Classes\Kernel\Routing\Routensammlung;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Zwei Sammlungen dürfen sich beim Mounten nicht gegenseitig auffressen (013-001-0005).
 *
 * `Routensammlung` zählt ihre Routen **je Provider** durch: Die erste Route heisst `login_0`,
 * egal aus welchem Provider sie kommt. `RouteCollection::addCollection()` überschreibt beim
 * Namen — die später gemountete Sammlung verdrängte die frühere, lautlos.
 *
 * **Gemessen an der laufenden Anwendung:** 30 registrierte Routen, 29 in der Sammlung.
 * Verschwunden waren `POST /api/login` und `POST /api/logout`, verdrängt von ihren
 * Namensvettern unter `/auth`. Sie galten als „tote Routen, die auf nicht existierende
 * Methoden zeigen" — in Wahrheit erreichten sie den Router nie.
 *
 * Der Test misst genau das: gleicher Pfad, zwei Mountpunkte, beide müssen ankommen.
 */
class RoutenNamenTest extends TestCase
{
    private function sammlung(string $pfad): RouteCollection
    {
        $sammlung = new Routensammlung();
        $sammlung->post($pfad, 'irgendein.dienst:irgendeineAction');

        return $sammlung->sammlung();
    }

    public function testZweiMountpunkteMitGleichemPfadVerlierenKeineRoute(): void
    {
        $app = new Application();
        $app->mount('/api',  $this->sammlung('/login'));
        $app->mount('/auth', $this->sammlung('/login'));

        $pfade = array();
        foreach ($this->routen($app) as $route) {
            $pfade[] = $route->getPath();
        }

        sort($pfade);

        $this->assertSame(array('/api/login', '/auth/login'), $pfade);
    }

    /**
     * Der Name trägt den Mountpunkt — daher die Eindeutigkeit.
     *
     * Die Namen benutzt sonst niemand: Es gibt keinen `url_generator` und keinen Aufruf, der
     * eine Route beim Namen nennt. Eindeutig müssen sie trotzdem sein, sonst ist Mounten ein
     * Glücksspiel.
     */
    public function testDerRoutennameTraegtDenMountpunkt(): void
    {
        $app = new Application();
        $app->mount('api/v1/example/', $this->sammlung('bootstrap'));

        $this->assertSame(array('api_v1_example_bootstrap_0'), array_keys($this->routen($app)->all()));
    }

    private function routen(Application $app): RouteCollection
    {
        $eigenschaft = (new \ReflectionObject($app))->getProperty('routen');
        $eigenschaft->setAccessible(true);

        return $eigenschaft->getValue($app);
    }
}
