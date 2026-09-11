<?php
namespace Tests\Unit\Manager;

use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Areanet\PIM\Classes\Exceptions\ContentflyException;
use Areanet\PIM\Classes\Manager\PluginManager;
use Areanet\PIM\Classes\Manager\TypeManager;
use PHPUnit\Framework\TestCase;
use Areanet\PIM\Classes\Kernel\Application;

/**
 * Charakterisierungstests für die Plugin-Infrastruktur.
 *
 * Story `012-006-0002` hat mit einem Wegwerf-Plugin nachgewiesen, dass die Erweiterbarkeit den
 * Rückbau der Oberfläche überlebt hat. Der Nachweis existierte danach nur als Text. Hier wird
 * er dauerhaft.
 *
 * **Warum als Unit-Test und nicht über HTTP:** Ein Plugin wird ausschließlich in
 * `custom/app.php` registriert — es gibt keinen konfigurationsgesteuerten Weg, und
 * `bootstrap-web.php` endet mit `$app->run()`, bietet also keine Naht für die
 * Testinfrastruktur. Ein Integrationstest müsste die **Vorlage** dauerhaft mit Testcode
 * belasten; `custom/` ist aber die Referenz, an der sich jedes Projekt orientiert
 * (Epic `007`). Das wäre der falsche Preis.
 *
 * Die Plugin-Klassen entstehen deshalb zur Laufzeit unter `plugins/` — ein Verzeichnis, das
 * `.gitignore` ohnehin ausschließt — und werden über die PSR-4-Zuordnung `Plugins\` geladen.
 */
class PluginManagerTest extends TestCase
{
    /** @var array<int,string> Verzeichnisse, die tearDown() entfernt. */
    private array $angelegteVerzeichnisse = array();

    private function app(): Application
    {
        $app = new Application();
        $app['orm.em'] = null;

        return $app;
    }

    /**
     * Schreibt ein lauffähiges Plugin nach `plugins/<Key>/` und liefert seinen Key.
     *
     * Der Key ist je Aufruf eindeutig: PHP kann eine einmal geladene Klasse nicht wieder
     * vergessen, zwei Tests mit demselben Key würden sich also überlagern.
     */
    private function pluginSchreiben(string $rumpf = '', string $entityQuelltext = ''): string
    {
        $key = 'Probe'.bin2hex(random_bytes(5));
        $dir = CONTENTFLY_PROJEKT.'/plugins/'.$key;

        mkdir($dir, 0777, true);
        $this->angelegteVerzeichnisse[] = $dir;

        file_put_contents($dir.'/'.$key.'Plugin.php', <<<PHP
<?php
namespace Plugins\\$key;

use Areanet\\PIM\\Classes\\Plugin;

class {$key}Plugin extends Plugin
{
$rumpf
}
PHP
        );

        if ($entityQuelltext !== '') {
            mkdir($dir.'/Entity', 0777, true);
            file_put_contents($dir.'/Entity/Beispiel.php', $entityQuelltext);
        }

        return $key;
    }

    protected function tearDown(): void
    {
        foreach ($this->angelegteVerzeichnisse as $dir) {
            $this->verzeichnisEntfernen($dir);
        }

        $this->angelegteVerzeichnisse = array();
    }

    private function verzeichnisEntfernen(string $pfad): void
    {
        if (!is_dir($pfad)) {
            return;
        }

        foreach (scandir($pfad) ?: array() as $eintrag) {
            if ($eintrag === '.' || $eintrag === '..') {
                continue;
            }
            $voll = $pfad.'/'.$eintrag;
            is_dir($voll) ? $this->verzeichnisEntfernen($voll) : @unlink($voll);
        }

        @rmdir($pfad);
    }

    // ── Registrierung ──────────────────────────────────────────────────────────────────

    public function testEinPluginWirdUnterSeinemKeyAbgelegt(): void
    {
        $key     = $this->pluginSchreiben();
        $manager = new PluginManager($this->app());

        $manager->register($key);

        $this->assertInstanceOf(
            'Plugins\\'.$key.'\\'.$key.'Plugin',
            $manager->getPlugin($key)
        );
    }

    public function testDerKeyUndDerNamespaceKommenAusDemKlassennamen(): void
    {
        // Plugin::__construct() zerlegt get_class($this): Teil 1 ist der Key, Teil 0 und 1
        // zusammen der Namespace. Ein Plugin muss also unter Plugins\<Key>\<Key>Plugin
        // liegen — das ist nirgends dokumentiert, aber zwingend.
        $key     = $this->pluginSchreiben();
        $manager = new PluginManager($this->app());

        $manager->register($key);
        $plugin = $manager->getPlugin($key);

        $this->assertSame($key, $plugin->getKey());
        $this->assertSame('Plugins\\'.$key, $plugin->getNamespace());
    }

