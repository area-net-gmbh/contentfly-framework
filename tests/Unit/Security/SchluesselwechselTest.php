<?php
namespace Tests\Unit\Security;

use Areanet\PIM\Classes\Config;
use Areanet\PIM\Classes\Config\Factory;
use Areanet\PIM\Classes\Security\TokenHandler;
use Areanet\PIM\Classes\Security\JwtAccessToken;
use Areanet\PIM\Entity\RevokedToken;
use Areanet\PIM\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Ein Schlüsselwechsel ohne Zwangsabmeldung (013-003-0004).
 *
 * Ein Signaturgeheimnis, das sich nicht wechseln lässt, ohne alle Sitzungen zu beenden, wird
 * nicht gewechselt — und damit ist ein Leak dauerhaft. Dieser Test spielt den vollen Wechsel
 * durch, in der Reihenfolge, in der ein Betreiber ihn machen würde.
 */
class SchluesselwechselTest extends TestCase
{
    private const ALT = 'der-alte-schluessel-mit-genug-laenge-32';
    private const NEU = 'der-neue-schluessel-mit-genug-laenge-32';

    protected function tearDown(): void
    {
        Factory::getInstance()->setConfig(new Config());
    }

    /**
     * @param array<string, mixed> $felder
     */
    private function konfigurieren(array $felder): void
    {
        $config = new Config();
        $config->SECURITY_JWT_TTL = 900;

        foreach ($felder as $name => $wert) {
            $config->$name = $wert;
        }

        Factory::getInstance()->setConfig($config);
    }

    private function benutzer(): User
    {
        $benutzer = new User();
        $benutzer->setAlias('admin');

        return $benutzer;
    }

    /** Ein EntityManager, der nur die (leere) Sperrliste kennt. */
    private function em(): EntityManagerInterface
    {
        $sperrliste = $this->createMock(EntityRepository::class);
        $sperrliste->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(
            function (string $klasse) use ($sperrliste) {
                if ($klasse === RevokedToken::class) {
                    return $sperrliste;
                }

                throw new \LogicException('Hier wird nur der JWT-Zweig gemessen');
            }
        );

        return $em;
    }

    private function gilt(string $token): bool
    {
        try {
            (new TokenHandler($this->em()))->getUserBadgeFrom($token);

            return true;
        } catch (AuthenticationException) {
            return false;
        }
    }

    // ── Der volle Wechsel ──────────────────────────────────────────────────────────────

    /**
     * **Der Nachweis, um den es geht.** Drei Zustände, in der Reihenfolge eines echten Wechsels.
     */
    public function testEinWechselLaesstLaufendeSitzungenBestehen(): void
    {
        // 1. Vorher: ein Schlüssel, ein Token.
        $this->konfigurieren(array('SECURITY_JWT_SECRET' => self::ALT, 'SECURITY_JWT_KEY_ID' => 'k1'));
        $altesToken = JwtAccessToken::issue($this->benutzer())['token'];

        $this->assertTrue($this->gilt($altesToken));

        // 2. Der Wechsel: der bisherige Wert wandert nach PREVIOUS, ein neuer kommt.
        $this->konfigurieren(array(
            'SECURITY_JWT_SECRET'          => self::NEU,
            'SECURITY_JWT_KEY_ID'          => 'k2',
            'SECURITY_JWT_SECRET_PREVIOUS' => self::ALT,
            'SECURITY_JWT_KEY_ID_PREVIOUS' => 'k1',
        ));

        $this->assertTrue($this->gilt($altesToken), 'Niemand muss sich neu anmelden');

        $neuesToken = JwtAccessToken::issue($this->benutzer())['token'];
        $this->assertTrue($this->gilt($neuesToken));
        $this->assertSame('k2', $this->kopf($neuesToken)['kid'], 'Signiert wird mit dem neuen');

        // 3. Nach Ablauf des längsten Access-JWT: der alte Schlüssel kann weg.
        $this->konfigurieren(array('SECURITY_JWT_SECRET' => self::NEU, 'SECURITY_JWT_KEY_ID' => 'k2'));

        $this->assertFalse($this->gilt($altesToken), 'Jetzt faellt das alte Token');
        $this->assertTrue($this->gilt($neuesToken), 'Das neue nicht');
    }

