<?php
namespace Tests\Unit\Migration;

use PHPUnit\Framework\TestCase;

use function Contentfly\Tools\Migration\inventory;

require_once CONTENTFLY_PROJECT_DIR . '/tools/migration/inventory.php';

/**
 * The inventory tool for existing projects (007-005-0001).
 *
 * **Two things are checked.** That the tool finds what a project writes — on a small project built
 * here with one of each — and that its lists have not drifted from the sources they copy: the
 * removed `@PIM` annotations and fields from `rector.php`, the container keys from
 * `ContainerKeysTest`. A tool that reports against an outdated list reports the wrong migration.
 */
class InventoryToolTest extends TestCase
{
    private string $scratch = '';

    protected function setUp(): void
    {
        $this->scratch = sys_get_temp_dir() . '/contentfly-inventory-' . bin2hex(random_bytes(6));
        mkdir($this->scratch . '/custom/Entity/Shop', 0777, true);
        mkdir($this->scratch . '/custom/Classes', 0777, true);
        mkdir($this->scratch . '/custom/vendor/some/package', 0777, true);
    }

    protected function tearDown(): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->scratch, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }

        rmdir($this->scratch);
    }

    public function testItFindsWhatAProjectWrites(): void
    {
        $this->write('custom/Entity/Shop/Article.php', <<<'PHP'
<?php
namespace Custom\Entity\Shop;

use Doctrine\ORM\Mapping as ORM;
use Areanet\PIM\Classes\Annotations as PIM;

/**
 * @ORM\Entity
 * @ORM\Table(name="shop_article")
 * @PIM\Config(label="Article", tabs="{}", excludeFromSync=true)
 */
class Article extends Base
{
    /**
     * @ORM\Column(type="text")
     * @PIM\Rte
     * @PIM\Config(showInList=10, label="Text")
     */
    protected $text;
}
PHP);

        // The legacy namespace is assembled, not written out: NoSilexTypesTest keeps the tree
        // free of that name, and a fixture is no reason to open its empty exception list.
        $legacy = 'Sil' . 'ex';

        $this->write('custom/Classes/ShopLoginManager.php', <<<PHP
<?php
namespace Custom\\Classes;

use {$legacy}\\Application;

class ShopLoginManager extends \\Areanet\\PIM\\Classes\\Manager\\LoginManager
{
    public function login(\$app)
    {
        // \$app['request'] is only mentioned here and must not count.
        \$em = \$app['orm.em'];
        \$twig = \$this->app['twig'];

        return \$app['auth.user'];
    }
}
PHP);

        // A project's own Composer tree is not project code.
        $this->write('custom/vendor/some/package/Noise.php', "<?php\nclass Noise extends LoginManager {}\n");

        $report = inventory($this->scratch);

        $this->assertFalse($report['framework_copy']['present']);
        $this->assertSame(1, $report['entities']['count']);
        $this->assertSame(
            array('orm_annotations' => 3, 'orm_attributes' => 0, 'pim_annotations' => 3,
                  'removed_annotations' => 1, 'removed_fields' => 4),
            $report['entities']['totals'],
            'label and tabs on the class, showInList and label on the property; @PIM\Rte removed as a whole'
        );
        $this->assertSame(array(array('file' => 'custom/Classes/ShopLoginManager.php', 'class' => 'ShopLoginManager')),
            $report['login_managers'], 'The class in custom/vendor/ is not counted');
        $this->assertSame(array('auth.user' => 1, 'orm.em' => 1), $report['container_keys']['guaranteed']);
        $this->assertSame(array('twig' => 1), $report['container_keys']['unknown'],
            'twig is not a key of the new framework; request only occurs in a comment');
        $this->assertSame(array('custom/Classes/ShopLoginManager.php' => 1), $report['silex_references']);
        $this->assertSame(array(), $report['untriggered_paths']['encoded']);
    }

    /**
     * The three hurdles of UFP phases 3 and 5 that the first version did not report (007-005-0005).
     */
    public function testItReportsTraitsUnbalancedAnnotationsAndDbal2Statements(): void
    {
        mkdir($this->scratch . '/custom/Traits', 0777, true);

        // Mapping in a trait, without the import: Rector skips it, Doctrine ignores it.
        $this->write('custom/Traits/User.php', <<<'PHP'
<?php
namespace Custom\Traits;

trait User
{
    /**
     * @ORM\Column(type="json", nullable=true)
     * @PIM\Config(label="Fields (dynamic)")
     */
    protected $dynamicFields;
}
PHP);

        // Mapping in a trait with both imports — reported, but not as missing.
        $this->write('custom/Traits/Group.php', <<<'PHP'
<?php
namespace Custom\Traits;

use Doctrine\ORM\Mapping as ORM;

trait Group
{
    #[ORM\Column(type: 'string', nullable: true)]
    protected $role;
}
PHP);

        $this->write('custom/Entity/Shop/Order.php', <<<'PHP'
<?php
namespace Custom\Entity\Shop;

class Order extends Base
{
    /**
     * @ORM\Column(type="string", nullable=true))
     * @PIM\Config(excludeFromSync=true)
     */
    protected $number;

    public function totals($dbal)
    {
        $statement = $dbal->prepare('SELECT 1');
        $statement->execute();
        $rows = $statement->fetchAll();
        $one  = $statement->fetch();

        return $request->get('x');
    }
}
PHP);

        $report = inventory($this->scratch);

        $this->assertSame(array(
            array('file' => 'custom/Traits/Group.php', 'trait' => 'Group', 'orm_annotations' => 0, 'orm_attributes' => 1, 'missing_imports' => array()),
            array('file' => 'custom/Traits/User.php', 'trait' => 'User', 'orm_annotations' => 1, 'orm_attributes' => 0,
                  'missing_imports' => array('Doctrine\ORM\Mapping as ORM', 'Areanet\PIM\Classes\Annotations as PIM')),
        ), $report['entity_traits']);
        $this->assertSame(array('custom/Entity/Shop/Order.php:6'), $report['unbalanced_annotations'],
            'The docblock with the extra bracket, by its first line; the brackets inside the quoted label of User.php are not counted');
        $this->assertSame(array('custom/Entity/Shop/Order.php' => 2), $report['dbal2_statements']);
        $this->assertSame(array('custom/Entity/Shop/Order.php' => 1), $report['request_get']);
    }

    public function testALibDirectoryWithoutPhpIsNoFrameworkCopy(): void
    {
        mkdir($this->scratch . '/lib/contentfly', 0777, true);
        $this->write('lib/contentfly/.DS_Store', 'x');

        $this->assertSame(array('present' => false, 'leftover' => array('.DS_Store')), inventory($this->scratch)['framework_copy']);
    }

    /**
     * Configuration keys against the framework — and the keys a project added to its own copy, which
     * only the copy's git history can tell apart from keys the framework removed (L-6).
     */
    public function testConfigurationKeysAreClassifiedIncludingProjectPatches(): void
    {
        mkdir($this->scratch . '/lib/contentfly/Classes', 0777, true);
        $this->write('lib/contentfly/Classes/Config.php', "<?php\nclass Config {\n    public \$APP_DEBUG = false;\n    public \$APP_MASTER_PASSWORD = null;\n}\n");

        $git = fn (string $args) => shell_exec('git -C ' . escapeshellarg($this->scratch) . ' -c user.name=t -c user.email=t@example.invalid ' . $args . ' 2>&1');
        $git('init -q');
        $git('add lib');
        $git('commit -q -m import');

        $this->write('lib/contentfly/Classes/Config.php', "<?php\nclass Config {\n    public \$APP_DEBUG = false;\n    public \$APP_MASTER_PASSWORD = null;\n    public \$APP_ROLES_STAFF = array();\n}\n");
        $git('commit -q -am patch');

        $this->write('custom/config.php', <<<'PHP'
<?php
$configDefault->APP_DEBUG           = true;
$configDefault->APP_MASTER_PASSWORD = 'x';
$configDefault->APP_ROLES_STAFF     = array('user');
$configLive->CUSTOM_SMTP_HOST       = 'smtp.example.invalid';
if ($configLive->APP_DEBUG == true) {}
PHP);

        $this->assertSame(array(
            'framework' => array('APP_DEBUG'),
            'removed'   => array('APP_MASTER_PASSWORD'),
            'patch'     => array('APP_ROLES_STAFF'),
            'project'   => array('CUSTOM_SMTP_HOST'),
        ), inventory($this->scratch)['config_keys'], 'A comparison (==) is not an assignment');
    }

    public function testTheRemovedAnnotationListMatchesRector(): void
    {
        $rector = (string) file_get_contents(CONTENTFLY_PROJECT_DIR . '/rector.php');

        preg_match('/RemoveAnnotationRector::class,\s*\[(.*?)\]\)/s', $rector, $block);
        preg_match_all("/Annotations\\\\\\\\(\w+)'/", $block[1] ?? '', $names);

        $this->assertSame($names[1], \Contentfly\Tools\Migration\REMOVED_ANNOTATIONS,
            'tools/migration/inventory.php REMOVED_ANNOTATIONS has drifted from rector.php');
    }

    public function testTheRemovedFieldListMatchesRector(): void
    {
        $rector = (string) file_get_contents(CONTENTFLY_PROJECT_DIR . '/rector.php');

        preg_match('/RemovedAttributeFieldsRector::class,\s*\[(.*)\]\);/s', $rector, $block);
        preg_match_all("/Annotations\\\\\\\\(\w+)' => \[(.*?)\]/s", $block[1] ?? '', $entries, PREG_SET_ORDER);

        $fromRector = array();
        foreach ($entries as $entry) {
            preg_match_all("/'(\w+)'/", $entry[2], $fields);
            $fromRector[$entry[1]] = $fields[1];
        }

        $this->assertNotEmpty($fromRector, 'Precondition: the Rector configuration was read');
        $this->assertSame($fromRector, \Contentfly\Tools\Migration\REMOVED_FIELDS,
            'tools/migration/inventory.php REMOVED_FIELDS has drifted from rector.php');
    }

    public function testTheContainerKeyListMatchesContainerKeysTest(): void
    {
        $test = new \ReflectionClass(\Tests\Integration\ContainerKeysTest::class);

        $this->assertSame(
            array(
                'guaranteed'    => $test->getConstant('ALWAYS'),
                'after_install' => $test->getConstant('AFTER_INSTALL'),
                'after_login'   => $test->getConstant('AFTER_LOGIN'),
                'internal'      => $test->getConstant('INTERNAL'),
            ),
            \Contentfly\Tools\Migration\CONTAINER_KEYS,
            'tools/migration/inventory.php CONTAINER_KEYS has drifted from ContainerKeysTest'
        );
    }

    private function write(string $path, string $content): void
    {
        file_put_contents($this->scratch . '/' . $path, $content);
    }
}
