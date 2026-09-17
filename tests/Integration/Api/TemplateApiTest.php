<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * Characterization tests for the template `custom/` — the project this framework ships with.
 *
 * Epic `008` requires that "a project based on this framework works" is covered as well.
 * `custom/` **is** that project: the template every new and every migrating project follows
 * (epic `007`). If it breaks during the kernel rebuild, it breaks for everyone.
 *
 * The seam between framework and project is checked in four places: the example entity via
 * the generic endpoints, the example endpoint from `custom/app.php`, the middleware and the
 * console command that is explicitly *not* registered.
 *
 * **Two findings concern the template itself** and are recorded as `000-000-0017`: the field
 * `jsonExample` of the example entity does not exist for the API at all, and the only
 * remaining `@PIM` annotation of the template — `@PIM\Select` — validates nothing. A template
 * that demonstrates an unusable field and an ineffective annotation teaches the wrong thing.
 *
 * Complements `RouteSecurityApiTest`, which already covers the example endpoint from the
 * security side (unsecured route, own envelope, `Referrer-Policy`). This adds the content
 * side.
 */
class TemplateApiTest extends IntegrationTestCase
{
    private const ENTITY = 'Core\\Example';

    /** @var array<int,string> Ids whose log rows tearDown() removes. */
    private array $loggedIds = array();

    /**
     * Removes the log rows of the created records and then hands over to the base class.
     *
     * Custom cleanup, because `deleteAfterTest()` registers a row by its **own** id — but the
     * log entry is stored under `model_id`, and the decisive row is only created **after**
     * the registration: `/api/delete` writes a log entry of its own. Without this, `pim_log`
     * would grow by one row with every run; exactly the kind of leftover `000-000-0008` was
     * written against.
     */
    protected function tearDown(): void
    {
        foreach ($this->loggedIds as $id) {
            $this->pdo()->prepare('DELETE FROM pim_log WHERE model_id = :id')->execute(array('id' => $id));
        }

        $this->loggedIds = array();

        parent::tearDown();
    }

    /**
     * Creates an example record via `/api/insert` and registers the row and its log rows for
     * cleanup.
     *
     * @return array{0:int,1:array} status, body
     */
    private function createExample(array $data): array
    {
        [$status, $body] = $this->postJson(
            '/api/insert',
            array('entity' => self::ENTITY, 'data' => $data),
            $this->token()
        );

        // 011-001-0002: insert answers with the created object as payload; it carries the id.
        $created = $body['data'] ?? null;

        if (is_array($created) && isset($created['id'])) {
            $this->deleteAfterTest('example_entity', $created['id']);
            $this->loggedIds[] = $created['id'];
        }

        return array($status, $created);
    }

    private function schema(): array
    {
        [$status, $raw] = $this->get('/api/schema', $this->token());
        $this->assertSame(200, $status, 'Precondition: the schema can be retrieved');

        return json_decode($raw, true);
    }

    // ── A: The example entity in the schema ────────────────────────────────────────────

    public function testTheExampleEntityAppearsInTheSchemaUnderItsSubdirectory(): void
    {
        // At the same time the proof that the subdirectory structure custom/Entity/Core/
        // works — exactly the point where Api::getAll() failed until 000-000-0007.
        // The short name is made up of directory and class, not of the full namespace:
        // "Core\Example", not "Custom\Entity\Core\Example".
        $schema = $this->schema();

        $this->assertArrayHasKey(self::ENTITY, $schema['data']);
        // 011-001-0002: the rights annotate the schema and now sit in the meta.
        $this->assertArrayHasKey(self::ENTITY, $schema['meta']['permissions'],
            'Also in the permissions block — a project entity is not treated differently');

        $this->assertSame('example_entity', $schema['data'][self::ENTITY]['settings']['dbname']);
    }

    public function testTheSelectOptionsOfTheTemplateAreInTheSchema(): void
    {
        // @PIM\Select(options="provisioning,active,…") becomes a list of id/name pairs.
        // It is the only remaining @PIM annotation of the template after 012-005 — so what it
        // does deserves to be recorded precisely. What it does NOT do is covered further
        // below.
        $state = $this->schema()['data'][self::ENTITY]['properties']['state'];

        $this->assertSame('select', $state['type']);
        $this->assertSame(
            array('provisioning', 'active', 'trial_expired', 'suspended', 'deactivated'),
            array_column($state['options'], 'id')
        );
        $this->assertSame(array_column($state['options'], 'id'), array_column($state['options'], 'name'),
            'id and name are the same value — the options carry no label');
        $this->assertSame('active', $state['default'], 'The default value comes from the ORM column');
    }

