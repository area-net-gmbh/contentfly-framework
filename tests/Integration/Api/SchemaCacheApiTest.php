<?php
namespace Tests\Integration\Api;

use Tests\Integration\ExtraServer;
use Tests\Integration\IntegrationTestCase;

/**
 * The schema cache — written once, read afterwards (000-000-0075).
 *
 * With `APP_ENABLE_SCHEMA_CACHE` on, `getSchema()` stores the schema in `data/cache/schema.cache`
 * and from then on reads it from there instead of from the entity files. It is on by default in
 * the framework (`Config`) and off in the template, so the suite's server never uses it — and
 * production does. This class runs its own server with the cache on.
 *
 * The cache file is shared: both servers work on the same `data/`. The suite's server does not
 * read it (the cache is off there), and `SystemControllerApiTest` expects it to be absent — each
 * test here therefore removes it before and after.
 */
class SchemaCacheApiTest extends IntegrationTestCase
{
    private static ?ExtraServer $server = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        if (self::$baseUrl !== null) {
            self::$server = ExtraServer::start(array('APP_ENABLE_SCHEMA_CACHE' => '1'));
        }
    }

    public static function tearDownAfterClass(): void
    {
        self::$server?->stop();
        self::$server = null;

        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();

        @unlink(self::cacheFile());
    }

    protected function tearDown(): void
    {
        @unlink(self::cacheFile());

        parent::tearDown();
    }

    private static function cacheFile(): string
    {
        return self::dataDir().'/cache/schema.cache';
    }

    /** @return array<string,mixed> the schema the server delivers */
    private function schema(?string $server = null): array
    {
        $read = fn () => $this->get('/api/schema', $this->login());

        [$status, $raw] = $server !== null ? $this->onServer($server, $read) : $read();

        $this->assertSame(200, $status);

        return json_decode($raw, true)['data'];
    }

    public function testTheFirstRequestWritesTheSchemaToTheCache(): void
    {
        $delivered = $this->schema(self::$server->url());

        $this->assertFileExists(self::cacheFile());

        $cached = unserialize(file_get_contents(self::cacheFile()));

        $this->assertIsArray($cached);
        $this->assertArrayHasKey('_hash', $cached, 'The cache holds the schema including its hash');
        $this->assertSame($delivered['PIM\\Tag'], $cached['PIM\\Tag'], 'and it is the schema that was delivered');
    }

    /**
     * THE CACHE IS DATA, NOT CODE (015-000-0005).
     *
     * `getSchema()` reads the file with `allowed_classes => false`. That is only free of charge
     * as long as the schema really holds no objects — otherwise they would come back as
     * `__PHP_Incomplete_Class` and the two tests around this one would fail rather than this one.
     *
     * So this test states the premise instead of relying on it: the serialised form carries no
     * object at all. Whoever puts one into the schema learns it here, with the reason, and not
     * three tests later through a type error.
     *
     * Why it matters: the file sits in `data/cache`, and until `015-000-0005` an upload could be
     * written there — the id of a `PIM\File` came out of the request and became the directory.
     * That path is closed; these two safeguards must not depend on each other.
     */
    public function testTheCachedSchemaCarriesNoObject(): void
    {
        $this->schema(self::$server->url());
        $this->assertFileExists(self::cacheFile());

        $raw = (string) file_get_contents(self::cacheFile());

        $this->assertSame(0, preg_match_all('/(^|[;{])O:\d+:"/', $raw, $found),
            "The serialised schema names a class:\n".implode("\n", $found[0] ?? array()));

        $cached = unserialize($raw, array('allowed_classes' => false));

        $this->assertIsArray($cached, 'and it still reads back as the schema');
        $this->assertArrayHasKey('PIM\\Tag', $cached);

        array_walk_recursive($cached, function ($value): void {
            $this->assertNotInstanceOf(\__PHP_Incomplete_Class::class, $value,
                'No value was turned into an incomplete class by the restriction');
        });
    }

    public function testALaterRequestReadsTheSchemaFromTheCache(): void
    {
        $this->schema(self::$server->url());

        // Mark the cached schema: only a server that reads the file can deliver the mark.
        $marker = 'from-the-cache-'.bin2hex(random_bytes(6));
        $cached = unserialize(file_get_contents(self::cacheFile()));
        $cached['PIM\\Tag']['settings']['label'] = $marker;
        file_put_contents(self::cacheFile(), serialize($cached));

        $this->assertSame($marker, $this->schema(self::$server->url())['PIM\\Tag']['settings']['label'],
            'The entity files are not read again');
    }

    public function testWithTheCacheOffTheFileIsNotRead(): void
    {
        $this->schema(self::$server->url());

        $marker = 'from-the-cache-'.bin2hex(random_bytes(6));
        $cached = unserialize(file_get_contents(self::cacheFile()));
        $cached['PIM\\Tag']['settings']['label'] = $marker;
        file_put_contents(self::cacheFile(), serialize($cached));

        // `label` exists only in the marked cache file; a schema built from the entity files has
        // no such key. Reading it plainly raised "Undefined array key" (000-000-0100).
        $this->assertNotSame($marker, $this->schema()['PIM\\Tag']['settings']['label'] ?? null,
            'The suite\'s server, cache off, builds the schema from the entity files');
    }
}
