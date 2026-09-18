<?php
namespace Tests\Unit\ORM;

use Areanet\PIM\Classes\Metadata\MetadataReader;
use Areanet\PIM\Entity\BaseI18nTree;
use Custom\Entity\Core\ExampleI18n;
use Doctrine\ORM\Mapping\Column;
use Doctrine\ORM\Mapping\GeneratedValue;
use Doctrine\ORM\Mapping\Id;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A redeclared property keeps the column of its parent declaration (000-000-0064).
 *
 * `BaseI18n` redeclares `$id` with its own `#[ORM\Id]` and `#[ORM\GeneratedValue('NONE')]` — an
 * i18n row gets its id from the main row. It carries NO `#[ORM\Column]`: the column comes from
 * `Base`, and ORM 3 rejects a second definition (010-003-0002). Doctrine inherits the column
 * mapping. The schema generation did not: it read only the attributes of the declaration itself,
 * found no column, matched no type — and `id` dropped out of the schema of every translatable
 * entity. `/api/insert` then rejected every translation with `unknown_property …::id`.
 */
class InheritedColumnTest extends TestCase
{
    /** @return array<string, array{0: class-string}> */
    public static function translatableEntities(): array
    {
        return array(
            'template entity on BaseI18n' => array(ExampleI18n::class),
            'BaseI18nTree, two levels further down' => array(BaseI18nTree::class),
        );
    }

    #[DataProvider('translatableEntities')]
    public function testTheIdOfATranslatableEntityCarriesTheColumnOfBase(string $class): void
    {
        $classes = $this->classesOf((new MetadataReader())->forProperty(new \ReflectionProperty($class, 'id')));

        $this->assertContains(Column::class, $classes, 'Without the column no type matches, and id leaves the schema');
    }

    public function testTheOwnIdAttributesOfTheRedeclarationStayInCharge(): void
    {
        // Only the column is inherited. The generator must stay the one of BaseI18n — Base's
        // UUID generator would give every language variant an id of its own.
        $attributes = (new MetadataReader())->forProperty(new \ReflectionProperty(ExampleI18n::class, 'id'));

        $generated = array_values(array_filter($attributes, fn (object $a): bool => $a instanceof GeneratedValue));

        $this->assertCount(1, $generated, 'Exactly one generator declaration, the own one');
        $this->assertSame('NONE', $generated[0]->strategy);
        $this->assertContains(Id::class, $this->classesOf($attributes));
    }

    public function testAPropertyWithItsOwnColumnKeepsExactlyThatOne(): void
    {
        // `title` declares its own column (length 255). Nothing may be added to it.
        $attributes = (new MetadataReader())->forProperty(new \ReflectionProperty(ExampleI18n::class, 'title'));

        $columns = array_values(array_filter($attributes, fn (object $a): bool => $a instanceof Column));

        $this->assertCount(1, $columns);
        $this->assertSame(255, $columns[0]->length);
    }

    /** @param object[] $attributes @return string[] */
    private function classesOf(array $attributes): array
    {
        return array_map('get_class', $attributes);
    }
}
