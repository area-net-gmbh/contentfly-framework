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

        /*
         * 011-001-0003. The body is the envelope now. Two of the three assertions that stood here
         * kept their meaning and only changed address; the third is gone on purpose:
         *
         * `status` is NOT in the body any more. It was put in there by 000-000-0006 so that a
         * client got a code at all where the shape was in disarray — but the code belongs in the
         * HTTP response, and a body that repeats it invites the two to disagree. The status code
         * is asserted one line above, where it comes from.
         */
        $entry = $this->assertErrorEnvelope($body);

        $this->assertNull($entry['code'],
            'A TypeError has no Messages key — null says: nothing stable to branch on here');
        // 000-000-0073: without debug an unforeseen fault no longer shows its text or class. The
        // TypeError's message named the method, its signature and a server path.
        $this->assertSame('contentfly_general_internal_error', $entry['detail'], 'The response is JSON — previously HTML');
        $this->assertSame('InternalServerError', $entry['type']);
        $this->assertNull($entry['context']);
    }

    /**
     * ONE SHAPE ACROSS THE STATUS CODES — the acceptance test of `011-001-0003`.
     *
     * The counterpart to `EnvelopeApiTest`, which walks the success cases: five faults of very
     * different origin — a missing token, a missing right, an unknown id, a rejected value, a PHP
     * error — and one reader for all of them. They used to differ in more than their status code:
     * a `ContentflyException` brought `message_value` along, a `ContentflyI18NException`
     * `message_entity` and `message_lang`, a PHP error neither. Which keys arrived depended on the
     * exception class, so a client had to know the framework's exceptions to read an error.
     *
     * What this test does NOT check is the status codes themselves — the endpoints' own tests do
     * that, and `000-000-0006` is where they were put right. Here they are only the proof that the
     * shape does not depend on them.
     */
    public function testEveryErrorIsReadableWithTheSameCode(): void
    {
        $token = $this->token();

        $editor = $this->createTestUser(array('PIM\\Tag' => array()))[0];

        $calls = array(
            '401 without token'       => array(array('entity' => 'PIM\\Tag'), null, '/api/list', 401),
            '403 without right'       => array(array('entity' => 'PIM\\Tag'), $editor, '/api/list', 403),
            '404 unknown entity'      => array(array('entity' => 'PIM\\DoesNotExist'), $token, '/api/list', 404),
            '404 unknown id'          => array(array('entity' => 'PIM\\Tag', 'id' => 'nope'), $token, '/api/single', 404),
            '500 php error'           => array(array('entity' => 'PIM\\Tag', 'id' => 'x', 'data' => 'notAnArray'), $token, '/api/update', 500),
        );

        foreach ($calls as $name => [$payload, $with, $path, $expected]) {
            [$status, $body] = $this->postJson($path, $payload, $with);

            $this->assertSame($expected, $status, $name);

            // The same three lines for every one of them — that is the whole claim.
            $this->assertSame(array('data', 'errors', 'meta'), array_keys($body), $name);
            $this->assertNull($body['data'], $name);
            $this->assertSame(array('code', 'detail', 'type', 'context'), array_keys($body['errors'][0]), $name);
        }
    }

    /** The status code left the body with 011-001-0003 — it stands in the HTTP response alone. */
    public function testTheStatusCodeIsNoLongerRepeatedInTheBody(): void
    {
        [, $body] = $this->postJson('/api/list', array('entity' => 'PIM\\DoesNotExist'), $this->token());

        $this->assertArrayNotHasKey('status', $body);
        $this->assertArrayNotHasKey('status', $body['errors'][0]);
        $this->assertArrayNotHasKey('status', $body['meta']);
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

    public function testAnUnforeseenFaultDoesNotShowItsInternals(): void
    {
        /*
         * 000-000-0073. An unknown column in /api/query makes DBAL throw — an exception that is
         * neither a Contentfly nor an HTTP exception. Its message carried the MySQL error and a
         * slice of the query into `detail`, with APP_DEBUG off (the test server runs without it).
         * Checked against the raw body, not the decoded one: nothing of it may appear anywhere.
         */
        [$status, $raw] = $this->postRaw('/api/query', array('select' => 'no_such_column', 'from' => 'PIM\\Tag'), $this->token());

        $this->assertSame(500, $status);

        $body  = json_decode($raw, true);
        $entry = $this->assertErrorEnvelope($body);

        $this->assertNull($entry['code'], 'Nothing stable to branch on — as the envelope says for every unforeseen fault');
        $this->assertSame('contentfly_general_internal_error', $entry['detail']);
        $this->assertSame('InternalServerError', $entry['type']);

        foreach (array('SQLSTATE', 'no_such_column', 'pim_tag', 'Doctrine', 'DBAL') as $internal) {
            $this->assertStringNotContainsString($internal, $raw, "The response names $internal");
        }
    }

    /** @return array{0:int,1:string} status and the undecoded body */
    private function postRaw(string $path, array $data, string $token): array
    {
        $ch = curl_init(self::$baseUrl.$path);
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => array('Content-Type: application/json', 'appcms-token: '.$token),
        ));
        $raw    = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        return array($status, $raw);
    }
}
