<?php
namespace Tests\Unit\Security;

use Areanet\PIM\Classes\Security\TokenSources;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Where a token may come from (013-002-0002).
 *
 * Five sources, four of them inherited. RFC 6750 only knows the first; the other four are what
 * `BaseControllerProvider::checkToken()` has always read. Without them every existing Ionic client
 * breaks on update.
 *
 * One test per source — and one for the order, because it is not the one you would expect.
 */
class TokenSourcesTest extends TestCase
{
    /** @param array<string,string> $headers */
    private function request(array $headers = array(), array $query = array(), array $body = array()): Request
    {
        $server = array();
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return new Request($query, $body, array(), array(), array(), $server);
    }

    private function read(Request $request): ?string
    {
        return TokenSources::chain()->extractAccessToken($request);
    }

    // ── The five sources ───────────────────────────────────────────────────────────────

    public function testAuthorizationBearer(): void
    {
        $this->assertSame('abc123', $this->read($this->request(array('Authorization' => 'Bearer abc123'))));
    }

    public function testAppcmsTokenHeader(): void
    {
        $this->assertSame('abc123', $this->read($this->request(array('appcms-token' => 'abc123'))));
    }

    public function testXsrfTokenHeader(): void
    {
        $this->assertSame('abc123', $this->read($this->request(array('X-XSRF-TOKEN' => 'abc123'))));
    }

    public function testTokenInQueryString(): void
    {
        $this->assertSame('abc123', $this->read($this->request(array(), array('_token' => 'abc123'))));
    }

    public function testTokenInBody(): void
    {
        $this->assertSame('abc123', $this->read($this->request(array(), array(), array('_token' => 'abc123'))));
    }

    public function testWithoutTokenTheChainReturnsNull(): void
    {
        $this->assertNull($this->read($this->request()));
    }

    // ── The order ──────────────────────────────────────────────────────────────────────

    /**
     * **Not the one you would expect.**
     *
     * `checkToken()` contains `$request->headers->get(TOKEN_HEADER_KEY_ALT, $tokenParameter)`:
     * `X-XSRF-TOKEN` is the value, `_token` only its default. So the header beats the parameter
     * instead of coming after it. Whoever reverses this when rebuilding it silently changes the
     * result for every client that sends both.
     */
    public function testOrderIsTheOneFromCheckToken(): void
    {
        $allFive = $this->request(
            array('Authorization' => 'Bearer bearer', 'appcms-token' => 'appcms', 'X-XSRF-TOKEN' => 'xsrf'),
            array('_token' => 'query'),
            array('_token' => 'body')
        );
        $this->assertSame('bearer', $this->read($allFive), 'RFC 6750 first');

        $withoutBearer = $this->request(
            array('appcms-token' => 'appcms', 'X-XSRF-TOKEN' => 'xsrf'),
            array('_token' => 'query'),
            array('_token' => 'body')
        );
        $this->assertSame('appcms', $this->read($withoutBearer));

        $onlyXsrfAndParameter = $this->request(
            array('X-XSRF-TOKEN' => 'xsrf'),
            array('_token' => 'query'),
            array('_token' => 'body')
        );
        $this->assertSame('xsrf', $this->read($onlyXsrfAndParameter), 'The header beats the parameter');

        $onlyParameter = $this->request(array(), array('_token' => 'query'), array('_token' => 'body'));
        $this->assertSame('query', $this->read($onlyParameter), 'Query before body');
    }

    // ── No silent narrowing ────────────────────────────────────────────────────────────

    /**
     * A legacy-source header is read as it comes.
     *
     * Symfony's `HeaderAccessTokenExtractor` checks the value against `[a-zA-Z0-9\-_+~\/.]+=*`. For
     * a bearer token per RFC 6750 that is correct; for the legacy sources it would be a silent
     * narrowing. `checkToken()` takes the header as it comes, and a project may freely choose its
     * API token via `addToken` — a token with a character outside that set would suddenly no
     * longer be recognised, and nobody would get to see why.
     */
    public function testLegacySourceTokenMayCarryCharactersOutsideRfc6750(): void
    {
        $value = 'project:token with spaces!';

        $this->assertSame($value, $this->read($this->request(array('appcms-token' => $value))));
        $this->assertSame($value, $this->read($this->request(array('X-XSRF-TOKEN' => $value))));
    }

    /**
     * An empty header counts as "not present" — like `empty()` in `checkToken()`.
     */
    public function testEmptyValueCountsAsAbsent(): void
    {
        $request = $this->request(array('appcms-token' => ''), array(), array('_token' => 'body'));

        $this->assertSame('body', $this->read($request));
    }
}