    public function testDieKennungStehtImHeader(): void
    {
        $this->konfigurieren(array('SECURITY_JWT_SECRET' => self::NEU, 'SECURITY_JWT_KEY_ID' => 'schluessel-2026-09'));

        $kopf = $this->kopf(JwtAccessToken::issue($this->benutzer())['token']);

        $this->assertSame('schluessel-2026-09', $kopf['kid']);
    }

    /**
     * Ein Token mit unbekannter Kennung wird abgewiesen — ununterscheidbar wie alles andere.
     */
    public function testEineUnbekannteKennungWirdAbgewiesen(): void
    {
        $this->konfigurieren(array('SECURITY_JWT_SECRET' => self::ALT, 'SECURITY_JWT_KEY_ID' => 'k1'));
        $token = JwtAccessToken::issue($this->benutzer())['token'];

        // Derselbe Schlüssel, aber die Anwendung kennt die Kennung k1 nicht mehr.
        $this->konfigurieren(array('SECURITY_JWT_SECRET' => self::ALT, 'SECURITY_JWT_KEY_ID' => 'k9'));

        $this->assertFalse($this->gilt($token));
    }

    // ── Fehlkonfiguration schlägt laut durch ───────────────────────────────────────────

    /**
     * Zwei gleiche Kennungen werden abgewiesen.
     *
     * Sonst überschriebe die eine die andere im Array, und die Anwendung akzeptierte
     * stillschweigend nur einen der beiden Schlüssel — mitten in einem Wechsel der schlechteste
     * Zeitpunkt für eine stille Überraschung.
     */
    public function testZweiGleicheKennungenWerdenAbgewiesen(): void
    {
        $this->konfigurieren(array(
            'SECURITY_JWT_SECRET'          => self::NEU,
            'SECURITY_JWT_KEY_ID'          => 'k1',
            'SECURITY_JWT_SECRET_PREVIOUS' => self::ALT,
            'SECURITY_JWT_KEY_ID_PREVIOUS' => 'k1',
        ));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/must differ/');

        JwtAccessToken::verificationKeys();
    }

    public function testEinVorherigerSchluesselOhneKennungWirdAbgewiesen(): void
    {
        $this->konfigurieren(array(
            'SECURITY_JWT_SECRET'          => self::NEU,
            'SECURITY_JWT_KEY_ID'          => 'k2',
            'SECURITY_JWT_SECRET_PREVIOUS' => self::ALT,
        ));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/SECURITY_JWT_KEY_ID_PREVIOUS/');

        JwtAccessToken::verificationKeys();
    }

    /**
     * Eine Fehlkonfiguration darf **nicht** wie ein ungültiges Token aussehen.
     *
     * Fänge der Handler sie mit ab, antwortete die Anwendung auf jeden Request mit „ungültiger
     * Token", und der Betreiber suchte den Fehler bei seinen Clients.
     */
    public function testEineFehlkonfigurationSchlaegtDurchStattAbzuweisen(): void
    {
        $this->konfigurieren(array('SECURITY_JWT_SECRET' => self::ALT, 'SECURITY_JWT_KEY_ID' => 'k1'));
        $token = JwtAccessToken::issue($this->benutzer())['token'];

        $this->konfigurieren(array(
            'SECURITY_JWT_SECRET'          => self::NEU,
            'SECURITY_JWT_KEY_ID'          => 'k1',
            'SECURITY_JWT_SECRET_PREVIOUS' => self::ALT,
            'SECURITY_JWT_KEY_ID_PREVIOUS' => 'k1',
        ));

        $this->expectException(\RuntimeException::class);

        (new TokenHandler($this->em()))->getUserBadgeFrom($token);
    }

    /** @return array<string, mixed> */
    private function kopf(string $token): array
    {
        return json_decode(base64_decode(strtr(explode('.', $token)[0], '-_', '+/')), true);
    }
}
