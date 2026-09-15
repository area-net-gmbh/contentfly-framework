<?php
namespace Tests\Integration\Database;

use Tests\Integration\IntegrationTestCase;

/**
 * Numbers from raw SQL arrive as strings, as they did on PHP 7.4 (000-000-0044).
 *
 * Since PHP 8.1 `pdo_mysql` returns native ints and floats for emulated prepares. Contentfly 1.x
 * clients compare those values strictly with strings; the framework therefore sets
 * `ATTR_STRINGIFY_FETCHES` on every connection. The second half of the guarantee is that entity
 * hydration does not change with it — Doctrine converts through the mapped types.
 *
 * Run in a subprocess, like `ContainerKeysTest`: building the application defines constants and
 * would alter every test that runs after it.
 */
class RawSqlTypesTest extends IntegrationTestCase
{
    public function testNumbersFromRawSqlAreStringsOnBothConnections(): void
    {
        $result = $this->inApplication(<<<'PHP'
$sql = 'SELECT 1 AS i, 1.5 AS d, CAST(2.5 AS DOUBLE) AS f, NULL AS n';
$out = [];
foreach (['database', 'db'] as $key) {
    $out[$key . '.executeQuery'] = $app[$key]->executeQuery($sql)->fetchAssociative();
    $out[$key . '.prepared'] = $app[$key]->prepare($sql . ' FROM DUAL WHERE 1 = ?')->executeQuery([1])->fetchAssociative();
}
echo json_encode($out);
PHP);

        foreach (array('database', 'db') as $key) {
            foreach (array('executeQuery', 'prepared') as $way) {
                $this->assertSame(
                    array('i' => '1', 'd' => '1.5', 'f' => '2.5', 'n' => null),
                    $result["$key.$way"],
                    "\$app['$key'] via $way returns numbers as strings, NULL stays NULL"
                );
            }
        }
    }

    public function testEntityHydrationKeepsTheMappedTypes(): void
    {
        $result = $this->inApplication(<<<'PHP'
$db       = $app['database'];
$previous = $db->executeQuery("SELECT views FROM pim_user WHERE alias = 'admin'")->fetchOne();
$db->executeStatement("UPDATE pim_user SET views = 7 WHERE alias = 'admin'");
try {
    $user = $app['orm.em']->getRepository('Areanet\PIM\Entity\User')->findOneBy(['alias' => 'admin']);
    echo json_encode([
        'isActive' => get_debug_type($user->getIsActive()),
        'views'    => [get_debug_type($user->getViews()), $user->getViews()],
    ]);
} finally {
    $db->executeStatement('UPDATE pim_user SET views = ? WHERE alias = ?', [$previous === false ? null : $previous, 'admin']);
}
PHP);

        $this->assertSame(array('isActive' => 'bool', 'views' => array('int', 7)), $result,
            'Doctrine converts through the mapped types; stringified fetches do not reach the entity');
    }

    /**
     * Runs a snippet against the built application and returns its JSON output.
     *
     * @return array<string,mixed>
     */
    private function inApplication(string $snippet): array
    {
        $project = self::applicationDir();
        $script  = 'require ' . var_export($project . '/vendor/autoload.php', true) . '; '
            . '$app = \Areanet\PIM\Classes\Kernel\Start::console(' . var_export($project, true) . '); '
            . $snippet;

        $output = array();
        $code   = 0;
        exec(sprintf('%s -r %s 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($script)), $output, $code);

        $raw = trim(implode("\n", $output));
        $this->assertSame(0, $code, "The application could not run the snippet:\n" . $raw);

        $decoded = json_decode($raw, true);
        $this->assertIsArray($decoded, "Not JSON:\n" . $raw);

        return $decoded;
    }
}
