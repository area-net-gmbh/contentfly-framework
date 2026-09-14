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
 * Characterisation tests for the `TypeManager` — the registration of the field types.
 *
 * Deliberately a **unit test**: the manager depends on nothing but a `Kernel\Application`
 * object and only reaches through to `$app['orm.em']` when a type uses it. Registration and
 * lookup need neither database nor HTTP.
 *
 * Epic `009` rebuilds the kernel underneath. What is asserted here must hold identically
 * afterwards — a project registers its own field types through the `TypeManager`.
 */
class TypeManagerTest extends TestCase
{
    private function app(): Application
    {
        $app = new Application();
        // The types expect the EntityManager in the constructor; registration and lookup
        // do not touch it.
        $app['orm.em'] = null;

        return $app;
    }

    public function testATypeIsStoredUnderItsAlias(): void
    {
        $app  = $this->app();
        $manager = new TypeManager($app);

        $manager->registerType(new StringType($app));

        $this->assertInstanceOf(StringType::class, $manager->getType('string'));
    }

    public function testAnUnknownAliasReturnsNullInsteadOfThrowing(): void
    {
        // Recorded because it deviates from the framework's usual error handling: Api and the
        // controllers throw a ContentflyException for anything unknown. getType() returns
        // null — the caller has to check for itself.
        $manager = new TypeManager($this->app());

        $this->assertNull($manager->getType('doesnotexist'));
    }

    public function testGetTypesReturnsAllRegisteredTypesKeyedByAlias(): void
    {
        $app     = $this->app();
        $manager = new TypeManager($app);

        $manager->registerType(new StringType($app));
        $manager->registerType(new BooleanType($app));

        $types = $manager->getTypes();

        $this->assertSame(array('string', 'boolean'), array_keys($types));
        $this->assertInstanceOf(BooleanType::class, $types['boolean']);
    }

    public function testASecondTypeWithTheSameAliasReplacesTheFirst(): void
    {
        // The alias is the key — there is no collision check. A project that registers its
        // own type under an existing alias silently overwrites the framework type. That is
        // the way to replace a type; it is at the same time the way to lose one by accident.
        $app     = $this->app();
        $manager = new TypeManager($app);

        $firstType = new StringType($app);
        $manager->registerType($firstType);

        $secondType = new StringType($app);
        $manager->registerType($secondType);

        $this->assertSame($secondType, $manager->getType('string'));
        $this->assertNotSame($firstType, $manager->getType('string'));
    }

    public function testAPluginTypeIsRejectedWithAnException(): void
    {
        // A PluginType belongs registered via registerPluginType(), because that sets the
        // plugin key and loads the annotation file from the plugin directory. registerType()
        // therefore rejects it.
        $app     = $this->app();
        $manager = new TypeManager($app);

        $this->expectException(ContentflyException::class);

        $manager->registerType(new class($app) extends Type\PluginType {
            public function doMatch($propertyAnnotations) { return false; }
            public function getAlias() { return 'pluginsample'; }
        });
    }

    public function testATypeNoLongerNeedsAnAnnotationFile(): void
    {
        // INVERTED WITH 010-001-0005, and that is a change in behaviour.
        //
        // Previously the test was called "…OhneAnnotationsdateiWirdOhneRegistrierungAbgelegt"
        // and asserted that `getAnnotationFile() === null` triggers NO
        // `AnnotationRegistry::registerFile()` — otherwise registering a type without its own
        // annotation would have failed.
        //
        // The method no longer exists. It told the DocParser the file path of an annotation
        // class, because the parser only resolves an annotation when its class is already
        // known. An attribute names a real class; the autoloader fetches it. With that, the
        // registration has nothing left to do, and the test only asserts that storing a type
        // works without it.
        $app     = $this->app();
        $manager = new TypeManager($app);

        $type = new StringType($app);
        $this->assertFalse(method_exists($type, 'getAnnotationFile'),
            'getAnnotationFile() was dropped with 010-001-0005');

        $manager->registerType($type);

        $this->assertSame($type, $manager->getType('string'));
    }
}
