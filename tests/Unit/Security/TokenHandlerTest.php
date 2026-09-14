<?php
namespace Tests\Unit\Security;

use Areanet\PIM\Classes\Config;
use Areanet\PIM\Classes\Config\Factory;
use Areanet\PIM\Classes\Security\TokenHandler;
use Areanet\PIM\Classes\Security\JwtAccessToken;
use Areanet\PIM\Entity\Group;
use Areanet\PIM\Entity\RevokedToken;
use Areanet\PIM\Entity\Token;
use Areanet\PIM\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Der verzweigende TokenHandler (013-002-0003).
 *
 * Symfony erlaubt genau einen `token_handler` je Firewall und bringt keine Verkettung mit. Die
 * Verzweigung ist deshalb eigener Code — und damit etwas, das geprüft gehört.
 */
class TokenHandlerTest extends TestCase
{
    private const GEHEIMNIS = 'test-geheimnis-nur-fuer-diesen-lauf';

    /**
     * Mindestens 32 Byte — php-jwt 7 weist ein kuerzeres Geheimnis fuer HS256 ab, schon beim
     * Signieren („Provided key is too short"). Beim ersten Entwurf dieses Tests stand hier ein
     * 21-Byte-Wert, und die Bibliothek hat ihn nicht angenommen.
     */
    private const FREMDES_GEHEIMNIS = 'ein-anderes-geheimnis-mit-genug-laenge';

    protected function setUp(): void
    {
        $config = new Config();
        $config->SECURITY_JWT_SECRET     = self::GEHEIMNIS;
        $config->APP_TOKEN_TIMEOUT       = 1800;
        $config->APP_CHECK_TOKEN_TIMEOUT = true;

        Factory::getInstance()->setConfig($config);
    }

    /**
     * Die Konfiguration ist ein Singleton — was dieser Test setzt, bliebe sonst stehen.
     *
     * Zurückgestellt wird auf die Vorgabewerte und nicht auf „nichts": Die Factory kennt kein
     * Entfernen, und ein `default`-Eintrag, der auf `null` zeigt, wäre schlimmer als einer mit
     * den Standardwerten.
     */
    protected function tearDown(): void
    {
        Factory::getInstance()->setConfig(new Config());
    }

    // ── Doppelgänger ───────────────────────────────────────────────────────────────────

    /** Ein EntityManager, der eine Token-Zeile liefert. */
    private function em(?Token $zeile, ?int $flushes = null): EntityManagerInterface
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($zeile);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        if ($flushes !== null) {
            $em->expects($this->exactly($flushes))->method('flush');
        }

