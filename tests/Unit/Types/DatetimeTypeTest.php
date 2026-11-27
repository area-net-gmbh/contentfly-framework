<?php
namespace Tests\Unit\Types;

use Areanet\PIM\Classes\Types\DatetimeType;
use Areanet\PIM\Entity\Base;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Both branches of a datetime field answer with the same four keys (`016-001-0001`),
 * and the `ISO8601` value among them carries its offset with a colon (`016-001-0002`).
 *
 * THE FINDING. `DatetimeType::fromDatabase()` answers a datetime field as four
 * representations — `LOCAL_TIME`, `LOCAL`, `ISO8601`, `TIMESTAMP`. In the branch for an
 * invalid date the last one was spelled `IMESTAMP`, so a client reading `TIMESTAMP` found
 * nothing there while the same client found it on every valid date.
 *
 * ── Why the branch under test is NOT the null branch ────────────────────────────────────
 *
 * The reported issue calls it the null branch. A NULL never reaches it: `fromDatabase()`
 * leaves four lines earlier when the getter holds no `\DateTime`, and answers a plain `null`
 * instead of an array. The branch belongs to an *invalid* date — its condition compares
 * `format('Y')` against `'-0001'` and `'0000'`. Reproducing the finding on a NULL therefore
 * looks in the wrong place, which is why `testAFieldWithoutADateIsNullAndNotAnArray()` below
 * pins that difference down as well.
 *
 * ── Why this needs no database ──────────────────────────────────────────────────────────
 *
 * The condition does not require a column holding `0000-00-00`, which MySQL 8 refuses under
 * its default `sql_mode` anyway. PHP normalises `new \DateTime('0000-00-00 00:00:00')` to the
 * year `-0001`, which is exactly what the condition asks for. So the branch is reachable in a
 * unit test, with no database and no server setting.
 *
 * `Type::__construct()` wants a whole application in order to reach `orm.em`.
 * `fromDatabase()` touches neither the application nor the entity manager, so the type is
 * built past the constructor — same approach as `EmptyValueTest`, and for the same reason.
 */
class DatetimeTypeTest extends TestCase
{
    /** The contract: these four keys, in this order. */
    private const KEYS = array('LOCAL_TIME', 'LOCAL', 'ISO8601', 'TIMESTAMP');

    /**
     * The regression. Against the state before the fix this fails on the key list, because the
     * fourth key read `IMESTAMP`.
     */
    public function testAnInvalidDateCarriesTheTimestampKeyAsWell(): void
    {
        $payload = $this->read(new \DateTime('0000-00-00 00:00:00'));

        $this->assertSame(
            self::KEYS,
            array_keys($payload),
            'An invalid date answers the same four keys as a valid one'
        );
        $this->assertNull($payload['TIMESTAMP'], 'An invalid date has no timestamp to report');
    }

    /**
     * The other half of the contract: the branch that was always right stays right, so the two
     * branches are compared against one list instead of two.
     */
    public function testAValidDateCarriesTheSameFourKeys(): void
    {
        $payload = $this->read(new \DateTime('2026-11-27 14:30:00'));

        $this->assertSame(self::KEYS, array_keys($payload));
        $this->assertIsInt($payload['TIMESTAMP']);
    }

    /**
     * The form of the `ISO8601` value: its offset carries a colon (`016-001-0002`).
     *
     * `\DateTime::ISO8601` wrote the offset as `+0200`. The JavaScript specification asks for
     * `+02:00`, and `Date.parse()` does not read the other form reliably. `\DateTime::ATOM` —
     * the class constant form of `DATE_ATOM`, same value `Y-m-d\TH:i:sP` — writes what is
     * asked for. The key keeps its name `ISO8601`; only the form of its value changes.
     *
     * ── Why no zone is written into this test ──────────────────────────────────────────────
     *
     * What is under test is the form, not the zone of the test run. So the value is read with
     * an offset of its own, which puts digits on both sides of the colon instead of the
     * `+00:00` a UTC process would produce, and the expectation is derived from that same
     * value: `format('P')` is the colon form of the offset, `format('O')` was the old one.
     * Against the state before the fix both assertions fail on the missing colon.
     */
    public function testAValidDateCarriesItsOffsetWithAColon(): void
    {
        $value = new \DateTime('2026-11-27 14:30:00', new \DateTimeZone('+02:00'));

        $payload = $this->read($value);

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
            $payload['ISO8601'],
            'The ISO8601 value ends in an offset of the form +02:00, which JavaScript reads'
        );
        $this->assertStringEndsWith(
            $value->format('P'),
            $payload['ISO8601'],
            'And it is the offset the value itself carries, not one this test fixed in place'
        );
    }

    /**
     * Guards the distinction the issue got wrong: a field without a date is not the branch
     * above. It answers `null`, not an array, so it has no keys to misspell.
     */
    public function testAFieldWithoutADateIsNullAndNotAnArray(): void
    {
        $this->assertNull($this->readEmpty());
    }

    // ── helpers ────────────────────────────────────────────────────────────────────────────

    /**
     * Puts `$value` into `Base::$created` and returns what `fromDatabase()` makes of it.
     *
     * `Base` already carries a `datetime` field with a getter and a setter, so the test needs
     * no fixture entity of its own.
     *
     * @return array<string, mixed>
     */
    private function read(\DateTime $value): array
    {
        $entity = new Base();
        $entity->setCreated($value);

        return $this->answerFor($entity);
    }

    /**
     * The same, for a field that holds no date at all.
     *
     * The field is emptied through reflection, and that is a finding rather than a
     * convenience: `Base::setCreated(null)` is silently a no-op. Its `if/else if` chain has a
     * branch for a `\DateTime` and one for anything else that is not `null`, and none for
     * `null` itself — so the field keeps whatever the constructor put there, which is
     * `new \DateTime()`. Going through the setter would therefore have measured "now" and
     * called it empty. The column is `nullable: true`, so the state exists in the database
     * even though the setter cannot produce it.
     *
     * @return array<string, mixed>|null
     */
    private function readEmpty(): ?array
    {
        $entity = new Base();

        $field = new \ReflectionProperty(Base::class, 'created');
        $field->setAccessible(true);
        $field->setValue($entity, null);

        return $this->answerFor($entity);
    }

    /**
     * `Type::__construct()` wants a whole application in order to reach `orm.em`;
     * `fromDatabase()` touches neither, so the type is built past the constructor.
     *
     * @return array<string, mixed>|null
     */
    private function answerFor(Base $entity): ?array
    {
        $type = (new ReflectionClass(DatetimeType::class))->newInstanceWithoutConstructor();

        return $type->fromDatabase($entity, 'Base', 'created');
    }
}
