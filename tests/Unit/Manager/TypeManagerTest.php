<?php
namespace Tests\Unit\Manager;

use Areanet\PIM\Classes\Exceptions\ContentflyException;
use Areanet\PIM\Classes\Manager\TypeManager;
use Areanet\PIM\Classes\Type;
use Areanet\PIM\Classes\Types\BooleanType;
use Areanet\PIM\Classes\Types\StringType;
use PHPUnit\Framework\TestCase;
use Areanet\PIM\Classes\Kernel\Application;

/**
 * Charakterisierungstests für den `TypeManager` — die Registrierung der Feldtypen.
 *
 * Bewusst als **Unit-Test**: Der Manager hängt allein an einem `Kernel\Application`-Objekt und
 * greift auf `$app['orm.em']` nur durch, wenn ein Typ es benutzt. Für Registrierung und
 * Abfrage braucht es weder Datenbank noch HTTP.
 *
 * Epic `009` baut den Kernel darunter aus. Was hier zugesichert ist, muss danach identisch
 * gelten — über den `TypeManager` registriert ein Projekt seine eigenen Feldtypen.
 */
class TypeManagerTest extends TestCase
{
    private function app(): Application
    {
        $app = new Application();
        // Die Typen erwarten den EntityManager im Konstruktor; fuer Registrierung und
        // Abfrage wird er nicht angefasst.
        $app['orm.em'] = null;

        return $app;
    }

    public function testEinTypWirdUnterSeinemAliasAbgelegt(): void
    {
        $app  = $this->app();
        $manager = new TypeManager($app);

        $manager->registerType(new StringType($app));

        $this->assertInstanceOf(StringType::class, $manager->getType('string'));
    }

    public function testEinUnbekannterAliasLiefertNullStattZuWerfen(): void
    {
        // Festgehalten, weil es von der sonstigen Fehlerbehandlung des Frameworks abweicht:
        // Api und die Controller werfen bei Unbekanntem eine ContentflyException. getType()
        // gibt null zurueck — der Aufrufer muss selbst prüfen.
        $manager = new TypeManager($this->app());

        $this->assertNull($manager->getType('gibtesnicht'));
    }

    public function testGetTypesLiefertAlleRegistriertenTypenNachAliasGeschluesselt(): void
    {
        $app     = $this->app();
        $manager = new TypeManager($app);

        $manager->registerType(new StringType($app));
        $manager->registerType(new BooleanType($app));

        $typen = $manager->getTypes();

        $this->assertSame(array('string', 'boolean'), array_keys($typen));
        $this->assertInstanceOf(BooleanType::class, $typen['boolean']);
    }

    public function testEinZweiterTypMitDemselbenAliasErsetztDenErsten(): void
    {
        // Der Alias ist der Schluessel — es gibt keine Kollisionspruefung. Ein Projekt, das
        // einen eigenen Typ unter einem vorhandenen Alias registriert, ueberschreibt den
        // Framework-Typ stillschweigend. Das ist der Weg, wie man einen Typ ersetzt; es ist
        // zugleich der Weg, wie man ihn versehentlich verliert.
        $app     = $this->app();
        $manager = new TypeManager($app);

        $ersterTyp = new StringType($app);
        $manager->registerType($ersterTyp);

        $zweiterTyp = new StringType($app);
        $manager->registerType($zweiterTyp);

        $this->assertSame($zweiterTyp, $manager->getType('string'));
        $this->assertNotSame($ersterTyp, $manager->getType('string'));
    }

    public function testEinPluginTypeWirdMitEinerAusnahmeAbgewiesen(): void
    {
        // PluginType gehoert ueber registerPluginType() angemeldet, weil dabei der
        // Plugin-Key gesetzt und die Annotationsdatei aus dem Plugin-Verzeichnis geladen
        // wird. registerType() weist ihn deshalb ab.
        $app     = $this->app();
        $manager = new TypeManager($app);

        $this->expectException(ContentflyException::class);

        $manager->registerType(new class($app) extends Type\PluginType {
            public function doMatch($propertyAnnotations) { return false; }
            public function getAlias() { return 'pluginprobe'; }
        });
    }

    public function testEinTypBrauchtKeineAnnotationsdateiMehr(): void
    {
        // UMGEDREHT MIT 010-001-0005, und das ist ein Verhaltenswechsel.
        //
        // Vorher hiess der Test „…OhneAnnotationsdateiWirdOhneRegistrierungAbgelegt" und
        // sicherte zu, dass `getAnnotationFile() === null` KEIN
        // `AnnotationRegistry::registerFile()` ausloest — sonst waere die Registrierung eines
        // Typs ohne eigene Annotation gescheitert.
        //
        // Die Methode gibt es nicht mehr. Sie nannte dem DocParser den Dateipfad einer
        // Annotationsklasse, weil der eine Annotation nur aufloest, wenn ihre Klasse bereits
        // bekannt ist. Ein Attribut nennt eine echte Klasse; der Autoloader holt sie. Damit
        // hat die Registrierung keinen Gegenstand mehr, und der Test sichert nur noch, dass
        // das Ablegen ohne sie funktioniert.
        $app     = $this->app();
        $manager = new TypeManager($app);

        $typ = new StringType($app);
        $this->assertFalse(method_exists($typ, 'getAnnotationFile'),
            'getAnnotationFile() ist mit 010-001-0005 entfallen');

        $manager->registerType($typ);

        $this->assertSame($typ, $manager->getType('string'));
    }
}
