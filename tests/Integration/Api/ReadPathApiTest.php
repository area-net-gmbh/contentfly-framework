<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * The read paths of `Api.php` that had no test — filters, translations, sync (000-000-0075).
 *
 * Found by the first coverage measurement (000-000-0056) and sorted in 000-000-0069: the largest
 * untested blocks without a permission aspect. One section per method, in the order of the task.
 *
 * Every record is created **bypassing the API**, as everywhere in this suite: a read test whose
 * precondition runs through the write path loses its meaning. Each test marks its records with a
 * run id and asks for exactly those, so records from other tests cannot change the result.
 *
 * What needs a differently configured server — the main language (`APP_LANGUAGES`) and the schema
 * cache — is in `MainLanguageApiTest` and `SchemaCacheApiTest`.
 */
class ReadPathApiTest extends IntegrationTestCase
{
    private string $run = '';

    /** @var array<int,string> model ids whose pim_log rows tearDown() removes */
    private array $logged = array();

    protected function setUp(): void
    {
        parent::setUp();

        $this->run = bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach ($this->logged as $modelId) {
            $this->pdo()->prepare('DELETE FROM pim_log WHERE model_id = :id')->execute(array('id' => $modelId));
        }

        $this->logged = array();

        parent::tearDown();
    }

    // ── Test data ──────────────────────────────────────────────────────────────────────

    private function createExample(string $name, string $modified = 'NOW()'): string
    {
        $id = 'rp-ex-'.bin2hex(random_bytes(6));

        $this->pdo()->prepare(
            "INSERT INTO example_entity (id, state, name, slug, boolExample, created, modified, views, isIntern)
             VALUES (:id, 'active', :name, :slug, 0, NOW(), $modified, 0, 0)"
        )->execute(array('id' => $id, 'name' => $name, 'slug' => $id));
        $this->deleteAfterTest('example_entity', $id);

        return $id;
    }

    /** @param array<int,string> $examples */
    private function createRelations(string $title, ?string $owner = null, array $examples = array(), string $modified = 'NOW()'): string
    {
        $id = 'rp-rel-'.bin2hex(random_bytes(6));

        $this->pdo()->prepare(
            "INSERT INTO example_relations (id, title, owner_id, created, modified, views, isIntern)
             VALUES (:id, :title, :owner, NOW(), $modified, 0, 0)"
        )->execute(array('id' => $id, 'title' => $title, 'owner' => $owner));
        $this->deleteAfterTest('example_relations', $id);

        foreach ($examples as $example) {
            $this->pdo()->prepare(
                'INSERT INTO example_relations_examples (examplerelations_id, example_id) VALUES (:rel, :ex)'
            )->execute(array('rel' => $id, 'ex' => $example));
        }

        return $id;
    }

    private function createTranslation(string $id, string $lang, string $title, ?string $code = null, ?string $related = null): void
    {
        $this->pdo()->prepare(
            'INSERT INTO example_i18n (id, lang, title, code, related_id, related_lang, created, modified, views, isIntern)
             VALUES (:id, :lang, :title, :code, :related, :relatedLang, NOW(), NOW(), 0, 0)'
        )->execute(array(
            'id'          => $id,
            'lang'        => $lang,
            'title'       => $title,
            'code'        => $code,
            'related'     => $related,
            'relatedLang' => $related !== null ? $lang : null,
        ));

        // Deleting by id removes every language of the record; a second registration is harmless.
        $this->deleteAfterTest('example_i18n', $id);
    }

    private function createFile(string $name, string $type, int $size = 5, string $modified = 'NOW()'): string
    {
        $id = 'rp-file-'.bin2hex(random_bytes(6));

        $this->pdo()->prepare(
            "INSERT INTO pim_file (id, name, type, hash, size, created, modified, views, isIntern)
             VALUES (:id, :name, :type, :hash, :size, NOW(), $modified, 0, 0)"
        )->execute(array('id' => $id, 'name' => $name, 'type' => $type, 'hash' => bin2hex(random_bytes(8)), 'size' => $size));
        $this->deleteAfterTest('pim_file', $id);

        // getAll() with filedata creates the directory of every file it looks at.
        $this->deleteDirectoryAfterTest(self::dataDir().'/files/'.$id);

        return $id;
    }

