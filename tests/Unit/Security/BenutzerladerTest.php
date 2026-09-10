<?php
namespace Tests\Unit\Security;

use Areanet\PIM\Classes\Security\Benutzerlader;
use Areanet\PIM\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

/**
 * Der Benutzerlader (013-002-0001).
 *
 * Er ist der Gegenpart zum TokenHandler: Der liefert eine Kennung, dieser macht daraus einen
 * Benutzer. Geprüft wird gegen einen Repository-Doppelgänger — die Abfrage selbst ist eine Zeile
 * Doctrine, das Verhalten drumherum ist der Punkt.
 */
class BenutzerladerTest extends TestCase
{
    private function lader(?User $gefunden): Benutzerlader
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($gefunden);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        return new Benutzerlader($em);
    }

    private function benutzer(string $alias, bool $aktiv = true): User
    {
        $benutzer = new User();
        $benutzer->setAlias($alias);
        $benutzer->setIsActive($aktiv);

        return $benutzer;
    }

    public function testEinBekannterBenutzerWirdGeladen(): void
    {
        $geladen = $this->lader($this->benutzer('admin'))->loadUserByIdentifier('admin');

        $this->assertSame('admin', $geladen->getUserIdentifier());
    }

    public function testEinUnbekannterBenutzerWirdAbgewiesen(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->lader(null)->loadUserByIdentifier('gibtesnicht');
    }

    /**
     * Ein gesperrter Benutzer wird behandelt wie ein unbekannter, **mit derselben Ausnahme**.
     *
     * Kein Versehen: Die Story verlangt, dass ein ungültiges Token ununterscheidbar scheitert.
     * Eine eigene Ausnahme für „gesperrt" wäre ein Orakel dafür, welche Konten es gibt und
     * welche gerade abgeschaltet sind.
     */
    public function testEinGesperrterBenutzerWirdWieEinUnbekannterAbgewiesen(): void
    {
        $this->expectException(UserNotFoundException::class);

        $this->lader($this->benutzer('gesperrt', false))->loadUserByIdentifier('gesperrt');
    }

    public function testDerLaderIstFuerDieBenutzerEntityZustaendig(): void
    {
        $lader = $this->lader(null);

        $this->assertTrue($lader->supportsClass(User::class));
        $this->assertFalse($lader->supportsClass(\stdClass::class));
    }

    // ── Der Benutzer als Symfony-Benutzer ──────────────────────────────────────────────

    public function testDieKennungIstDerAlias(): void
    {
        $this->assertSame('admin', $this->benutzer('admin')->getUserIdentifier());
    }

    /**
     * Abgebildet wird **nur**, was der Zugriffsschutz braucht.
     *
     * `Permission`, `I18nPermission` und `Group` bleiben, wo sie sind — zwei Berechtigungsmodelle
     * nebeneinander laufen auseinander.
     */
    public function testDieRollenBildenNurDenZugriffsschutzAb(): void
    {
        $normal = $this->benutzer('redakteur');
        $this->assertSame(array('ROLE_USER'), $normal->getRoles());

        $admin = $this->benutzer('admin');
        $admin->setIsAdmin(true);
        $this->assertSame(array('ROLE_USER', 'ROLE_ADMIN'), $admin->getRoles());
    }
}
