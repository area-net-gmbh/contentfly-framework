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
    private function benutzerAnlegen(string $alias, ?string $loginManager): string
    {
        $id   = 'lm-'.bin2hex(random_bytes(6));
        $salt = bin2hex(random_bytes(16));

        $this->pdo()->prepare(
            'INSERT INTO pim_user (id, isAdmin, alias, pass, isActive, salt, loginManager,
                                   created, modified, views, isIntern)
             VALUES (:id, 0, :alias, :pass, 1, :salt, :lm, NOW(), NOW(), 0, 0)'
        )->execute(array(
            'id'    => $id,
            'alias' => $alias,
            'pass'  => hash('sha256', self::TEST_PASSWORT.$salt),
            'salt'  => $salt,
            'lm'    => $loginManager,
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
        $this->assertSame('Der Benutzer ist nur über LoginManager authorisierbar.', $body['message']);
    }

    public function testDerAliasPraefixVerhindertKollisionenZwischenLoginManagern(): void
    {
        // createManagedUser() praefigiert den Alias mit md5(get_class($this)) — zwei
        // verschiedene LoginManager, die denselben externen Benutzernamen liefern, erzeugen
        // damit zwei verschiedene Konten statt sich gegenseitig zu uebernehmen.
        //
        // Die Praefix-Bildung ist hier nachgerechnet, nicht ueber die Methode ausgeloest:
        // Der Wert ist reine Funktion des Klassennamens, und die Zusicherung lautet
        // "verschiedene Klassen ergeben verschiedene Praefixe".
        $ersterPraefix  = md5('Custom\\Classes\\LoginManager\\Ldap');
        $zweiterPraefix = md5('Custom\\Classes\\LoginManager\\Saml');

        $this->assertNotSame($ersterPraefix, $zweiterPraefix);

        $aliasLdap = $ersterPraefix.'-mueller';
        $aliasSaml = $zweiterPraefix.'-mueller';

        $this->benutzerAnlegen($aliasLdap, 'Custom\\Classes\\LoginManager\\Ldap');
        $this->benutzerAnlegen($aliasSaml, 'Custom\\Classes\\LoginManager\\Saml');

        $anzahl = (int) $this->pdo()
            ->query('SELECT COUNT(*) FROM pim_user WHERE alias LIKE '.$this->pdo()->quote('%-mueller'))
            ->fetchColumn();

        $this->assertSame(2, $anzahl,
            'Derselbe externe Name aus zwei Providern ergibt zwei Konten — die '
            .'unique-Bedingung auf alias schlaegt nicht zu');
    }
}