    /**
     * **Inverted with `000-000-0017`, not deleted.**
     *
     * The test used to record that the JSON field of the template has no type and is therefore
     * missing from the schema: the field **silently** dropped out of the schema: no entry, no warning, no
     * hint — although column and entity field existed. The cause was the missing `JsonType`;
     * the `TypeManager` did not know Doctrine's `json`.
     */
    public function testTheJsonFieldOfTheTemplateIsInTheSchema(): void
    {
        $properties = $this->schema()['data'][self::ENTITY]['properties'];

        $this->assertArrayHasKey('jsonExample', $properties,
            'The field is in the schema since a JsonType exists');
        $this->assertSame('json', $properties['jsonExample']['type'],
            'and under its own type, not disguised as a string');
        $this->assertArrayHasKey('boolExample', $properties,
            'While the other fields of the same entity are present');

        $columns = $this->pdo()->query('SHOW COLUMNS FROM example_entity')->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertContains('jsonExample', $columns, 'and the column does exist');
    }

    // ── A: The example entity via the generic endpoints ────────────────────────────────

    public function testTheExampleEntityIsUsableViaTheGenericEndpoints(): void
    {
        // The actual contract of the template: a project entity needs no controller of its own
        // and no special treatment. Create, read, list, delete — all via the same endpoints
        // as the framework entities.
        [$status, $created] = $this->createExample(array(
            'name'        => 'Template sample',
            'slug'        => 'template-sample-'.bin2hex(random_bytes(4)),
            'state'       => 'suspended',
            'boolExample' => true,
        ));

        $this->assertSame(200, $status, 'create');
        $this->assertNotEmpty($created['id']);

        [$statusSingle, $single] = $this->postJson(
            '/api/single',
            array('entity' => self::ENTITY, 'id' => $created['id']),
            $this->token()
        );
        $this->assertSame(200, $statusSingle, 'read');
        $this->assertSame('Template sample', $single['data']['name']);
        $this->assertSame('suspended', $single['data']['state']);
        $this->assertTrue($single['data']['boolExample']);

        [$statusList, $list] = $this->postJson(
            '/api/list',
            array('entity' => self::ENTITY),
            $this->token()
        );
        $this->assertSame(200, $statusList, 'list');
        $this->assertContains($created['id'], array_column($list['data'], 'id'));

        [$statusDelete] = $this->postJson(
            '/api/delete',
            array('entity' => self::ENTITY, 'id' => $created['id']),
            $this->token()
        );
        $this->assertSame(200, $statusDelete, 'delete');

        $rest = $this->pdo()->prepare('SELECT COUNT(*) FROM example_entity WHERE id = :id');
        $rest->execute(array('id' => $created['id']));
        $this->assertSame('0', (string) $rest->fetchColumn(), 'The row is really gone');
    }

    public function testAProjectEntityIsLoggedLikeAnyOther(): void
    {
        // The log entry carries the short name with subdirectory and Log::INSERTED — the
        // project entity goes through the same logging as PIM\Tag.
        [, $created] = $this->createExample(array('name' => 'Log sample'));

        $log = $this->pdo()->prepare('SELECT mode, model_name FROM pim_log WHERE model_id = :id');
        $log->execute(array('id' => $created['id']));
        $entry = $log->fetch(\PDO::FETCH_ASSOC);

        $this->assertSame('INS', $entry['mode']);
        $this->assertSame(self::ENTITY, $entry['model_name']);
    }

    /**
     * **Inverted with `000-000-0017`, not deleted.**
     *
     * The test used to record that the JSON field can be neither written nor read:
     * `jsonExample` did not exist for the API. Writing failed with
     * `contentfly_general_unknown_property`, reading did not return the field. The cause was a
     * missing framework type — the `TypeManager` did not know Doctrine's `json`, and the field
     * **silently** dropped out of the schema.
     *
     * `JsonType` closes the gap. What is checked now is what the task required: that a
     * **nested** value comes back unchanged — not just that something arrives.
     */
    public function testTheJsonFieldAcceptsANestedValueAndReturnsIt(): void
    {
        $value = array(
            'title'    => 'Example',
            'features' => array('a', 'b'),
            'nested'   => array('number' => 42, 'flag' => true, 'empty' => null),
        );

        [$status, $created] = $this->createExample(array('name' => 'Json', 'jsonExample' => $value));
        $this->assertSame(200, $status, 'The field is now part of the schema');

        [, $single] = $this->postJson(
            '/api/single',
            array('entity' => self::ENTITY, 'id' => $created['id']),
            $this->token()
        );

        $this->assertArrayHasKey('jsonExample', $single['data'], 'and comes back when reading');

        // assertEquals, not assertSame: MySQL's native JSON type **normalizes the key order**
        // in objects. The value comes back complete and with the same types — only "nested"
        // comes before "features" afterwards. An assertSame would compare MySQL's storage
        // form here, not the guarantee of the API.
        $this->assertEquals($value, $single['data']['jsonExample'],
            'fully nested — Doctrine encodes and decodes, the type does not interfere');

        $this->assertSame('json', $this->schema()['data'][self::ENTITY]['properties']['jsonExample']['type'],
            'and the schema names the type');
    }

