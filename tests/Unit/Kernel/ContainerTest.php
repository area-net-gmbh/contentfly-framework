<?php
namespace Tests\Unit\Kernel;

use Areanet\PIM\Classes\Kernel\Container;
use PHPUnit\Framework\TestCase;

/**
 * Der Container aus `009-002-0002`, gegen genau die Zusagen geprüft, wegen derer er
 * geschrieben wurde.
 *
 * Er bildet Pimples Vertrag nach, weil `custom/app.php` daran hängt — und weil ein
 * Bestandsprojekt seine Dienste genau so registriert. Was hier steht, ist damit kein
 * Implementierungsdetail, sondern die Schnittstelle zum Projekt.
 */
class ContainerTest extends TestCase
{
    public function testEinWertKommtZurueckWieErAbgelegtWurde(): void
    {
        $c = new Container();
        $c['zahl'] = 42;

        $this->assertSame(42, $c['zahl']);
    }

    public function testEineClosureIstEineFactoryUndLaeuftErstBeimZugriff(): void
    {
        // Der Kern des Vertrags: Registrieren fuehrt nichts aus. custom/app.php wird beim
        // Bootstrap gelesen, lange bevor eine Datenbank steht — eine Factory, die sofort
        // liefe, wuerde dort auf einen EntityManager zugreifen, den es noch nicht gibt.
        $gelaufen = false;

        $c = new Container();
        $c['dienst'] = function () use (&$gelaufen) {
            $gelaufen = true;

            return new \stdClass();
        };

        $this->assertFalse($gelaufen, 'Registrieren allein fuehrt die Factory nicht aus');

        $c['dienst'];

        $this->assertTrue($gelaufen, 'Der erste Zugriff tut es');
    }

    public function testDieFactoryBekommtDenContainerAlsArgument(): void
    {
        // So loest custom/app.php Abhaengigkeiten auf:
        //     $app['meine.service'] = function ($app) { return new X($app['orm.em']); };
        $c = new Container();
        $c['abhaengigkeit'] = 'da';
        $c['dienst'] = function ($app) {
            return 'gebaut mit: '.$app['abhaengigkeit'];
        };

        $this->assertSame('gebaut mit: da', $c['dienst']);
    }

    public function testEineFactoryLaeuftGenauEinmal(): void
    {
        // Darauf beruht, dass $app['orm.em'] ueberall derselbe EntityManager ist.
        $c = new Container();
        $c['objekt'] = function () {
            return new \stdClass();
        };

        $this->assertSame($c['objekt'], $c['objekt']);
    }

    public function testEinUnbekannterSchluesselWirftStattNullZuLiefern(): void
    {
        // Null zurueckzugeben hiesse, einen Tippfehler in einen stillen Fehler weit spaeter
        // zu verwandeln.
        $c = new Container();

        $this->expectException(\InvalidArgumentException::class);

        $c['gibtsnicht'];
    }

    public function testIssetUndUnsetWirkenWieErwartet(): void
    {
        $c = new Container();
        $c['da'] = 1;

        $this->assertTrue(isset($c['da']));
        $this->assertFalse(isset($c['nichtda']));

        unset($c['da']);

        $this->assertFalse(isset($c['da']));
    }

    public function testEinEintragMitNullGiltAlsVorhanden(): void
    {
        // bootstrap.php legt $app['auth.user'] = null ab und setzt ihn spaeter. Wuerde isset()
        // darauf false liefern, waere die Unterscheidung "nicht registriert" gegen "noch
        // niemand angemeldet" verloren.
        $c = new Container();
        $c['auth.user'] = null;

        $this->assertTrue(isset($c['auth.user']));
        $this->assertNull($c['auth.user']);
    }

    // ── extend() und das Einfrieren ────────────────────────────────────────────────────

    public function testExtendUmschliesstDieAlteFactory(): void
    {
        $c = new Container();
        $c['liste'] = function () {
            return array('eins');
        };

        $c->extend('liste', function (array $alt) {
            $alt[] = 'zwei';

            return $alt;
        });

        $this->assertSame(array('eins', 'zwei'), $c['liste']);
    }

    public function testExtendNachDemErstenZugriffWirft(): void
    {
        // Die Zusage, wegen derer das Einfrieren ueberhaupt nachgebaut ist. Der
        // ConsoleManager ergaenzt den Dispatcher ueber extend() und muss das tun, bevor
        // jemand ihn ausliest; 000-000-0006 ist genau darueber gestolpert. Ein Container,
        // der das stillschweigend erlaubte, wuerde den Fehler verstecken.
        $c = new Container();
        $c['dispatcher'] = function () {
            return new \stdClass();
        };

        $c['dispatcher'];

        $this->expectException(\RuntimeException::class);

        $c->extend('dispatcher', function ($alt) {
            return $alt;
        });
    }

    public function testExtendAufEinemWertWirft(): void
    {
        $c = new Container();
        $c['zahl'] = 42;

        $this->expectException(\InvalidArgumentException::class);

        $c->extend('zahl', function ($alt) {
            return $alt;
        });
    }

    public function testNeuSetzenHebtDasEinfrierenAuf(): void
    {
        // Wer eine Definition ersetzt, faengt von vorn an — sonst waere ein Dienst nach dem
        // ersten Zugriff fuer immer festgelegt, auch fuer den, der ihn bewusst austauscht.
        $c = new Container();
        $c['dienst'] = function () {
            return 'alt';
        };

        $c['dienst'];

        $c['dienst'] = function () {
            return 'neu';
        };

        $c->extend('dienst', function ($alt) {
            return $alt.'+erweitert';
        });

        $this->assertSame('neu+erweitert', $c['dienst']);
    }

    public function testKeysLiefertDieSchluesselInRegistrierungsreihenfolge(): void
    {
        $c = new Container();
        $c['erster'] = 1;
        $c['zweiter'] = 2;

        $this->assertSame(array('erster', 'zweiter'), $c->keys());
    }
}
