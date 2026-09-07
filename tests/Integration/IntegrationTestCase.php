<?php
namespace Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Basis für alle Integrationstests: HTTP gegen eine laufende, installierte Instanz.
 *
 * Bündelt, was `AuthApiTest` und `FileApiTest` bis Story `008-001` je für sich mitbrachten —
 * Übersprung-Logik, Anmeldung, HTTP-Helfer. Epic `008` fügt weitere Testdateien hinzu; ohne
 * diese Basis wäre jede davon die nächste Kopie derselben vier Methoden.
 *
 * Voraussetzungen (sonst wird übersprungen):
 * - `CONTENTFLY_TEST_BASE_URL` zeigt auf eine laufende, installierte Instanz
 * - `CONTENTFLY_TEST_ADMIN_PASS` ist das Passwort des Benutzers `admin`
 *
 * Siehe `tests/README.md`.
 *
 * **Diese Datei wird von `tests/bootstrap.php` per `require_once` geladen**, nicht über den
 * Autoloader: `custom/composer.json` mappt `Custom\Tests\`, die Testklassen liegen aber unter
 * `Tests\`. PHPUnit selbst lädt nur Dateien, die auf `Test.php` enden — diese also nicht.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected static ?string $baseUrl = null;

    /** Angemeldeter Token, einmal je Testklasse. */
    private static ?string $zwischengespeicherterToken = null;

    private static ?PDO $pdo = null;

    /** @var array<int, array{0:string,1:string}> Tabelle und Id, die tearDown() entfernt. */
    private array $aufzuraeumen = array();

    public static function setUpBeforeClass(): void
    {
        self::$baseUrl                     = getenv('CONTENTFLY_TEST_BASE_URL') ?: null;
        self::$zwischengespeicherterToken  = null;
    }

    protected function setUp(): void
    {
        if (self::$baseUrl === null) {
            $this->markTestSkipped('CONTENTFLY_TEST_BASE_URL nicht gesetzt — Integrationstests übersprungen.');
        }
    }

    /**
     * Entfernt, was der Test über nachTestLoeschen() angemeldet hat — in umgekehrter
     * Reihenfolge, damit abhängige Zeilen vor ihren Zielen verschwinden.
     */
    protected function tearDown(): void
    {
        foreach (array_reverse($this->aufzuraeumen) as [$tabelle, $id]) {
            $stmt = $this->pdo()->prepare("DELETE FROM `$tabelle` WHERE id = :id");
            $stmt->execute(array('id' => $id));
        }

        $this->aufzuraeumen = array();
    }

    // ── Anmeldung ──────────────────────────────────────────────────────────────────────

    protected function pass(): string
    {
        return getenv('CONTENTFLY_TEST_ADMIN_PASS') ?: 'admin';
    }

    /** Meldet neu an und liefert einen frischen Token. */
    protected function login(): string
    {
        [, $body] = $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => $this->pass()));

        if (!isset($body['token'])) {
            $this->fail('Anmeldung fehlgeschlagen: '.json_encode($body));
        }

        return $body['token'];
    }

    /**
     * Liefert einen Token und behält ihn für die Testklasse.
     *
     * Für Tests, die nur *irgendeinen* gültigen Token brauchen. Wer das Anmelden selbst
     * prüft — oder einen Token entwertet — nimmt login().
     */
    protected function token(): string
    {
        if (self::$zwischengespeicherterToken === null) {
            self::$zwischengespeicherterToken = $this->login();
        }

        return self::$zwischengespeicherterToken;
    }

    // ── HTTP ───────────────────────────────────────────────────────────────────────────

    /** @return array{0:int,1:array,2:string} Status, Rumpf als Array, Kopfzeilen */
    protected function postJson(string $pfad, array $daten, ?string $token = null): array
    {
        $kopf = array('Content-Type: application/json');
        if ($token !== null) {
            $kopf[] = 'appcms-token: '.$token;
        }

        $ch = curl_init(self::$baseUrl.$pfad);
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_POSTFIELDS     => json_encode($daten),
            CURLOPT_HTTPHEADER     => $kopf,
        ));

        return $this->auswerten($ch, true);
    }

    /** @return array{0:int,1:string,2:string} Status, Rumpf, Kopfzeilen */
    protected function get(string $pfad, ?string $token = null): array
    {
        $ch = curl_init(self::$baseUrl.$pfad);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_HTTPHEADER     => $token !== null ? array('appcms-token: '.$token) : array(),
        ));

        return $this->auswerten($ch, false);
    }

    /** Liest eine Kopfzeile aus dem Rohkopf einer Antwort. */
    protected function kopfzeile(string $kopf, string $name): ?string
    {
        if (preg_match('/^'.preg_quote($name, '/').':\s*(.+)$/mi', $kopf, $treffer)) {
            return trim($treffer[1]);
        }

        return null;
    }

    /** @return array{0:int,1:array|string,2:string} */
    private function auswerten($ch, bool $alsJson): array
    {
        $antwort = (string) curl_exec($ch);
        $status  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $kopfLen = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $kopf = substr($antwort, 0, $kopfLen);
        $body = substr($antwort, $kopfLen);

        return array($status, $alsJson ? (json_decode($body, true) ?: array()) : $body, $kopf);
    }

    // ── Testdaten ──────────────────────────────────────────────────────────────────────

    /**
     * Verbindung zur Testdatenbank.
     *
     * Testdaten entstehen bewusst **an der API vorbei**: Ein Lesetest, dessen Vorbedingung
     * über den Schreibpfad läuft, den er selbst nicht prüft, verliert seine Aussagekraft —
     * und Story `008-001` soll ausdrücklich nicht von `008-002` abhängen.
     *
     * Die Zugangsdaten kommen aus der Umgebung; die Standardwerte entsprechen der
     * `docker-compose.yml` aus dem Runbook.
     */
    protected function pdo(): PDO
    {
        if (self::$pdo === null) {
            $host = getenv('CONTENTFLY_TEST_DB_HOST') ?: '127.0.0.1';
            $port = getenv('CONTENTFLY_TEST_DB_PORT') ?: '3307';
            $name = getenv('CONTENTFLY_TEST_DB_NAME') ?: 'contentfly';
            $user = getenv('CONTENTFLY_TEST_DB_USER') ?: 'contentfly';
            $pass = getenv('CONTENTFLY_TEST_DB_PASSWORD') ?: 'contentfly';

            self::$pdo = new PDO(
                "mysql:host=$host;port=$port;dbname=$name;charset=utf8",
                $user,
                $pass,
                array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
            );
        }

        return self::$pdo;
    }

    /**
     * Meldet eine Zeile zum Aufräumen an. tearDown() entfernt sie, auch wenn der Test
     * scheitert — sonst faerbt ein roter Test die folgenden mit ein.
     */
    protected function nachTestLoeschen(string $tabelle, string $id): void
    {
        $this->aufzuraeumen[] = array($tabelle, $id);
    }
}