    /**
     * **Inverted with `000-000-0017`, not deleted.**
     *
     * The test used to record that the select annotation checked nothing it listed: the options
     * were in the schema, but nobody compared a write value against them — `"doesnotexist"`
     * was accepted and ended up in the column. The annotation merely supplied metadata, its
     * only consumer the deleted user interface.
     *
     * `@PIM\Select` now validates. This sets the case apart from `canExport` and
     * `getExtended` (`000-000-0012`), which show the same pattern: there enforcement is a
     * decision about permissions, here the allowed values are right next to it.
     */
    public function testTheSelectAnnotationRejectsAnUnknownValue(): void
    {
        $invalid = 'doesnotexist-'.bin2hex(random_bytes(3));

        [$status] = $this->postJson(
            '/api/insert',
            array('entity' => self::ENTITY, 'data' => array('name' => 'Select', 'state' => $invalid)),
            $this->token()
        );

        $this->assertSame(500, $status, 'ContentflyException: contentfly_general_invalid_params');

        $stray = $this->pdo()->prepare('SELECT COUNT(*) FROM example_entity WHERE state = :state');
        $stray->execute(array('state' => $invalid));
        $this->assertSame('0', (string) $stray->fetchColumn(),
            'and none of it reaches the column');
    }

    public function testAnAllowedSelectValueStillPasses(): void
    {
        // The opposite direction: the validation must not reject everything. Without this
        // test a select field that accepts nothing at all would be just as "green".
        [$status, $created] = $this->createExample(array('name' => 'Select-valid', 'state' => 'suspended'));

        $this->assertSame(200, $status);

        $stored = $this->pdo()->prepare('SELECT state FROM example_entity WHERE id = :id');
        $stored->execute(array('id' => $created['id']));

        $this->assertSame('suspended', $stored->fetchColumn());
    }

    // ── B: The example endpoint ────────────────────────────────────────────────────────

    public function testTheExampleEndpointReturnsTheTemplateContent(): void
    {
        // The security side is covered by RouteSecurityApiTest (unsecured route). Here the
        // content: what ApiResponseService::success() makes of the controller's arguments.
        [$status, $body] = $this->postJson('/api/v1/example/bootstrap', array());

        $this->assertSame(200, $status);

        /*
         * INVERTED WITH 011-003-0001. This used to assert the template's own shape — `success`
         * true, `status` 200 in the body, and an `i18n` block with four keys. Two of those were
         * exactly what `api-envelope.md` decided NOT to adopt: `success` and `status` repeat the
         * HTTP status code, and the translation key is a requirement of the project the template
         * came from.
         *
         * The example now answers in the one hull, and the payload is real instead of empty —
         * `startedAt` shows what ApiDateTimeFormatter is for: formatting DATA, not the envelope's
         * own timestamp, which is `meta.ts`.
         */
        $payload = $this->assertEnvelope($body);

        $this->assertSame(array('name', 'startedAt'), array_keys($payload));
        $this->assertSame('contentfly', $payload['name']);
    }

