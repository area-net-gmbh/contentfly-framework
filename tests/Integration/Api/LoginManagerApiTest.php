<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * Charakterisierungstests für die beobachtbare Wirkung des `LoginManager`.
 *
 * **Warum kein direkter Test von `createManagedUser()`:** Die Methode braucht einen
 * EntityManager. Ihn im Testprozess aufzubauen hiesse, die Anwendung ein **zweites Mal und
 * anders** zu konstruieren als `lib/contentfly/bootstrap.php` es tut — mit eigener
 * Annotationsregistrierung und eigener Metadaten-Konfiguration. Ein solcher Test bewiese,
 * dass *diese* Konstruktion funktioniert, nicht die produktive. Er könnte davon wegdriften und
 * dabei grün bleiben.
 *
 * Stattdessen ist hier festgehalten, was ein `LoginManager` nach aussen bewirkt: Ein Benutzer,
 * dem er zugeordnet ist, kann sich **nicht mehr mit Passwort anmelden**. Das ist die
 * Zusicherung, auf die es ankommt, und sie ist über HTTP prüfbar.
 */
class LoginManagerApiTest extends IntegrationTestCase
{
    private function benutzerAnlegen(string $alias, ?string $loginManager, ?string $externalId = null): string
    {
        $id   = 'lm-'.bin2hex(random_bytes(6));
        $salt = bin2hex(random_bytes(16));

        $this->pdo()->prepare(
            'INSERT INTO pim_user (id, isAdmin, alias, pass, isActive, salt, loginManager,
                                   externalId, created, modified, views, isIntern)
             VALUES (:id, 0, :alias, :pass, 1, :salt, :lm, :ext, NOW(), NOW(), 0, 0)'
        )->execute(array(
            'id'    => $id,
            'alias' => $alias,
            'pass'  => hash('sha256', self::TEST_PASSWORT.$salt),
            'salt'  => $salt,
            'lm'    => $loginManager,
            'ext'   => $externalId,
        ));

        $this->nachTestLoeschen('pim_user', $id);

