<?php
namespace Tests\Unit\Security;

use Areanet\PIM\Classes\Api;
use Areanet\PIM\Classes\Kernel\Application;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * `Api::getTree2()` must bind `lang`, never put it into the SQL string (000-000-0062).
 *
 * For an i18n tree the join condition used to read `AND t.lang = '$lang'`, with `$lang` taken
 * unchanged from the request body of `/api/tree2` — an SQL injection for every logged-in user.
 *
 * WHY A UNIT TEST AND NOT AN INTEGRATION TEST. The path only exists for an entity based on
 * `BaseI18nTree`, and neither the framework nor the template has one; the integration suite
 * cannot reach it without a test-only entity and a schema of its own. What has to be proven is
 * narrow: which SQL and which parameters reach the connection. A connection double records
 * exactly that — and it records the statement that would have been sent, not a guess at it.
 */
class Tree2LangBindingTest extends TestCase
{
    private const HOSTILE_LANG = "de' OR '1'='1";

    /** @var array{0:string,1:array<int,mixed>}|null */
    private ?array $sent = null;

    public function testLangOfAnI18nTreeIsBoundAsAParameter(): void
    {
        $this->api(true)->getTree2('TreeFixture', self::HOSTILE_LANG);

        [$sql, $params] = $this->sent;

        $this->assertStringNotContainsString(self::HOSTILE_LANG, $sql,
            'The request value must not be part of the statement');
        $this->assertStringNotContainsString("'1'='1", $sql);
        $this->assertSame(array(self::HOSTILE_LANG), $params,
            'It travels as a bound parameter, unchanged');
    }

    public function testATreeWithoutI18nSendsNoLangCondition(): void
    {
        $this->api(false)->getTree2('TreeFixture', self::HOSTILE_LANG);

        [$sql, $params] = $this->sent;

        $this->assertStringNotContainsString('lang', $sql,
            'Without i18n there is no language to filter by — the value is ignored');
        $this->assertSame(array(), $params);
    }

    private function api(bool $i18n): Api
    {
        $database = $this->createMock(Connection::class);
        $database->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturnCallback(function (string $sql, array $params = array()): array {
                $this->sent = array($sql, $params);

                return array();
            });

        $app = new Application();
        $app['orm.em']   = null;
        $app['database'] = $database;
        $app['schema']   = array(
            'TreeFixture' => array(
                'settings'   => array('i18n' => $i18n, 'dbname' => 'fixture_tree'),
                'properties' => array(
                    'id'    => array('type' => 'string'),
                    'title' => array('type' => 'string'),
                ),
            ),
        );

        return new Api($app);
    }
}