    public function testEinUnbekanntesPluginWirdMitEinerAusnahmeAbgewiesen(): void
    {
        $manager = new PluginManager($this->app());

        $this->expectException(ContentflyException::class);

        $manager->register('GibtesNicht');
    }

    public function testEineKlasseDieNichtVonPluginErbtWirdAbgewiesen(): void
    {
        $key = 'Falsch'.bin2hex(random_bytes(5));
        $dir = CONTENTFLY_PROJEKT.'/plugins/'.$key;
        mkdir($dir, 0777, true);
        $this->angelegteVerzeichnisse[] = $dir;

        file_put_contents($dir.'/'.$key.'Plugin.php', <<<PHP
<?php
namespace Plugins\\$key;

class {$key}Plugin
{
    public function __construct(\$app, \$options = null) {}
}
PHP
        );

        $manager = new PluginManager($this->app());

        $this->expectException(ContentflyException::class);

        $manager->register($key);
    }

    // ── Der Fehlerpfad von getPlugin() — siehe 000-000-0011 ────────────────────────────

    /**
     * **Umgedreht mit `000-000-0011`, nicht geloescht.**
     *
     * Der Test hiess `testGetPluginVerliertDenPluginNamenAusDerFehlermeldung()` und hielt den
     * Defekt fest: Der Fehlerpfad warf
     *
     *     throw new ContentflyException(Messages::contentfly_general_unknown_plugin, $key);
     *
     * `$key` gab es in dieser Methode nicht — gemeint war `$pluginName`. Unter PHP 8 ist das
     * keine Ausnahme, sondern eine **Warning**, und der Ausdruck ergibt null: Die Exception kam
     * wie vorgesehen, aber **ohne den Namen des gesuchten Plugins**.
     *
     * Jetzt traegt sie ihn. Der Error-Handler bleibt trotzdem stehen — er ist nicht mehr die
     * Zusicherung, sondern ihre Gegenprobe: `assertSame(array(), $warnungen)` faellt auf, wenn
     * jemand die Variable wieder verliert. `phpunit.xml.dist` setzt `failOnWarning`, eine
     * ungefangene Warning faerbte den Lauf ohnehin rot — hier wird sie gezaehlt statt nur
     * verhindert.
     */
    public function testGetPluginNenntDenGesuchtenPluginNamen(): void
    {
        $manager   = new PluginManager($this->app());
        $warnungen = array();

        set_error_handler(function (int $stufe, string $meldung) use (&$warnungen): bool {
            $warnungen[] = $meldung;

            return true;
        }, E_WARNING);

        try {
            $manager->getPlugin('GibtesNicht');
            $this->fail('Es haette eine ContentflyException kommen muessen');
        } catch (ContentflyException $e) {
            $this->assertSame('contentfly_general_unknown_plugin', $e->getMessage());
            $this->assertSame('GibtesNicht', $e->getValue(),
                'Wer den Fehler untersucht, muss erfahren, wonach gesucht wurde');
        } finally {
            restore_error_handler();
        }

        $this->assertSame(array(), $warnungen, 'Keine Warning mehr — die Variable ist definiert');
    }

    // ── Entities ───────────────────────────────────────────────────────────────────────

    public function testOhneUseOrmMeldetEinPluginKeineEntities(): void
    {
        $key     = $this->pluginSchreiben();
        $manager = new PluginManager($this->app());

        $manager->register($key);

        $this->assertSame(array(), $manager->getEntities(),
            'getEntities() liefert erst etwas, wenn das Plugin useORM() aufgerufen hat');
    }

    public function testUseOrmRegistriertEinenAttributeDriverFuerDasPluginVerzeichnis(): void
    {
        // Der Kern der Erweiterbarkeit: useORM() haengt einen Metadaten-Treiber fuer
        // plugins/<Key>/Entity unter dem Namespace Plugins\<Key>\Entity in die
        // Doctrine-Konfiguration. Beobachtet ueber einen Spion auf der Konfiguration —
        // ein echter EntityManager waere hier nicht ehrlicher, nur langsamer.
        //
        // ES IST SEIT 010-001-0004 EIN AttributeDriver, vorher ein Annotation-Driver. Das ist
        // ein Verhaltenswechsel und keine Umformulierung: Ein Plugin, dessen Entities noch
        // Docblock-Annotationen tragen, wird ab hier nicht mehr gelesen.
        //
        // Die Umstellung war nicht wahlfrei. Eine Plugin-Entity erbt von
        // Areanet\PIM\Entity\Base, und bei einer MappedSuperclass setzt Doctrine an den
        // geerbten Feldern kein `inherited` — der Treiber der Unterklasse liest sie neu und
        // faende an der umgestellten Base nichts mehr. Gemessen in 010-001-0003.
        $key = $this->pluginSchreiben('    public function init(){ $this->useORM(); }');

        $spion = new OrmKonfigurationsSpion();
        $app   = $this->app();
        $app['orm.em'] = new EntityManagerAttrappe($spion);

        (new PluginManager($app))->register($key);

        $this->assertSame(
            'Plugins\\'.$key.'\\Entity',
            $spion->namespace,
            'Der Driver wird unter dem Entity-Namespace des Plugins eingehaengt'
        );
        $this->assertInstanceOf(
            AttributeDriver::class,
            $spion->treiber,
            'und ist ein AttributeDriver, kein Annotation-Driver'
        );
        $this->assertSame(
            array(CONTENTFLY_PROJEKT.'/plugins/'.$key.'/Entity'),
            $spion->treiber->getPaths(),
            'und zeigt auf das Entity-Verzeichnis des Plugins'
        );
    }

