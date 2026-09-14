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
 * The OIDC provider via the userinfo endpoint (013-005-0003).
 *
 * **Limitation, explicitly:** tested against `MockHttpClient`, not against a real identity
 * provider. That is the consequence of the chosen variant — local verification against a JWKS
 * would have been cryptographically testable for real here, because one can generate the key
 * oneself. The userinfo path needs a counterpart.
 *
 * What does **not** remain untested as a result: that the login ends in the same token issuing
 * as the local login. That is the path behind `LoginProvider`, and `AnmeldeproviderApiTest`
 * measures it end-to-end — a provider is interchangeable there, because the contract has exactly
 * one method.
 */
class OidcProviderTest extends TestCase
{
    /** Remembers the request the provider made. */
    private array $sentRequest = array();

    private function settings(array $overrides = array()): array
    {
        return $overrides + array(
            'endpoint'      => 'https://idp.example.invalid/userinfo',
            'identifier_claim' => 'sub',
            'groups_claim' => 'groups',
        );
    }

    private function provider(MockResponse|\Throwable $response, array $settings = array()): OidcProvider
    {
        $this->sentRequest = array();
        $sentRequest = &$this->sentRequest;

        $client = new MockHttpClient(
            static function (string $method, string $url, array $options) use (&$sentRequest, $response) {
                $sentRequest = array('method' => $method, 'url' => $url, 'options' => $options);

                if ($response instanceof \Throwable) {
                    throw $response;
                }

                return $response;
            }
        );

        return new OidcProvider($client, $this->settings($settings));
    }

    private function response(array $data, int $status = 200): MockResponse
    {
        return new MockResponse(json_encode($data), array(
            'http_code' => $status,
            'response_headers' => array('content-type' => 'application/json'),
        ));
    }

    private function request(string $field = 'accessToken', ?string $token = 'an-oidc-token'): Request
    {
        return new Request(array(), $token === null ? array() : array($field => $token));
    }

    // ── The successful path ────────────────────────────────────────────────────────────

    public function testAValidResponseReturnsIdentifierAndGroups(): void
    {
        $provider = $this->provider($this->response(array(
            'sub'    => '8c1e-4f',
            'groups' => array('editorial', 'everyone'),
        )));

        $external = $provider->authenticate($this->request());

        $this->assertInstanceOf(ExternalIdentity::class, $external);
        $this->assertSame('8c1e-4f', $external->identifier);
        $this->assertSame(array('editorial', 'everyone'), $external->groups);
    }

    public function testTheTokenIsSentAsBearerToTheEndpoint(): void
    {
        $this->provider($this->response(array('sub' => 'x')))->authenticate($this->request());

        $this->assertSame('GET', $this->sentRequest['method']);
        $this->assertSame('https://idp.example.invalid/userinfo', $this->sentRequest['url']);
        $this->assertContains('Authorization: Bearer an-oidc-token', $this->sentRequest['options']['headers']);
    }

    /**
     * `pass` is read as well: a client that already sends a login form to `/auth/login` can use
     * the same field as for a password.
     */
    public function testTheTokenMayAlsoBeInPass(): void
    {
        $provider = $this->provider($this->response(array('sub' => 'x')));

        $this->assertNotNull($provider->authenticate($this->request('pass')));
    }

    /**
     * The claim name is configurable — it is not standardised, and every provider names it
     * differently.
     */
    public function testTheGroupsClaimIsConfigurable(): void
    {
        $provider = $this->provider(
            $this->response(array('sub' => 'x', 'roles' => array('admin'))),
            array('groups_claim' => 'roles')
        );

        $this->assertSame(array('admin'), $provider->authenticate($this->request())->groups);
    }

    public function testAResponseWithoutGroupsIsFine(): void
    {
        $provider = $this->provider($this->response(array('sub' => 'x')));

        $this->assertSame(array(), $provider->authenticate($this->request())->groups);
    }

    // ── Rejections, all alike ──────────────────────────────────────────────────────────

    public function testARejectedTokenIsRejected(): void
    {
        $provider = $this->provider($this->response(array('error' => 'invalid_token'), 401));

        $this->assertNull($provider->authenticate($this->request()));
    }

    /**
     * A 200 response without the expected identifier is not a login.
     *
     * The case is not theoretical: a misconfigured claim name looks exactly like this.
     */
    public function testAResponseWithoutAnIdentifierIsRejected(): void
    {
        $provider = $this->provider($this->response(array('email' => 'm@example.invalid')));

        $this->assertNull($provider->authenticate($this->request()));
    }

    public function testAnEmptyIdentifierIsRejected(): void
    {
        $provider = $this->provider($this->response(array('sub' => '   ')));

        $this->assertNull($provider->authenticate($this->request()));
    }

    public function testAnUnreachableProviderIsRejected(): void
    {
        $provider = $this->provider(new TransportException('Timeout'));

        $this->assertNull($provider->authenticate($this->request()));
    }

    public function testWithoutATokenNothingIsAsked(): void
    {
        $provider = $this->provider($this->response(array('sub' => 'x')));

        $this->assertNull($provider->authenticate($this->request('accessToken', null)));
        $this->assertSame(array(), $this->sentRequest, 'No call to the endpoint');
    }

    /**
     * Without a configured endpoint nothing is asked at all — and nobody gets in.
     *
     * The same line as with the JWT secret (`013-002-0003`) and the provider template
     * (`013-004-0004`): a path that is open without configuration would be worse than none.
     */
    public function testWithoutAnEndpointNothingIsAsked(): void
    {
        $provider = $this->provider($this->response(array('sub' => 'x')), array('endpoint' => ''));

        $this->assertNull($provider->authenticate($this->request()));
        $this->assertSame(array(), $this->sentRequest);
    }
}
