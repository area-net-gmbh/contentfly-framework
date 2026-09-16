<?php
namespace Tests\Integration\Api;

use Areanet\PIM\Entity\Permission;
use Tests\Integration\IntegrationTestCase;

/**
 * Characterization tests for `/api/query` and `/api/translations`.
 *
 * `/api/query` is the **only reading endpoint with its own permission check**: anyone who is
 * not an admin needs a group with `apiQueryEnabled = 'enabled'`. An endpoint for free-form
 * queries whose gating silently disappears during the kernel swap would be an open
 * database — which is why all three cases are recorded here.
 *
 * `/api/translations` serves multilingual content. It is **not configured** in the template
 * (`APP_LANGUAGES` is empty, and there is no concrete `BaseI18n` entity), which is why all
 * that can be recorded here is how the endpoint reacts without i18n.
 */
class QueryApiTest extends IntegrationTestCase
{
    private string $tag = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tag = 'query-'.bin2hex(random_bytes(6));
        $this->pdo()->prepare(
            'INSERT INTO pim_tag (id, title, created, modified, views, isIntern)
             VALUES (:id, :title, NOW(), NOW(), 0, 0)'
        )->execute(array('id' => $this->tag, 'title' => 'Query-probe'));
        $this->deleteAfterTest('pim_tag', $this->tag);
    }

    /**
     * Logs in a non-admin whose group allows the query API or not.
     *
     * The permission row for PIM\Tag is needed because a non-admin would otherwise already
     * fail at the entity's read check — i.e. at a different gate than the one this test is
     * meant to isolate.
     */
    private function loginAsNonAdmin(string $apiQueryEnabled): string
    {
        [$token] = $this->createTestUser(
            array('PIM\\Tag' => array('readable' => Permission::ALL)),
            array('apiQueryEnabled' => $apiQueryEnabled)
        );

        return $token;
    }

    // ── /api/query ─────────────────────────────────────────────────────────────────────

    public function testQueryAsAdminIsAllowedAndEchoesTheParameters(): void
    {
        [$status, $body] = $this->postJson(
            '/api/query',
            array('select' => 'id', 'from' => 'PIM\\Tag'),
            $this->token()
        );

        $this->assertSame(200, $status);
        // 011-001-0002: query is still the only endpoint that carries the request parameters back —
        // but they are no longer a fourth shape beside the payload, they are one meta key.
        $this->assertIsArray($this->assertEnvelope($body, array('params')));
        $this->assertSame(array('select' => 'id', 'from' => 'PIM\\Tag'), $body['meta']['params']);
    }

    public function testQueryForNonAdminWithEnabledGroupIsAllowed(): void
    {
        $token = $this->loginAsNonAdmin('enabled');

        [$status] = $this->postJson('/api/query', array('select' => 'id', 'from' => 'PIM\\Tag'), $token);

        $this->assertSame(200, $status,
            'apiQueryEnabled = "enabled" opens the endpoint for the group — '
            .'together with a permission row for the queried entity');
    }

    public function testQueryForNonAdminWithoutEnablementIsForbidden(): void
    {
        // The most important case: if this gating disappears during the kernel swap, the
        // database is open to every logged-in user.
        $token = $this->loginAsNonAdmin('disabled');

        [$status, $body] = $this->postJson('/api/query', array('select' => 'id', 'from' => 'PIM\\Tag'), $token);

        $this->assertNotSame(200, $status, 'Without apiQueryEnabled the endpoint is locked');
        $this->assertArrayNotHasKey('data', $body, 'No data flows');
    }

    public function testQueryWithoutSelectIsRejected(): void
    {
        [$status] = $this->postJson('/api/query', array('from' => 'PIM\\Tag'), $this->token());

        $this->assertNotSame(200, $status, 'Api::getQuery() requires select and from');
    }

    public function testQueryWithoutFromIsRejected(): void
    {
        [$status] = $this->postJson('/api/query', array('select' => 'id'), $this->token());

        $this->assertNotSame(200, $status);
    }

    public function testQueryWithoutTokenReturnsNoData(): void
    {
        [$status, $body] = $this->postJson('/api/query', array('select' => 'id', 'from' => 'PIM\\Tag'));

        $this->assertSame(401, $status, 'Since the stack switch (006-002-0003) the intended code — Symfony 4.4 fixes 000-000-0006 here');
        $this->assertArrayNotHasKey('data', $body);
    }

    // ── /api/translations ──────────────────────────────────────────────────────────────

    public function testTranslationsForEntityWithoutI18nThrows(): void
    {
        // Current state. The template configures no languages (`APP_LANGUAGES` is empty)
        // and ships no concrete BaseI18n entity — only the abstract base classes.
        // The effect of i18n_universal is therefore not observable today; it belongs in a
        // test as soon as a project or the template switches on multilingual support.
        [$status] = $this->postJson('/api/translations', array('entity' => 'PIM\\Tag'), $this->token());

        $this->assertSame(500, $status,
            'Without configured multilingual support the endpoint ends in an error — see 000-000-0006 '
            .'for the question why that is a 500 and not a domain response');
    }

    public function testTranslationsWithoutTokenReturnsNoData(): void
    {
        [$status, $body] = $this->postJson('/api/translations', array('entity' => 'PIM\\Tag'));

        $this->assertSame(401, $status,
            'Since 006-002-0003 the intended code — Symfony 4.4 fixes 000-000-0006 here');
        $this->assertArrayNotHasKey('data', $body);
    }

    public function testAppLanguagesIsEmptyInTheTemplate(): void
    {
        // Records the precondition of the test above: if APP_LANGUAGES were set,
        // /api/translations would have to react differently — and this test fails.
        [$status, $raw] = $this->get('/api/config');

        $this->assertSame(200, $status, '/api/config is the only route without a token requirement');

        $config = json_decode($raw, true);
        $this->assertSame(array(), $config['data']['languages'] ?? array(),
            'The template configures no languages');
    }
}
