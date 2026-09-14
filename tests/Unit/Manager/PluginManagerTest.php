<?php
namespace Tests\Unit\Manager;

use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Areanet\PIM\Classes\Exceptions\ContentflyException;
use Areanet\PIM\Classes\Manager\PluginManager;
use Areanet\PIM\Classes\Manager\TypeManager;
use PHPUnit\Framework\TestCase;
use Areanet\PIM\Classes\Kernel\Application;

/**
 * Characterisation tests for the plugin infrastructure.
 *
 * Story `012-006-0002` proved with a throwaway plugin that extensibility survived the removal
 * of the user interface. Afterwards the proof existed only as text. Here it becomes permanent.
 *
 * **Why as a unit test and not over HTTP:** a plugin is registered exclusively in
 * `custom/app.php` — there is no configuration-driven way, and `bootstrap-web.php` ends with
 * `$app->run()`, so it offers no seam for the test infrastructure. An integration test would
 * have to burden the **template** permanently with test code; but `custom/` is the reference
 * every project orients itself by (Epic `007`). That would be the wrong price.
 *
 * The plugin classes are therefore created at runtime under `plugins/` — a directory that
 * `.gitignore` excludes anyway — and are loaded via the PSR-4 mapping `Plugins\`.
 */
class PluginManagerTest extends TestCase
{
    /** @var array<int,string> Directories that tearDown() removes. */
    private array $createdDirectories = array();

    private function app(): Application
    {
        $app = new Application();
        $app['orm.em'] = null;

        return $app;
    }

    /**
     * Writes a working plugin to `plugins/<Key>/` and returns its key.
     *
     * The key is unique per call: PHP cannot forget a class once it has been loaded, so two
     * tests with the same key would overlap.
     */
    private function writePlugin(string $body = '', string $entitySource = ''): string
    {
        $key = 'Sample'.bin2hex(random_bytes(5));
        $dir = CONTENTFLY_PROJECT_DIR.'/plugins/'.$key;

        mkdir($dir, 0777, true);
        $this->createdDirectories[] = $dir;

        file_put_contents($dir.'/'.$key.'Plugin.php', <<<PHP
<?php
namespace Plugins\\$key;

use Areanet\\PIM\\Classes\\Plugin;

class {$key}Plugin extends Plugin
{
$body
}
PHP
        );

        if ($entitySource !== '') {
            mkdir($dir.'/Entity', 0777, true);
            file_put_contents($dir.'/Entity/Example.php', $entitySource);
        }

        return $key;
    }

    protected function tearDown(): void
    {
        foreach ($this->createdDirectories as $dir) {
            $this->removeDirectory($dir);
        }

        $this->createdDirectories = array();
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        foreach (scandir($path) ?: array() as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $full = $path.'/'.$entry;
            is_dir($full) ? $this->removeDirectory($full) : @unlink($full);
        }

        @rmdir($path);
    }

    // ── Registration ───────────────────────────────────────────────────────────────────

    public function testAPluginIsStoredUnderItsKey(): void
    {
        $key     = $this->writePlugin();
        $manager = new PluginManager($this->app());

        $manager->register($key);

        $this->assertInstanceOf(
            'Plugins\\'.$key.'\\'.$key.'Plugin',
            $manager->getPlugin($key)
        );
    }

    public function testTheKeyAndTheNamespaceComeFromTheClassName(): void
    {
        // Plugin::__construct() splits get_class($this): part 1 is the key, parts 0 and 1
        // together the namespace. A plugin must therefore live at Plugins\<Key>\<Key>Plugin
        // — that is documented nowhere, but mandatory.
        $key     = $this->writePlugin();
        $manager = new PluginManager($this->app());

        $manager->register($key);
        $plugin = $manager->getPlugin($key);

        $this->assertSame($key, $plugin->getKey());
        $this->assertSame('Plugins\\'.$key, $plugin->getNamespace());
    }

