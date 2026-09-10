<?php
namespace Tests\Integration\Command;

use Areanet\PIM\Classes\Config;
use Areanet\PIM\Classes\Config\Factory;
use Areanet\PIM\Classes\Security\Feldverschluesselung;
use Areanet\PIM\Command\ReencryptCommand;
use Doctrine\DBAL\DriverManager;
use Tests\Integration\IntegrationTestCase;
use PHPUnit\Framework\TestCase;

/**
 * Prueft `appcms:security:reencrypt` gegen eine ECHTE Datenbank (010-004-0003).
 *
 * WARUM MIT EIGENER TABELLE. Im Baum gibt es kein Feld mit `encoded: true` — ein Test, der den
 * Befehl von aussen aufriefe, koennte nur bestaetigen, dass er nichts tut. Deshalb legt dieser
 * Test seine eigene Tabelle an, fuellt sie mit Werten im ALTEN Format und laesst die
 * Umschluesselung darueber laufen. Was gemessen wird, ist damit der echte Codepfad auf echten
 * Zeilen, nicht eine Attrappe.
 *
 * Die Tabelle heisst `probe_reencrypt` und wird am Ende wieder entfernt. Sie beruehrt kein
 * Schema des Frameworks.
 */
class ReencryptCommandTest extends TestCase
{
    private const SCHLUESSEL = 'ein-schluessel-fuer-den-test-32b';
    private const TABELLE    = 'probe_reencrypt';

    /** @var \Doctrine\DBAL\Connection */
    private $db;

    protected function setUp(): void
    {
        $config = new Config();
        $config->SECURITY_CIPHER_KEY = self::SCHLUESSEL;
        Factory::getInstance()->setConfig($config);

        // Dieselben Zugangsdaten wie die uebrigen Integrationstests. Ein zweiter Weg dorthin
        // liefe irgendwann auseinander — genau die Begruendung, die an `dbZugangsdaten()`
        // selbst steht.
        $zugang = IntegrationTestCase::dbZugangsdaten();

        $this->db = DriverManager::getConnection(array(
            'driver'   => 'pdo_mysql',
            'host'     => $zugang['host'],
            'port'     => (int) $zugang['port'],
            'dbname'   => $zugang['name'],
            'user'     => $zugang['user'],
            'password' => $zugang['pass'],
        ));

        $this->db->executeStatement('DROP TABLE IF EXISTS '.self::TABELLE);
        $this->db->executeStatement(
            'CREATE TABLE '.self::TABELLE.' (id INT AUTO_INCREMENT PRIMARY KEY, geheim TEXT NULL)'
        );
    }

    protected function tearDown(): void
    {
        if ($this->db) {
            $this->db->executeStatement('DROP TABLE IF EXISTS '.self::TABELLE);
        }

        $config = new Config();
        $config->SECURITY_CIPHER_KEY = null;
        Factory::getInstance()->setConfig($config);
    }

    /** Verschluesselt im ALTEN Format — der Wortlaut des Codes vor 010-004-0002. */
    private function altVerschluesseln(string $klartext): string
    {
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('AES-256-CBC'));

