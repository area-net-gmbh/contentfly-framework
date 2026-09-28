<?php
namespace Tests\Unit\File;

use Areanet\PIM\Classes\File\FilePath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The id of a `PIM\File` is a directory name (`015-000-0005`).
 *
 * `FileSystem::getPath()` builds `data/files/<id>`, and with `DB_GUID_STRATEGY` — the shipped
 * default — the column is a free string that `/file/upload` and `/api/insert` took straight out
 * of the request. An id of `../cache/x` moved the upload into `data/cache`, where
 * `Api::getSchema()` hands the schema cache to `unserialize()`, and it made `/api/delete` empty
 * a directory that was never a file's.
 *
 * THE STRATEGY IS A PARAMETER. Reading the configuration inside would make one of the two cases
 * unmeasurable, and the auto-increment case is exactly the one no installation in this suite
 * runs.
 */
class RecordIdTest extends TestCase
{
    /** @return array<string, array{0: mixed}> */
    public static function attacks(): array
    {
        return array(
            'parent traversal'  => array('../cache/x'),
            'nested traversal'  => array('../../custom'),
            'absolute'          => array('/tmp/x'),
            'plain slash'       => array('a/b'),
            'backslash'         => array('..\\cache'),
            'dot'               => array('.'),
            'dotdot'            => array('..'),
            'empty'             => array(''),
            'null byte'         => array("ok\0/../x"),
            'array'             => array(array('id' => 'x')),
            'null'              => array(null),
        );
    }

    #[DataProvider('attacks')]
    public function testNeitherStrategyAcceptsAPathAsAnId(mixed $id): void
    {
        $this->assertFalse(FilePath::isRecordId($id, true), 'not under the guid strategy');
        $this->assertFalse(FilePath::isRecordId($id, false), 'and not under auto-increment either');
    }

    public function testTheGuidStrategyAcceptsExactlyAUuid(): void
    {
        $this->assertTrue(FilePath::isRecordId('12dd32a0-ea65-4a0d-8869-5d08a6e48f8f', true));
        $this->assertTrue(FilePath::isRecordId(strtoupper('12dd32a0-ea65-4a0d-8869-5d08a6e48f8f'), true),
            'the hex may be written in either case');
    }

    /** @return array<string, array{0: string}> */
    public static function notQuiteUuids(): array
    {
        return array(
            'braced'        => array('{12dd32a0-ea65-4a0d-8869-5d08a6e48f8f}'),
            'urn prefix'    => array('urn:uuid:12dd32a0-ea65-4a0d-8869-5d08a6e48f8f'),
            'no dashes'     => array('12dd32a0ea654a0d886905d08a6e48f8'),
            'too short'     => array('12dd32a0-ea65-4a0d-8869-5d08a6e48f8'),
            'trailing dot'  => array('12dd32a0-ea65-4a0d-8869-5d08a6e48f8f.'),
            'with a slash'  => array('12dd32a0-ea65-4a0d-8869-5d08a6e48f8f/x'),
            'plain number'  => array('42'),
        );
    }

    /**
     * `Uuid::isValid()` would accept the first two — they are valid UUIDs and unusable as a
     * directory name. That is why the check is a pattern and not that method.
     */
    #[DataProvider('notQuiteUuids')]
    public function testTheGuidStrategyRefusesAnythingElse(string $id): void
    {
        $this->assertFalse(FilePath::isRecordId($id, true));
    }

    public function testAutoIncrementAcceptsAWholePositiveNumber(): void
    {
        $this->assertTrue(FilePath::isRecordId('1', false));
        $this->assertTrue(FilePath::isRecordId(42, false));
        $this->assertTrue(FilePath::isRecordId('9007199254740993', false), 'and one beyond a float');
    }

    /** @return array<string, array{0: mixed}> */
    public static function notNumbers(): array
    {
        return array(
            'zero'          => array('0'),
            'negative'      => array('-1'),
            'signed'        => array('+1'),
            'leading zero'  => array('007'),
            'decimal'       => array('1.5'),
            'spaced'        => array(' 1'),
            'hex'           => array('0x1'),
            'a uuid'        => array('12dd32a0-ea65-4a0d-8869-5d08a6e48f8f'),
        );
    }

    #[DataProvider('notNumbers')]
    public function testAutoIncrementRefusesAnythingElse(mixed $id): void
    {
        $this->assertFalse(FilePath::isRecordId($id, false));
    }

    /**
     * The two strategies do not overlap — an id valid under one is invalid under the other.
     * Worth pinning: a check that accepted both would be no check at all for a project that
     * switched.
     */
    public function testTheTwoStrategiesDoNotOverlap(): void
    {
        $uuid   = '12dd32a0-ea65-4a0d-8869-5d08a6e48f8f';
        $number = '42';

        $this->assertTrue(FilePath::isRecordId($uuid, true));
        $this->assertFalse(FilePath::isRecordId($uuid, false));
        $this->assertTrue(FilePath::isRecordId($number, false));
        $this->assertFalse(FilePath::isRecordId($number, true));
    }
}
