<?php
namespace Tests\Integration\Api;

use Areanet\PIM\Entity\Permission;
use PHPUnit\Framework\Attributes\DataProvider;
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
        // 011-001-0003: `data` is present and null instead of missing — the stronger statement.
        $this->assertErrorEnvelope($body);
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
    // way to express a join — the object syntax passes two arguments, a join needs four — and the
    // join branch carries its own permission check: every joined entity is narrowed by the read
    // right of the caller, like the one in `from`.
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

    /** @return iterable<string, array{0:int, 1:array<int,string>}> */
    public static function joinedLevels(): iterable
    {
        yield 'OWN reaches the own tag and the one listing the user'      => array(Permission::OWN, array('own', 'shared'));
        yield 'GROUP reaches own, listed and group-shared tags'            => array(Permission::GROUP, array('own', 'shared', 'group'));
        yield 'ALL reaches every tag'                                      => array(Permission::ALL, array('own', 'shared', 'group', 'foreign'));
    }

    #[DataProvider('joinedLevels')]
    public function testAJoinedEntityIsNarrowedByTheReadRight(int $level, array $reached): void
    {
        // The `from` entity is readable in full; only the joined one is narrowed. A cross join
        // onto this test's tags shows exactly which of them the level lets through.
        [$token, $userId, $groupId] = $this->createTestUser(
            array('Core\\Example' => array('readable' => Permission::ALL), 'PIM\\Tag' => array('readable' => $level)),
            array('apiQueryEnabled' => 'enabled')
        );

        $run     = bin2hex(random_bytes(6));
        $example = $this->example();
        $tags    = array(
            'own'     => $this->probeTag("q76-$run-own", 0, $userId),
            'shared'  => $this->probeTag("q76-$run-shared", 0, $this->adminId(), null, $userId),
            'group'   => $this->probeTag("q76-$run-group", 0, $this->adminId(), $groupId),
            'foreign' => $this->probeTag("q76-$run-foreign", 0, $this->adminId()),
        );

        [$status, $body] = $this->postJson('/api/query', array(
            'select' => array('t.id'),
            'from'   => array('Core\\Example' => 'e'),
            'join'   => array(array('e', 'PIM\\Tag', 't', "t.title LIKE 'q76-$run-%'")),
            'where'  => array('e.id = ?' => $example),
        ), $token);

        $this->assertSame(200, $status, json_encode($body['errors'] ?? null));
        $returned = array_column($this->assertEnvelope($body, array('params')), 'id');

        foreach ($tags as $record => $id) {
            $expected = in_array($record, $reached, true);

            $this->assertSame($expected, in_array($id, $returned, true),
                "The $record tag is ".($expected ? 'joined' : 'left out'));
        }
    }

    /** @return iterable<string, array{0:string, 1:int, 2:array<int,string>}> */
    public static function fromLevels(): iterable
    {
        foreach (array('as a name' => 'name', 'with an alias' => 'alias') as $label => $form) {
            yield "from $label, OWN"   => array($form, Permission::OWN, array('own', 'shared'));
            yield "from $label, GROUP" => array($form, Permission::GROUP, array('own', 'shared', 'group'));
        }
    }

    #[DataProvider('fromLevels')]
    public function testTheFromEntityIsNarrowedByTheReadRight(string $form, int $level, array $reached): void
    {
        // The same check as for a join, on the `from` side — once as `"from": "PIM\\Tag"`, once
        // as `"from": {"PIM\\Tag": "t"}`. The two forms run through different branches.
        [$token, $userId, $groupId] = $this->createTestUser(
            array('PIM\\Tag' => array('readable' => $level)),
            array('apiQueryEnabled' => 'enabled')
        );

        $run  = bin2hex(random_bytes(6));
        $tags = array(
            'own'     => $this->probeTag("q76-$run-own", 0, $userId),
            'shared'  => $this->probeTag("q76-$run-shared", 0, $this->adminId(), null, $userId),
            'group'   => $this->probeTag("q76-$run-group", 0, $this->adminId(), $groupId),
            'foreign' => $this->probeTag("q76-$run-foreign", 0, $this->adminId()),
        );

        $request = $form === 'alias'
            ? array('select' => 't.id', 'from' => array('PIM\\Tag' => 't'), 'where' => array('t.title LIKE ?' => "q76-$run-%"))
            : array('select' => 'id', 'from' => 'PIM\\Tag', 'where' => array('title LIKE ?' => "q76-$run-%"));

        [$status, $body] = $this->postJson('/api/query', $request, $token);

        $this->assertSame(200, $status, json_encode($body['errors'] ?? null));
        $returned = array_column($this->assertEnvelope($body, array('params')), 'id');

        foreach ($tags as $record => $id) {
            $expected = in_array($record, $reached, true);

            $this->assertSame($expected, in_array($id, $returned, true),
                "The $record tag is ".($expected ? 'returned' : 'left out'));
        }
    }

    public function testAFromEntityWithoutReadRightIsDenied(): void
    {
        [$token] = $this->createTestUser(
            array('Core\\Example' => array('readable' => Permission::ALL)),
            array('apiQueryEnabled' => 'enabled')
        );

        foreach (array('PIM\\Tag', 'pim_tag') as $from) {
            [$status, $body] = $this->postJson('/api/query', array('select' => 'id', 'from' => $from), $token);

            $this->assertSame(403, $status, "from $from — the entity by name or by table");
            $this->assertErrorEnvelope($body, 'contentfly_general_access_denied');
        }
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

    public function testAJoinedEntityWithoutReadRightIsDenied(): void
    {
        [$token] = $this->createTestUser(
            array('Core\\Example' => array('readable' => Permission::ALL)),
            array('apiQueryEnabled' => 'enabled')
        );

        [$status, $body] = $this->postJson('/api/query', array(
            'select' => array('t.id'),
            'from'   => array('Core\\Example' => 'e'),
            'join'   => array(array('e', 'PIM\\Tag', 't', '1 = 1')),
        ), $token);

        $this->assertSame(403, $status, 'The from entity is readable, the joined one is not');
        $this->assertErrorEnvelope($body, 'contentfly_general_access_denied');
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

    /** A tag titled $title; $userCreated, $groups and $users decide whose record it is. */
    private function probeTag(string $title, int $isIntern, ?string $userCreated = null, ?string $groups = null, ?string $users = null): string
    {
        $id = 'q76-'.bin2hex(random_bytes(6));

        $this->pdo()->prepare(
            // `groups` is a reserved word in MySQL 8.
            'INSERT INTO pim_tag (id, title, created, modified, views, isIntern, usercreated_id, `groups`, users)
             VALUES (:id, :title, NOW(), NOW(), 0, :intern, :uc, :grp, :usr)'
        )->execute(array(
            'id' => $id, 'title' => $title, 'intern' => $isIntern,
            'uc' => $userCreated, 'grp' => $groups, 'usr' => $users,
        ));
        $this->deleteAfterTest('pim_tag', $id);

        return $id;
    }

    /** One record of the template's Core\Example — the `from` side of the join tests. */
    private function example(): string
    {
        $id = 'q76-e-'.bin2hex(random_bytes(6));

        $this->pdo()->prepare(
            "INSERT INTO example_entity (id, state, name, slug, boolExample, created, modified, views, isIntern)
             VALUES (:id, 'active', :name, :slug, 0, NOW(), NOW(), 0, 0)"
        )->execute(array('id' => $id, 'name' => "Example $id", 'slug' => $id));
        $this->deleteAfterTest('example_entity', $id);

        return $id;
    }

    private function adminId(): string
    {
        return (string) $this->pdo()->query("SELECT id FROM pim_user WHERE alias = 'admin'")->fetchColumn();
    }
}
