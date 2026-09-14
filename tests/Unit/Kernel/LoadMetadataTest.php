<?php
namespace Tests\Unit\Kernel;

use Areanet\PIM\Classes\Events\LoadMetadata;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use PHPUnit\Framework\TestCase;

/**
 * The index on `modified` — and when it is left out (`000-000-0028`).
 *
 * Until then the listener attached it **unconditionally** to every entity that is not a tree. If
 * the column was missing, even the installation failed, with a message that did not name the reason:
 *
 * > There is no column with name "modified" on table "pim_revoked_token".
 *
 * Found in `013-003-0003`. Now it is skipped instead of aborted, and this test records
 * which of the two applies.
 */
class LoadMetadataTest extends TestCase
{
    /** @param list<string> $fields */
    private function apply(array $fields, array $parents = array()): ClassMetadata
    {
        $metadata = new ClassMetadata('Tests\\Example');
        $metadata->parentClasses = $parents;

        foreach ($fields as $field) {
            $metadata->mapField(array('fieldName' => $field, 'type' => 'datetime'));
        }

        $factory = $this->createMock(ClassMetadataFactory::class);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($factory);

        (new LoadMetadata())->loadClassMetadata(new LoadClassMetadataEventArgs($metadata, $em));

        return $metadata;
    }

    private function hasIndex(ClassMetadata $metadata): bool
    {
        return isset($metadata->table['indexes']['modified_index']);
    }

    public function testAnEntityWithModifiedGetsTheIndex(): void
    {
        $metadata = $this->apply(array('modified'));

        $this->assertTrue($this->hasIndex($metadata));
        $this->assertSame(array('modified'), $metadata->table['indexes']['modified_index']['columns']);
    }

    /**
     * **The core of the task.** No field, no index — and above all no abort.
     */
    public function testAnEntityWithoutModifiedIsSkipped(): void
    {
        $metadata = $this->apply(array('created'));

        $this->assertFalse($this->hasIndex($metadata));
    }

    /**
     * Trees have been excluded for as long as the listener has existed: `BaseTree` and `BaseI18nTree`
     * bring their own indexes, and an additional one would be a duplicate there.
     */
    public function testTreesRemainExcluded(): void
    {
        $tree = $this->apply(array('modified'), array('Areanet\\PIM\\Entity\\BaseTree'));
        $this->assertFalse($this->hasIndex($tree));

        $i18nTree = $this->apply(array('modified'), array('Areanet\\PIM\\Entity\\BaseI18nTree'));
        $this->assertFalse($this->hasIndex($i18nTree));
    }
}