        return $em;
    }

    /**
     * Ein EntityManager, der jeden Zugriff auf `pim_token` mit einer Ausnahme beantwortet.
     *
     * So wird „der JWT-Zweig fasst `pim_token` nicht an" gemessen statt behauptet: Greift er
     * doch zu, fliegt hier eine Ausnahme, die der Handler nicht fängt.
     *
     * **Nachgezogen mit `013-003-0003`:** Die Sperrliste liegt in einer eigenen Tabelle, und der
     * JWT-Zweig liest sie bei jedem Request. Das ist der Preis für den Widerruf, und es ist ein
     * **Lesezugriff auf eine kleine Tabelle** gegen den Schreibzugriff auf `pim_token`, der der
     * Grund für den ganzen Umbau war. Der Doppelgänger unterscheidet deshalb nach Tabelle statt
     * pauschal zu werfen — sonst mässe der Test nicht mehr, was er zu messen behauptet.
     *
     * @param list<RevokedToken> $sperren
     */
    private function emDerWirft(array $sperren = array()): EntityManagerInterface
    {
        $sperrliste = $this->createMock(EntityRepository::class);
        $sperrliste->method('findOneBy')->willReturnCallback(
            static function (array $kriterien) use ($sperren) {
                foreach ($sperren as $sperre) {
                    if ($sperre->getJti() === ($kriterien['jti'] ?? null)) {
                        return $sperre;
                    }
                }

                return null;
            }
        );

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(
            function (string $klasse) use ($sperrliste) {
                if ($klasse === RevokedToken::class) {
                    return $sperrliste;
                }

                throw new \LogicException('Der JWT-Zweig darf pim_token nicht anfassen');
            }
        );
        $em->method('flush')->willThrowException(new \LogicException('Der JWT-Zweig darf nicht schreiben'));

        return $em;
    }

    private function benutzer(string $alias = 'admin', bool $aktiv = true): User
    {
        $benutzer = new User();
        $benutzer->setAlias($alias);
        $benutzer->setIsActive($aktiv);

        return $benutzer;
    }

    private function zeile(User $benutzer, ?\DateTime $modified = null, ?string $referrer = null): Token
    {
        $zeile = new Token();
        $zeile->setUser($benutzer);
        $zeile->setReferrer($referrer);
        $zeile->setModified($modified ?? new \DateTime());

        return $zeile;
    }

    private function jwt(array $claims): string
    {
        return JWT::encode(
            $claims + array(
                'iss' => JwtAccessToken::ISSUER,
                'exp' => time() + 600,
                'jti' => bin2hex(random_bytes(16)),
            ),
            self::GEHEIMNIS,
            'HS256',
            // Die Kennung ist seit 013-003-0004 Pflicht: `JWT::decode()` waehlt den Schluessel
            // danach, und ein Token ohne `kid` wird abgewiesen.
            JwtAccessToken::keyId()
        );
    }

    // ── Der opaque Zweig ───────────────────────────────────────────────────────────────

    public function testEinOpaquerTokenLiefertSeinenBenutzer(): void
    {
        $benutzer = $this->benutzer();
        $handler  = new TokenHandler($this->em($this->zeile($benutzer)));

        $badge = $handler->getUserBadgeFrom('irgendein-opaker-token');

        $this->assertSame('admin', $badge->getUserIdentifier());
        $this->assertSame($benutzer, $badge->getUser(), 'Der Benutzer liegt schon vor — keine zweite Abfrage');
    }

    public function testEinUnbekannterOpaquerTokenWirdAbgewiesen(): void
    {
        $handler = new TokenHandler($this->em(null));

        $this->expectException(AuthenticationException::class);
        $handler->getUserBadgeFrom('gibtesnicht');
    }

    public function testEinGesperrterBenutzerWirdAbgewiesen(): void
    {
        $handler = new TokenHandler($this->em($this->zeile($this->benutzer('gesperrt', false))));

        $this->expectException(AuthenticationException::class);
        $handler->getUserBadgeFrom('irgendein-opaker-token');
    }

    public function testEinAbgelaufenerTokenWirdAbgewiesen(): void
    {
        $alt     = new \DateTime('-1 day');
        $handler = new TokenHandler($this->em($this->zeile($this->benutzer(), $alt)));

        $this->expectException(AuthenticationException::class);
        $handler->getUserBadgeFrom('abgelaufen');
    }

    /**
     * Ein Token mit `referrer` ist ein API-Token und verfällt nicht — wie in `checkToken()`.
     */
    public function testEinReferrerTokenVerfaelltNicht(): void
    {
        $alt      = new \DateTime('-1 year');
        $handler  = new TokenHandler($this->em($this->zeile($this->benutzer(), $alt, 'https://example.invalid')));

        $this->assertSame('admin', $handler->getUserBadgeFrom('api-token')->getUserIdentifier());
    }

    /**
     * Der Timeout kommt aus der Gruppe, wenn der Benutzer eine hat — in Minuten, nicht Sekunden.
     */
    public function testDerTimeoutDerGruppeSchlaegtDenAusDerKonfiguration(): void
    {
        $gruppe = new Group();
        $gruppe->setTokenTimeout(1);

        $benutzer = $this->benutzer();
        $benutzer->setGroup($gruppe);

        $handler = new TokenHandler($this->em($this->zeile($benutzer, new \DateTime('-2 minutes'))));

        $this->expectException(AuthenticationException::class);
        $handler->getUserBadgeFrom('opaker-token');
    }

    /**
     * Der Sliding-Expiration-Write: Ein gültiger opaquer Token schreibt `modified` zurück.
     */
    public function testDerOpaqueZweigSchreibtModifiedZurueck(): void
    {
        $zeile   = $this->zeile($this->benutzer(), new \DateTime('-10 minutes'));
        $vorher  = $zeile->getModified()->getTimestamp();
        $handler = new TokenHandler($this->em($zeile, 1));

        $handler->getUserBadgeFrom('opaker-token');

        $this->assertGreaterThan($vorher, $zeile->getModified()->getTimestamp());
    }

    public function testDerAufgeloesteTokenBleibtAbrufbar(): void
    {
        $zeile   = $this->zeile($this->benutzer());
        $handler = new TokenHandler($this->em($zeile));

        $handler->getUserBadgeFrom('opaker-token');

        $this->assertSame($zeile, $handler->lastToken(), '$app[\'auth.token\'] braucht die Zeile');
    }

    // ── Der JWT-Zweig ──────────────────────────────────────────────────────────────────

    public function testEinJwtLiefertSeinenBenutzer(): void
    {
        $handler = new TokenHandler($this->emDerWirft());

        $badge = $handler->getUserBadgeFrom($this->jwt(array('sub' => 'admin')));

        $this->assertSame('admin', $badge->getUserIdentifier());
    }

    /**
     * **Der Nachweis, um den es geht:** Der JWT-Zweig fasst `pim_token` nicht an.
     *
     * Gemessen, nicht behauptet — der EntityManager wirft bei jedem Zugriff. Damit entfällt
     * auch der Sliding-Expiration-Write, den der opaque Zweig bei *jedem* Request macht.
     */
    public function testDerJwtZweigFasstDieTokentabelleNichtAn(): void
    {
        $handler = new TokenHandler($this->emDerWirft());

        $handler->getUserBadgeFrom($this->jwt(array('sub' => 'admin')));

        $this->assertNull($handler->lastToken(), 'Im JWT-Zweig gibt es keine Zeile');
    }

    /**
     * Das Badge kommt **ohne** eigenen Lader zurück — den Benutzer holt der `UserLoader`,
     * den der Authenticator kennt.
     */
    public function testDasJwtBadgeUeberlaesstDasLadenDemBenutzerlader(): void
    {
        $handler = new TokenHandler($this->emDerWirft());

        $badge = $handler->getUserBadgeFrom($this->jwt(array('sub' => 'admin')));

        $this->assertNull($badge->getUserLoader());
    }

    public function testEinAbgelaufenesJwtWirdAbgewiesen(): void
    {
        $abgelaufen = JWT::encode(array('sub' => 'admin', 'iss' => JwtAccessToken::ISSUER, 'exp' => time() - 10), self::GEHEIMNIS, 'HS256', JwtAccessToken::keyId());
        $handler    = new TokenHandler($this->emDerWirft());

        $this->expectException(AuthenticationException::class);
        $handler->getUserBadgeFrom($abgelaufen);
    }

    public function testEinManipuliertesJwtWirdAbgewiesen(): void
    {
        $echt        = $this->jwt(array('sub' => 'admin'));
        $manipuliert = substr($echt, 0, -3).'aaa';
        $handler     = new TokenHandler($this->emDerWirft());

        $this->expectException(AuthenticationException::class);
        $handler->getUserBadgeFrom($manipuliert);
    }

    public function testEinJwtMitFremdemGeheimnisWirdAbgewiesen(): void
    {
        $fremd   = JWT::encode(array('sub' => 'admin', 'iss' => JwtAccessToken::ISSUER, 'exp' => time() + 600), self::FREMDES_GEHEIMNIS, 'HS256', JwtAccessToken::keyId());
        $handler = new TokenHandler($this->emDerWirft());

        $this->expectException(AuthenticationException::class);
        $handler->getUserBadgeFrom($fremd);
    }

    public function testEinJwtOhneSubWirdAbgewiesen(): void
    {
        $handler = new TokenHandler($this->emDerWirft());

        $this->expectException(AuthenticationException::class);
        $handler->getUserBadgeFrom($this->jwt(array()));
    }

    /**
     * Ohne gesetztes Geheimnis wird abgewiesen, **nicht** übersprungen.
     *
     * Ein Zweig, der sich mangels Konfiguration selbst abschaltet, ist keine Prüfung.
     */
    public function testOhneGeheimnisWirdDerJwtZweigAbgewiesen(): void
    {
        $token = $this->jwt(array('sub' => 'admin'));

        $config = new Config();
        $config->SECURITY_JWT_SECRET = null;
        Factory::getInstance()->setConfig($config);

        $handler = new TokenHandler($this->emDerWirft());

        $this->expectException(AuthenticationException::class);
        $handler->getUserBadgeFrom($token);
    }

    /**
     * Ein zu kurzes Geheimnis wird abgewiesen, nicht durchgewunken.
     *
     * php-jwt 7 verlangt für HS256 mindestens 32 Byte und wirft sonst eine `DomainException` —
     * gefunden beim Schreiben dieser Tests. Die fängt der Handler mit allem anderen ab; ein
     * Betreiber mit einem zu kurzen Geheimnis bekommt also kein halb funktionierendes System,
     * sondern gar keines. Das ist die richtige Richtung: HS256 mit einem kurzen Geheimnis ist
     * ratbar.
     */
    public function testEinZuKurzesGeheimnisWirdAbgewiesen(): void
    {
        $token = $this->jwt(array('sub' => 'admin'));

        $config = new Config();
        $config->SECURITY_JWT_SECRET = 'zu-kurz';
        Factory::getInstance()->setConfig($config);

        $handler = new TokenHandler($this->emDerWirft());

        $this->expectException(AuthenticationException::class);
        $handler->getUserBadgeFrom($token);
    }

    /**
     * Ein fremder Ausgeber wird abgewiesen (013-003-0001).
     *
     * Die Bibliothek prüft Signatur und Ablauf, den `iss` nicht. Ohne die eigene Prüfung gälte
     * hier jedes Token, das mit demselben Geheimnis signiert wurde — auch eines, das eine ganz
     * andere Anwendung für einen ganz anderen Zweck ausgestellt hat.
     */
    public function testEinTokenMitFremdemAusgeberWirdAbgewiesen(): void
    {
        $fremd = JWT::encode(
            array('sub' => 'admin', 'iss' => 'eine-andere-anwendung', 'exp' => time() + 600),
            self::GEHEIMNIS,
            'HS256',
            JwtAccessToken::keyId()
        );
        $handler = new TokenHandler($this->emDerWirft());

        $this->expectException(AuthenticationException::class);
        $handler->getUserBadgeFrom($fremd);
    }

    public function testEinTokenOhneAusgeberWirdAbgewiesen(): void
    {
        $ohne = JWT::encode(array('sub' => 'admin', 'exp' => time() + 600), self::GEHEIMNIS, 'HS256', JwtAccessToken::keyId());
        $handler = new TokenHandler($this->emDerWirft());

        $this->expectException(AuthenticationException::class);
        $handler->getUserBadgeFrom($ohne);
    }

    /**
     * **Ein Refresh-Token ist kein JwtAccessToken (013-003-0001).**
     *
     * Es ist eine gewöhnliche Zeile in `pim_token`, und dieser Zweig nahm bis dahin jede Zeile
     * an. Ein Refresh-Token gilt länger als ein Access-JWT — das ist sein Zweck —, und ohne
     * diese Prüfung wäre es damit ein langlebiger Generalschlüssel für die ganze API.
     */
    public function testEinRefreshTokenOeffnetDieApiNicht(): void
    {
        $zeile = $this->zeile($this->benutzer());
        $zeile->setPurpose(Token::PURPOSE_REFRESH);

        $handler = new TokenHandler($this->em($zeile));

        $this->expectException(AuthenticationException::class);
        $handler->getUserBadgeFrom('ein-refresh-token');
    }

    // ── Die Sperrliste (013-003-0003) ─────────────────────────────────────────────────

    /**
     * Ein gesperrtes Token wird abgewiesen, **obwohl es noch gilt**.
     *
     * Das ist der ganze Zweck der Liste: Ein zustandsloses Token lässt sich sonst nicht
     * zurückrufen, solange sein `exp` in der Zukunft liegt.
     */
    public function testEinGesperrtesTokenWirdAbgewiesenObwohlEsNochGilt(): void
    {
        $handler = new TokenHandler($this->emDerWirft());
        $token   = $this->jwt(array('sub' => 'admin'));

        // Erst gilt es.
        $this->assertSame('admin', $handler->getUserBadgeFrom($token)->getUserIdentifier());

        $jti    = $handler->lastClaims()['jti'];
        $sperre = new RevokedToken();
        $sperre->setJti($jti);
        $sperre->setExpiresAt(new \DateTime('+10 minutes'));

        $gesperrt = new TokenHandler($this->emDerWirft(array($sperre)));

        $this->expectException(AuthenticationException::class);
        $gesperrt->getUserBadgeFrom($token);
    }

    public function testEineFremdeSperreTrifftDiesesTokenNicht(): void
    {
        $fremd = new RevokedToken();
        $fremd->setJti('eine-ganz-andere-jti');
        $fremd->setExpiresAt(new \DateTime('+10 minutes'));

        $handler = new TokenHandler($this->emDerWirft(array($fremd)));

        $this->assertSame('admin', $handler->getUserBadgeFrom($this->jwt(array('sub' => 'admin')))->getUserIdentifier());
    }

    /**
     * Ohne `jti` liesse sich ein Token nicht sperren — also wird es gar nicht erst angenommen.
     */
    public function testEinTokenOhneJtiWirdAbgewiesen(): void
    {
        $ohneJti = JWT::encode(
            array('sub' => 'admin', 'iss' => JwtAccessToken::ISSUER, 'exp' => time() + 600),
            self::GEHEIMNIS,
            'HS256',
            JwtAccessToken::keyId()
        );
        $handler = new TokenHandler($this->emDerWirft());

        $this->expectException(AuthenticationException::class);
        $handler->getUserBadgeFrom($ohneJti);
    }

    public function testDieClaimsBleibenFuerDasAbmeldenAbrufbar(): void
    {
        $handler = new TokenHandler($this->emDerWirft());
        $handler->getUserBadgeFrom($this->jwt(array('sub' => 'admin')));

        $claims = $handler->lastClaims();

        $this->assertIsArray($claims);
        $this->assertArrayHasKey('jti', $claims);
        $this->assertArrayHasKey('exp', $claims);
    }

    public function testNachEinemOpaquenTokenGibtEsKeineClaims(): void
    {
        $handler = new TokenHandler($this->em($this->zeile($this->benutzer())));
        $handler->getUserBadgeFrom('opaker-token');

        $this->assertNull($handler->lastClaims());
    }

    // ── Die Verzweigung selbst ─────────────────────────────────────────────────────────

    /**
     * Der JOSE-Header entscheidet mit, nicht nur die zwei Punkte.
     *
     * Ein opaquer Token, den ein Projekt über `addToken` selbst gewählt hat, darf Punkte
     * enthalten — `pim_token.token` nimmt jede Zeichenkette. Entschiede allein die Form, landete
     * er im JWT-Zweig und würde abgewiesen, obwohl er in der Datenbank steht.
     */
    public function testEinOpaquerTokenMitPunktenLandetImOpaquenZweig(): void
    {
        $benutzer = $this->benutzer();
        $handler  = new TokenHandler($this->em($this->zeile($benutzer)));

        $badge = $handler->getUserBadgeFrom('projekt.api.token');

        $this->assertSame('admin', $badge->getUserIdentifier());
        $this->assertNotNull($handler->lastToken(), 'Er wurde in der Tabelle gesucht');
    }

    // ── Ununterscheidbar scheitern ─────────────────────────────────────────────────────

    /**
     * **Beide Zweige scheitern mit derselben Ausnahme und derselben Meldung.**
     *
     * Verschiedene Meldungen verraten, welche Tokenart erwartet wird, und damit, welche ein
     * Angreifer bauen muss. Der Test hält die Antworten gegeneinander, statt sie einzeln gegen
     * einen erwarteten Text zu prüfen — so fällt er auch dann um, wenn jemand *beide* ändert,
     * aber nur eine davon.
     */
    public function testBeideZweigeScheiternUnunterscheidbar(): void
    {
        $ausOpaquem = null;
        try {
            (new TokenHandler($this->em(null)))->getUserBadgeFrom('gibtesnicht');
        } catch (AuthenticationException $e) {
            $ausOpaquem = $e;
        }

        $ausJwt = null;
        try {
            (new TokenHandler($this->emDerWirft()))->getUserBadgeFrom(
                JWT::encode(array('sub' => 'admin', 'iss' => JwtAccessToken::ISSUER, 'exp' => time() + 600), self::FREMDES_GEHEIMNIS, 'HS256', JwtAccessToken::keyId())
            );
        } catch (AuthenticationException $e) {
            $ausJwt = $e;
        }

        $this->assertNotNull($ausOpaquem);
        $this->assertNotNull($ausJwt);
        $this->assertSame($ausOpaquem::class, $ausJwt::class, 'dieselbe Ausnahme');
        $this->assertSame($ausOpaquem->getMessage(), $ausJwt->getMessage(), 'dieselbe Meldung');
        $this->assertSame($ausOpaquem->getMessageKey(), $ausJwt->getMessageKey(), 'derselbe Meldungsschluessel');
    }
}
