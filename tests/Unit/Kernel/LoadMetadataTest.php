<?php
namespace Tests\Unit\Kernel;

use Areanet\PIM\Classes\Events\LoadMetadata;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LoadClassMetadataEventArgs;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\ClassMetadataFactory;
use PHPUnit\Framework\TestCase;

/**
 * Der Index auf `modified` — und wann er ausbleibt (`000-000-0028`).
 *
 * Der Listener hängte ihn bis dahin **bedingungslos** an jede Entity, die kein Tree ist. Fehlte
 * die Spalte, scheiterte schon die Installation, mit einer Meldung, die den Grund nicht nannte:
 *
 * > There is no column with name "modified" on table "pim_revoked_token".
 *
 * Gefunden bei `013-003-0003`. Jetzt wird übersprungen statt abgebrochen, und dieser Test hält
 * fest, welches von beidem gilt.
 */
class LoadMetadataTest extends TestCase
{
    /** @param list<string> $felder */
    private function anwenden(array $felder, array $eltern = array()): ClassMetadata
    {
        $metadata = new ClassMetadata('Tests\\Beispiel');
        $metadata->parentClasses = $eltern;

        foreach ($felder as $feld) {
            $metadata->mapField(array('fieldName' => $feld, 'type' => 'datetime'));
        }

        $fabrik = $this->createMock(ClassMetadataFactory::class);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getMetadataFactory')->willReturn($fabrik);

        (new LoadMetadata())->loadClassMetadata(new LoadClassMetadataEventArgs($metadata, $em));

        return $metadata;
    }

    private function hatIndex(ClassMetadata $metadata): bool
    {
        return isset($metadata->table['indexes']['modified_index']);
    }

    public function testEineEntityMitModifiedBekommtDenIndex(): void
    {
        $metadata = $this->anwenden(array('modified'));

        $this->assertTrue($this->hatIndex($metadata));
        $this->assertSame(array('modified'), $metadata->table['indexes']['modified_index']['columns']);
    }

    /**
     * **Der Kern des Tasks.** Kein Feld, kein Index — und vor allem kein Abbruch.
     */
    public function testEineEntityOhneModifiedWirdUebersprungen(): void
    {
        $metadata = $this->anwenden(array('created'));

        $this->assertFalse($this->hatIndex($metadata));
    }

    /**
     * Bäume sind ausgenommen, seit es den Listener gibt: `BaseTree` und `BaseI18nTree` bringen
     * eigene Indizes mit, und ein zusätzlicher wäre dort doppelt.
     */
    public function testBaeumeBleibenAusgenommen(): void
    {
        $baum = $this->anwenden(array('modified'), array('Areanet\\PIM\\Entity\\BaseTree'));
        $this->assertFalse($this->hatIndex($baum));

        $i18nBaum = $this->anwenden(array('modified'), array('Areanet\\PIM\\Entity\\BaseI18nTree'));
        $this->assertFalse($this->hatIndex($i18nBaum));
    }
}
