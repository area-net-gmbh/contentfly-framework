<?php
namespace Tests\Unit\Type;

use Areanet\PIM\Classes\Type\UntypedColumn;
use PHPUnit\Framework\TestCase;

/**
 * Which untyped columns warn and which are left out quietly (000-000-0046).
 *
 * The warning from 000-000-0017 stays for unknown types. A binary column is data for the server and
 * never a field of the API; warning about it printed HTML in front of the JSON under APP_DEBUG, on
 * every request that built the schema (UFP, `AI\VectorDocument::embedding`).
 */
class UntypedColumnTest extends TestCase
{
    /** @var list<array{int,string}> */
    private array $raised = array();

    protected function setUp(): void
    {
        $this->raised = array();
        set_error_handler(function (int $level, string $message): bool {
            $this->raised[] = array($level, $message);

            return true;
        });
    }

    protected function tearDown(): void
    {
        restore_error_handler();
    }

    public function testBinaryColumnsAreLeftOutWithoutAWarning(): void
    {
        UntypedColumn::report('AI\\VectorDocument', 'embedding', 'blob');
        UntypedColumn::report('Some\\Entity', 'digest', 'binary');

        $this->assertSame(array(), $this->raised);
    }

    public function testAnUnknownColumnTypeStillWarnsWithEntityPropertyAndType(): void
    {
        UntypedColumn::report('Some\\Entity', 'shape', 'geometry');

        $this->assertCount(1, $this->raised);
        $this->assertSame(E_USER_WARNING, $this->raised[0][0]);
        $this->assertSame(
            'No Contentfly type matches Some\\Entity::shape (column type "geometry") — the field is missing from the API schema.',
            $this->raised[0][1]
        );
    }

    public function testTheSchemaBuilderReportsThroughThisClass(): void
    {
        $source = (string) file_get_contents(CONTENTFLY_PROJECT_DIR . '/lib/contentfly/Classes/Api.php');

        $this->assertStringContainsString('UntypedColumn::report(', $source);
        $this->assertStringNotContainsString("'No Contentfly type matches", $source,
            'A second copy of the warning in Api.php would bypass the exception for binary columns');
    }
}