    public function testUseOrmLegtDasEntityVerzeichnisAnWennEsFehlt(): void
    {
        // initORM() ruft mkdir(), wenn plugins/<Key>/Entity nicht existiert — ein Plugin
        // muss das Verzeichnis also nicht mitliefern.
        $key = $this->pluginSchreiben('    public function init(){ $this->useORM(); }');
        $this->assertDirectoryDoesNotExist(CONTENTFLY_PROJEKT.'/plugins/'.$key.'/Entity', 'Vorbedingung');

        $app = $this->app();
        $app['orm.em'] = new EntityManagerAttrappe(new OrmKonfigurationsSpion());

        (new PluginManager($app))->register($key);

        $this->assertDirectoryExists(CONTENTFLY_PROJEKT.'/plugins/'.$key.'/Entity');
    }

    public function testMitUseOrmSammeltGetEntitiesDieKlassenAusDemVerzeichnis(): void
    {
        $key = $this->pluginSchreiben(
            '    public function init(){ $this->useORM(); }',
            "<?php\nnamespace Plugins\\Platzhalter\\Entity;\nclass Beispiel {}\n"
        );

        $app = $this->app();
        $app['orm.em'] = new EntityManagerAttrappe(new OrmKonfigurationsSpion());

        $manager = new PluginManager($app);
        $manager->register($key);

        $this->assertSame(
            array('Plugins\\'.$key.'\\Entity\\Beispiel'),
            $manager->getEntities(),
            'Jede PHP-Datei im Entity-Verzeichnis wird zu einem Klassennamen'
        );
    }

    // ── Eigene Feldtypen ───────────────────────────────────────────────────────────────

    public function testEinPluginKannEinenEigenenFeldtypRegistrieren(): void
    {
        $app = $this->app();
        $typeManager = new TypeManager($app);
        $app['typeManager'] = $typeManager;

        $key = $this->pluginSchreiben(<<<'RUMPF'
    public function init(){
        $this->registerPluginType(new class($this->app) extends \Areanet\PIM\Classes\Type\PluginType {
            public function doMatch($propertyAnnotations) { return false; }
            public function getAlias() { return 'pluginprobe'; }
            public function getAnnotationFile() { return null; }
        });
    }
RUMPF
        );

        (new PluginManager($app))->register($key);

        $typ = $typeManager->getType('pluginprobe');

        $this->assertNotNull($typ, 'Der Typ ist unter seinem Alias registriert');
        $this->assertSame($key, $typ->getPluginKey(),
            'registerPluginType() setzt den Plugin-Key — daran haengt spaeter der Pfad zur '
            .'Annotationsdatei');
    }
}

/** Merkt sich, mit welchem Pfad und Namespace ein Driver eingehaengt wurde. */
class OrmKonfigurationsSpion
{
    public ?string $namespace = null;

    /**
     * Der Treiber, den Plugin::initORM() eingehaengt hat.
     *
     * Vorher fing der Spion stattdessen `newDefaultAnnotationDriver()` ab und merkte sich
     * dessen Pfade. Die Methode wird nicht mehr gerufen (010-001-0004), und sie steht hier
     * bewusst NICHT mehr: Rufe der Code sie doch, staerbe der Spion an einer undefinierten
     * Methode — laut ist besser als still.
     *
     * @var object|null
     */
    public $treiber = null;

    public function getMetadataDriverImpl(): self
    {
        return $this;
    }

    public function addDriver($driver, string $namespace): void
    {
        $this->treiber   = $driver;
        $this->namespace = $namespace;
    }
}

/** Liefert nur die Konfiguration — mehr fragt Plugin::initORM() nicht ab. */
class EntityManagerAttrappe
{
    public function __construct(private OrmKonfigurationsSpion $konfiguration) {}

    public function getConfiguration(): OrmKonfigurationsSpion
    {
        return $this->konfiguration;
    }
}