    private function createTag(string $title, string $modified = 'NOW()'): string
    {
        $id = 'rp-tag-'.bin2hex(random_bytes(6));

        $this->pdo()->prepare(
            "INSERT INTO pim_tag (id, title, created, modified, views, isIntern)
             VALUES (:id, :title, NOW(), $modified, 0, 0)"
        )->execute(array('id' => $id, 'title' => $title));
        $this->deleteAfterTest('pim_tag', $id);

        return $id;
    }

    private function logDeletion(string $entity, string $modelId, string $mode, string $created = 'NOW()'): void
    {
        $this->pdo()->prepare(
            "INSERT INTO pim_log (id, model_id, model_name, mode, created, modified, views, isIntern)
             VALUES (:id, :model, :entity, :mode, $created, $created, 0, 0)"
        )->execute(array('id' => 'rp-log-'.bin2hex(random_bytes(6)), 'model' => $modelId, 'entity' => $entity, 'mode' => $mode));

        $this->logged[] = $modelId;
    }

    /** @return array<int,string> the ids /api/list returns for the request */
    private function listIds(array $request): array
    {
        [$status, $body] = $this->postJson('/api/list', $request, $this->token());

        $this->assertSame(200, $status, 'list answers: '.json_encode($body));

        $ids = array_column($body['data'] ?? array(), 'id');
        sort($ids);

        return $ids;
    }

    /** @param array<int,string> $ids */
    private function sorted(array $ids): array
    {
        sort($ids);

        return $ids;
    }

    // ── getList: where on join and multijoin ───────────────────────────────────────────

    public function testWhereOnAJoinFieldReturnsTheRecordsPointingToTheId(): void
    {
        $owner = $this->createExample('Owner');
        $with  = $this->createRelations($this->run, $owner);
        $this->createRelations($this->run);

        $this->assertSame(array($with), $this->listIds(array(
            'entity' => 'Core\\ExampleRelations',
            'where'  => array('title' => $this->run, 'owner' => $owner),
        )));
    }

    public function testMinusOneOnAJoinFieldMeansWithoutAJoinedRecord(): void
    {
        $owner   = $this->createExample('Owner');
        $this->createRelations($this->run, $owner);
        $without = $this->createRelations($this->run);

        $this->assertSame(array($without), $this->listIds(array(
            'entity' => 'Core\\ExampleRelations',
            'where'  => array('title' => $this->run, 'owner' => -1),
        )), '-1 is the "no link" choice of a filter, not an id');
    }

    public function testWhereOnAMultijoinFieldReturnsTheRecordsContainingTheId(): void
    {
        $first  = $this->createExample('First');
        $second = $this->createExample('Second');
        $with   = $this->createRelations($this->run, null, array($first, $second));
        $this->createRelations($this->run, null, array($second));
        $this->createRelations($this->run);

        $this->assertSame(array($with), $this->listIds(array(
            'entity' => 'Core\\ExampleRelations',
            'where'  => array('title' => $this->run, 'examples' => $first),
        )));
    }

    public function testMinusOneOnAMultijoinFieldMeansWithoutAnyJoinedRecord(): void
    {
        $example = $this->createExample('Example');
        $this->createRelations($this->run, null, array($example));
        $without = $this->createRelations($this->run);

        $this->assertSame(array($without), $this->listIds(array(
            'entity' => 'Core\\ExampleRelations',
            'where'  => array('title' => $this->run, 'examples' => -1),
        )));
    }

    // ── getList: fulltext, mimetypes ───────────────────────────────────────────────────

    public function testFulltextSearchesTheTextFields(): void
    {
        $hit = $this->createExample('Before '.$this->run.' after');
        $this->createExample('Something else');

        $this->assertSame(array($hit), $this->listIds(array(
            'entity' => 'Core\\Example',
            'where'  => array('fulltext' => $this->run),
        )), 'LIKE on every string field, anywhere in the value');
    }

    public function testFulltextAlsoFindsTheExactId(): void
    {
        $hit = $this->createExample('Nothing to find here');

        $this->assertSame(array($hit), $this->listIds(array(
            'entity' => 'Core\\Example',
            'where'  => array('fulltext' => $hit),
        )));
    }

