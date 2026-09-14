<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * The error response itself — subject of `000-000-0006`.
 *
 * These tests do not record which status code a particular endpoint returns; the
 * characterization tests of the endpoints do that. They record that **the application
 * responds** and not the emergency fallback underneath it.
 *
 * The difference is not cosmetic. Until `000-000-0006` a PHP error — a TypeError, for
 * instance — made the error chain fail: the `$app->error()` handler died on `$app['request']`,
 * which was `null` at that point, and the closure in `bootstrap-web.php` that was meant to
 * rescue it died on the same `null`. What remained was Symfony's "Whoops" page with HTTP 500 —
 * HTML, without `message`, without `type`, without `status`. A client expecting JSON thus got
 * something it cannot read at every place where anything went wrong.
 */
class ErrorResponseApiTest extends IntegrationTestCase
{
    /**
     * A real PHP error, triggered on purpose: `data` as a string instead of an object.
     * `Api::doUpdate()` takes `array $data` typed — that is a TypeError, and a TypeError is not
     * an `Exception`.
     */
    public function testPhpErrorArrivesAsJsonNotAsHtmlPage(): void
    {
        [$status, $body, ] = $this->postJson(
            '/api/update',
            array('entity' => 'PIM\\Tag', 'id' => 'whatever', 'data' => 'notAnArray'),
            $this->token()
        );

        $this->assertSame(500, $status, 'A PHP error is a server error');
        $this->assertArrayHasKey('message', $body, 'The response is JSON — previously HTML');
        $this->assertArrayHasKey('type', $body);
        $this->assertSame(500, $body['status'],
            'The status code is under "status" — previously as a keyless entry under "0"');
    }

    /**
     * The counter-check to the body: the raw text must not contain the "Whoops" page either.
     * Otherwise an empty json_decode would be hard to tell apart from an empty body.
     */
    public function testWhoopsPageDoesNotAppear(): void
    {
        $ch = curl_init(self::$baseUrl.'/api/update');
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => json_encode(array('entity' => 'PIM\\Tag', 'id' => 'whatever', 'data' => 'notAnArray')),
            CURLOPT_HTTPHEADER     => array('Content-Type: application/json', 'appcms-token: '.$this->token()),
        ));
        $raw = (string) curl_exec($ch);
        curl_close($ch);

        $this->assertStringNotContainsString('Whoops', $raw);
        $this->assertStringNotContainsString('<!DOCTYPE html>', $raw);
        $this->assertNotNull(json_decode($raw, true), 'The body is valid JSON');
    }
}
