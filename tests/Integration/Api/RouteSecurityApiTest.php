<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * Characterization tests for route protection and the template's middleware.
 *
 * The switch is called **`Route::$isSecure`** and lives in
 * `Classes/Controller/Provider/Base/CustomControllerProvider.php` — not `_secured` in the
 * `RouteManager`, as the text of epic `008` originally assumed; that name does not occur in
 * the tree (established in `012-006-0003`).
 *
 * Epic `009` relies on these semantics during the kernel swap: a project builds its public
 * endpoints with it. That is why **both** directions are recorded — that a secured route
 * rejects, and that an unsecured one responds.
 */
class RouteSecurityApiTest extends IntegrationTestCase
{
    // ── isSecure = true ────────────────────────────────────────────────────────────────

    public function testSecuredRouteRejectsWithoutToken(): void
    {
        [$status, $body] = $this->postJson('/api/list', array('entity' => 'PIM\\Tag'));

        $this->assertSame(401, $status, 'Since the stack switch (006-002-0003) the intended code — Symfony 4.4 fixes 000-000-0006 here');
        $this->assertArrayNotHasKey('data', $body, 'What matters: no data flows');
    }

    /**
     * **GET, not POST** — clarified with `000-000-0020`.
     *
     * Until then this assertion called `/api/schema` via POST. The route, however, has been
     * defined as `$controllers->get('/schema', ...)` since the initial import; POST was
     * **never** part of the contract. Every other caller in the repo — 19 places in 11 test
     * files — uses GET as well.
     *
     * That it passed for so long was due to the weakness of the old assertion: it checked
     * `assertNotSame(500, ...)`, and a "Method Not Allowed" is 405. Only since the stack
     * switch does the same case come out as 500 (`MethodNotAllowedHttpException`, with its
     * status code overwritten — that is `000-000-0006`), and that is when it was noticed.
     *
     * The assertion now targets the actual result instead of negating a single error code.
     * The purpose of this class requires that: epic `009` relies on the `isSecure` semantics,
     * and "anything except 500" does not prove them.
     */
    public function testSecuredRouteRespondsWithToken(): void
    {
        [$status] = $this->get('/api/schema', $this->token());

        $this->assertSame(200, $status, 'With a token the check passes');
    }

    public function testEmptyListComesAs200(): void
    {
        // Inverted with 000-000-0014. The test used to record that listAction() responds to an
        // empty result with HTTP 404 {"message":"Not found"} — an eighth response shape that had
        // nothing to do with any of the other seven. For a client, "no hits" and "route does not
        // exist" were thus indistinguishable.
        //
        // PIM\\Nav is empty after a fresh installation — no deletion needed, which would
        // touch the data of other tests.
        [$status, $body] = $this->postJson('/api/list', array('entity' => 'PIM\\Nav'), $this->token());

        $this->assertSame(200, $status);
        $this->assertSame(array(), $this->assertEnvelope($body, array('totalItems')),
            'An empty list, not an error message');
        $this->assertSame(0, $body['meta']['totalItems']); // 011-001-0002: totalItems is meta
    }

    public function testUnknownEntityRemainsA404WithReason(): void
    {
        // The counter-check to the test above: the case the 404 was meant for is still
        // reported — and distinguishably so, with a reason in the body.
        [$status, $body] = $this->postJson('/api/list', array('entity' => 'PIM\\DoesNotExist'), $this->token());

        $this->assertSame(404, $status);
        $this->assertSame('contentfly_general_unknown_entity', $body['message']);
    }

    // ── isSecure = false ───────────────────────────────────────────────────────────────

