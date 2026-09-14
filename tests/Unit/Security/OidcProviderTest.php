<?php
namespace Tests\Unit\Security;

use Areanet\PIM\Classes\Security\ExternalIdentity;
use Areanet\PIM\Classes\Security\OidcProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Der OIDC-Provider über den Userinfo-Endpunkt (013-005-0003).
 *
 * **Einschränkung, ausdrücklich:** Geprüft wird gegen `MockHttpClient`, nicht gegen einen echten
 * Identity-Provider. Das ist die Konsequenz der gewählten Variante — die lokale Prüfung gegen
 * ein JWKS wäre hier kryptografisch echt prüfbar gewesen, weil man sich den Schlüssel selbst
 * erzeugen kann. Der Userinfo-Weg braucht ein Gegenüber.
 *
 * Was dadurch **nicht** ungeprüft bleibt: dass die Anmeldung in dieselbe Token-Ausstellung
 * mündet wie der lokale Login. Das ist der Weg hinter `LoginProvider`, und den misst
 * `AnmeldeproviderApiTest` end-to-end — ein Provider ist dort austauschbar, weil der Vertrag
 * genau eine Methode hat.
 */
class OidcProviderTest extends TestCase
{
    /** Merkt sich die Anfrage, die der Provider gestellt hat. */
    private array $anfrage = array();

    private function einstellungen(array $abweichend = array()): array
    {
        return $abweichend + array(
            'endpoint'      => 'https://idp.example.invalid/userinfo',
            'identifier_claim' => 'sub',
            'groups_claim' => 'groups',
        );
    }

    private function provider(MockResponse|\Throwable $antwort, array $einstellungen = array()): OidcProvider
    {
        $this->anfrage = array();
        $anfrage = &$this->anfrage;

        $client = new MockHttpClient(
            static function (string $methode, string $url, array $optionen) use (&$anfrage, $antwort) {
                $anfrage = array('methode' => $methode, 'url' => $url, 'optionen' => $optionen);

                if ($antwort instanceof \Throwable) {
                    throw $antwort;
                }

                return $antwort;
            }
        );

        return new OidcProvider($client, $this->einstellungen($einstellungen));
    }

    private function antwort(array $daten, int $status = 200): MockResponse
    {
        return new MockResponse(json_encode($daten), array(
            'http_code' => $status,
            'response_headers' => array('content-type' => 'application/json'),
        ));
    }

    private function request(string $feld = 'accessToken', ?string $token = 'ein-oidc-token'): Request
    {
        return new Request(array(), $token === null ? array() : array($feld => $token));
    }

    // ── Der gelungene Weg ──────────────────────────────────────────────────────────────

    public function testEineGueltigeAntwortLiefertKennungUndGruppen(): void
    {
        $provider = $this->provider($this->antwort(array(
            'sub'    => '8c1e-4f',
            'groups' => array('redaktion', 'alle'),
        )));

        $fremd = $provider->authenticate($this->request());

        $this->assertInstanceOf(ExternalIdentity::class, $fremd);
        $this->assertSame('8c1e-4f', $fremd->identifier);
        $this->assertSame(array('redaktion', 'alle'), $fremd->groups);
    }

    public function testDasTokenGehtAlsBearerAnDenEndpunkt(): void
    {
        $this->provider($this->antwort(array('sub' => 'x')))->authenticate($this->request());

        $this->assertSame('GET', $this->anfrage['methode']);
        $this->assertSame('https://idp.example.invalid/userinfo', $this->anfrage['url']);
        $this->assertContains('Authorization: Bearer ein-oidc-token', $this->anfrage['optionen']['headers']);
    }

    /**
     * `pass` wird zusätzlich gelesen: Ein Client, der schon eine Anmeldemaske gegen
     * `/auth/login` schickt, kann dasselbe Feld benutzen wie für ein Passwort.
     */
    public function testDasTokenDarfAuchInPassStehen(): void
    {
        $provider = $this->provider($this->antwort(array('sub' => 'x')));

        $this->assertNotNull($provider->authenticate($this->request('pass')));
    }

    /**
     * Der Claim-Name ist konfigurierbar — er ist nicht standardisiert, und jeder Provider nennt
     * ihn anders.
     */
    public function testDerGruppenClaimIstKonfigurierbar(): void
    {
        $provider = $this->provider(
            $this->antwort(array('sub' => 'x', 'roles' => array('admin'))),
            array('groups_claim' => 'roles')
        );

        $this->assertSame(array('admin'), $provider->authenticate($this->request())->groups);
    }

    public function testEineAntwortOhneGruppenIstInOrdnung(): void
    {
        $provider = $this->provider($this->antwort(array('sub' => 'x')));

        $this->assertSame(array(), $provider->authenticate($this->request())->groups);
    }

    // ── Abweisungen, alle gleich ───────────────────────────────────────────────────────

    public function testEinAbgelehntesTokenWirdAbgewiesen(): void
    {
        $provider = $this->provider($this->antwort(array('error' => 'invalid_token'), 401));

        $this->assertNull($provider->authenticate($this->request()));
    }

    /**
     * Eine 200er-Antwort ohne die erwartete Kennung ist keine Anmeldung.
     *
     * Der Fall ist nicht theoretisch: Ein falsch konfigurierter Claim-Name sieht genau so aus.
     */
    public function testEineAntwortOhneKennungWirdAbgewiesen(): void
    {
        $provider = $this->provider($this->antwort(array('email' => 'm@example.invalid')));

        $this->assertNull($provider->authenticate($this->request()));
    }

    public function testEineLeereKennungWirdAbgewiesen(): void
    {
        $provider = $this->provider($this->antwort(array('sub' => '   ')));

        $this->assertNull($provider->authenticate($this->request()));
    }

    public function testEinNichtErreichbarerProviderWirdAbgewiesen(): void
    {
        $provider = $this->provider(new TransportException('Zeitueberschreitung'));

        $this->assertNull($provider->authenticate($this->request()));
    }

    public function testOhneTokenWirdNichtGefragt(): void
    {
        $provider = $this->provider($this->antwort(array('sub' => 'x')));

        $this->assertNull($provider->authenticate($this->request('accessToken', null)));
        $this->assertSame(array(), $this->anfrage, 'Kein Aufruf am Endpunkt');
    }

    /**
     * Ohne konfigurierten Endpunkt wird gar nicht erst gefragt — und niemand kommt herein.
     *
     * Dieselbe Linie wie beim JWT-Geheimnis (`013-002-0003`) und bei der Provider-Vorlage
     * (`013-004-0004`): Ein Weg, der ohne Konfiguration offensteht, wäre schlimmer als keiner.
     */
    public function testOhneEndpunktWirdNichtGefragt(): void
    {
        $provider = $this->provider($this->antwort(array('sub' => 'x')), array('endpoint' => ''));

        $this->assertNull($provider->authenticate($this->request()));
        $this->assertSame(array(), $this->anfrage);
    }
}
