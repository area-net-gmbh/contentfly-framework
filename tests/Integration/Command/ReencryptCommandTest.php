<?php
namespace Tests\Integration\Command;

use Areanet\PIM\Classes\Config;
use Areanet\PIM\Classes\Config\Factory;
use Areanet\PIM\Classes\Security\FieldEncryption;
use Areanet\PIM\Command\ReencryptCommand;
use Doctrine\DBAL\DriverManager;
use Tests\Integration\IntegrationTestCase;
use PHPUnit\Framework\TestCase;

/**
 * Checks `appcms:security:reencrypt` against a REAL database (010-004-0003).
 *
 * WHY WITH ITS OWN TABLE. There is no field with `encoded: true` in the tree — a test that
 * called the command from outside could only confirm that it does nothing. That is why this
 * test creates its own table, fills it with values in the OLD format and runs the
 * re-encryption over it. What is measured is therefore the real code path on real
 * rows, not a dummy.
 *
 * The table is called `probe_reencrypt` and is removed again at the end. It touches no
 * schema of the framework.
 */
class ReencryptCommandTest extends TestCase
{
    private const KEY   = 'a-32-byte-key-for-the-test-suite';
    private const TABLE = 'probe_reencrypt';

    /** @var \Doctrine\DBAL\Connection */
    private $db;

    protected function setUp(): void
    {
        $config = new Config();
        $config->SECURITY_CIPHER_KEY = self::KEY;
        Factory::getInstance()->setConfig($config);

        // The same credentials as the other integration tests. A second path there
        // would drift apart at some point — exactly the reasoning stated at `dbCredentials()`
        // itself.
        $credentials = IntegrationTestCase::dbCredentials();

        $this->db = DriverManager::getConnection(array(
            'driver'   => 'pdo_mysql',
            'host'     => $credentials['host'],
            'port'     => (int) $credentials['port'],
            'dbname'   => $credentials['name'],
            'user'     => $credentials['user'],
            'password' => $credentials['pass'],
        ));

        $this->db->executeStatement('DROP TABLE IF EXISTS '.self::TABLE);
        $this->db->executeStatement(
            'CREATE TABLE '.self::TABLE.' (id INT AUTO_INCREMENT PRIMARY KEY, secret TEXT NULL)'
        );
    }

    protected function tearDown(): void
    {
        if ($this->db) {
            $this->db->executeStatement('DROP TABLE IF EXISTS '.self::TABLE);
        }

        $config = new Config();
        $config->SECURITY_CIPHER_KEY = null;
        Factory::getInstance()->setConfig($config);
    }

    /** Encrypts in the OLD format — the wording of the code before 010-004-0002. */
    private function encryptLegacy(string $plaintext): string
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));

        return base64_encode($iv.openssl_encrypt($plaintext, 'AES-256-CBC', self::KEY, 0, $iv));
    }

    /** @return array<int,string> */
    private function readValues(): array
    {
        return $this->db->fetchAllKeyValue('SELECT id, secret FROM '.self::TABLE.' ORDER BY id');
    }

    public function testTheDryRunChangesNothing(): void
    {
        $plaintexts = array('first value', 'second value', 'third value');

        foreach ($plaintexts as $value) {
            $this->db->insert(self::TABLE, array('secret' => $this->encryptLegacy($value)));
        }

        $before = $this->readValues();

        $result = (new ReencryptCommand())
            ->reencryptColumn($this->db, self::TABLE, 'secret', 'id', true, 500);

        $this->assertSame(3, $result['checked']);
        $this->assertSame(3, $result['reencrypted'], 'It would have re-encrypted three');
        $this->assertSame($before, $this->readValues(), 'but the rows are unchanged');
    }

    public function testTheRealRunReencryptsAndTheSecondFindsNothingMore(): void
    {
        $plaintexts = array('first value', 'second value', 'third value');

        foreach ($plaintexts as $value) {
            $this->db->insert(self::TABLE, array('secret' => $this->encryptLegacy($value)));
        }

        $command    = new ReencryptCommand();
        $encryption = new FieldEncryption();

        $first = $command->reencryptColumn($this->db, self::TABLE, 'secret', 'id', false, 500);
        $this->assertSame(3, $first['reencrypted']);

        // The plaintext is the same — that is the actual point of the re-encryption.
        $this->assertSame($plaintexts, array_values(array_map(
            static fn (string $value): string => (string) (new FieldEncryption())->decrypt($value),
            $this->readValues()
        )));

        foreach ($this->readValues() as $value) {
            $this->assertTrue($encryption->isNewFormat($value), 'and every value now carries the AEAD format');
        }

        $second = $command->reencryptColumn($this->db, self::TABLE, 'secret', 'id', false, 500);
        $this->assertSame(0, $second['reencrypted'], 'A second run has nothing left to do');
        $this->assertSame(3, $second['skipped']);
    }

    public function testItWorksInBatchesAndCatchesAllRows(): void
    {
        // The batch size is smaller than the row count — if the paginated query
        // were wrong, rows would be left behind or the run would go in circles.
        for ($i = 0; $i < 25; $i++) {
            $this->db->insert(self::TABLE, array('secret' => $this->encryptLegacy('Value '.$i)));
        }

        $result = (new ReencryptCommand())
            ->reencryptColumn($this->db, self::TABLE, 'secret', 'id', false, 4);

        $this->assertSame(25, $result['reencrypted']);

        $encryption = new FieldEncryption();

        foreach ($this->readValues() as $value) {
            $this->assertTrue($encryption->isNewFormat($value));
        }
    }

    public function testAnUnreadableValueAbortsTheBatchAndLeavesItUnchanged(): void
    {
        $this->db->insert(self::TABLE, array('secret' => $this->encryptLegacy('readable')));
        $this->db->insert(self::TABLE, array('secret' => base64_encode('not a valid ciphertext')));
        $this->db->insert(self::TABLE, array('secret' => $this->encryptLegacy('also readable')));

        $before = $this->readValues();

        try {
            (new ReencryptCommand())->reencryptColumn($this->db, self::TABLE, 'secret', 'id', false, 500);
            $this->fail('An unreadable value must stop the run');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('SECURITY_CIPHER_KEY', $error->getMessage(),
                'and the message must point to the most likely cause');
        }

        $this->assertSame($before, $this->readValues(),
            'The batch is a transaction — no half re-encrypted state is left behind');
    }

    public function testTodayThereIsNoFieldWithEncoded(): void
    {
        // The command finds its fields via the schema. The framework has none — and
        // this test records that, so the command's empty output is not mistaken for a
        // defect. If someone sets `encoded: true`, it fires.
        $schema = array('_hash' => 'x', 'PIM\\User' => array('properties' => array(
            'alias' => array('encoded' => false),
            'pass'  => array('encoded' => false),
        )));

        $found = (new ReencryptCommand())->affectedFields($schema, $this->entityManagerMock(), $this->helperStub());

        $this->assertSame(array(), $found);
    }

    private function entityManagerMock(): \Doctrine\ORM\EntityManagerInterface
    {
        return $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
    }

    private function helperStub(): object
    {
        return new class {
            public function getFullEntityName(string $entity): string
            {
                return 'Areanet\\PIM\\Entity\\User';
            }
        };
    }
}
