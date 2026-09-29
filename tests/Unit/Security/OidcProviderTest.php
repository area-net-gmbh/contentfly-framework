<?php
namespace Tests\Unit\Security;

use Areanet\PIM\Classes\Config;
use Areanet\PIM\Classes\Config\Factory;
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
 * as the local login. That is the path behind `LoginProvider`, and `LoginProviderApiTest`
 * measures it end-to-end — a provider is interchangeable there, because the contract has exactly
 * one method.
 */
class OidcProviderTest extends TestCase
{
    /** Remembers the userinfo request the provider made. */
    private array $sentRequest = array();

    /** Remembers the introspection request (015-000-0015). */
    private array $sentIntrospection = array();

    protected function tearDown(): void
    {
        Factory::getInstance()->setConfig(new Config());
    }

    private function settings(array $overrides = array()): array
    {
        return $overrides + array(
            'endpoint'      => 'https://idp.example.invalid/userinfo',
            'identifier_claim' => 'sub',
            'groups_claim' => 'groups',
            'introspection_endpoint' => 'https://idp.example.invalid/introspect',
            'client_id' => 'contentfly',
            'client_secret' => 'a-secret',
        );
    }

    /**
     * The provider under test, with both endpoints answered.
     *
     * `$introspection` defaults to "active, and issued for this client" — so every test that is
     * not about the audience check reads exactly as it did before `015-000-0015`.
     */
    private function provider(
        MockResponse|\Throwable $response,
        array $settings = array(),
        MockResponse|\Throwable|null $introspection = null
    ): OidcProvider {
        $this->sentRequest = array();
        $this->sentIntrospection = array();
        $sentRequest = &$this->sentRequest;
        $sentIntrospection = &$this->sentIntrospection;

        $introspection ??= $this->response(array('active' => true, 'client_id' => 'contentfly'));

        $client = new MockHttpClient(
            static function (string $method, string $url, array $options)
            use (&$sentRequest, &$sentIntrospection, $response, $introspection) {
                $record = array('method' => $method, 'url' => $url, 'options' => $options);
                $answer = $method === 'POST' ? $introspection : $response;

                if ($method === 'POST') {
                    $sentIntrospection = $record;
                } else {
                    $sentRequest = $record;
                }

                if ($answer instanceof \Throwable) {
                    throw $answer;
                }

                return $answer;
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


    // ── The token has to have been issued for THIS client (015-000-0015) ───────────────

    /**
     * The finding, as a test.
     *
     * The userinfo endpoint answers 200 with a perfectly good user — it always would, the token
     * IS valid. What makes it not a login here is the client it was issued for.
     */
    public function testATokenIssuedForAnotherClientIsRejected(): void
    {
        $provider = $this->provider(
            $this->response(array('sub' => '8c1e-4f', 'groups' => array('admins'))),
            array(),
            $this->response(array('active' => true, 'client_id' => 'some-other-app'))
        );

        $this->assertNull($provider->authenticate($this->request()));
    }

    /**
     * And the userinfo endpoint is not even asked.
     *
     * The order is the point: a token for a foreign client must not reach the endpoint that
     * would hand out this user's claims.
     */
    public function testAForeignTokenNeverReachesTheUserinfoEndpoint(): void
    {
        $provider = $this->provider(
            $this->response(array('sub' => '8c1e-4f')),
            array(),
            $this->response(array('active' => true, 'client_id' => 'some-other-app'))
        );

        $provider->authenticate($this->request());

        $this->assertNotSame(array(), $this->sentIntrospection, 'Introspection was asked');
        $this->assertSame(array(), $this->sentRequest, 'Userinfo was not');
    }

    public function testATokenIssuedForThisClientLogsIn(): void
    {
        $provider = $this->provider(
            $this->response(array('sub' => '8c1e-4f')),
            array(),
            $this->response(array('active' => true, 'client_id' => 'contentfly'))
        );

        $this->assertSame('8c1e-4f', $provider->authenticate($this->request())->identifier);
    }

    /** `aud` as a list — the form most providers use. */
    public function testTheAudienceMayBeAList(): void
    {
        $provider = $this->provider(
            $this->response(array('sub' => 'x')),
            array(),
            $this->response(array('active' => true, 'aud' => array('another-app', 'contentfly')))
        );

        $this->assertNotNull($provider->authenticate($this->request()));
    }

    /** `aud` as a plain string — the other form in the wild. */
    public function testTheAudienceMayBeAString(): void
    {
        $provider = $this->provider(
            $this->response(array('sub' => 'x')),
            array(),
            $this->response(array('active' => true, 'aud' => 'contentfly'))
        );

        $this->assertNotNull($provider->authenticate($this->request()));
    }

    public function testAListedAudienceThatDoesNotContainThisClientIsRejected(): void
    {
        $provider = $this->provider(
            $this->response(array('sub' => 'x')),
            array(),
            $this->response(array('active' => true, 'aud' => array('another-app', 'a-third')))
        );

        $this->assertNull($provider->authenticate($this->request()));
    }

    /** A revoked or expired token: the provider says so, and that is enough. */
    public function testAnInactiveTokenIsRejected(): void
    {
        $provider = $this->provider(
            $this->response(array('sub' => 'x')),
            array(),
            $this->response(array('active' => false, 'client_id' => 'contentfly'))
        );

        $this->assertNull($provider->authenticate($this->request()));
    }

    /**
     * `active` has to be exactly `true`.
     *
     * A provider that answers `"true"` or `1` is not saying something different — but reading a
     * truthy value here would mean reading `"false"` as a yes too.
     */
    public function testATruthyActiveIsNotTrue(): void
    {
        $provider = $this->provider(
            $this->response(array('sub' => 'x')),
            array(),
            $this->response(array('active' => 'true', 'client_id' => 'contentfly'))
        );

        $this->assertNull($provider->authenticate($this->request()));
    }

    /**
     * An answer that names no client at all is a no.
     *
     * `client_id` and `aud` are both OPTIONAL in RFC 7662, so this is a legitimate answer — it
     * just does not answer the question that was asked, and "did not say" must not read as "yes".
     */
    public function testAnIntrospectionAnswerWithoutClientOrAudienceIsRejected(): void
    {
        $provider = $this->provider(
            $this->response(array('sub' => 'x')),
            array(),
            $this->response(array('active' => true))
        );

        $this->assertNull($provider->authenticate($this->request()));
    }

    public function testARejectedIntrospectionIsRejected(): void
    {
        $provider = $this->provider(
            $this->response(array('sub' => 'x')),
            array(),
            $this->response(array('error' => 'invalid_client'), 401)
        );

        $this->assertNull($provider->authenticate($this->request()));
    }

    public function testAnUnreachableIntrospectionEndpointIsRejected(): void
    {
        $provider = $this->provider(
            $this->response(array('sub' => 'x')),
            array(),
            new TransportException('Timeout')
        );

        $this->assertNull($provider->authenticate($this->request()));
        $this->assertSame(array(), $this->sentRequest, 'Userinfo was not asked');
    }

    public function testTheTokenIsPostedToTheIntrospectionEndpointWithClientCredentials(): void
    {
        $this->provider($this->response(array('sub' => 'x')))->authenticate($this->request());

        $this->assertSame('POST', $this->sentIntrospection['method']);
        $this->assertSame('https://idp.example.invalid/introspect', $this->sentIntrospection['url']);
        $this->assertStringContainsString('token=an-oidc-token', $this->sentIntrospection['options']['body']);
        $this->assertContains(
            'Authorization: Basic '.base64_encode('contentfly:a-secret'),
            $this->sentIntrospection['options']['headers']
        );
    }

    /** Without a secret the request goes out unauthenticated — and the provider answers 401. */
    public function testWithoutAClientSecretNoCredentialsAreSent(): void
    {
        $this->provider($this->response(array('sub' => 'x')), array('client_secret' => ''))
            ->authenticate($this->request());

        $authorization = array_filter(
            $this->sentIntrospection['options']['headers'],
            static fn (string $header): bool => str_starts_with($header, 'Authorization:')
        );

        $this->assertSame(array(), array_values($authorization));
    }

    /**
     * Not configured means nothing is asked — the same line as with the userinfo endpoint.
     *
     * `fromConfig()` refuses to build at all in this case; these two cover a provider that is
     * constructed directly, which the tests and a project with its own wiring do.
     */
    public function testWithoutAClientIdNothingIsAsked(): void
    {
        $provider = $this->provider($this->response(array('sub' => 'x')), array('client_id' => ''));

        $this->assertNull($provider->authenticate($this->request()));
        $this->assertSame(array(), $this->sentIntrospection);
        $this->assertSame(array(), $this->sentRequest);
    }

    public function testWithoutAnIntrospectionEndpointNothingIsAsked(): void
    {
        $provider = $this->provider(
            $this->response(array('sub' => 'x')),
            array('introspection_endpoint' => '')
        );

        $this->assertNull($provider->authenticate($this->request()));
        $this->assertSame(array(), $this->sentIntrospection);
        $this->assertSame(array(), $this->sentRequest);
    }

    // ── fromConfig fails closed ────────────────────────────────────────────────────────

    private function configure(array $values): void
    {
        $config = new Config();
        $config->SECURITY_OIDC_USERINFO_ENDPOINT = 'https://idp.example.invalid/userinfo';

        foreach ($values as $key => $value) {
            $config->$key = $value;
        }

        Factory::getInstance()->setConfig($config);
    }

    public function testFromConfigRefusesWithoutAClientId(): void
    {
        $this->configure(array(
            'SECURITY_OIDC_INTROSPECTION_ENDPOINT' => 'https://idp.example.invalid/introspect',
            'SECURITY_OIDC_CLIENT_ID' => null,
        ));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/SECURITY_OIDC_CLIENT_ID/');

        OidcProvider::fromConfig();
    }

    public function testFromConfigRefusesWithoutAnIntrospectionEndpoint(): void
    {
        $this->configure(array(
            'SECURITY_OIDC_INTROSPECTION_ENDPOINT' => null,
            'SECURITY_OIDC_CLIENT_ID' => 'contentfly',
        ));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/SECURITY_OIDC_INTROSPECTION_ENDPOINT/');

        OidcProvider::fromConfig();
    }

    public function testFromConfigBuildsWithBoth(): void
    {
        $this->configure(array(
            'SECURITY_OIDC_INTROSPECTION_ENDPOINT' => 'https://idp.example.invalid/introspect',
            'SECURITY_OIDC_CLIENT_ID' => 'contentfly',
        ));

        $this->assertInstanceOf(OidcProvider::class, OidcProvider::fromConfig());
    }
}
