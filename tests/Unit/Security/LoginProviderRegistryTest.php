<?php
namespace Tests\Unit\Security;

use Areanet\PIM\Classes\Security\LoginProviderRegistry;
use Areanet\PIM\Classes\Security\LoginProvider;
use Areanet\PIM\Classes\Security\ExternalIdentity;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Die Allowlist der LoginProvider (013-004-0001).
 *
 * Sie ersetzt die Auflösung eines Klassennamens aus dem Request. Der Unterschied ist nicht
 * kosmetisch: Aus „der Aufrufer sagt, was geladen wird" wird „der Aufrufer wählt aus dem, was
 * der Betreiber freigegeben hat".
 */
class LoginProviderRegistryTest extends TestCase
{
    private function provider(?string $kennung = 'extern-1'): LoginProvider
    {
        return new class($kennung) implements LoginProvider {
            public function __construct(private ?string $kennung) {}

            public function authenticate(Request $request): ?ExternalIdentity
            {
                return $this->kennung === null ? null : new ExternalIdentity($this->kennung);
            }
        };
    }

    public function testEinEingetragenerNameLiefertSeinenProvider(): void
    {
        $verzeichnis = new LoginProviderRegistry();
        $verzeichnis->register('ldap', $this->provider());

        $this->assertTrue($verzeichnis->has('ldap'));
        $this->assertInstanceOf(LoginProvider::class, $verzeichnis->get('ldap'));
    }

    /**
     * **Der Kern:** Ein Name, den niemand eingetragen hat, existiert nicht — und ein
     * Klassenname ist so ein Name.
     */
    public function testEinNichtEingetragenerNameLiefertNichts(): void
    {
        $verzeichnis = new LoginProviderRegistry();
        $verzeichnis->register('ldap', $this->provider());

        $this->assertNull($verzeichnis->get('saml'));
        $this->assertNull($verzeichnis->get('Custom\\Classes\\LoginManager\\Beispiel'));
        $this->assertNull($verzeichnis->get('Plugins\\Auth\\Ldap'));
        $this->assertNull($verzeichnis->get(null));
    }

    /**
     * Ein leeres Verzeichnis ist der Vorgabezustand: Solange nichts eingetragen ist, gibt es
     * keinen Weg an der Passwortprüfung vorbei.
     */
    public function testEinLeeresVerzeichnisLaesstNiemandenDurch(): void
    {
        $this->assertSame(array(), (new LoginProviderRegistry())->names());
        $this->assertNull((new LoginProviderRegistry())->get('ldap'));
    }

    public function testGrossUndKleinschreibungEntscheidetNicht(): void
    {
        $verzeichnis = new LoginProviderRegistry();
        $verzeichnis->register('LDAP', $this->provider());

        $this->assertNotNull($verzeichnis->get('ldap'));
        $this->assertNotNull($verzeichnis->get(' Ldap '));
    }

    /**
     * Ein zweiter Eintrag unter demselben Namen wird abgewiesen.
     *
     * Stillschweigend zu überschreiben hiesse, dass die Reihenfolge zweier Zeilen in
     * `custom/app.php` darüber entscheidet, gegen welches Fremdsystem geprüft wird. Das fällt
     * niemandem auf, bis es das Falsche tut.
     */
    public function testEinZweiterEintragUnterDemselbenNamenWirdAbgewiesen(): void
    {
        $verzeichnis = new LoginProviderRegistry();
        $verzeichnis->register('ldap', $this->provider());

        $this->expectException(\LogicException::class);
        $verzeichnis->register('ldap', $this->provider());
    }

    /**
     * Der Eintrag ist faul: Ein Provider baut womöglich eine Verbindung zu einem Fremdsystem
     * auf, und das darf nicht bei jedem Request passieren.
     */
    public function testEinEintragWirdErstBeimAbrufenGebaut(): void
    {
        $gebaut = 0;

        $verzeichnis = new LoginProviderRegistry();
        $verzeichnis->register('ldap', function () use (&$gebaut) {
            $gebaut++;

            return $this->provider();
        });

        $this->assertSame(0, $gebaut, 'Noch nicht gebaut');

        $verzeichnis->get('ldap');
        $verzeichnis->get('ldap');

        $this->assertSame(1, $gebaut, 'Einmal gebaut, danach derselbe');
    }

    public function testEineClosureDieKeinenProviderLiefertWirdAbgewiesen(): void
    {
        $verzeichnis = new LoginProviderRegistry();
        $verzeichnis->register('kaputt', fn () => new \stdClass());

        $this->expectException(\LogicException::class);
        $verzeichnis->get('kaputt');
    }

    // ── Die ExternalIdentity ───────────────────────────────────────────────────────────────

    public function testEineFremdkennungOhneKennungGibtEsNicht(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ExternalIdentity('   ');
    }

    public function testEineFremdkennungTraegtWasDasFremdsystemSagt(): void
    {
        $kennung = new ExternalIdentity('mmustermann', array('Redaktion'), array('mail' => 'm@example.invalid'));

        $this->assertSame('mmustermann', $kennung->identifier);
        $this->assertSame(array('Redaktion'), $kennung->groups);
        $this->assertSame('m@example.invalid', $kennung->attributes['mail']);
    }
}
