<?php
namespace Tests\Unit\Security;

use Areanet\PIM\Classes\Security\Anmeldetreiber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\AccessToken\AccessTokenExtractorInterface;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Der Treiber, der Symfonys `access_token`-Authenticator ohne Firewall fährt (013-002-0001).
 *
 * Geprüft wird gegen Doppelgänger für Handler und Extractor. Das ist die halbe Absicht dieses
 * Tasks: Der Treiber muss stehen und messbar sein, **bevor** es einen echten Handler gibt —
 * der kommt erst mit `013-002-0003`.
 */
class AnmeldetreiberTest extends TestCase
{
    /** Ein Extractor, der immer denselben Wert liefert — oder keinen. */
    private function extractor(?string $token): AccessTokenExtractorInterface
    {
        return new class($token) implements AccessTokenExtractorInterface {
            public function __construct(private ?string $token) {}

            public function extractAccessToken(Request $request): ?string
            {
                return $this->token;
            }
        };
    }

    /** Ein Handler, der eine Kennung liefert — oder die übergebene Ausnahme wirft. */
    private function handler(?string $kennung, ?AuthenticationException $fehler = null): AccessTokenHandlerInterface
    {
        return new class($kennung, $fehler) implements AccessTokenHandlerInterface {
            public function __construct(private ?string $kennung, private ?AuthenticationException $fehler) {}

            public function getUserBadgeFrom(string $accessToken): UserBadge
            {
                if ($this->fehler !== null) {
                    throw $this->fehler;
                }

                return new UserBadge(
                    (string) $this->kennung,
                    fn (string $kennung) => new InMemoryUser($kennung, null)
                );
            }
        };
    }

    public function testEinGueltigesTokenLiefertDenBenutzer(): void
    {
        $treiber = new Anmeldetreiber($this->handler('admin'), $this->extractor('irgendein-token'));

        $benutzer = $treiber->benutzer(new Request());

        $this->assertNotNull($benutzer);
        $this->assertSame('admin', $benutzer->getUserIdentifier());
    }

    public function testOhneTokenGreiftDerTreiberNicht(): void
    {
        $treiber = new Anmeldetreiber($this->handler('admin'), $this->extractor(null));

        $this->assertNull($treiber->benutzer(new Request()));
    }

    /**
     * Der Grund für `supports() === false` statt `!supports()`.
     *
     * `AccessTokenAuthenticator::supports()` liefert **null**, wenn ein Token da ist — die
     * Kennzeichnung für „vielleicht, entscheide später". Nur `false` heisst „gar kein Token".
     * Ein `!supports()` behandelte beide Fälle gleich und wiese jeden Request ab; dieser Test
     * fällt genau dann um.
     */
    public function testEinVorhandenesTokenWirdNichtVorschnellAbgewiesen(): void
    {
        $treiber = new Anmeldetreiber($this->handler('redakteur'), $this->extractor('token'));

        $this->assertNotNull($treiber->benutzer(new Request()));
    }

    /**
     * Jeder Fehlschlag sieht gleich aus: `null`.
     *
     * Kein Token, unbekannte Kennung, gesperrter Benutzer, abgelaufenes oder manipuliertes
     * Token — der Aufrufer erfährt nur, dass es nicht gereicht hat. Wer hier nach Ursachen
     * unterscheidet, sagt ihm, welche Tokenart erwartet wird und welche Konten es gibt.
     */
    public function testJederFehlschlagSiehtGleichAus(): void
    {
        $faelle = array(
            'unbekannte Kennung' => new UserNotFoundException(),
            'abgelaufen'         => new CustomUserMessageAuthenticationException('abgelaufen'),
            'manipuliert'        => new CustomUserMessageAuthenticationException('Signatur falsch'),
        );

        foreach ($faelle as $name => $fehler) {
            $treiber = new Anmeldetreiber($this->handler(null, $fehler), $this->extractor('token'));

            $this->assertNull($treiber->benutzer(new Request()), $name.' muss wie alles andere scheitern');
        }
    }
}
