<?php
namespace Tests\Unit\Security;

use Areanet\PIM\Classes\Security\TokenSources;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Woher ein Token kommen darf (013-002-0002).
 *
 * Fünf Quellen, vier davon geerbt. RFC 6750 kennt nur die erste; die anderen vier sind das, was
 * `BaseControllerProvider::checkToken()` seit jeher liest. Ohne sie bricht jeder bestehende
 * Ionic-Client beim Update.
 *
 * Je Quelle ein Test — und einer für die Reihenfolge, denn die ist nicht die, die man vermutet.
 */
class TokenSourcesTest extends TestCase
{
    /** @param array<string,string> $kopfzeilen */
    private function request(array $kopfzeilen = array(), array $query = array(), array $rumpf = array()): Request
    {
        $server = array();
        foreach ($kopfzeilen as $name => $wert) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $wert;
        }

        return new Request($query, $rumpf, array(), array(), array(), $server);
    }

    private function lesen(Request $request): ?string
    {
        return TokenSources::chain()->extractAccessToken($request);
    }

    // ── Die fünf Quellen ───────────────────────────────────────────────────────────────

    public function testAuthorizationBearer(): void
    {
        $this->assertSame('abc123', $this->lesen($this->request(array('Authorization' => 'Bearer abc123'))));
    }

    public function testAppcmsTokenHeader(): void
    {
        $this->assertSame('abc123', $this->lesen($this->request(array('appcms-token' => 'abc123'))));
    }

    public function testXsrfTokenHeader(): void
    {
        $this->assertSame('abc123', $this->lesen($this->request(array('X-XSRF-TOKEN' => 'abc123'))));
    }

    public function testTokenImQueryString(): void
    {
        $this->assertSame('abc123', $this->lesen($this->request(array(), array('_token' => 'abc123'))));
    }

    public function testTokenImRumpf(): void
    {
        $this->assertSame('abc123', $this->lesen($this->request(array(), array(), array('_token' => 'abc123'))));
    }

    public function testOhneTokenLiefertDieKetteNull(): void
    {
        $this->assertNull($this->lesen($this->request()));
    }

    // ── Die Reihenfolge ────────────────────────────────────────────────────────────────

    /**
     * **Nicht die, die man vermutet.**
     *
     * In `checkToken()` steht `$request->headers->get(TOKEN_HEADER_KEY_ALT, $tokenParameter)`:
     * `X-XSRF-TOKEN` ist der Wert, `_token` nur dessen Vorgabe. Der Header schlägt den Parameter
     * also, statt nach ihm zu kommen. Wer das beim Nachbauen umdreht, ändert für jeden Client,
     * der beides mitschickt, still das Ergebnis.
     */
    public function testDieReihenfolgeIstDieAusCheckToken(): void
    {
        $alleFuenf = $this->request(
            array('Authorization' => 'Bearer bearer', 'appcms-token' => 'appcms', 'X-XSRF-TOKEN' => 'xsrf'),
            array('_token' => 'query'),
            array('_token' => 'rumpf')
        );
        $this->assertSame('bearer', $this->lesen($alleFuenf), 'RFC 6750 zuerst');

        $ohneBearer = $this->request(
            array('appcms-token' => 'appcms', 'X-XSRF-TOKEN' => 'xsrf'),
            array('_token' => 'query'),
            array('_token' => 'rumpf')
        );
        $this->assertSame('appcms', $this->lesen($ohneBearer));

        $nurXsrfUndParameter = $this->request(
            array('X-XSRF-TOKEN' => 'xsrf'),
            array('_token' => 'query'),
            array('_token' => 'rumpf')
        );
        $this->assertSame('xsrf', $this->lesen($nurXsrfUndParameter), 'Der Header schlaegt den Parameter');

        $nurParameter = $this->request(array(), array('_token' => 'query'), array('_token' => 'rumpf'));
        $this->assertSame('query', $this->lesen($nurParameter), 'Query vor Rumpf');
    }

    // ── Keine stille Verengung ─────────────────────────────────────────────────────────

    /**
     * Ein Altquellen-Header wird gelesen, wie er kommt.
     *
     * Symfonys `HeaderAccessTokenExtractor` prüft den Wert gegen `[a-zA-Z0-9\-_+~\/.]+=*`. Für
     * ein Bearer-Token nach RFC 6750 ist das richtig; für die Altquellen wäre es eine stille
     * Verengung. `checkToken()` nimmt den Header, wie er kommt, und ein Projekt darf sich seinen
     * API-Token über `addToken` frei wählen — ein Token mit einem Zeichen ausserhalb dieser
     * Menge würde ab sofort nicht mehr erkannt, und niemand bekäme zu sehen, warum.
     */
    public function testEinAltquellenTokenDarfZeichenAusserhalbVonRfc6750Tragen(): void
    {
        $wert = 'projekt:token mit leerzeichen!';

        $this->assertSame($wert, $this->lesen($this->request(array('appcms-token' => $wert))));
        $this->assertSame($wert, $this->lesen($this->request(array('X-XSRF-TOKEN' => $wert))));
    }

    /**
     * Ein leerer Header zählt als „nicht da" — wie `empty()` in `checkToken()`.
     */
    public function testEinLeererWertZaehltAlsAbwesend(): void
    {
        $request = $this->request(array('appcms-token' => ''), array(), array('_token' => 'rumpf'));

        $this->assertSame('rumpf', $this->lesen($request));
    }
}
