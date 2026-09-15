<?php
namespace Tests\Integration\Command;

use Tests\Integration\IntegrationTestCase;

/**
 * `orm:validate-schema` — mapping and database, against the installed instance (`000-000-0025`).
 *
 * Until this task the mapping half was red, and knowingly so: `BaseI18nTree::$treeParent` had one
 * join column for a composite key. Epic `010` handed that on with a reason, and every check since
 * then had to be read as "red, but the known red". A known red hides the next one.
 *
 * **Why a test and not just a verification step.** The mapping is read from attributes across
 * every entity of the framework and the template. A new relation with the same mistake would
 * otherwise only show up the next time somebody happens to run the command.
 *
 * Integration, not unit: the database half needs the installed schema, and the console builds
 * its EntityManager from the configuration the installer wrote.
 */
class SchemaValidationTest extends IntegrationTestCase
{
    public function testMappingAndDatabaseAreValid(): void
    {
        $output = array();
        exec(
            sprintf('%s %s orm:validate-schema 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg(self::console())),
            $output,
            $code
        );
        $all = implode("\n", $output);

        $this->assertStringContainsString('[OK] The mapping files are correct.', $all,
            "The mapping half is valid — until 000-000-0025 BaseI18nTree reported a missing join column:\n".$all);
        $this->assertStringContainsString('[OK] The database schema is in sync with the mapping files.', $all,
            "The installed schema matches the mapping:\n".$all);
        $this->assertSame(0, $code, 'Exit code 0 — 1 would be the mapping, 2 the database');
    }
}