        return base64_encode($iv.openssl_encrypt($klartext, 'AES-256-CBC', self::SCHLUESSEL, 0, $iv));
    }

    /** @return array<int,string> */
    private function werteLesen(): array
    {
        return $this->db->fetchAllKeyValue('SELECT id, geheim FROM '.self::TABELLE.' ORDER BY id');
    }

    public function testDerTrockenlaufAendertNichts(): void
    {
        $klartexte = array('erster Wert', 'zweiter Wert', 'dritter Wert');

        foreach ($klartexte as $wert) {
            $this->db->insert(self::TABELLE, array('geheim' => $this->altVerschluesseln($wert)));
        }

        $vorher = $this->werteLesen();

        $ergebnis = (new ReencryptCommand())
            ->spalteUmschluesseln($this->db, self::TABELLE, 'geheim', 'id', true, 500);

        $this->assertSame(3, $ergebnis['geprueft']);
        $this->assertSame(3, $ergebnis['umgeschluesselt'], 'Er haette drei umgeschluesselt');
        $this->assertSame($vorher, $this->werteLesen(), 'aber die Zeilen sind unveraendert');
    }

    public function testDerEchteLaufSchluesseltUmUndDerZweiteFindetNichtsMehr(): void
    {
        $klartexte = array('erster Wert', 'zweiter Wert', 'dritter Wert');

        foreach ($klartexte as $wert) {
            $this->db->insert(self::TABELLE, array('geheim' => $this->altVerschluesseln($wert)));
        }

        $befehl = new ReencryptCommand();
        $krypto = new Feldverschluesselung();

        $erster = $befehl->spalteUmschluesseln($this->db, self::TABELLE, 'geheim', 'id', false, 500);
        $this->assertSame(3, $erster['umgeschluesselt']);

        // Der Klartext ist derselbe — das ist der eigentliche Punkt der Umschluesselung.
        $this->assertSame($klartexte, array_values(array_map(
            static fn (string $wert): string => (string) (new Feldverschluesselung())->entschluesseln($wert),
            $this->werteLesen()
        )));

        foreach ($this->werteLesen() as $wert) {
            $this->assertTrue($krypto->istNeuesFormat($wert), 'und jeder Wert traegt jetzt das AEAD-Format');
        }

        $zweiter = $befehl->spalteUmschluesseln($this->db, self::TABELLE, 'geheim', 'id', false, 500);
        $this->assertSame(0, $zweiter['umgeschluesselt'], 'Ein zweiter Lauf hat nichts mehr zu tun');
        $this->assertSame(3, $zweiter['uebersprungen']);
    }

    public function testErArbeitetInStapelnUndErwischtAlleZeilen(): void
    {
        // Die Stapelgroesse ist kleiner als die Zeilenzahl — wenn die Seitenweise-Abfrage
        // falsch waere, blieben Zeilen liegen oder der Lauf drehte sich im Kreis.
        for ($i = 0; $i < 25; $i++) {
            $this->db->insert(self::TABELLE, array('geheim' => $this->altVerschluesseln('Wert '.$i)));
        }

        $ergebnis = (new ReencryptCommand())
            ->spalteUmschluesseln($this->db, self::TABELLE, 'geheim', 'id', false, 4);

        $this->assertSame(25, $ergebnis['umgeschluesselt']);

        $krypto = new Feldverschluesselung();

        foreach ($this->werteLesen() as $wert) {
            $this->assertTrue($krypto->istNeuesFormat($wert));
        }
    }

    public function testEinUnlesbarerWertBrichtDenStapelAbUndLaesstIhnUnveraendert(): void
    {
        $this->db->insert(self::TABELLE, array('geheim' => $this->altVerschluesseln('lesbar')));
        $this->db->insert(self::TABELLE, array('geheim' => base64_encode('kein gueltiger Chiffretext')));
        $this->db->insert(self::TABELLE, array('geheim' => $this->altVerschluesseln('auch lesbar')));

        $vorher = $this->werteLesen();

        try {
            (new ReencryptCommand())->spalteUmschluesseln($this->db, self::TABELLE, 'geheim', 'id', false, 500);
            $this->fail('Ein unlesbarer Wert muss den Lauf anhalten');
        } catch (\RuntimeException $fehler) {
            $this->assertStringContainsString('SECURITY_CIPHER_KEY', $fehler->getMessage(),
                'und die Meldung muss auf die wahrscheinlichste Ursache zeigen');
        }

        $this->assertSame($vorher, $this->werteLesen(),
            'Der Stapel ist eine Transaktion — es bleibt kein halb umgeschluesselter Stand zurueck');
    }

    public function testHeuteGibtEsKeinFeldMitEncoded(): void
    {
        // Der Befehl findet seine Felder ueber das Schema. Im Framework gibt es keines — und
        // dieser Test haelt das fest, damit die leere Ausgabe des Befehls nicht mit einem
        // Defekt verwechselt wird. Setzt jemand `encoded: true`, schlaegt er an.
        $schema = array('_hash' => 'x', 'PIM\\User' => array('properties' => array(
            'alias' => array('encoded' => false),
            'pass'  => array('encoded' => false),
        )));

        $gefunden = (new ReencryptCommand())->betroffeneFelder($schema, $this->emAttrappe(), $this->helferAttrappe());

        $this->assertSame(array(), $gefunden);
    }

    private function emAttrappe(): \Doctrine\ORM\EntityManagerInterface
    {
        return $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
    }

    private function helferAttrappe(): object
    {
        return new class {
            public function getFullEntityName(string $entity): string
            {
                return 'Areanet\\PIM\\Entity\\User';
            }
        };
    }
}
