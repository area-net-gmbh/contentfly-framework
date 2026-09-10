<?php
namespace Tests\Unit\Security;

use Areanet\PIM\Classes\Config;
use Areanet\PIM\Classes\Config\Factory;
use Areanet\PIM\Classes\Security\Zugangstoken;
use Areanet\PIM\Entity\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\Framework\TestCase;

/**
 * Der Claim-Satz und die Ausstellung (013-003-0001).
 *
 * Bis zu diesem Task konnte der `Tokenhandler` JWT prüfen, aber niemand stellte welche aus — es
 * gab also auch keinen Satz, gegen den man prüfen konnte. Diese Tests halten fest, was ein
 * ausgestelltes Token trägt, und vor allem, **was nicht**.
 */
class ZugangstokenTest extends TestCase
{
    private const GEHEIMNIS = 'test-geheimnis-mit-mindestens-32-byte-laenge';

    protected function setUp(): void
    {
        $config = new Config();
        $config->SECURITY_JWT_SECRET = self::GEHEIMNIS;
        $config->SECURITY_JWT_TTL    = 900;

        Factory::getInstance()->setConfig($config);
    }

    protected function tearDown(): void
    {
        Factory::getInstance()->setConfig(new Config());
    }

    private function benutzer(string $alias = 'admin', bool $admin = false): User
    {
        $benutzer = new User();
        $benutzer->setAlias($alias);
        $benutzer->setIsAdmin($admin);

        return $benutzer;
    }

    private function claims(string $token): array
    {
        return (array) JWT::decode($token, new Key(self::GEHEIMNIS, Zugangstoken::VERFAHREN));
    }

    // ── Der Claim-Satz ─────────────────────────────────────────────────────────────────

    public function testEinTokenTraegtGenauDieFuenfFestgelegtenClaims(): void
    {
        $namen = array_keys($this->claims(Zugangstoken::ausstellen($this->benutzer())['token']));
        sort($namen);

        $erwartet = Zugangstoken::CLAIMS;
        sort($erwartet);

        $this->assertSame($erwartet, $namen, 'Genau diese fuenf — ein sechster faellt hier auf');
    }

    /**
     * **Der wichtigste Test dieser Datei.**
     *
     * Rollen, Gruppen und Berechtigungen können sich ändern, während der Token gilt. Stünden sie
     * darin, wirkte eine Rechteänderung erst nach dessen Ablauf — der klassische Fehler beim
     * Umstieg auf zustandslose Tokens, und einer, der erst auffällt, wenn jemandem ein Recht
     * entzogen wird und es nicht wirkt.
     */
    public function testKeinRechtUndKeineRolleStehtImToken(): void
    {
        $admin = $this->benutzer('chef', true);

        $roh = Zugangstoken::ausstellen($admin)['token'];

        $this->assertStringNotContainsStringIgnoringCase('ROLE_', base64_decode(strtr(explode('.', $roh)[1], '-_', '+/')));

        foreach (array('roles', 'rolle', 'group', 'gruppe', 'permissions', 'isAdmin') as $verboten) {
            $this->assertArrayNotHasKey($verboten, $this->claims($roh), $verboten.' gehoert nicht ins Token');
        }
    }

    public function testDieKennungStehtInSub(): void
    {
        $claims = $this->claims(Zugangstoken::ausstellen($this->benutzer('redakteur'))['token']);

        $this->assertSame('redakteur', $claims['sub']);
    }

    public function testDerAusgeberStehtInIss(): void
    {
        $claims = $this->claims(Zugangstoken::ausstellen($this->benutzer())['token']);

        $this->assertSame(Zugangstoken::AUSGEBER, $claims['iss']);
    }

    /**
     * Die `jti` ist die Kennung dieses **einen** Tokens — die Handhabe für den Widerruf in
     * `013-003-0003`. Zwei Tokens desselben Benutzers tragen verschiedene.
     */
    public function testJedesTokenTraegtEineEigeneJti(): void
    {
        $benutzer = $this->benutzer();

        $eins = Zugangstoken::ausstellen($benutzer);
        $zwei = Zugangstoken::ausstellen($benutzer);

        $this->assertNotSame($eins['jti'], $zwei['jti']);
        $this->assertSame($eins['jti'], $this->claims($eins['token'])['jti']);
    }

    // ── Lebensdauer ────────────────────────────────────────────────────────────────────

    public function testDieLebensdauerKommtAusDerKonfiguration(): void
    {
        $config = new Config();
        $config->SECURITY_JWT_SECRET = self::GEHEIMNIS;
        $config->SECURITY_JWT_TTL    = 300;
        Factory::getInstance()->setConfig($config);

        $zugang = Zugangstoken::ausstellen($this->benutzer());

        $this->assertEqualsWithDelta(time() + 300, $zugang['exp'], 2);
    }

    /**
     * Ein unsinniger Wert fällt auf die Vorgabe zurück, statt ein Token ohne Ablauf zu bauen.
     */
    public function testEineUnsinnigeLebensdauerFaelltAufDieVorgabeZurueck(): void
    {
        $config = new Config();
        $config->SECURITY_JWT_SECRET = self::GEHEIMNIS;
        $config->SECURITY_JWT_TTL    = 0;
        Factory::getInstance()->setConfig($config);

        $this->assertSame(900, Zugangstoken::lebensdauer());
    }

    // ── Ohne Geheimnis ─────────────────────────────────────────────────────────────────

    public function testOhneGeheimnisIstNichtsEingerichtet(): void
    {
        Factory::getInstance()->setConfig(new Config());

        $this->assertFalse(Zugangstoken::eingerichtet());
    }

    /**
     * Geworfen, nicht mit einem Ersatz weitergemacht.
     *
     * Ein im Code hinterlegter Standardschlüssel wäre kein Schlüssel, und eine Anwendung, die
     * stillschweigend etwas anderes tut als das Verlangte, ist schlimmer als eine, die
     * stehenbleibt.
     */
    public function testOhneGeheimnisWirdNichtAusgestellt(): void
    {
        Factory::getInstance()->setConfig(new Config());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/SECURITY_JWT_SECRET/');

        Zugangstoken::ausstellen($this->benutzer());
    }
}
