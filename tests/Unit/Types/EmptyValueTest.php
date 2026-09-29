<?php
namespace Tests\Unit\Types;

use Areanet\PIM\Classes\Api;
use Areanet\PIM\Classes\Config;
use Areanet\PIM\Classes\Config\Factory;
use Areanet\PIM\Classes\Security\FieldEncryption;
use Areanet\PIM\Classes\Type;
use Areanet\PIM\Classes\Types\StringType;
use Areanet\PIM\Classes\Types\TextareaType;
use Areanet\PIM\Entity\Base;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * `'0'` is content, not emptiness (`000-000-0102`).
 *
 * THE FINDING. `StringType::toDatabase()` opened with `if(empty($value))` and stored `''` for
 * whatever it caught. `empty()` says yes to `'0'` — so a title, an article number, a house
 * number, a floor, a meter reading or a sort value of `0` was silently replaced by the empty
 * string. `TextareaType` carried the same three lines.
 *
 * It is old and went unnoticed until `015-000-0001`, where it showed up through `pass`. The
 * password paths are caught at `User::setPass()` since then; for ordinary string fields the
 * silent loss remained.
 *
 * ── Why the encrypted half is measured HERE and not through the API ──────────────────────
 *
 * No entity in the tree sets `encoded: true`, and `ConstraintApiTest` records exactly that. So
 * the encrypted branch cannot be reached over HTTP at all, and adding a field to the template
 * just to reach it would change the product for one test. The type is driven directly instead —
 * the same way `FieldEncryptionTest` is the only place the algorithm runs.
 */
class EmptyValueTest extends TestCase
{
    /**
     * Any 32-byte key does — unlike the one in `FieldEncryptionTest`, which is a historical
     * value kept verbatim because a stored ciphertext was produced with it.
     */
    private const KEY = 'a-key-for-this-test-32-bytes-abc';

    protected function setUp(): void
    {
        $config = new Config();
        $config->SECURITY_CIPHER_KEY = self::KEY;
        Factory::getInstance()->setConfig($config);
    }

    protected function tearDown(): void
    {
        Factory::getInstance()->setConfig(new Config());
    }

    /** @return array<string, array{0: class-string}> */
    public static function textTypes(): array
    {
        return array(
            'StringType'   => array(StringType::class),
            'TextareaType' => array(TextareaType::class),
        );
    }

    // ── The finding ────────────────────────────────────────────────────────────────────────

    #[DataProvider('textTypes')]
    public function testZeroAsAStringIsStored(string $class): void
    {
        $this->assertSame('0', $this->store($class, '0', false));
    }

    /** An integer zero is content too — it reaches the column as `'0'`. */
    #[DataProvider('textTypes')]
    public function testIntegerZeroIsStored(string $class): void
    {
        $this->assertSame('0', (string) $this->store($class, 0, false));
    }

    /** The second acceptance criterion: `'0'` reaches the encryption, it is not short-circuited. */
    #[DataProvider('textTypes')]
    public function testZeroIsEncryptedAndReadsBackAsZero(string $class): void
    {
        $stored = $this->store($class, '0', true);

        $this->assertNotSame('0', $stored, 'the column holds ciphertext, not the plaintext');
        $this->assertNotSame('', $stored, 'and it is not empty — the encoded branch was reached');
        $this->assertSame('0', (new FieldEncryption())->decrypt($stored));
    }

    // ── And what stays empty ───────────────────────────────────────────────────────────────

    /** @return array<string, array{0: mixed}> */
    public static function emptyValues(): array
    {
        return array(
            'null'         => array(null),
            'empty string' => array(''),
            'empty array'  => array(array()),
        );
    }

    #[DataProvider('emptyValues')]
    public function testTheseStayEmptyInStringType(mixed $value): void
    {
        $this->assertSame('', $this->store(StringType::class, $value, false));
    }

    #[DataProvider('emptyValues')]
    public function testTheseStayEmptyInTextareaType(mixed $value): void
    {
        $this->assertSame('', $this->store(TextareaType::class, $value, false));
    }

    /**
     * An empty value is stored as `''` and NOT encrypted.
     *
     * That was the behaviour before and it stays: a ciphertext of the empty string would make
     * every untouched field a blob, and `fromDatabase()` maps an empty column back to `''`
     * without asking the cipher.
     */
    public function testAnEmptyValueIsNotEncrypted(): void
    {
        $this->assertSame('', $this->store(StringType::class, null, true));
    }

    // ── helpers ────────────────────────────────────────────────────────────────────────────

    /**
     * Runs `toDatabase()` and returns what landed on the entity.
     *
     * Built past the constructor: `Type::__construct()` wants a whole application in order to
     * reach `orm.em`, and none of that is involved in the decision under test. What the type does
     * need is `$app['schema']`, which the stub below provides.
     */
    private function store(string $class, mixed $value, bool $encoded): mixed
    {
        $type = (new ReflectionClass($class))->newInstanceWithoutConstructor();

        $app = new SchemaOnlyApplication(array(
            'Example' => array('properties' => array('title' => array('encoded' => $encoded))),
        ));

        $property = new ReflectionProperty(Type::class, 'app');
        $property->setAccessible(true);
        $property->setValue($type, $app);

        $entity = new EntityWithATitle();
        $api    = (new ReflectionClass(Api::class))->newInstanceWithoutConstructor();

        $type->toDatabase($api, $entity, 'title', $value, 'Example', array(), null);

        return $entity->getTitle();
    }
}

/** The smallest thing the types need from the application: `$app['schema']`. */
class SchemaOnlyApplication implements \ArrayAccess
{
    /** @param array<string, mixed> $schema */
    public function __construct(private readonly array $schema)
    {
    }

    public function offsetExists(mixed $offset): bool
    {
        return $offset === 'schema';
    }

    public function offsetGet(mixed $offset): mixed
    {
        if ($offset !== 'schema') {
            throw new \RuntimeException(sprintf('The type asked for "%s", which this test does not stub.', (string) $offset));
        }

        return $this->schema;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \RuntimeException('read only');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \RuntimeException('read only');
    }
}

/** An entity with exactly the one field under test. */
class EntityWithATitle extends Base
{
    private mixed $title = null;

    public function setTitle(mixed $title): void
    {
        $this->title = $title;
    }

    public function getTitle(): mixed
    {
        return $this->title;
    }
}
