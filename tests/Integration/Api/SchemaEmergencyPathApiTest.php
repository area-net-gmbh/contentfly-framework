<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * A broken schema is no longer a way in (`015-000-0019`).
 *
 * THE FINDING. `SystemControllerProvider`'s `checkAuth` caught `InvalidFieldNameException` — what
 * a broken schema throws, because loading user and token fails too — and swallowed it as long as
 * the body said `method=updateDatabase`. `/system/do` then ran WITHOUT a user and WITHOUT the
 * admin check, and the action behind it is `SchemaTool::updateSchema()`, which under ORM 3 applies
 * the full diff, drops included.
 *
 * The window is the worst one there is: right after a deploy that changes the mapping of `User` or
 * `Token` and before the operators migrate. Anyone could then have the schema rewritten at a
 * moment of their choosing, dropping the very columns that were about to be migrated.
 *
 * ── How the broken schema is produced, and why that is safe ──────────────────────────────
 *
 * `pim_token.token` is renamed, which is exactly the state the finding describes: the column the
 * authentication looks up is not there. The rename is undone in a `finally`, and the test asserts
 * afterwards that it really is undone — a test that left the database broken would take the rest
 * of the suite with it.
 *
 * ── What is measured is the SCHEMA, not the status code ──────────────────────────────────
 *
 * Before the fix the request ran `updateSchema()`, which put `token` back and dropped the renamed
 * column. So the proof is the column that is still wrong afterwards: nothing ran.
 */
class SchemaEmergencyPathApiTest extends IntegrationTestCase
{
    private const BROKEN_COLUMN = 'token_broken_by_test';

    public function testAnonymousUpdateDatabaseDoesNotRunOnABrokenSchema(): void
    {
        if (self::$baseUrl === null) {
            $this->markTestSkipped('No integration server configured.');
        }

        $this->breakTokenTable();

        try {
            [$status] = $this->postJson(
                '/system/do',
                array('method' => 'updateDatabase'),
                'any-bearer-value-at-all'
            );

            $this->assertNotSame(200, $status, 'The request is refused');
            $this->assertTrue(
                $this->hasColumn(self::BROKEN_COLUMN),
                'and the schema is untouched — updateSchema() did not run'
            );
            $this->assertFalse($this->hasColumn('token'), 'nothing put the column back either');
        } finally {
            $this->repairTokenTable();
        }

        $this->assertTrue($this->hasColumn('token'), 'the table is back as it was');
        $this->assertFalse($this->hasColumn(self::BROKEN_COLUMN));
    }

    /** And without a token it is refused on an intact schema too — that never was the gap. */
    public function testAnonymousUpdateDatabaseIsRefusedOnAnIntactSchema(): void
    {
        if (self::$baseUrl === null) {
            $this->markTestSkipped('No integration server configured.');
        }

        [$status] = $this->postJson('/system/do', array('method' => 'updateDatabase'), 'any-bearer-value-at-all');

        $this->assertNotSame(200, $status);
    }

    private function breakTokenTable(): void
    {
        $this->pdo()->exec(sprintf(
            'ALTER TABLE pim_token CHANGE `token` `%s` VARCHAR(255) NOT NULL',
            self::BROKEN_COLUMN
        ));
    }

    private function repairTokenTable(): void
    {
        if ($this->hasColumn(self::BROKEN_COLUMN)) {
            $this->pdo()->exec(sprintf(
                'ALTER TABLE pim_token CHANGE `%s` `token` VARCHAR(255) NOT NULL',
                self::BROKEN_COLUMN
            ));
        }
    }

    private function hasColumn(string $column): bool
    {
        $statement = $this->pdo()->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $statement->execute(array('pim_token', $column));

        return (int) $statement->fetchColumn() > 0;
    }
}
