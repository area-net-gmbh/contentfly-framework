<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * The CORS headers an installation actually sends (000-000-0039).
 *
 * Until this task `Origin: https://evil.example` came back as
 * `Access-Control-Allow-Origin: https://evil.example` with `Access-Control-Allow-Credentials: true` —
 * every foreign page was allowed to read responses with a signed-in user's credentials. The tests
 * check the headers, not a status code: the status was 200 either way.
 *
 * The test server allows exactly one origin, `CONTENTFLY_TEST_ALLOWED_ORIGIN`
 * (tools/ci/prepare-test-environment.sh passes it on as `APP_ALLOW_ORIGIN`).
 */
class CorsApiTest extends IntegrationTestCase
{
    public function testAForeignOriginIsNotAllowed(): void
    {
        $headers = $this->headers('GET', '/api/config', 'https://evil.example');

        $this->assertArrayNotHasKey('access-control-allow-origin', $headers, 'Until 000-000-0039 the foreign origin came back');
        $this->assertArrayNotHasKey('access-control-allow-credentials', $headers);
        $this->assertStringContainsString('Origin', $headers['vary'] ?? '', 'The answer depends on the Origin header');
    }

    public function testTheConfiguredOriginGetsExactlyItselfWithCredentials(): void
    {
        $origin = $this->allowedOrigin();

        $headers = $this->headers('GET', '/api/config', $origin);

        $this->assertSame($origin, $headers['access-control-allow-origin'] ?? null);
        $this->assertSame('true', $headers['access-control-allow-credentials'] ?? null);
    }

    public function testWithoutAnOriginHeaderNoneIsNamed(): void
    {
        $headers = $this->headers('GET', '/api/config', null);

        $this->assertArrayNotHasKey('access-control-allow-origin', $headers);
    }

    public function testThePreflightFollowsTheSameRule(): void
    {
        $foreign = $this->headers('OPTIONS', '/api/list', 'https://evil.example');
        $allowed = $this->headers('OPTIONS', '/api/list', $this->allowedOrigin());

        $this->assertArrayNotHasKey('access-control-allow-origin', $foreign);
        $this->assertSame($this->allowedOrigin(), $allowed['access-control-allow-origin'] ?? null);
    }

    private function allowedOrigin(): string
    {
        $origin = getenv('CONTENTFLY_TEST_ALLOWED_ORIGIN');

        if (!is_string($origin) || $origin === '') {
            $this->markTestSkipped('CONTENTFLY_TEST_ALLOWED_ORIGIN is not set — see tests/README.md.');
        }

        return $origin;
    }

    /** @return array<string,string> response headers, names in lower case */
    private function headers(string $method, string $path, ?string $origin): array
    {
        $ch = curl_init(self::$baseUrl . $path);
        curl_setopt_array($ch, array(
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_NOBODY         => $method === 'OPTIONS',
            CURLOPT_HTTPHEADER     => $origin !== null ? array('Origin: ' . $origin) : array(),
        ));
        $raw        = (string) curl_exec($ch);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $headers = array();
        foreach (explode("\r\n", substr($raw, 0, $headerSize)) as $line) {
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $name = strtolower(trim($name));
                $headers[$name] = isset($headers[$name]) ? $headers[$name] . ', ' . trim($value) : trim($value);
            }
        }

        return $headers;
    }
}