    public function testMimetypesNarrowsFilesToAGroupOfTypes(): void
    {
        $png = $this->createFile($this->run.'.png', 'image/png');
        $pdf = $this->createFile($this->run.'.pdf', 'application/pdf');
        $this->createFile($this->run.'.txt', 'text/plain');

        $this->assertSame(array($png), $this->listIds(array(
            'entity' => 'PIM\\File',
            'where'  => array('fulltext' => $this->run, 'mimetypes' => 'images'),
        )));
        $this->assertSame(array($pdf), $this->listIds(array(
            'entity' => 'PIM\\File',
            'where'  => array('fulltext' => $this->run, 'mimetypes' => 'pdf'),
        )));
    }

    public function testMimetypesOtherIsEverythingOutsideTheGroups(): void
    {
        $this->createFile($this->run.'.png', 'image/png');
        $this->createFile($this->run.'.pdf', 'application/pdf');
        $txt = $this->createFile($this->run.'.txt', 'text/plain');

        $this->assertSame(array($txt), $this->listIds(array(
            'entity' => 'PIM\\File',
            'where'  => array('fulltext' => $this->run, 'mimetypes' => 'other'),
        )));
    }

    public function testAnUnknownMimetypesGroupDoesNotFilter(): void
    {
        $png = $this->createFile($this->run.'.png', 'image/png');
        $txt = $this->createFile($this->run.'.txt', 'text/plain');

        $this->assertSame($this->sorted(array($png, $txt)), $this->listIds(array(
            'entity' => 'PIM\\File',
            'where'  => array('fulltext' => $this->run, 'mimetypes' => 'video'),
        )), 'Current state: an unknown group is ignored, not rejected');
    }

    // ── getList: lastModified, untranslatedLang, i18n joins ────────────────────────────

    public function testLastModifiedReturnsOnlyWhatChangedSince(): void
    {
        $this->createRelations($this->run, null, array(), "'2020-01-01 00:00:00'");
        $recent = $this->createRelations($this->run);

        $this->assertSame(array($recent), $this->listIds(array(
            'entity'       => 'Core\\ExampleRelations',
            'where'        => array('title' => $this->run),
            'lastModified' => '2021-01-01 00:00:00',
        )));
    }

    public function testAnUnreadableLastModifiedEndsInAServerError(): void
    {
        // Current state, and a finding: getList() tries to read the value as a date, swallows the
        // failure and hands the raw string on to the query. MySQL rejects it, and the caller gets
        // 500 for a wrong parameter. /api/all reads the value in the controller and drops it.
        $this->createRelations($this->run);

        [$status, $body] = $this->postJson('/api/list', array(
            'entity'       => 'Core\\ExampleRelations',
            'where'        => array('title' => $this->run),
            'lastModified' => 'not a date',
        ), $this->token());

        $this->assertSame(500, $status, 'Current state: a wrong parameter is a server error');
        $this->assertErrorEnvelope($body);
    }

    public function testUntranslatedLangFindsNothingWhileTheEntityJoinsATranslatableOne(): void
    {
        // Current state, and a finding. untranslatedLang binds `:lang` to the language to
        // translate FROM; the join loop further down binds the same parameter again, to the
        // language of the request, as soon as the entity joins a translatable entity. The query
        // then asks for records in English that have no English version — never any.
        //
        // Core\ExampleI18n joins itself through `related`, and it is the only translatable entity
        // of the template. So the path that works — an entity without such a join — has no
        // entity left to be shown on.
        $translated   = 'rp-i18n-'.bin2hex(random_bytes(6));
        $untranslated = 'rp-i18n-'.bin2hex(random_bytes(6));

        $this->createTranslation($translated, 'de', 'Translated (de)');
        $this->createTranslation($translated, 'en', 'Translated');
        $this->createTranslation($untranslated, 'de', 'Not translated (de)');

        [$status, $body] = $this->postJson('/api/list', array(
            'entity'           => 'Core\\ExampleI18n',
            'lang'             => 'en',
            'untranslatedLang' => 'de',
            'itemsPerPage'     => 1000,
        ), $this->token());

        $this->assertSame(200, $status, json_encode($body));
        $this->assertNotContains($untranslated, array_column($body['data'], 'id'),
            'Current state: the German record without an English version is not found');
        $this->assertNotContains($translated, array_column($body['data'], 'id'));
    }

