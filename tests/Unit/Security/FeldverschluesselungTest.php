<?php
namespace Tests\Unit\Security;

use Areanet\PIM\Classes\Config;
use Areanet\PIM\Classes\Config\Factory;
use Areanet\PIM\Classes\Security\Feldverschluesselung;
use PHPUnit\Framework\TestCase;

/**
 * Die ersten Tests, die die Feldverschluesselung ueberhaupt ausfuehren (010-004-0001).
 *
 * WARUM ES SIE VORHER NICHT GAB: Der Code hatte keinen Pruefgegenstand. Keine Entity setzt
 * `encoded: true`, und `SECURITY_CIPHER_KEY` steht in der Vorgabe auf `null` — die
 * Verschluesselung war ueber die API nicht ausloesbar.
 * `ConstraintApiTest::testKeineEntityNutztDieEncodedVerschluesselung` haelt genau das fest und
 * fordert den Nachweis ein, sobald jemand das Flag setzt. Bis dahin ist DIESE Datei der
 * einzige Ort, an dem das Verfahren laeuft.
 *
 * WAS SIE ZUSICHERN: den Ist-Stand vor dem Verfahrenswechsel. `010-004-0001` zieht den
 * doppelten Code zusammen, ohne das Verfahren zu aendern — diese Tests sagen, ob das gelungen
 * ist. Mit `010-004-0002` aendern sich zwei von ihnen, und das wird dort begruendet.
 */
class FeldverschluesselungTest extends TestCase
{
    /**
     * Ein Chiffretext, den der Code VOR 010-004-0001 erzeugt hat.
     *
     * Fest hinterlegt und nicht neu berechnet: Ein Wert, den der Test selbst verschluesselt,
     * beweist nur, dass er mit sich selbst uebereinstimmt. Dieser hier stammt aus dem
     * Wortlaut des alten `StringType` und ist damit der Nachweis, dass Bestandsdaten lesbar
     * bleiben — auch ueber `010-004-0002` hinaus.
     *
     * Klartext: `Bestandswert aus dem alten Format`, Schluessel: die Konstante unten.
     */
    private const ALTER_CHIFFRETEXT = '6AKaJ5rHsB62yZ1yqNB41WMvNkdtcGpMVFhodVBUODBUVG5hbWkzREtCNUcrcUMxS1F1bG04VVIycWVhaVpHYUJZY29TSXBuTlZPOUYwNFE=';

    private const ALTER_KLARTEXT = 'Bestandswert aus dem alten Format';

    private const SCHLUESSEL = 'ein-schluessel-fuer-den-test-32b';

    protected function setUp(): void
    {
        $config = new Config();
        $config->SECURITY_CIPHER_KEY = self::SCHLUESSEL;
        Factory::getInstance()->setConfig($config);
    }

    protected function tearDown(): void
    {
        $config = new Config();
        $config->SECURITY_CIPHER_KEY = null;
        Factory::getInstance()->setConfig($config);
    }

    public function testEinWertKommtDurchDenRundlaufUnveraendertZurueck(): void
    {
        $krypto = new Feldverschluesselung();
        $wert   = 'Ein Wert mit Umlauten: äöü, und einem Zeilenumbruch:'."\n".'zweite Zeile.';

        $this->assertSame($wert, $krypto->entschluesseln($krypto->verschluesseln($wert)));
    }

    public function testEinChiffretextAusDemAltenCodeBleibtLesbar(): void
    {
        // Der Formatnachweis. Ohne ihn koennte 010-004-0001 das Format still veraendert haben,
        // und ein Bestandsprojekt haette es erst beim naechsten Lesen gemerkt.
        $this->assertSame(
            self::ALTER_KLARTEXT,
            (new Feldverschluesselung())->entschluesseln(self::ALTER_CHIFFRETEXT)
        );
    }

    public function testZweiVerschluesselungenDesselbenWertsUnterscheidenSich(): void
    {
        // Der IV ist zufaellig. Waeren zwei Chiffretexte gleich, liesse sich aus der Datenbank
        // ablesen, welche Zeilen denselben Wert tragen — bei einem verschluesselten Feld ist
        // das genau die Auskunft, die niemand geben will.
        $krypto = new Feldverschluesselung();

        $this->assertNotSame($krypto->verschluesseln('derselbe Wert'), $krypto->verschluesseln('derselbe Wert'));
    }