    public function testAnUnknownPluginIsRejectedWithAnException(): void
    {
        $manager = new PluginManager($this->app());

        $this->expectException(ContentflyException::class);

        $manager->register('DoesNotExist');
    }

    public function testAClassThatDoesNotExtendPluginIsRejected(): void
    {
        $key = 'Wrong'.bin2hex(random_bytes(5));
        $dir = CONTENTFLY_PROJECT_DIR.'/plugins/'.$key;
        mkdir($dir, 0777, true);
        $this->createdDirectories[] = $dir;

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

    // ── The error path of getPlugin() — see 000-000-0011 ───────────────────────────────

    /**
     * **Inverted with `000-000-0011`, not deleted.**
     *
     * The test was called `testGetPluginVerliertDenPluginNamenAusDerFehlermeldung()` and
     * recorded the defect: the error path threw
     *
     *     throw new ContentflyException(Messages::contentfly_general_unknown_plugin, $key);
     *
     * `$key` did not exist in this method — `$pluginName` was meant. Under PHP 8 that is not
     * an exception but a **warning**, and the expression evaluates to null: the exception came
     * as intended, but **without the name of the plugin being looked for**.
     *
     * Now it carries it. The error handler stays anyway — it is no longer the assertion but its
     * counter-check: `assertSame(array(), $warnings)` catches it if someone loses the variable
     * again. `phpunit.xml.dist` sets `failOnWarning`, so an uncaught warning would turn the run
     * red anyway — here it is counted instead of merely prevented.
     */
    public function testGetPluginNamesThePluginBeingLookedFor(): void
    {
        $manager  = new PluginManager($this->app());
        $warnings = array();

        set_error_handler(function (int $level, string $message) use (&$warnings): bool {
            $warnings[] = $message;

            return true;
        }, E_WARNING);

        try {
            $manager->getPlugin('DoesNotExist');
            $this->fail('A ContentflyException should have been thrown');
        } catch (ContentflyException $e) {
            $this->assertSame('contentfly_general_unknown_plugin', $e->getMessage());
            $this->assertSame('DoesNotExist', $e->getValue(),
                'Whoever investigates the error must learn what was being looked for');
        } finally {
            restore_error_handler();
        }

        $this->assertSame(array(), $warnings, 'No warning any more — the variable is defined');
    }

    // ── Entities ───────────────────────────────────────────────────────────────────────

    public function testWithoutUseOrmAPluginReportsNoEntities(): void
    {
        $key     = $this->writePlugin();
        $manager = new PluginManager($this->app());

        $manager->register($key);

        $this->assertSame(array(), $manager->getEntities(),
            'getEntities() only returns something once the plugin has called useORM()');
    }

    public function testUseOrmRegistersAnAttributeDriverForThePluginDirectory(): void
    {
        // The core of extensibility: useORM() hooks a metadata driver for
        // plugins/<Key>/Entity under the namespace Plugins\<Key>\Entity into the
        // Doctrine configuration. Observed through a spy on the configuration —
        // a real EntityManager would not be more honest here, only slower.
        //
        // SINCE 010-001-0004 IT IS AN AttributeDriver, previously an annotation driver. That is
        // a change in behaviour and not a rewording: a plugin whose entities still carry
        // docblock annotations is no longer read from here on.
        //
        // The switch was not optional. A plugin entity inherits from
        // Areanet\PIM\Entity\Base, and for a MappedSuperclass Doctrine sets no `inherited` on
        // the inherited fields — the driver of the subclass reads them anew and would find
        // nothing on the converted Base. Measured in 010-001-0003.
        $key = $this->writePlugin('    public function init(){ $this->useORM(); }');

        $spy = new OrmConfigurationSpy();
        $app = $this->app();
        $app['orm.em'] = new EntityManagerStub($spy);

        (new PluginManager($app))->register($key);

        $this->assertSame(
            'Plugins\\'.$key.'\\Entity',
            $spy->namespace,
            'The driver is hooked in under the entity namespace of the plugin'
        );
        $this->assertInstanceOf(
            AttributeDriver::class,
            $spy->driver,
            'and is an AttributeDriver, not an annotation driver'
        );
        $this->assertSame(
            array(CONTENTFLY_PROJECT_DIR.'/plugins/'.$key.'/Entity'),
            $spy->driver->getPaths(),
            'and points to the entity directory of the plugin'
        );
    }

    public function testUseOrmCreatesTheEntityDirectoryIfItIsMissing(): void
    {
        // initORM() calls mkdir() if plugins/<Key>/Entity does not exist — so a plugin
        // does not have to ship the directory.
        $key = $this->writePlugin('    public function init(){ $this->useORM(); }');
        $this->assertDirectoryDoesNotExist(CONTENTFLY_PROJECT_DIR.'/plugins/'.$key.'/Entity', 'Precondition');

        $app = $this->app();
        $app['orm.em'] = new EntityManagerStub(new OrmConfigurationSpy());

        (new PluginManager($app))->register($key);

        $this->assertDirectoryExists(CONTENTFLY_PROJECT_DIR.'/plugins/'.$key.'/Entity');
    }

    public function testWithUseOrmGetEntitiesCollectsTheClassesFromTheDirectory(): void
    {
        $key = $this->writePlugin(
            '    public function init(){ $this->useORM(); }',
            "<?php\nnamespace Plugins\\Placeholder\\Entity;\nclass Example {}\n"
        );

        $app = $this->app();
        $app['orm.em'] = new EntityManagerStub(new OrmConfigurationSpy());

        $manager = new PluginManager($app);
        $manager->register($key);

        $this->assertSame(
            array('Plugins\\'.$key.'\\Entity\\Example'),
            $manager->getEntities(),
            'Every PHP file in the entity directory becomes a class name'
        );
    }

    // ── Custom field types ─────────────────────────────────────────────────────────────

    public function testAPluginCanRegisterItsOwnFieldType(): void
    {
        $app = $this->app();
        $typeManager = new TypeManager($app);
        $app['typeManager'] = $typeManager;

        $key = $this->writePlugin(<<<'BODY'
    public function init(){
        $this->registerPluginType(new class($this->app) extends \Areanet\PIM\Classes\Type\PluginType {
            public function doMatch($propertyAnnotations) { return false; }
            public function getAlias() { return 'pluginsample'; }
            public function getAnnotationFile() { return null; }
        });
    }
BODY
        );

        (new PluginManager($app))->register($key);

        $type = $typeManager->getType('pluginsample');

        $this->assertNotNull($type, 'The type is registered under its alias');
        $this->assertSame($key, $type->getPluginKey(),
            'registerPluginType() sets the plugin key — the path to the annotation file '
            .'depends on it later');
    }
}

/** Remembers with which path and namespace a driver was hooked in. */
class OrmConfigurationSpy
{
    public ?string $namespace = null;

    /**
     * The driver that Plugin::initORM() hooked in.
     *
     * Previously the spy intercepted `newDefaultAnnotationDriver()` instead and remembered
     * its paths. The method is no longer called (010-001-0004), and it is deliberately NOT
     * here any more: should the code call it after all, the spy would die on an undefined
     * method — loud is better than silent.
     *
     * @var object|null
     */
    public $driver = null;

    public function getMetadataDriverImpl(): self
    {
        return $this;
    }

    public function addDriver($driver, string $namespace): void
    {
        $this->driver    = $driver;
        $this->namespace = $namespace;
    }
}

/** Only provides the configuration — Plugin::initORM() asks for nothing more. */
class EntityManagerStub
{
    public function __construct(private OrmConfigurationSpy $configuration) {}

    public function getConfiguration(): OrmConfigurationSpy
    {
        return $this->configuration;
    }
}