    public function testUnsecuredRouteRespondsWithoutToken(): void
    {
        // The other direction, and the actual contract: `custom/app.php` binds
        //
        //     $controllerProvider->mount('api/v1/example/', …)->post('/bootstrap', false, …)
        //
        // The second argument is isSecure. Set to false, the route is reachable without a
        // token — this is how a project builds its public endpoints. If this capability
        // disappears during the kernel swap, nobody notices until a project breaks.
        [$status, $body] = $this->postJson('/api/v1/example/bootstrap', array());

        $this->assertSame(200, $status, 'Reachable without a token because isSecure=false');

        // And a finding on the side: the template responds in its OWN format — success,
        // status, i18n, data, errors, meta, timestamp — not in the framework's envelope.
        // A project is therefore not bound to its shape.
        $this->assertSame(
            array('success', 'status', 'i18n', 'data', 'errors', 'meta', 'timestamp'),
            array_keys($body),
            'The template brings its own response envelope'
        );
        $this->assertTrue($body['success']);
    }

    public function testUnsecuredRouteAlsoRespondsWithToken(): void
    {
        [$status] = $this->postJson('/api/v1/example/bootstrap', array(), $this->token());

        $this->assertSame(200, $status, 'isSecure=false means "token not required", not "token forbidden"');
    }

    // ── /api/config: the only GET route without a token requirement ────────────────────

    public function testApiConfigIsReachableWithoutToken(): void
    {
        // The only route in ApiControllerProvider that is bound without ->before($checkAuth).
        // Whatever it reveals therefore belongs to the public part of the API.
        [$status, $raw] = $this->get('/api/config');

        $this->assertSame(200, $status);

        $config = json_decode($raw, true);

        // INVERTED WITH 000-000-0010, not deleted.
        //
        // Until then this test recorded that the public endpoint still advertises
        // customLogo — a property of the UI that epic 012 removed.
        // The comment read: "Recorded, not cleaned up — that would be a task of its own."
        // This is that task.
        //
        // The frontend key was dropped entirely, not emptied: a key that no longer carries
        // anything invites putting something back into it. Noted as a
        // breaking change in an_project/docs/breaking-changes.md.
        // 011-001-0002 ends the second half of the sentence that stood here: "An envelope of its
        // own, the seventh — without data and without ts". /api/config answers in the one envelope
        // like everything else; what it has to say is one flag.
        $this->assertSame(array('devmode' => false), $this->assertEnvelope($config));

        $this->assertArrayNotHasKey('frontend', $config['data'],
            'The public endpoint no longer advertises anything from the deleted UI');
    }

    // ── The template's middleware ──────────────────────────────────────────────────────

    public function testAfterHookFromCustomAppSetsTheReferrerPolicyHeader(): void
    {
        // custom/app.php registers an after hook that sets this header on every response.
        // It is the simplest proof that the template's middleware takes effect at all — and
        // thus that RouteManager and hook order work together.
        [$status, , $headers] = $this->get('/api/config');

        $this->assertSame(200, $status);
        $this->assertSame('strict-origin-when-cross-origin', $this->header($headers, 'Referrer-Policy'));
    }

    public function testAfterHookAlsoAppliesOnASecuredRoute(): void
    {
        [, , $headers] = $this->get('/api/schema', $this->token());

        $this->assertSame('strict-origin-when-cross-origin', $this->header($headers, 'Referrer-Policy'),
            'The middleware runs regardless of whether the route is secured');
    }

    // ── I18nPermission via the API ─────────────────────────────────────────────────────

    public function testLanguagePermissionCheckCannotBeTriggeredViaTheApi(): void
    {
        // Api.php:122 checks I18nPermission::isWritable() on delete, and
        // Api.php:248/465 check isOnlyReadable() on write. All three need an
        // i18n entity and configured languages — the template has neither the one nor the
        // other (`APP_LANGUAGES` is empty, there are only the abstract BaseI18n classes).
        //
        // The logic behind it is covered by a unit test instead:
        // tests/Unit/Entity/GroupLanguagePermissionTest.php. If the precondition changes,
        // this test fails and demands the integration proof.
        [$status, $raw] = $this->get('/api/config');
        $this->assertSame(200, $status);

        $config = json_decode($raw, true);

        $this->assertArrayNotHasKey('languages', $config,
            'Without configured languages /api/config reports none at all — so there are no '
            .'language permissions to check');
    }
}