    public function testOhneSchluesselWirdNichtVerschluesselt(): void
    {
        $config = new Config();
        $config->SECURITY_CIPHER_KEY = null;
        Factory::getInstance()->setConfig($config);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Für die Verschlüsselung muss ein Wert für SECURITY_CIPHER_KEY gesetzt sein.');

        (new Feldverschluesselung())->verschluesseln('irgendwas');
    }

    public function testOhneSchluesselWirdNichtEntschluesselt(): void
    {
        $krypto     = new Feldverschluesselung();
        $chiffretext = $krypto->verschluesseln('irgendwas');

        $config = new Config();
        $config->SECURITY_CIPHER_KEY = null;
        Factory::getInstance()->setConfig($config);

        $this->expectException(\Exception::class);

        (new Feldverschluesselung())->entschluesseln($chiffretext);
    }

    /**
     * DER NACHWEIS, UM DEN ES DER GANZEN STORY GEHT.
     *
     * UMGEDREHT MIT 010-004-0002, und das ist ein Verhaltenswechsel mit Ansage. Vorher hiess
     * dieser Test `testEinManipulierterChiffretextFaelltHeuteNichtAuf` und sicherte den
     * Gegenteil-Zustand zu: AES-256-CBC hat keinen MAC, ein gekipptes Byte ging durch und
     * lieferte einen anderen Klartext — ein Block Muell, der Rest stand. Gemessen an
     * `Ueberweisung an Konto A, Betrag 100 Euro, dringend bitte`: Byte 20 und 40 gingen durch,
     * Byte 30 und 101 scheiterten nur am Padding.
     *
     * XChaCha20-Poly1305 authentifiziert. Jede Aenderung am Chiffretext faellt auf, und
     * entschluesselt wird nichts.
     *
     * DER TEST PROBIERT JEDE POSITION DURCH, nicht eine ausgewaehlte: Beim alten Verfahren
     * genuegte EINE durchgehende Manipulation, um die Zusicherung wertlos zu machen. Also muss
     * hier JEDE abgewiesen werden.
     */
    public function testJedeManipulationFaelltAuf(): void
    {
        $krypto      = new Feldverschluesselung();
        $klartext    = 'Ueberweisung an Konto A, Betrag 100 Euro, dringend bitte';
        $chiffretext = $krypto->verschluesseln($klartext);

        $roh          = base64_decode(substr($chiffretext, strlen('PIM1:')));
        $durchgekommen = array();

        for ($pos = 0; $pos < strlen($roh); $pos++) {
            $manipuliert       = $roh;
            $manipuliert[$pos] = chr(ord($manipuliert[$pos]) ^ 0x01);

            $ergebnis = $krypto->entschluesseln('PIM1:'.base64_encode($manipuliert));

            if ($ergebnis !== false) {
                $durchgekommen[] = $pos;
            }
        }

        $this->assertSame(array(), $durchgekommen,
            'Keine einzige Byte-Aenderung darf entschluesselt werden — auch nicht im Nonce');
    }

    public function testEinFremderChiffretextMitDemRichtigenPraefixWirdAbgewiesen(): void
    {
        // Das Praefix ist eine Formatangabe, keine Zusicherung. Wer es davorschreibt, bekommt
        // trotzdem nichts entschluesselt.
        $this->assertFalse((new Feldverschluesselung())->entschluesseln('PIM1:'.base64_encode(random_bytes(60))));
    }

    public function testDieAbleitungIstDeterministisch(): void
    {
        // Waere sie es nicht, waeren Bestandsdaten nach jedem Neustart verloren. Belegt ueber
        // zwei Instanzen: Was die eine verschluesselt, liest die andere.
        $eine    = new Feldverschluesselung();
        $andere  = new Feldverschluesselung();

        $this->assertSame('ein Wert', $andere->entschluesseln($eine->verschluesseln('ein Wert')));
    }

    public function testNeueWerteTragenDasPraefixUndAlteNicht(): void
    {
        $krypto = new Feldverschluesselung();

        $this->assertTrue($krypto->istNeuesFormat($krypto->verschluesseln('frisch')),
            'Was jetzt geschrieben wird, ist AEAD');
        $this->assertFalse($krypto->istNeuesFormat(self::ALTER_CHIFFRETEXT),
            'und ein Bestandswert ist daran zu erkennen, dass ihm das Praefix fehlt');
    }
}
