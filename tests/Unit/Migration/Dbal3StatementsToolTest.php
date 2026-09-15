<?php
namespace Tests\Unit\Migration;

use PHPUnit\Framework\TestCase;

use function Contentfly\Tools\Migration\Dbal3Statements\migrate;

require_once CONTENTFLY_PROJECT_DIR . '/tools/migration/dbal3-statements.php';

/**
 * The DBAL 2 → 3 statement codemod (007-005-0005).
 *
 * Checked on the text it produces, character for character — a codemod that is almost right changes
 * what a query returns without any error.
 */
class Dbal3StatementsToolTest extends TestCase
{
    public function testTheThreeShapesOfTheIdiomAreRewritten(): void
    {
        $before = <<<'PHP'
<?php
class PeriodController
{
    public function listAction($dbal, $id)
    {
        $statement = $dbal->prepare("SELECT * FROM ux_period WHERE dashboard_id = :id");
        $statement->bindValue('id', $id);
        $statement->execute();
        $periods = $statement->fetchAll();

        $statement = $dbal->prepare("SELECT COUNT(*) AS c FROM ux_period");
        $statement->execute([$id]);
        $count = $statement->fetch();

        $stmt = $dbal->prepare("DELETE FROM ai_rag_query WHERE sessionId = :sid");
        $stmt->execute();
        $deleted = $stmt->rowCount();

        $insert = $dbal->prepare("INSERT INTO log VALUES (1)");
        foreach ($periods as $period) {
            $insert->execute();
        }

        return [$periods, $count, $deleted];
    }
}
PHP;

        $after = <<<'PHP'
<?php
class PeriodController
{
    public function listAction($dbal, $id)
    {
        $statement = $dbal->prepare("SELECT * FROM ux_period WHERE dashboard_id = :id");
        $statement->bindValue('id', $id);
        $statementResult = $statement->executeQuery();
        $periods = $statementResult->fetchAllAssociative();

        $statement = $dbal->prepare("SELECT COUNT(*) AS c FROM ux_period");
        $statementResult = $statement->executeQuery([$id]);
        $count = $statementResult->fetchAssociative();

        $stmt = $dbal->prepare("DELETE FROM ai_rag_query WHERE sessionId = :sid");
        $stmtAffected = $stmt->executeStatement();
        $deleted = $stmtAffected;

        $insert = $dbal->prepare("INSERT INTO log VALUES (1)");
        foreach ($periods as $period) {
            $insert->executeStatement();
        }

        return [$periods, $count, $deleted];
    }
}
PHP;

        $result = migrate($before);

        $this->assertSame($after, $result['code']);
        $this->assertSame(array('executeQuery' => 2, 'executeStatement' => 2, 'rowCount' => 1), $result['counts']);
        $this->assertSame(array(), $result['manual']);
        $this->assertSame(0, $this->lint($result['code']), 'The rewritten code is valid PHP');
    }

    public function testAFetchInsideAWhileLoopFollowsItsExecute(): void
    {
        $result = migrate("<?php\nfunction f(\$s) {\n    \$s->execute();\n    while (\$row = \$s->fetch()) {\n        echo \$row['id'];\n    }\n}\n");

        $this->assertSame("<?php\nfunction f(\$s) {\n    \$sResult = \$s->executeQuery();\n    while (\$row = \$sResult->fetchAssociative()) {\n        echo \$row['id'];\n    }\n}\n", $result['code']);
    }

    public function testWhatItCannotDecideIsLeftAloneAndReported(): void
    {
        $source = <<<'PHP'
<?php
class X
{
    public function a($s)
    {
        if ($s->execute()) {
            return $s->fetchAll();
        }
    }

    public function b($s)
    {
        $s->execute();
        return $s->fetchAll(\PDO::FETCH_NUM);
    }

    public function c($s)
    {
        $s->execute();
        return function () use ($s) {
            return $s->fetch();
        };
    }

    public function d($s, $sResult)
    {
        $s->execute();
        return $s->fetchAll();
    }
}
PHP;

        $result = migrate($source);

        $this->assertSame(array(
            6  => 'the return value of $s->execute() is used',
            7  => '$s->fetchAll() without an execute() before it in its scope',
            14 => '$s->fetchAll() has arguments (a fetch mode)',
            21 => '$s->fetch() without an execute() before it in its scope',
            27 => '$sResult is already used in this scope',
            28 => '$s->fetchAll() without an execute() before it in its scope',
        ), $result['manual']);
        $this->assertStringContainsString("\$s->execute();\n        return \$s->fetchAll(\\PDO::FETCH_NUM);", $result['code'],
            'A fetch mode is not guessed');
        $this->assertStringContainsString("        \$s->executeStatement();\n        return function () use (\$s) {", $result['code'],
            'The closure is its own scope: its fetch does not belong to the execute outside');
    }

    private function lint(string $code): int
    {
        $file = tempnam(sys_get_temp_dir(), 'dbal3') . '.php';
        file_put_contents($file, $code);
        exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1', $output, $status);
        unlink($file);

        return $status;
    }
}
