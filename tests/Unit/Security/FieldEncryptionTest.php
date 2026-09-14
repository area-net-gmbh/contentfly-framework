<?php
namespace Tests\Unit\Security;

use Areanet\PIM\Classes\Config;
use Areanet\PIM\Classes\Config\Factory;
use Areanet\PIM\Classes\Security\FieldEncryption;
use PHPUnit\Framework\TestCase;

/**
 * The first tests that run FieldEncryption at all (010-004-0001).
 *
 * WHY THEY DID NOT EXIST BEFORE: the code had nothing to be tested against. No entity sets
 * `encoded: true`, and `SECURITY_CIPHER_KEY` defaults to `null` — encryption could not be
 * triggered through the API.
 * `ConstraintApiTest::testNoEntityUsesEncodedEncryption` records exactly that and
 * demands proof as soon as someone sets the flag. Until then THIS file is the only place where
 * the algorithm runs.
 *
 * WHAT THEY GUARANTEE: the state before the algorithm change. `010-004-0001` merges the
 * duplicated code without changing the algorithm — these tests tell whether that worked. With
 * `010-004-0002` two of them change, and the reason is given there.
 */
class FieldEncryptionTest extends TestCase
{
    /**
     * A ciphertext produced by the code BEFORE 010-004-0001.
     *
     * Stored as a fixed value, not recomputed: a value the test encrypts itself only proves that
     * it agrees with itself. This one comes from the exact wording of the old `StringType` and is
     * therefore the proof that existing data stays readable — beyond `010-004-0002` as well.
     *
     * Plaintext: `Bestandswert aus dem alten Format`, key: the constant below.
     */
    private const LEGACY_CIPHERTEXT = '6AKaJ5rHsB62yZ1yqNB41WMvNkdtcGpMVFhodVBUODBUVG5hbWkzREtCNUcrcUMxS1F1bG04VVIycWVhaVpHYUJZY29TSXBuTlZPOUYwNFE=';

    /** What LEGACY_CIPHERTEXT decrypts to — must stay verbatim. */
    private const LEGACY_PLAINTEXT = 'Bestandswert aus dem alten Format';

    /** The key LEGACY_CIPHERTEXT was encrypted with — must stay verbatim. */
    private const KEY = 'ein-schluessel-fuer-den-test-32b';

    protected function setUp(): void
    {
        $config = new Config();
        $config->SECURITY_CIPHER_KEY = self::KEY;
        Factory::getInstance()->setConfig($config);
    }

    protected function tearDown(): void
    {
        $config = new Config();
        $config->SECURITY_CIPHER_KEY = null;
        Factory::getInstance()->setConfig($config);
    }

    public function testAValueSurvivesTheRoundTripUnchanged(): void
    {
        $crypto = new FieldEncryption();
        $value  = 'A value with umlauts: äöü, and a line break:'."\n".'second line.';

        $this->assertSame($value, $crypto->decrypt($crypto->encrypt($value)));
    }

    public function testACiphertextFromTheLegacyCodeStaysReadable(): void
    {
        // The format proof. Without it 010-004-0001 could have changed the format silently, and
        // an existing project would only have noticed on the next read.
        $this->assertSame(
            self::LEGACY_PLAINTEXT,
            (new FieldEncryption())->decrypt(self::LEGACY_CIPHERTEXT)
        );
    }

    public function testTwoEncryptionsOfTheSameValueDiffer(): void
    {
        // The IV is random. If two ciphertexts were equal, the database would reveal which rows
        // carry the same value — for an encrypted field that is exactly the information nobody
        // wants to give away.
        $crypto = new FieldEncryption();

        $this->assertNotSame($crypto->encrypt('the same value'), $crypto->encrypt('the same value'));
    }

    public function testWithoutAKeyNothingIsEncrypted(): void
    {
        $config = new Config();
        $config->SECURITY_CIPHER_KEY = null;
        Factory::getInstance()->setConfig($config);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('A value for SECURITY_CIPHER_KEY must be set for encryption.');

        (new FieldEncryption())->encrypt('anything');
    }

    public function testWithoutAKeyNothingIsDecrypted(): void
    {
        $crypto     = new FieldEncryption();
        $ciphertext = $crypto->encrypt('anything');

        $config = new Config();
        $config->SECURITY_CIPHER_KEY = null;
        Factory::getInstance()->setConfig($config);

        $this->expectException(\Exception::class);

        (new FieldEncryption())->decrypt($ciphertext);
    }

    /**
     * THE PROOF THE WHOLE STORY IS ABOUT.
     *
     * INVERTED WITH 010-004-0002, and that is an announced change of behaviour. Before, this test
     * was called `testEinManipulierterChiffretextFaelltHeuteNichtAuf` and guaranteed the opposite
     * state: AES-256-CBC has no MAC, a flipped byte went through and yielded a different
     * plaintext — one block of garbage, the rest intact. Measured with the plaintext this test
     * uses (then still in its German wording): bytes 20 and 40 went through, bytes 30 and 101
     * failed only on the padding.
     *
     * XChaCha20-Poly1305 authenticates. Every change to the ciphertext is detected, and nothing
     * is decrypted.
     *
     * THE TEST TRIES EVERY POSITION, not a selected one: with the old algorithm ONE tampering
     * that went through was enough to make the guarantee worthless. So EVERY one has to be
     * rejected here.
     */
    public function testEveryTamperingIsDetected(): void
    {
        $crypto     = new FieldEncryption();
        $plaintext  = 'Transfer to account A, amount 100 euros, urgent please';
        $ciphertext = $crypto->encrypt($plaintext);

        $raw         = base64_decode(substr($ciphertext, strlen('PIM1:')));
        $gotThrough  = array();

        for ($pos = 0; $pos < strlen($raw); $pos++) {
            $tampered       = $raw;
            $tampered[$pos] = chr(ord($tampered[$pos]) ^ 0x01);

            $result = $crypto->decrypt('PIM1:'.base64_encode($tampered));

            if ($result !== false) {
                $gotThrough[] = $pos;
            }
        }

        $this->assertSame(array(), $gotThrough,
            'Not a single changed byte may be decrypted — not even in the nonce');
    }

    public function testAForeignCiphertextWithTheCorrectPrefixIsRejected(): void
    {
        // The prefix states a format, it guarantees nothing. Whoever prepends it still gets
        // nothing decrypted.
        $this->assertFalse((new FieldEncryption())->decrypt('PIM1:'.base64_encode(random_bytes(60))));
    }

    public function testTheDerivationIsDeterministic(): void
    {
        // If it were not, existing data would be lost after every restart. Proven with two
        // instances: what one encrypts, the other reads.
        $one   = new FieldEncryption();
        $other = new FieldEncryption();

        $this->assertSame('a value', $other->decrypt($one->encrypt('a value')));
    }

    public function testNewValuesCarryThePrefixAndLegacyValuesDoNot(): void
    {
        $crypto = new FieldEncryption();

        $this->assertTrue($crypto->isNewFormat($crypto->encrypt('fresh')),
            'What is written now is AEAD');
        $this->assertFalse($crypto->isNewFormat(self::LEGACY_CIPHERTEXT),
            'and an existing value is recognisable by the missing prefix');
    }
}