        return $id;
    }

    public function testEinBenutzerOhneLoginManagerMeldetSichMitPasswortAn(): void
    {
        $alias = 'lm-frei-'.bin2hex(random_bytes(4));
        $this->benutzerAnlegen($alias, null);

        [$status, $body] = $this->postJson('/auth/login', array('alias' => $alias, 'pass' => self::TEST_PASSWORT));

        $this->assertSame(200, $status);
        $this->assertArrayHasKey('token', $body);
    }

    public function testEinBenutzerMitLoginManagerKannSichNichtMitPasswortAnmelden(): void
    {
        // Die eigentliche Zusicherung: Ist einem Benutzer ein LoginManager zugeordnet, geht
        // die Anmeldung nur ueber diesen Weg — auch mit dem richtigen Passwort nicht.
        // createManagedUser() setzt genau dieses Feld auf get_class($this).
        $alias = 'lm-verwaltet-'.bin2hex(random_bytes(4));
        $this->benutzerAnlegen($alias, 'Custom\\Classes\\LoginManager\\Beispiel');

        [$status, $body] = $this->postJson('/auth/login', array('alias' => $alias, 'pass' => self::TEST_PASSWORT));

        $this->assertSame(401, $status);
        $this->assertArrayNotHasKey('token', $body);
        $this->assertSame('The user can only be authenticated through their login provider.', $body['message']);
    }

    /**
     * **Umgedreht mit `013-004-0002`, nicht gelöscht.**
     *
     * Der Test hiess `testDerAliasPraefixVerhindertKollisionenZwischenLoginManagern` und hielt
     * fest, dass `createManagedUser()` den Alias mit `md5(get_class($this))` präfigiert. Der
     * Präfix löste ein echtes Problem — zwei Fremdsysteme, die denselben Benutzernamen liefern,
     * dürfen nicht dasselbe Konto bekommen —, aber er löste es, indem er die Antwort unleserlich
     * machte: Wer in `pim_user` nachsah, fand `3f2a…-mueller` und wusste nicht, wer das ist.
     *
     * Dieselbe Eindeutigkeit kommt jetzt aus einer Bedingung über `loginManager` **und**
     * `externalId`, und der Alias liest sich als `<provider>:<kennung>`.
     */
    public function testDieEindeutigkeitKommtAusDerSpaltenbedingungStattAusEinemMd5Praefix(): void
    {
        $bedingung = $this->pdo()->query(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'pim_user'
               AND INDEX_NAME = 'uniq_user_external_identity' AND NON_UNIQUE = 0"
        )->fetchColumn();

        $this->assertSame('2', (string) $bedingung,
            'Die unique-Bedingung steht ueber beiden Spalten — loginManager und externalId');

        $ldap = $this->benutzerAnlegen('ldap:mueller', 'ldap', 'mueller');
        $saml = $this->benutzerAnlegen('saml:mueller', 'saml', 'mueller');

        $this->assertNotSame($ldap, $saml, 'Zwei Konten fuer denselben externen Namen');

        $aliase = $this->pdo()->query(
            "SELECT alias FROM pim_user WHERE externalId = 'mueller' ORDER BY alias"
        )->fetchAll(\PDO::FETCH_COLUMN);

        $this->assertSame(array('ldap:mueller', 'saml:mueller'), $aliase,
            'Lesbar, und die Herkunft steht davor');
    }

    /**
     * **Befund A-6, über HTTP.**
     *
     * `createManagedUser()` setzte `setPass($alias)` — das Passwort war der Benutzername.
     * Entschärft war das allein durch den Riegel „nur über LoginManager authorisierbar"; jeder
     * Pfad, der ihn umging, war eine triviale Kontoübernahme. Jetzt ist das Passwort gesperrt,
     * und der Riegel ist die **zweite** Sicherung.
     */
    public function testEinBereitgestellterBenutzerHatKeinErratbaresPasswort(): void
    {
        $alias = 'ldap:a6-'.bin2hex(random_bytes(4));
        $this->benutzerAnlegenMitGesperrtemPasswort($alias, 'ldap');

        foreach (array($alias, substr($alias, 5), 'ldap', '*', '') as $versuch) {
            [$status, $body] = $this->postJson('/auth/login', array('alias' => $alias, 'pass' => $versuch));

            $this->assertSame(401, $status, 'Versuch mit "'.$versuch.'"');
            $this->assertArrayNotHasKey('token', $body);
        }
    }

    /**
     * Und der Riegel steht weiterhin: Auch ohne gesperrtes Passwort käme man nicht durch.
     */
    public function testDerRiegelIstDieZweiteSicherungUndStehtWeiterhin(): void
    {
        $alias = 'lm-riegel-'.bin2hex(random_bytes(4));
        $this->benutzerAnlegen($alias, 'ldap');

        [$status, $body] = $this->postJson('/auth/login', array('alias' => $alias, 'pass' => self::TEST_PASSWORT));

        $this->assertSame(401, $status);
        $this->assertSame('The user can only be authenticated through their login provider.', $body['message']);
    }

    /** Legt einen Benutzer mit gesperrtem Passwort an — wie die Bereitstellung es täte. */
    private function benutzerAnlegenMitGesperrtemPasswort(string $alias, string $anbieter): string
    {
        $id = 'lm-'.bin2hex(random_bytes(6));

        $this->pdo()->prepare(
            'INSERT INTO pim_user (id, isAdmin, alias, pass, isActive, salt, loginManager,
                                   externalId, created, modified, views, isIntern)
             VALUES (:id, 0, :alias, :pass, 1, :salt, :lm, :ext, NOW(), NOW(), 0, 0)'
        )->execute(array(
            'id'    => $id,
            'alias' => $alias,
            'pass'  => '*',
            'salt'  => bin2hex(random_bytes(16)),
            'lm'    => $anbieter,
            'ext'   => substr($alias, strlen($anbieter) + 1),
        ));

        $this->nachTestLoeschen('pim_user', $id);

        return $id;
    }

    // ── Die Auswahl kommt aus einer Allowlist (013-004-0001) ──────────────────────────

    /**
     * **Ein Klassenname im Request wählt keine Klasse mehr aus.**
     *
     * Bis `013-004-0001` wurde der Parameter `loginManager` zu `Custom\Classes\<Name>`
     * aufgelöst und die Klasse instanziiert. Der Präfix und eine `instanceof`-Prüfung
     * begrenzten den Schaden — aber die Auswahl lag beim Aufrufer. Jetzt benennt der Parameter
     * einen Eintrag im Verzeichnis, und ein Klassenname steht dort nicht.
     */
    public function testEinKlassennameWaehltKeineKlasseMehrAus(): void
    {
        foreach (array(
            'Custom\\Classes\\LoginManager\\Beispiel',
            'Plugins\\Auth\\Ldap',
            'Areanet\\PIM\\Classes\\Manager\\LoginManager',
        ) as $klassenname) {
            [$status, $body] = $this->postJson('/auth/login', array(
                'alias'        => 'admin',
                'pass'         => $this->pass(),
                'loginManager' => $klassenname,
            ));

            $this->assertSame(401, $status, $klassenname.' darf nichts oeffnen');
            $this->assertArrayNotHasKey('token', $body);
        }
    }

    /**
     * Ein unbekannter Name wird abgewiesen und **nicht** auf die Passwortprüfung
     * zurückgeführt.
     *
     * Sonst wäre ein Tippfehler im Providernamen eine stille Anmeldung über den falschen Weg —
     * mit richtigem Passwort sogar eine erfolgreiche.
     */
    public function testEinUnbekannterProvidernameFaelltNichtAufDasPasswortZurueck(): void
    {
        [$status, $body] = $this->postJson('/auth/login', array(
            'alias'        => 'admin',
            'pass'         => $this->pass(),
            'loginManager' => 'gibtesnicht',
        ));

        $this->assertSame(401, $status);
        $this->assertArrayNotHasKey('token', $body);
    }

    /**
     * Und die Gegenprobe: Ohne den Parameter läuft die Anmeldung wie immer.
     *
     * Das Verzeichnis ist im ausgelieferten Zustand leer — solange nichts eingetragen ist, gibt
     * es keinen Weg an der Passwortprüfung vorbei, aber auch keinen zusätzlichen Riegel davor.
     */
    public function testOhneProvidernameLaeuftDieAnmeldungWieImmer(): void
    {
        [$status, $body] = $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => $this->pass()));

        $this->assertSame(200, $status);
        $this->assertArrayHasKey('token', $body);
    }
}