    public function testAJoinToATranslatableEntityComesInTheLanguageOfTheList(): void
    {
        $target = 'rp-i18n-'.bin2hex(random_bytes(6));
        $source = 'rp-i18n-'.bin2hex(random_bytes(6));

        $this->createTranslation($target, 'de', 'Target (de)');
        $this->createTranslation($target, 'en', 'Target');
        $this->createTranslation($source, 'de', $this->run, null, $target);
        $this->createTranslation($source, 'en', $this->run, null, $target);

        [$status, $body] = $this->postJson('/api/list', array(
            'entity' => 'Core\\ExampleI18n',
            'lang'   => 'en',
            'where'  => array('title' => $this->run),
        ), $this->token());

        $this->assertSame(200, $status, json_encode($body));
        $this->assertCount(1, $body['data']);
        $this->assertSame('en', $body['data'][0]['lang']);
        $this->assertSame($target, $body['data'][0]['related']['id']);
        $this->assertSame('Target', $body['data'][0]['related']['title'],
            'The joined record in the same language — its key carries the language');
    }

    public function testAJoinToATranslatableEntityWithPropertiesSelectsItPartially(): void
    {
        $target = 'rp-i18n-'.bin2hex(random_bytes(6));
        $source = 'rp-i18n-'.bin2hex(random_bytes(6));

        $this->createTranslation($target, 'en', 'Target');
        $this->createTranslation($source, 'en', $this->run, null, $target);

        [$status, $body] = $this->postJson('/api/list', array(
            'entity'     => 'Core\\ExampleI18n',
            'lang'       => 'en',
            'where'      => array('title' => $this->run),
            'properties' => array('title', 'related'),
        ), $this->token());

        $this->assertSame(200, $status, json_encode($body));
        $this->assertCount(1, $body['data']);
        $this->assertSame($target, $body['data'][0]['related']['id'],
            'The partial select keeps id and lang of the joined record');
    }

    // ── getSingle: i18n joins, loadJoinedLang, compareToLang ───────────────────────────

    public function testSingleReturnsTheJoinedTranslationInTheLanguageOfTheRequest(): void
    {
        $target = 'rp-i18n-'.bin2hex(random_bytes(6));
        $source = 'rp-i18n-'.bin2hex(random_bytes(6));

        $this->createTranslation($target, 'de', 'Target (de)');
        $this->createTranslation($target, 'en', 'Target');
        $this->createTranslation($source, 'en', 'Source', null, $target);

        [$status, $body] = $this->postJson('/api/single', array(
            'entity' => 'Core\\ExampleI18n',
            'id'     => $source,
            'lang'   => 'en',
        ), $this->token());

        $this->assertSame(200, $status, json_encode($body));
        $this->assertSame('Target', $body['data']['related']['title']);
    }

    public function testLoadJoinedLangFindsNoJoinedRecordInAnotherLanguage(): void
    {
        // Current state, and a finding. loadJoinedLang narrows the join to a language — but the
        // reference to a translatable record has two columns, `related_id` AND `related_lang`,
        // and the second one already names the language the reference was written in. Asked for
        // another language, the join has two conditions on `lang` that cannot both hold, and the
        // joined record comes back as null although it exists in that language.
        $target = 'rp-i18n-'.bin2hex(random_bytes(6));
        $source = 'rp-i18n-'.bin2hex(random_bytes(6));

        $this->createTranslation($target, 'de', 'Target (de)');
        $this->createTranslation($target, 'en', 'Target');
        $this->createTranslation($source, 'en', 'Source', null, $target);

        [$status, $body] = $this->postJson('/api/single', array(
            'entity'         => 'Core\\ExampleI18n',
            'id'             => $source,
            'lang'           => 'en',
            'loadJoinedLang' => 'de',
        ), $this->token());

        $this->assertSame(200, $status, json_encode($body));
        $this->assertSame('Source', $body['data']['title'], 'The record itself stays in the language of the request');
        $this->assertNull($body['data']['related'],
            'Current state: the German version of the target exists and is not found');
    }

