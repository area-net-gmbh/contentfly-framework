<?php
namespace Tests\Integration\Api;

use Areanet\PIM\Entity\Permission;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Integration\IntegrationTestCase;

/**
 * Characterization tests for `/api/query` and `/api/translations`.
 *
 * `/api/query` is **for admins only** since 000-000-0097. Until then a group with
 * `apiQueryEnabled = 'enabled'` reached it, and a read check narrowed the entities of the
 * schema — no boundary for a query whose parts go to the database as SQL. An admin reads
 * everything anyway. So the gate is the one thing to record here, for every non-admin and every
 * shape of request.
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
     * Logs in a non-admin whose group sets apiQueryEnabled or not.
     *
     * The permission row for PIM\Tag gives the user a read right on the queried entity, so a
     * refusal cannot come from a missing right — it comes from the gate.
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

    /** @return iterable<string, array{0:string, 1:array<string,mixed>}> */
    public static function nonAdminRequests(): iterable
    {
        foreach (array('enabled', 'disabled') as $apiQueryEnabled) {
            yield "apiQueryEnabled $apiQueryEnabled, simple"       => array($apiQueryEnabled, array('select' => 'id', 'from' => 'PIM\\Tag'));
            yield "apiQueryEnabled $apiQueryEnabled, list and join" => array($apiQueryEnabled, array(
                'select' => array('t.id', 'j.title'),
                'from'   => array('PIM\\Tag' => 't'),
                'join'   => array('t', 'PIM\\Tag', 'j', 'j.id = t.id'),
            ));
        }
    }

    #[DataProvider('nonAdminRequests')]
    public function testQueryIsForbiddenForEveryNonAdmin(string $apiQueryEnabled, array $request): void
    {
        // INVERTED WITH 000-000-0097. Until then apiQueryEnabled = "enabled" opened the endpoint
        // for the group. Now it is for admins only — with or without the flag, whatever the
        // request looks like, and although the user may read the queried entity.
        $token = $this->loginAsNonAdmin($apiQueryEnabled);

        [$status, $body] = $this->postJson('/api/query', $request, $token);

        $this->assertSame(403, $status, 'Only an admin may query');
        // 011-001-0003: `data` is present and null instead of missing — the stronger statement.
        $this->assertErrorEnvelope($body, 'contentfly_general_access_denied');
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
        $this->assertErrorEnvelope($body); // 011-001-0003: `data` is present and null
    }

    // ── The array syntax (000-000-0076) ──────────────────────────────────────────────
    //
    // A parameter given as a LIST is spread into the QueryBuilder call: `"select": ["a", "b"]`
    // becomes select('a', 'b'), `"where": ["x", "y"]` becomes andWhere('x', 'y'). For the join
    // methods the list is the four arguments of one join, or a list of such lists. It is the only
    // way to express a join — the object syntax passes two arguments, a join needs four.
    //
    // All of this runs as admin: since 000-000-0097 nobody else reaches the endpoint. The tests
    // of 0076 that narrowed `from` and joined entities by the caller's read right are gone with
    // that narrowing.
    //
    // Decided on 2026-09-25: the syntax stays. Removing it would take joins and multi-column
    // selects from every client, and the second is in the documentation's own example.

    public function testSelectAsAListReturnsEveryNamedColumn(): void
    {
        [$status, $body] = $this->postJson('/api/query', array(
            'select' => array('id', 'title'),
            'from'   => 'PIM\\Tag',
            'where'  => array('id = ?' => $this->tag),
        ), $this->token());

        $this->assertSame(200, $status, json_encode($body['errors'] ?? null));
        $this->assertSame(
            array(array('id' => $this->tag, 'title' => 'Query-probe')),
            $this->assertEnvelope($body, array('params'))
        );
    }

    public function testSeveralJoinsInOneRequest(): void
    {
        [$status, $body] = $this->postJson('/api/query', array(
            'select' => array('t.id', 'a.title AS first', 'b.title AS second'),
            'from'   => array('PIM\\Tag' => 't'),
            'join'   => array(
                array('t', 'PIM\\Tag', 'a', 'a.id = t.id'),
                array('t', 'pim_tag', 'b', 'b.id = t.id'),
            ),
            'where'  => array('t.id = ?' => $this->tag),
        ), $this->token());

        $this->assertSame(200, $status, json_encode($body['errors'] ?? null));
        $this->assertSame(
            array(array('id' => $this->tag, 'first' => 'Query-probe', 'second' => 'Query-probe')),
            $this->assertEnvelope($body, array('params')),
            'Both joins arrive — one named by entity, one by table'
        );
    }

    public function testASingleJoinCanBeAFlatListOfFour(): void
    {
        [$status, $body] = $this->postJson('/api/query', array(
            'select' => 'j.title',
            'from'   => array('PIM\\Tag' => 't'),
            'join'   => array('t', 'PIM\\Tag', 'j', 'j.id = t.id'),
            'where'  => array('t.id = ?' => $this->tag),
        ), $this->token());

        $this->assertSame(200, $status, json_encode($body['errors'] ?? null));
        $this->assertSame(array(array('title' => 'Query-probe')), $this->assertEnvelope($body, array('params')));
    }

    public function testAJoinWithoutFourPartsIsRejected(): void
    {
        [$status, $body] = $this->postJson('/api/query', array(
            'select' => 'id',
            'from'   => array('PIM\\Tag' => 't'),
            'join'   => array('t', 'PIM\\Tag', 'j'),
        ), $this->token());

        $this->assertNotSame(200, $status);
        $this->assertErrorEnvelope($body, 'contentfly_general_invalid_params');
    }

    public function testSeveralConditionsInOneWhereAreAllApplied(): void
    {
        $run    = bin2hex(random_bytes(6));
        $active = $this->probeTag("q76-$run-active", 0);
        $this->probeTag("q76-$run-intern", 1);

        [$status, $body] = $this->postJson('/api/query', array(
            'select' => 'id',
            'from'   => 'PIM\\Tag',
            'where'  => array("title LIKE 'q76-$run-%'", 'isIntern = 0'),
        ), $this->token());

        $this->assertSame(200, $status, json_encode($body['errors'] ?? null));
        $this->assertSame(array($active), array_column($this->assertEnvelope($body, array('params')), 'id'),
            'Both conditions hold — the intern tag matches the first one only');
    }

    public function testSeveralValuesBindToOneCondition(): void
    {
        // The object form with a list as value: every `?` of the key takes one value, in order.
        $run    = bin2hex(random_bytes(6));
        $first  = $this->probeTag("q76-$run-first", 0);
        $second = $this->probeTag("q76-$run-second", 0);
        $this->probeTag("q76-$run-third", 0);

        [$status, $body] = $this->postJson('/api/query', array(
            'select' => 'id',
            'from'   => 'PIM\\Tag',
            'where'  => array('title = ? OR title = ?' => array("q76-$run-first", "q76-$run-second")),
        ), $this->token());

        $this->assertSame(200, $status, json_encode($body['errors'] ?? null));
        $returned = array_column($this->assertEnvelope($body, array('params')), 'id');
        sort($returned);
        $expected = array($first, $second);
        sort($expected);

        $this->assertSame($expected, $returned);
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
        $this->assertErrorEnvelope($body); // 011-001-0003: `data` is present and null
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

    // ── Helpers ──────────────────────────────────────────────────────────────────────

    /** A tag titled $title, owned by nobody in particular. */
    private function probeTag(string $title, int $isIntern): string
    {
        $id = 'q76-'.bin2hex(random_bytes(6));

        $this->pdo()->prepare(
            'INSERT INTO pim_tag (id, title, created, modified, views, isIntern)
             VALUES (:id, :title, NOW(), NOW(), 0, :intern)'
        )->execute(array('id' => $id, 'title' => $title, 'intern' => $isIntern));
        $this->deleteAfterTest('pim_tag', $id);

        return $id;
    }
}