    public function testTheTemplateFormatsItsOwnDatesFinerThanTheEnvelope(): void
    {
        /*
         * RENAMED AND TURNED AROUND WITH 011-003-0001.
         *
         * It used to record a FINDING: the template answered with its own `timestamp` in ISO 8601
         * with milliseconds while the framework used "Y-m-d H:i:s" — two formats in one response
         * chain, noted for the envelope unification.
         *
         * The template no longer has a timestamp of its own; `meta.ts` is the envelope's, and it
         * is the framework's format. What remains — and what this test now holds — is the useful
         * half: a project formats the dates IN ITS PAYLOAD however it needs, and
         * ApiDateTimeFormatter is the example of that. Two formats, but no longer two answers to
         * the same question.
         */
        [, $template] = $this->postJson('/api/v1/example/bootstrap', array());
        [, $framework] = $this->postJson('/api/list', array('entity' => 'PIM\\User'), $this->token());

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}\+\d{2}:\d{2}$/',
            $template['data']['startedAt'],
            'Template payload: ISO 8601 with milliseconds'
        );
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            $template['meta']['ts'],
            'And the envelope around it carries the framework format — the same in both answers'
        );
        $this->assertSame($template['meta']['ts'] !== null, $framework['meta']['ts'] !== null,
            'Both answers carry the timestamp in the same place — meta.ts');
        $this->assertArrayNotHasKey('timestamp', $template,
            'And the template has no timestamp of its own any more');
    }

    // ── C: The middleware ──────────────────────────────────────────────────────────────

    public function testTheTemplateHooksRunAtAll(): void
    {
        // The after hook is the observable half: it sets Referrer-Policy, and the header
        // arrives. This establishes that custom/app.php is loaded and executed — and because
        // both hooks are registered in the same file in the same pass, the before hook is
        // registered as well.
        [$status, , $headers] = $this->get('/api/config');

        $this->assertSame(200, $status);
        $this->assertSame('strict-origin-when-cross-origin', $this->header($headers, 'Referrer-Policy'),
            'The after hook of the template takes effect');
    }

    public function testTheTemplateBeforeHookLeavesNoObservableTrace(): void
    {
        // The other half, and frankly: it cannot be checked from the outside. The before hook
        // of the template does exactly one thing —
        //
        //     $app['request.startedAt'] = microtime(true);
        //
        // — and nobody reads this value. No endpoint outputs it, no header carries it, no
        // response depends on it.
        //
        // That is **not a shortcoming of the test, but a statement about the template**: it
        // demonstrates the before pattern with an example that does not show its own effect.
        // The after hook does better — it shows what a hook achieves.
        //
        // What is checked is therefore what can be checked: that the hook is registered, and
        // that the template describes the order as significant. Whoever implements epic 009
        // finds here what the new kernel has to replicate.
        $template = file_get_contents(CONTENTFLY_PROJECT_DIR.'/custom/app.php');

        $this->assertStringContainsString('$app->before(function (Request $request) use ($app) {', $template,
            'The before hook is registered');
        $this->assertStringContainsString("\$app['request.startedAt'] = microtime(true);", $template,
            'and sets a value nothing reads');
        // The wording changed with 009-004-0001: it now also names the priority argument and
        // refers to HookOrderTest, which proves the order instead of claiming it. The
        // statement has stayed the same.
        // Since 000-000-0034 the comments of the template are English; the English sentence is
        // checked since then. The guarantee is unchanged.
        $this->assertStringContainsString('the order of registration is the order of execution', $template,
            'The template describes the order as significant — not checkable with a hook');
    }

    // ── D: What the template deliberately cannot do ───────────────────────────────────

    public function testTheExampleCommandIsRegisteredAndCarriesTheCustomPrefix(): void
    {
        /*
         * INVERTED WITH 009-004-0001. The test used to record that the example command is
         * deliberately not registered, and with that a contradiction:
         * the example extended Symfony\…\Command, but the ConsoleManager only accepts
         * CustomCommand descendants — so it lay around unused and showed a path the
         * framework does not offer. technical.md had listed this as an open decision since
         * epic 012.
         *
         * The decision was to convert the example rather than open up the manager: the
         * custom: prefix is the guarantee that a project command never overrides one of the
         * framework. If the manager were open to every Symfony command, the prefix would be
         * merely an offer.
         */
        $command = file_get_contents(CONTENTFLY_PROJECT_DIR.'/custom/Command/ExampleCommand.php');
        $this->assertStringContainsString('class ExampleCommand extends CustomCommand', $command,
            'It extends CustomCommand, the path the framework offers');

        $template = file_get_contents(CONTENTFLY_PROJECT_DIR.'/custom/app.php');
        $this->assertStringContainsString('addCommand(new \\Custom\\Command\\ExampleCommand', $template,
            'and is registered in custom/app.php');

        // The counter-check on the console itself — including the prefix CustomCommand prepends.
        $output = array();
        exec(sprintf('%s %s list 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg(self::console())), $output);
        $all = implode("\n", $output);

        $this->assertStringContainsString('appcms:install', $all, 'Precondition: the list has arrived');
        $this->assertStringContainsString('custom:example:command:run', $all,
            'The example command shows up, and with the custom: prefix');
    }
}