    public function testCompareToLangAcceptsATranslationWhoseJoinsAreTranslatedToo(): void
    {
        $target = 'rp-i18n-'.bin2hex(random_bytes(6));
        $source = 'rp-i18n-'.bin2hex(random_bytes(6));

        $this->createTranslation($target, 'de', 'Target (de)');
        $this->createTranslation($target, 'en', 'Target');
        $this->createTranslation($source, 'de', 'Source (de)', null, $target);
        $this->createTranslation($source, 'en', 'Source', null, $target);

        [$status, $body] = $this->postJson('/api/single', array(
            'entity'        => 'Core\\ExampleI18n',
            'id'            => $source,
            'lang'          => 'en',
            'compareToLang' => 'de',
        ), $this->token());

        $this->assertSame(200, $status, json_encode($body));
        $this->assertSame('Source', $body['data']['title']);
    }

    public function testCompareToLangReportsAJoinTheOtherLanguageLacks(): void
    {
        $target = 'rp-i18n-'.bin2hex(random_bytes(6));
        $source = 'rp-i18n-'.bin2hex(random_bytes(6));

        $this->createTranslation($target, 'en', 'Target');
        $this->createTranslation($source, 'de', 'Source (de)');
        $this->createTranslation($source, 'en', 'Source', null, $target);

        [$status, $body] = $this->postJson('/api/single', array(
            'entity'        => 'Core\\ExampleI18n',
            'id'            => $source,
            'lang'          => 'en',
            'compareToLang' => 'de',
        ), $this->token());

        $this->assertNotSame(200, $status, 'The English version links a record the German one does not');
        $this->assertErrorEnvelope($body);
    }

    public function testCompareToLangWithLoadJoinedLangReportsAJoinMissingInThatLanguage(): void
    {
        $target = 'rp-i18n-'.bin2hex(random_bytes(6));
        $source = 'rp-i18n-'.bin2hex(random_bytes(6));

        // The target exists in English only: read in German, the join comes back empty.
        $this->createTranslation($target, 'en', 'Target');
        $this->createTranslation($source, 'en', 'Source', null, $target);

        [$status, $body] = $this->postJson('/api/single', array(
            'entity'         => 'Core\\ExampleI18n',
            'id'             => $source,
            'lang'           => 'en',
            'compareToLang'  => 'de',
            'loadJoinedLang' => 'de',
        ), $this->token());

        $this->assertNotSame(200, $status, 'Translating anew needs every joined record in the new language');
        $this->assertErrorEnvelope($body);
    }

    public function testCompareToLangOnAPlainEntityComparesTheRecordWithItself(): void
    {
        $owner    = $this->createExample('Owner');
        $relation = $this->createRelations($this->run, $owner, array($owner));

        [$status, $body] = $this->postJson('/api/single', array(
            'entity'        => 'Core\\ExampleRelations',
            'id'            => $relation,
            'compareToLang' => 'de',
        ), $this->token());

        $this->assertSame(200, $status,
            'Current state: without languages the "other language" is the same row, so nothing differs');
        $this->assertSame($relation, $body['data']['id']);
    }

    // ── getAll: filedata, lastModified, deletions ──────────────────────────────────────

