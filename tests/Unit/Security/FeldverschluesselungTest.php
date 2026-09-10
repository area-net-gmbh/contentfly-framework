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
     * DER BEFUND, UM DEN ES DER GANZEN STORY GEHT.
     *
     * AES-256-CBC hat keinen MAC. Ein veraenderter Chiffretext wird nicht abgewiesen — er wird
     * entschluesselt, und heraus kommt ein ANDERER Klartext. Die Anwendung merkt nichts und
     * liefert ihn aus.
     *
     * Gemessen an einem Beispiel: Kippt man ein Byte im zweiten Block, liest der Klartext
     *
     *     Ueberweisung an G···}···[·*6·10 Euro, dri
     *
     * statt „Ueberweisung an Konto A, Betrag 100 Euro". Ein Block ist Muell, der Rest steht.
     * Wer den Chiffretext erreicht, kann also gezielt Teile veraendern.
     *
     * Nicht jede Position gelingt: Trifft die Aenderung den letzten Block, scheitert die
     * Padding-Pruefung und `openssl_decrypt()` liefert `false`. Der Test sucht deshalb eine
     * Position, an der die Manipulation DURCHGEHT — es genuegt eine einzige, damit die
     * Zusicherung wertlos ist.
     *
     * Dieser Test haelt den Zustand fest, statt ihn zu beklagen. Mit `010-004-0002` wird er
     * umgedreht: Dann muss jede Manipulation auffallen.
     */
    public function testEinManipulierterChiffretextFaelltHeuteNichtAuf(): void
    {
        $krypto   = new Feldverschluesselung();
        $klartext = 'Ueberweisung an Konto A, Betrag 100 Euro, dringend bitte';
        $roh      = base64_decode($krypto->verschluesseln($klartext));

        $durchgerutscht = null;

        // Der IV steht in den ersten 16 Byte; veraendert wird der Rumpf dahinter.
        for ($pos = 16; $pos < strlen($roh); $pos++) {
            $manipuliert       = $roh;
            $manipuliert[$pos] = chr(ord($manipuliert[$pos]) ^ 0x01);

            $ergebnis = $krypto->entschluesseln(base64_encode($manipuliert));

            if ($ergebnis !== false && $ergebnis !== $klartext) {
                $durchgerutscht = $ergebnis;
                break;
            }
        }

        $this->assertNotNull($durchgerutscht,
            'Es gibt eine Manipulation, die nicht auffaellt — CBC ohne MAC weist sie nicht ab');
        $this->assertNotSame($klartext, $durchgerutscht,
            'und der Klartext ist ein anderer als der verschluesselte');
    }
}
