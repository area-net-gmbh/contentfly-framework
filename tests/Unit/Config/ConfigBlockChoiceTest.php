<?php
namespace Tests\Unit\Config;

use Areanet\PIM\Classes\Config;
use Areanet\PIM\Classes\Config\Factory;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Who picks the configuration block — the deployment, not the caller (`015-000-0017`).
 *
 * THE FINDING. `bootstrap.php` derived the block from `$_SERVER['SERVER_NAME']`, which under
 * Apache's default `UseCanonicalName Off` and under PHP's built-in server IS the client's `Host`
 * header. `Factory::getConfig()` then returned the whole block for that name — `APP_DEBUG`,
 * `APP_HTTP_AUTH_*`, `APP_FORCE_SSL`, `DB_*`, `SECURITY_*` — and an unknown name fell through to
 * `default` without a word. Following the documented pattern (relaxed `default` for local work,
 * a strict block per host), `Host: anything` therefore handed out the development configuration.
 *
 * ON A FRESH FACTORY, not the singleton: `Factory` has no way to forget a block once set, so a
 * test that added host blocks to the singleton would leak them into every later test in the
 * process. The constructor is protected by design; reflection is the honest way past that, and it
 * is confined to this file.
 */
class ConfigBlockChoiceTest extends TestCase
{
    /** A factory holding exactly the given blocks. */
    private function factoryWith(string ...$hosts): Factory
    {
        $factory = (new ReflectionClass(Factory::class))->newInstanceWithoutConstructor();

        foreach ($hosts as $host) {
            $factory->setConfig(new Config($host));
        }

        return $factory;
    }

    // ── The ordinary installation: one block, nothing to decide ───────────────────────────

    /**
     * The shipped template has only `default`, and must keep working untouched.
     *
     * This is the line between "a breaking change for everyone" and "a breaking change for
     * installations that actually use host blocks", and it is the whole reason the rule below is
     * tied to the presence of host blocks.
     */
    public function testWithOnlyADefaultBlockNothingNeedsToBeSet(): void
    {
        $this->assertSame('default', $this->factoryWith('default')->chooseBlock(null));
    }

    public function testAnEmptyValueCountsAsUnset(): void
    {
        $this->assertSame('default', $this->factoryWith('default')->chooseBlock('   '));
    }

    // ── The deployment names its block ────────────────────────────────────────────────────

    public function testTheNamedBlockIsChosen(): void
    {
        $factory = $this->factoryWith('default', 'www.example.com', 'staging.example.com');

        $this->assertSame('www.example.com', $factory->chooseBlock('www.example.com'));
        $this->assertSame('staging.example.com', $factory->chooseBlock('staging.example.com'));
    }

    /** Naming `default` explicitly is allowed — it is a block like any other. */
    public function testDefaultMayBeNamedExplicitly(): void
    {
        $this->assertSame('default', $this->factoryWith('default', 'www.example.com')->chooseBlock('default'));
    }

    public function testSurroundingWhitespaceIsIgnored(): void
    {
        $this->assertSame(
            'www.example.com',
            $this->factoryWith('default', 'www.example.com')->chooseBlock('  www.example.com  ')
        );
    }

    // ── Fail closed, in both directions ───────────────────────────────────────────────────

    /**
     * A name that matches no block aborts — it does NOT fall back to `default`.
     *
     * That fallback is the second half of the finding: it is what turned an unknown host into
     * the development configuration.
     */
    public function testAnUnknownNameAbortsInsteadOfFallingBackToDefault(): void
    {
        $factory = $this->factoryWith('default', 'www.example.com');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/does not define/');

        $factory->chooseBlock('attacker.example.invalid');
    }

    /**
     * And so does a MISSING name while host blocks exist.
     *
     * This is the case that would otherwise be invisible: the instance would come up and serve,
     * just out of the wrong block. An abort at start is the only moment anybody looks.
     */
    public function testHostBlocksWithoutANamedBlockAbort(): void
    {
        $factory = $this->factoryWith('default', 'www.example.com');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/CONTENTFLY_CONFIG/');

        $factory->chooseBlock(null);
    }

    /** The message names the blocks that do exist — a misconfiguration one can act on. */
    public function testTheMessageNamesTheDefinedBlocks(): void
    {
        $factory = $this->factoryWith('default', 'www.example.com');

        try {
            $factory->chooseBlock('typo.example.com');
            $this->fail('An unknown block must abort');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('www.example.com', $e->getMessage());
            $this->assertStringContainsString('typo.example.com', $e->getMessage());
        }
    }
}