    /** @return array<int,array<string,mixed>> the rows /api/all returns for one entity */
    private function all(array $request, string $entity): array
    {
        [$status, $body] = $this->postJson('/api/all', $request, $this->token());

        $this->assertSame(200, $status, json_encode($body));

        return $body['data'][$entity] ?? array();
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function row(array $rows, string $id): ?array
    {
        foreach ($rows as $row) {
            if (($row['id'] ?? null) === $id) {
                return $row;
            }
        }

        return null;
    }

    public function testFiledataDeliversTheFileContentAsBase64(): void
    {
        $file = $this->createFile('rp-'.$this->run.'.txt', 'text/plain');
        mkdir(self::dataDir().'/files/'.$file, 0777, true);
        file_put_contents(self::dataDir().'/files/'.$file.'/rp-'.$this->run.'.txt', 'content '.$this->run);

        $row = $this->row($this->all(array('filedata' => array('org')), 'PIM\\File'), $file);

        $this->assertNotNull($row);
        $this->assertSame(base64_encode('content '.$this->run), $row['filedata']['org'] ?? null);
    }

    public function testFiledataTakesAThumbnailSizeByItsAlias(): void
    {
        $alias = $this->pdo()->query('SELECT alias FROM pim_thumbnail_setting ORDER BY alias LIMIT 1')->fetchColumn();
        $this->assertIsString($alias, 'Precondition: the installation has a thumbnail size');

        $file = $this->createFile('rp-'.$this->run.'.png', 'image/png');
        mkdir(self::dataDir().'/files/'.$file, 0777, true);
        file_put_contents(self::dataDir().'/files/'.$file.'/'.$alias.'-rp-'.$this->run.'.png', 'thumbnail');

        $row = $this->row($this->all(array('filedata' => array($alias, 'org')), 'PIM\\File'), $file);

        $this->assertSame(array($alias => base64_encode('thumbnail')), $row['filedata'] ?? null,
            'The size that exists on disk is delivered; the original is missing and left out');
    }

    public function testFiledataDoesNotFollowASizeThatIsAPath(): void
    {
        // The size is part of a file path: <data>/files/<id>/<size>-<name>. With `../<other id>/x`
        // as size, the path leaves the directory of this file and ends in another one — in a file
        // the caller never asked for and may not be allowed to read.
        $file  = $this->createFile('rp-'.$this->run.'.txt', 'text/plain');
        $other = 'rp-other-'.bin2hex(random_bytes(6));

        mkdir(self::dataDir().'/files/'.$file, 0777, true);
        mkdir(self::dataDir().'/files/'.$other, 0777, true);
        $this->deleteDirectoryAfterTest(self::dataDir().'/files/'.$other);
        file_put_contents(self::dataDir().'/files/'.$other.'/x-rp-'.$this->run.'.txt', 'secret '.$this->run);

        [$status, $raw] = $this->postJson('/api/all', array('filedata' => array('../'.$other.'/x')), $this->token());

        $this->assertSame(200, $status);
        $this->assertStringNotContainsString(base64_encode('secret '.$this->run), json_encode($raw),
            'A size is a name — a path in it is not followed');
        $this->assertArrayNotHasKey('filedata', $this->row($raw['data']['PIM\\File'], $file) ?? array());
    }

    public function testFiledataAsASingleValueIsTakenAsOneSize(): void
    {
        $file = $this->createFile('rp-'.$this->run.'.txt', 'text/plain');
        mkdir(self::dataDir().'/files/'.$file, 0777, true);
        file_put_contents(self::dataDir().'/files/'.$file.'/rp-'.$this->run.'.txt', 'single');

        $row = $this->row($this->all(array('filedata' => 'org'), 'PIM\\File'), $file);

        $this->assertSame(base64_encode('single'), $row['filedata']['org'] ?? null);
    }

    public function testAllWithLastModifiedReturnsOnlyWhatChangedSince(): void
    {
        $old    = $this->createTag('Old '.$this->run, "'2020-01-01 00:00:00'");
        $recent = $this->createTag('Recent '.$this->run);

        $ids = array_column($this->all(array('lastModified' => '2021-01-01 00:00:00'), 'PIM\\Tag'), 'id');

        $this->assertContains($recent, $ids);
        $this->assertNotContains($old, $ids);
    }

    public function testAllReportsDeletionsFromTheLog(): void
    {
        $deleted = 'rp-gone-'.bin2hex(random_bytes(6));
        $legacy  = 'rp-gone-'.bin2hex(random_bytes(6));

        $this->logDeletion('PIM\\Tag', $deleted, 'DEL');
        $this->logDeletion('PIM\\Tag', $legacy, 'Gelöscht');

        $rows = $this->all(array(), 'PIM\\Tag');

        $this->assertSame(array('id' => $deleted, 'isDeleted' => true), $this->row($rows, $deleted));
        $this->assertSame(array('id' => $legacy, 'isDeleted' => true), $this->row($rows, $legacy),
            'The value Contentfly 1.x wrote still counts as a deletion (014-003-0002)');
    }

    public function testAllReportsOnlyTheDeletionsSinceLastModified(): void
    {
        $old    = 'rp-gone-'.bin2hex(random_bytes(6));
        $recent = 'rp-gone-'.bin2hex(random_bytes(6));

        $this->logDeletion('PIM\\Tag', $old, 'DEL', "'2020-01-01 00:00:00'");
        $this->logDeletion('PIM\\Tag', $recent, 'DEL');

        $ids = array_column($this->all(array('lastModified' => '2021-01-01 00:00:00'), 'PIM\\Tag'), 'id');

        $this->assertContains($recent, $ids);
        $this->assertNotContains($old, $ids);
    }

    // ── getCount: lastModified, join tables, files ─────────────────────────────────────

    /** @return array<string,mixed> */
    private function statistic(array $request): array
    {
        [$status, $body] = $this->postJson('/api/count', $request, $this->token());

        $this->assertSame(200, $status, json_encode($body));

        return $body['data'];
    }

    private function scalar(string $sql, array $params = array()): int
    {
        $statement = $this->pdo()->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    public function testCountWithLastModifiedCountsWhatChangedSince(): void
    {
        $this->createTag('Recent '.$this->run);
        $since = '2021-01-01 00:00:00';

        $data = $this->statistic(array('entity' => 'PIM\\Tag', 'lastModified' => $since));

        $this->assertSame(
            $this->scalar('SELECT COUNT(*) FROM pim_tag WHERE modified > ?', array($since)),
            $data['details']['PIM\\Tag']
        );
        $this->assertSame(0, $this->statistic(array('entity' => 'PIM\\Tag', 'lastModified' => '2999-01-01 00:00:00'))['details']['PIM\\Tag'],
            'Nothing changed after a date in the future');
    }

    public function testCountTakesLastModifiedPerEntity(): void
    {
        $this->createTag('Recent '.$this->run);
        $this->createExample('Recent '.$this->run);

        $data = $this->statistic(array('lastModified' => array('PIM\\Tag' => '2999-01-01 00:00:00')));

        $this->assertSame(0, $data['details']['PIM\\Tag'], 'The entity named gets its own point in time');
        $this->assertSame($this->scalar('SELECT COUNT(*) FROM example_entity'), $data['details']['Core\\Example'],
            'An entity not named is counted in full');
    }

    public function testCountAddsTheRowsOfJoinTables(): void
    {
        $first  = $this->createExample('First');
        $second = $this->createExample('Second');
        $this->createRelations($this->run, null, array($first, $second));

        $file = $this->createFile('rp-'.$this->run.'.txt', 'text/plain');
        $relation = $this->createRelations($this->run);
        $this->pdo()->prepare('INSERT INTO example_relations_attachments (examplerelations_id, file_id) VALUES (?, ?)')
            ->execute(array($relation, $file));

        $data = $this->statistic(array('entity' => 'Core\\ExampleRelations'));

        $records     = $this->scalar('SELECT COUNT(*) FROM example_relations');
        $attachments = $this->scalar('SELECT COUNT(*) FROM example_relations_attachments INNER JOIN pim_file ON file_id = id');
        $examples    = $this->scalar('SELECT COUNT(*) FROM example_relations_examples INNER JOIN example_relations ON examplerelations_id = id');

        $this->assertSame($records, $data['details']['Core\\ExampleRelations'], 'details counts the records');
        $this->assertSame($records + $attachments + $examples, $data['dataCount'],
            'dataCount adds a row per linked file and per linked record');
    }

    public function testCountOfFilesIsAnAmountAndASize(): void
    {
        $this->createFile('rp-'.$this->run.'.txt', 'text/plain', 1234);

        $data = $this->statistic(array('entity' => 'PIM\\File'));

        $this->assertSame(0, $data['dataCount'], 'Files are not records in this statistic');
        $this->assertSame(array(), $data['details']);
        $this->assertSame($this->scalar('SELECT COUNT(*) FROM pim_file'), $data['filesCount']);
        $this->assertEquals($this->scalar('SELECT SUM(size) FROM pim_file'), $data['filesSize']);
    }

    public function testCountOfFilesTakesLastModifiedToo(): void
    {
        $this->createFile('rp-'.$this->run.'.txt', 'text/plain');

        $this->assertSame(0, $this->statistic(array('entity' => 'PIM\\File', 'lastModified' => '2999-01-01 00:00:00'))['filesCount']);
        $this->assertSame(0, $this->statistic(array('entity' => 'PIM\\File', 'lastModified' => array('PIM\\File' => '2999-01-01 00:00:00')))['filesCount']);
        $this->assertSame(
            $this->scalar('SELECT COUNT(*) FROM pim_file'),
            $this->statistic(array('entity' => 'PIM\\File', 'lastModified' => array('PIM\\Tag' => '2999-01-01 00:00:00')))['filesCount'],
            'A point in time for another entity does not narrow the files'
        );
    }

    // ── doInsert: unique per field, universal fields ───────────────────────────────────

    public function testADuplicateOnAUniqueColumnTheSchemaDoesNotKnowIsAConflict(): void
    {
        // Core\Example.slug is unique in the database (a UniqueConstraint), not in the schema: the
        // check before the insert does not see it, the database does.
        //
        // Inverted with 000-000-0080. It recorded 500 with `unknown_perror`, naming the last field
        // of an earlier loop instead of `slug`; until 000-000-0079 `context.value` carried MySQL's
        // message as well.
        $slug = 'rp-slug-'.$this->run;

        [$first, $body] = $this->postJson('/api/insert', array('entity' => 'Core\\Example', 'data' => array('slug' => $slug)), $this->token());
        $this->assertSame(200, $first, json_encode($body));
        $this->deleteAfterTest('example_entity', $body['data']['id']);
        $this->logged[] = $body['data']['id'];

        [$second, $body] = $this->postJson('/api/insert', array('entity' => 'Core\\Example', 'data' => array('slug' => $slug)), $this->token());

        $this->assertSame(409, $second, 'A conflict, like the check before the insert reports it');
        $entry = $this->assertErrorEnvelope($body, 'contentfly_general_ressource_already_exists');
        $this->assertSame(array('value' => 'Core\\Example'), $entry['context'],
            'It names the entity: which key collided is only in MySQL\'s text');
        $this->assertStringNotContainsString('SQLSTATE', json_encode($entry), 'MySQL\'s text goes to the log, not to the client (000-000-0079)');
        $this->assertStringNotContainsString($slug, json_encode($entry), 'nor the value that collided');
        $this->assertSame(1, $this->scalar('SELECT COUNT(*) FROM example_entity WHERE slug = ?', array($slug)),
            'The second record with the same slug is not written');
    }

    public function testADuplicateUserAliasIsCaughtBeforeTheDatabase(): void
    {
        // PIM\User.alias is unique in the schema too, so the check before the insert answers; the
        // database never sees the duplicate.
        [$status, $body] = $this->postJson('/api/insert', array(
            'entity' => 'PIM\\User',
            'data'   => array('alias' => 'admin', 'pass' => 'irrelevant-'.$this->run),
        ), $this->token());

        $this->assertSame(409, $status);
        $this->assertErrorEnvelope($body, 'contentfly_general_ressource_already_exists');
        $this->assertSame(1, $this->scalar("SELECT COUNT(*) FROM pim_user WHERE alias = 'admin'"));
    }

    public function testAUniversalFieldWrittenWithATranslationReachesTheOtherLanguages(): void
    {
        $id = 'rp-i18n-'.bin2hex(random_bytes(6));
        $this->createTranslation($id, 'de', 'German', 'OLD');

        [$status, $body] = $this->postJson('/api/insert', array(
            'entity' => 'Core\\ExampleI18n',
            'lang'   => 'en',
            'data'   => array('id' => $id, 'title' => 'English', 'code' => 'NEW-'.$this->run),
        ), $this->token());

        $this->assertSame(200, $status, json_encode($body));
        $this->logged[] = $id;

        $this->assertSame('NEW-'.$this->run, $this->pdo()->query(
            "SELECT code FROM example_i18n WHERE id = ".$this->pdo()->quote($id)." AND lang = 'de'"
        )->fetchColumn(), 'i18n_universal: the same value in every language');
    }
}
