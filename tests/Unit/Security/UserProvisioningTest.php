<?php
namespace Tests\Unit\Security;

use Areanet\PIM\Classes\Security\UserProvisioning;
use Areanet\PIM\Classes\Security\ExternalIdentity;
use Areanet\PIM\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

/**
 * Provisionierung ohne setzbares Passwort (013-004-0002).
 *
 * **Befund A-6 ist das, wogegen es geht.** `createManagedUser()` setzte `setPass($alias)` — das
 * Passwort war der Benutzername. Entschärft war das allein durch den Riegel „nur über
 * LoginManager authorisierbar"; jeder Pfad, der ihn umging, war eine triviale Kontoübernahme.
 */
class UserProvisioningTest extends TestCase
{
    /** @var list<object> */
    private array $persistiert = array();

    private function bereitstellung(?User $vorhanden): UserProvisioning
    {
        $this->persistiert = array();

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($vorhanden);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);
        $em->method('persist')->willReturnCallback(function ($objekt) {
            $this->persistiert[] = $objekt;
        });

        return new UserProvisioning($em);
    }

    // ── Anlegen ────────────────────────────────────────────────────────────────────────

    /**
     * **Der Kern des Tasks.** Ein neu angelegter Benutzer hat kein Passwort — nicht ein
     * zufälliges, sondern gar keines.
     */
    public function testEinNeuerBenutzerHatEinGesperrtesPasswort(): void
    {
        $benutzer = $this->bereitstellung(null)->findOrCreate('ldap', new ExternalIdentity('mmustermann'));

        $this->assertTrue($benutzer->isPasswordLocked());
        $this->assertSame(User::PASSWORD_LOCKED, $benutzer->getPass());
    }

    /**
     * Und die Probe, die Befund A-6 beschreibt: Der Benutzername als Passwort passt nicht.
     */
    public function testDerBenutzernameTaugtNichtAlsPasswort(): void
    {
        $benutzer = $this->bereitstellung(null)->findOrCreate('ldap', new ExternalIdentity('mmustermann'));

        $this->assertFalse($benutzer->isPass('mmustermann'));
        $this->assertFalse($benutzer->isPass($benutzer->getAlias()));
        $this->assertFalse($benutzer->isPass(User::PASSWORD_LOCKED));
        $this->assertFalse($benutzer->isPass(''));
    }

    /**
     * Die Kennung des Fremdsystems steht lesbar da — nicht in einem MD5-Präfix.
     */
    public function testKennungUndHerkunftStehenLesbarInEigenenFeldern(): void
    {
        $benutzer = $this->bereitstellung(null)->findOrCreate('ldap', new ExternalIdentity('mmustermann'));

        $this->assertSame('mmustermann', $benutzer->getExternalId());
        $this->assertSame('ldap', $benutzer->getLoginManager());
        $this->assertSame('ldap:mmustermann', $benutzer->getAlias());
        $this->assertStringNotContainsString(md5('ldap'), (string) $benutzer->getAlias());
    }

    /**
     * Zwei Provider, dieselbe Kennung, zwei Konten.
     *
     * Genau das leistete früher der MD5-Präfix — nur unleserlich.
     */
    public function testZweiProviderMitDerselbenKennungErgebenZweiKonten(): void
    {
        $einer  = $this->bereitstellung(null)->findOrCreate('ldap', new ExternalIdentity('mueller'));
        $andere = $this->bereitstellung(null)->findOrCreate('saml', new ExternalIdentity('mueller'));

        $this->assertNotSame($einer->getAlias(), $andere->getAlias());
        $this->assertSame('ldap:mueller', $einer->getAlias());
        $this->assertSame('saml:mueller', $andere->getAlias());
    }

    public function testEinNeuerBenutzerBekommtKeineAdminrechte(): void
    {
        $benutzer = $this->bereitstellung(null)->findOrCreate('ldap', new ExternalIdentity('mmustermann'));

        $this->assertFalse((bool) $benutzer->getIsAdmin());
        $this->assertTrue((bool) $benutzer->getIsActive());
    }

    // ── Wiederfinden ───────────────────────────────────────────────────────────────────

    public function testEinVorhandenerBenutzerWirdWiedergefunden(): void
    {
        $vorhanden = new User();
        $vorhanden->setAlias('ldap:mmustermann');
        $vorhanden->setExternalId('mmustermann');
        $vorhanden->setLoginManager('ldap');

        $gefunden = $this->bereitstellung($vorhanden)->findOrCreate('ldap', new ExternalIdentity('mmustermann'));

        $this->assertSame($vorhanden, $gefunden);
        $this->assertSame(array(), $this->persistiert, 'Nichts angelegt');
    }

    /**
     * Ein vorhandener Benutzer, dessen Passwort ein Mensch gesetzt hat, wird nicht gesperrt.
     *
     * Der Fall ist kein Hirngespinst: Ein Administrator kann einem Konto einen Provider
     * zuordnen, das schon existierte. Die Sperre gehört zum **Anlegen**, nicht zum Anmelden.
     */
    public function testEinVorhandenerBenutzerWirdNichtNachtraeglichGesperrt(): void
    {
        $vorhanden = new User();
        $vorhanden->setAlias('ldap:chef');
        $vorhanden->setPass('ein-echtes-passwort');

        $gefunden = $this->bereitstellung($vorhanden)->findOrCreate('ldap', new ExternalIdentity('chef'));

        $this->assertFalse($gefunden->isPasswordLocked());
        $this->assertTrue($gefunden->isPass('ein-echtes-passwort'));
    }

    // ── Der Umgang mit dem gesperrten Hash ─────────────────────────────────────────────

    /**
     * Ein gesperrtes Passwort wird nicht umgeschlüsselt — es soll ja keines werden.
     *
     * Ohne diese Prüfung hielte `needsRehash()` den Stern für einen Altformat-Hash und der
     * Login versuchte, ihn durch das vorgezeigte Passwort zu ersetzen.
     */
    public function testEinGesperrtesPasswortWirdNichtUmgeschluesselt(): void
    {
        $benutzer = $this->bereitstellung(null)->findOrCreate('ldap', new ExternalIdentity('mmustermann'));

        $this->assertFalse($benutzer->needsRehash());
    }
}
