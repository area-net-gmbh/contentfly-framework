<?php
namespace Tests\Unit\Security;

use Areanet\PIM\Classes\Security\Anbieterverzeichnis;
use Areanet\PIM\Classes\Security\Anmeldeprovider;
use Areanet\PIM\Classes\Security\Fremdkennung;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Die Allowlist der Anmeldeprovider (013-004-0001).
 *
 * Sie ersetzt die Auflösung eines Klassennamens aus dem Request. Der Unterschied ist nicht
 * kosmetisch: Aus „der Aufrufer sagt, was geladen wird" wird „der Aufrufer wählt aus dem, was
 * der Betreiber freigegeben hat".
 */
class AnbieterverzeichnisTest extends TestCase
{
    private function provider(?string $kennung = 'extern-1'): Anmeldeprovider
    {
        return new class($kennung) implements Anmeldeprovider {
            public function __construct(private ?string $kennung) {}

            public function pruefen(Request $request): ?Fremdkennung
            {
                return $this->kennung === null ? null : new Fremdkennung($this->kennung);
            }
        };
    }

    public function testEinEingetragenerNameLiefertSeinenProvider(): void
    {
        $verzeichnis = new Anbieterverzeichnis();
        $verzeichnis->eintragen('ldap', $this->provider());

        $this->assertTrue($verzeichnis->hat('ldap'));
        $this->assertInstanceOf(Anmeldeprovider::class, $verzeichnis->holen('ldap'));
    }

    /**
     * **Der Kern:** Ein Name, den niemand eingetragen hat, existiert nicht — und ein
     * Klassenname ist so ein Name.
     */
    public function testEinNichtEingetragenerNameLiefertNichts(): void
    {
        $verzeichnis = new Anbieterverzeichnis();
        $verzeichnis->eintragen('ldap', $this->provider());

        $this->assertNull($verzeichnis->holen('saml'));
        $this->assertNull($verzeichnis->holen('Custom\\Classes\\LoginManager\\Beispiel'));
        $this->assertNull($verzeichnis->holen('Plugins\\Auth\\Ldap'));
        $this->assertNull($verzeichnis->holen(null));
    }

    /**
     * Ein leeres Verzeichnis ist der Vorgabezustand: Solange nichts eingetragen ist, gibt es
     * keinen Weg an der Passwortprüfung vorbei.
     */
    public function testEinLeeresVerzeichnisLaesstNiemandenDurch(): void
    {
        $this->assertSame(array(), (new Anbieterverzeichnis())->namen());
        $this->assertNull((new Anbieterverzeichnis())->holen('ldap'));
    }

    public function testGrossUndKleinschreibungEntscheidetNicht(): void
    {
        $verzeichnis = new Anbieterverzeichnis();
        $verzeichnis->eintragen('LDAP', $this->provider());

        $this->assertNotNull($verzeichnis->holen('ldap'));
        $this->assertNotNull($verzeichnis->holen(' Ldap '));
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
        $verzeichnis = new Anbieterverzeichnis();
        $verzeichnis->eintragen('ldap', $this->provider());

        $this->expectException(\LogicException::class);
        $verzeichnis->eintragen('ldap', $this->provider());
    }

    /**
     * Der Eintrag ist faul: Ein Provider baut womöglich eine Verbindung zu einem Fremdsystem
     * auf, und das darf nicht bei jedem Request passieren.
     */
    public function testEinEintragWirdErstBeimAbrufenGebaut(): void
    {
        $gebaut = 0;

        $verzeichnis = new Anbieterverzeichnis();
        $verzeichnis->eintragen('ldap', function () use (&$gebaut) {
            $gebaut++;

            return $this->provider();
        });

        $this->assertSame(0, $gebaut, 'Noch nicht gebaut');

        $verzeichnis->holen('ldap');
        $verzeichnis->holen('ldap');

        $this->assertSame(1, $gebaut, 'Einmal gebaut, danach derselbe');
    }

    public function testEineClosureDieKeinenProviderLiefertWirdAbgewiesen(): void
    {
        $verzeichnis = new Anbieterverzeichnis();
        $verzeichnis->eintragen('kaputt', fn () => new \stdClass());

        $this->expectException(\LogicException::class);
        $verzeichnis->holen('kaputt');
    }

    // ── Die Fremdkennung ───────────────────────────────────────────────────────────────

    public function testEineFremdkennungOhneKennungGibtEsNicht(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Fremdkennung('   ');
    }

    public function testEineFremdkennungTraegtWasDasFremdsystemSagt(): void
    {
        $kennung = new Fremdkennung('mmustermann', array('Redaktion'), array('mail' => 'm@example.invalid'));

        $this->assertSame('mmustermann', $kennung->kennung);
        $this->assertSame(array('Redaktion'), $kennung->gruppen);
        $this->assertSame('m@example.invalid', $kennung->attribute['mail']);
    }
}
