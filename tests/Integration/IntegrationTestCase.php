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
 * **Diese Datei kommt über den Autoloader** — `composer.json` mappt `Tests\` auf `tests/`, in
 * `autoload-dev`. Bis `006-004-0004` war das anders: `tests/bootstrap.php` lud sie per
 * `require_once`, weil das Mapping im **Projekt**-Manifest lag und auf `Custom\Tests\` zeigte,
 * einen Namensraum, den keine Testdatei je trug. PHPUnit selbst lädt nur Dateien, die auf
 * `Test.php` enden — diese also nicht.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected static ?string $baseUrl = null;

    /** Angemeldeter Token, einmal je Testklasse. */
    private static ?string $zwischengespeicherterToken = null;

    private static ?PDO $pdo = null;

    /** @var array<int, array{0:string,1:string}> Tabelle und Id, die tearDown() entfernt. */
    private array $aufzuraeumen = array();

    /** @var array<int, string> Verzeichnisse, die tearDown() samt Inhalt entfernt. */
    private array $verzeichnisse = array();

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

        foreach ($this->verzeichnisse as $pfad) {
            $this->verzeichnisEntfernen($pfad);
        }

        $this->aufzuraeumen  = array();
        $this->verzeichnisse = array();
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
            $zugang = self::dbZugangsdaten();

            self::$pdo = new PDO(
                $zugang['dsn'],
                $zugang['user'],
                $zugang['pass'],
                array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
            );
        }

        return self::$pdo;
    }

    /**
     * Die Verbindungsdaten der Testdatenbank — an genau **einer** Stelle.
     *
     * Öffentlich und statisch, weil `UmgebungsWaechterTest` sie ebenfalls braucht: Er prüft
     * vor dem eigentlichen Lauf, ob die Datenbank überhaupt antwortet, erbt aber bewusst
     * nicht von dieser Klasse. Stünden die Vorgaben dort ein zweites Mal, liefen sie
     * auseinander — und der Wächter prüfte irgendwann eine andere Datenbank als die Tests.
     *
     * Die Standardwerte entsprechen der `docker-compose.yml` aus dem Runbook.
     *
     * @return array{dsn:string,user:string,pass:string,host:string,port:string,name:string}
     */
    public static function dbZugangsdaten(): array
    {
        $host = getenv('CONTENTFLY_TEST_DB_HOST') ?: '127.0.0.1';
        $port = getenv('CONTENTFLY_TEST_DB_PORT') ?: '3307';
        $name = getenv('CONTENTFLY_TEST_DB_NAME') ?: 'contentfly';
        $user = getenv('CONTENTFLY_TEST_DB_USER') ?: 'contentfly';
        $pass = getenv('CONTENTFLY_TEST_DB_PASSWORD') ?: 'contentfly';

        return array(
            'dsn'  => "mysql:host=$host;port=$port;dbname=$name;charset=utf8",
            'user' => $user,
            'pass' => $pass,
            'host' => $host,
            'port' => $port,
            'name' => $name,
        );
    }

    /**
     * Meldet eine Zeile zum Aufräumen an. tearDown() entfernt sie, auch wenn der Test
     * scheitert — sonst faerbt ein roter Test die folgenden mit ein.
     */
    protected function nachTestLoeschen(string $tabelle, string $id): void
    {
        $this->aufzuraeumen[] = array($tabelle, $id);
    }

    /**
     * Meldet ein Verzeichnis zum Aufräumen an — samt Inhalt.
     *
     * Für Tests, die Dateien auf der Platte hinterlassen. Ohne das wächst `data/files` mit
     * jedem Lauf, und ein frischer Checkout verhält sich anders als eine gewachsene
     * Umgebung — genau der Unterschied, den ein Testnetz nicht haben soll (`000-000-0008`).
     */
    protected function nachTestVerzeichnisLoeschen(string $pfad): void
    {
        $this->verzeichnisse[] = $pfad;
    }

    /** Entfernt ein Verzeichnis samt Inhalt; ein fehlendes ist kein Fehler. */
    private function verzeichnisEntfernen(string $pfad): void
    {
        if (!is_dir($pfad)) {
            return;
        }

        foreach (scandir($pfad) ?: array() as $eintrag) {
            if ($eintrag === '.' || $eintrag === '..') {
                continue;
            }

            $voll = $pfad.'/'.$eintrag;
            is_dir($voll) ? $this->verzeichnisEntfernen($voll) : @unlink($voll);
        }

        @rmdir($pfad);
    }

    // ── Testbenutzer mit Berechtigungen ────────────────────────────────────────────────

    /** Passwort aller ueber testbenutzer() angelegten Konten. */
    protected const TEST_PASSWORT = 'test-nur-fuer-diesen-lauf';

    /**
     * Legt eine Gruppe, einen Nicht-Admin darin und dessen Entity-Berechtigungen an und
     * meldet sich als dieser Benutzer an.
     *
     * `$rechte` ist eine Zuordnung Entity-Kurzname → Rechte, etwa:
     *
     *     $this->testbenutzer(array(
     *         'PIM\Tag' => array('readable' => Permission::ALL, 'writable' => Permission::OWN),
     *     ));
     *
     * Fehlende Schluessel sind `Permission::NONE`. **Die Stufen gehoeren als Konstanten
     * uebergeben, nicht als Zahlen:** Sie sind nicht aufsteigend geordnet — `OWN` ist 1,
     * `ALL` ist 2 und `GROUP` ist 3. Wer sie als Rangfolge liest, irrt.
     *
     * Das Passwort wird gehasht, wie `User::setPass()` es tut: sha256 aus Passwort und Salt.
     * Alles Angelegte meldet sich zum Aufraeumen an, in einer Reihenfolge, die die
     * Fremdschluessel auf `pim_group` respektiert.
     *
     * @param array<string, array<string,int>> $rechte
     * @param array<string,mixed>              $gruppe  Zusaetzliche Gruppenfelder,
     *                                                  etwa apiQueryEnabled oder languages
     * @return array{0:string,1:string,2:string} Token, Benutzer-Id, Gruppen-Id
     */
    protected function testbenutzer(array $rechte = array(), array $gruppe = array()): array
    {
        $lauf     = bin2hex(random_bytes(6));
        $gruppeId = 'tgrp-'.$lauf;
        $userId   = 'tusr-'.$lauf;
        $alias    = 'testuser-'.$lauf;
        $salt     = bin2hex(random_bytes(16));

        $this->pdo()->prepare(
            'INSERT INTO pim_group (id, name, tokenTimeout, apiQueryEnabled, languages,
                                    created, modified, views, isIntern)
             VALUES (:id, :name, 60, :ape, :languages, NOW(), NOW(), 0, 0)'
        )->execute(array(
            'id'        => $gruppeId,
            'name'      => 'Testgruppe '.$lauf,
            'ape'       => $gruppe['apiQueryEnabled'] ?? 'disabled',
            'languages' => $gruppe['languages'] ?? null,
        ));
        $this->nachTestLoeschen('pim_group', $gruppeId);

        $this->pdo()->prepare(
            'INSERT INTO pim_user (id, isAdmin, alias, pass, isActive, salt,
                                   created, modified, views, isIntern, group_id)
             VALUES (:id, 0, :alias, :pass, 1, :salt, NOW(), NOW(), 0, 0, :gruppe)'
        )->execute(array(
            'id'     => $userId,
            'alias'  => $alias,
            'pass'   => hash('sha256', self::TEST_PASSWORT.$salt),
            'salt'   => $salt,
            'gruppe' => $gruppeId,
        ));
        $this->nachTestLoeschen('pim_user', $userId);

        $nummer = 0;
        foreach ($rechte as $entity => $stufen) {
            $rechtId = 'tperm-'.$lauf.'-'.($nummer++);

            $this->pdo()->prepare(
                'INSERT INTO pim_permission (id, entityName, readable, writable, deletable,
                                             export, extended, created, modified, views,
                                             isIntern, group_id)
                 VALUES (:id, :entity, :readable, :writable, :deletable, :export, :extended,
                         NOW(), NOW(), 0, 0, :gruppe)'
            )->execute(array(
                'id'        => $rechtId,
                'entity'    => $entity,
                'readable'  => $stufen['readable']  ?? 0,
                'writable'  => $stufen['writable']  ?? 0,
                'deletable' => $stufen['deletable'] ?? 0,
                'export'    => $stufen['export']    ?? 0,
                'extended'  => $stufen['extended']  ?? null,
                'gruppe'    => $gruppeId,
            ));
            $this->nachTestLoeschen('pim_permission', $rechtId);
        }

        [, $body] = $this->postJson('/auth/login', array('alias' => $alias, 'pass' => self::TEST_PASSWORT));

        if (!isset($body['token'])) {
            $this->fail('Anmeldung des Testbenutzers fehlgeschlagen: '.json_encode($body));
        }

        return array($body['token'], $userId, $gruppeId);
    }
}
