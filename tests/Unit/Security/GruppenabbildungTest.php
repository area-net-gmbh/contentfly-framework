<?php
namespace Tests\Unit\Security;

use Areanet\PIM\Classes\Config;
use Areanet\PIM\Classes\Config\Factory;
use Areanet\PIM\Classes\Security\Fremdkennung;
use Areanet\PIM\Classes\Security\Gruppenabbildung;
use Areanet\PIM\Entity\Group;
use Areanet\PIM\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

/**
 * Was das Fremdsystem sagt, auf Contentfly-Gruppen abgebildet (013-004-0003).
 *
 * Vorher nahm `createManagedUser($alias, $group, $isAdmin)` beides als Argumente entgegen — jedes
 * Projekt entschied für sich, wie es von „der Benutzer ist in CN=Redaktion" zu einer
 * Contentfly-Gruppe kommt, und das Ergebnis stand in Projektcode, den niemand mehr liest.
 */
class GruppenabbildungTest extends TestCase
{
    protected function tearDown(): void
    {
        Factory::getInstance()->setConfig(new Config());
    }

    /** @param array<string, mixed> $abbildung */
    private function konfigurieren(array $abbildung): void
    {
        $config = new Config();
        $config->SECURITY_PROVIDER_GRUPPEN = $abbildung;

        Factory::getInstance()->setConfig($config);
    }

    /** @param array<string, Group> $gruppen */
    private function abbildung(array $gruppen = array()): Gruppenabbildung
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturnCallback(
            static fn (array $kriterien) => $gruppen[$kriterien['name'] ?? ''] ?? null
        );

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        return new Gruppenabbildung($em);
    }

    private function gruppe(string $name): Group
    {
        $gruppe = new Group();
        $gruppe->setName($name);

        return $gruppe;
    }

    private function benutzer(): User
    {
        $benutzer = new User();
        $benutzer->setAlias('ldap:mmustermann');

        return $benutzer;
    }

    // ── Treffer ────────────────────────────────────────────────────────────────────────

    public function testEineZugeordneteFremdgruppeSetztDieContentflyGruppe(): void
    {
        $redakteure = $this->gruppe('Redakteure');
        $this->konfigurieren(array('ldap' => array('gruppen' => array('CN=Redaktion' => 'Redakteure'))));

        $benutzer = $this->benutzer();
        $this->abbildung(array('Redakteure' => $redakteure))
            ->anwenden('ldap', new Fremdkennung('mmustermann', array('CN=Redaktion')), $benutzer);

        $this->assertSame($redakteure, $benutzer->getGroup());
    }

    public function testEineAdmingruppeSetztDasAdminflag(): void
    {
        $this->konfigurieren(array('ldap' => array('admin' => array('CN=Admins'))));

        $benutzer = $this->benutzer();
        $this->abbildung()->anwenden('ldap', new Fremdkennung('chef', array('CN=Admins')), $benutzer);

        $this->assertTrue($benutzer->getIsAdmin());
    }

    /**
     * **Die Reihenfolge ist eine Entscheidung.**
     *
     * Ein Benutzer kann in mehreren Fremdgruppen sein; Contentfly kennt genau eine Gruppe je
     * Benutzer. Welche gewinnt, steht in der Konfiguration und nicht in der Laune einer
     * Hashtabelle.
     */
    public function testBeiMehrerenTreffernGewinntDerErsteEintrag(): void
    {
        $erste  = $this->gruppe('Erste');
        $zweite = $this->gruppe('Zweite');

        $this->konfigurieren(array('ldap' => array('gruppen' => array(
            'CN=A' => 'Erste',
            'CN=B' => 'Zweite',
        ))));

        $benutzer = $this->benutzer();
        $this->abbildung(array('Erste' => $erste, 'Zweite' => $zweite))
            ->anwenden('ldap', new Fremdkennung('m', array('CN=B', 'CN=A')), $benutzer);

        $this->assertSame($erste, $benutzer->getGroup(), 'Die Reihenfolge der Konfiguration entscheidet');
    }

    // ── Nichttreffer ───────────────────────────────────────────────────────────────────

    /**
     * **Im Zweifel keine Rechte.** Eine Abbildung, die im Zweifel Rechte vergibt, ist die
     * falsche Richtung: Das Fremdsystem soll Rechte begründen, nicht ihr Fehlen.
     */
    public function testOhneTrefferGibtEsKeineAdminrechte(): void
    {
        $this->konfigurieren(array('ldap' => array('admin' => array('CN=Admins'))));

        $benutzer = $this->benutzer();
        $benutzer->setIsAdmin(true);

        $this->abbildung()->anwenden('ldap', new Fremdkennung('m', array('CN=Praktikanten')), $benutzer);

        $this->assertFalse($benutzer->getIsAdmin(), 'Einmal Administrator ist nicht immer Administrator');
    }

    public function testOhneTrefferGreiftDieVorgabe(): void
    {
        $gaeste = $this->gruppe('Gaeste');
        $this->konfigurieren(array('ldap' => array(
            'gruppen' => array('CN=Redaktion' => 'Redakteure'),
            'vorgabe' => 'Gaeste',
        )));

        $benutzer = $this->benutzer();
        $this->abbildung(array('Gaeste' => $gaeste))
            ->anwenden('ldap', new Fremdkennung('m', array('CN=Sonstige')), $benutzer);

        $this->assertSame($gaeste, $benutzer->getGroup());
    }

    /**
     * Ohne Treffer und ohne Vorgabe wird die Gruppe abgeräumt, nicht stehengelassen.
     *
     * Sonst behielte jemand die Rechte einer Gruppe, aus der ihn das Fremdsystem entfernt hat.
     */
    public function testOhneTrefferUndOhneVorgabeWirdDieGruppeAbgeraeumt(): void
    {
        $this->konfigurieren(array('ldap' => array('gruppen' => array('CN=Redaktion' => 'Redakteure'))));

        $benutzer = $this->benutzer();
        $benutzer->setGroup($this->gruppe('Redakteure'));

        $this->abbildung()->anwenden('ldap', new Fremdkennung('m', array()), $benutzer);

        $this->assertNull($benutzer->getGroup());
    }

    /**
     * Ohne Eintrag für diesen Anbieter passiert gar nichts — auch kein Abräumen.
     *
     * Wer keine Abbildung konfiguriert, verwaltet die Gruppen von Hand, und dann darf eine
     * Anmeldung sie nicht wegnehmen.
     */
    public function testOhneEintragFuerDenAnbieterPassiertNichts(): void
    {
        $redakteure = $this->gruppe('Redakteure');
        $this->konfigurieren(array('saml' => array('gruppen' => array('X' => 'Y'))));

        $benutzer = $this->benutzer();
        $benutzer->setGroup($redakteure);
        $benutzer->setIsAdmin(true);

        $this->abbildung()->anwenden('ldap', new Fremdkennung('m', array('X')), $benutzer);

        $this->assertSame($redakteure, $benutzer->getGroup());
        $this->assertTrue($benutzer->getIsAdmin());
    }

    // ── Fehlkonfiguration ──────────────────────────────────────────────────────────────

    /**
     * Eine Abbildung auf eine Gruppe, die es nicht gibt, bricht die Anmeldung ab.
     *
     * Sie stillschweigend zu ignorieren hiesse: Der Benutzer kommt herein und hat andere Rechte
     * als gedacht — und niemand erfährt, warum.
     */
    public function testEineUnbekannteZielgruppeSchlaegtLautDurch(): void
    {
        $this->konfigurieren(array('ldap' => array('gruppen' => array('CN=Redaktion' => 'GibtsNicht'))));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/GibtsNicht/');

        $this->abbildung()->anwenden('ldap', new Fremdkennung('m', array('CN=Redaktion')), $this->benutzer());
    }

    // ── Änderungen wirken bei der nächsten Anmeldung ───────────────────────────────────

    /**
     * Derselbe Benutzer, zweimal angemeldet, mit verschiedener Zuordnung.
     *
     * Genau deshalb stehen Rollen und Gruppen **nicht** im JWT (`013-003-0001`) — dort wären sie
     * bis zum Ablauf eingefroren.
     */
    public function testEineGeaenderteZuordnungWirktBeiDerNaechstenAnmeldung(): void
    {
        $redakteure = $this->gruppe('Redakteure');
        $gaeste     = $this->gruppe('Gaeste');

        $this->konfigurieren(array('ldap' => array(
            'gruppen' => array('CN=Redaktion' => 'Redakteure', 'CN=Extern' => 'Gaeste'),
            'admin'   => array('CN=Admins'),
        )));

        $abbildung = $this->abbildung(array('Redakteure' => $redakteure, 'Gaeste' => $gaeste));
        $benutzer  = $this->benutzer();

        $abbildung->anwenden('ldap', new Fremdkennung('m', array('CN=Redaktion', 'CN=Admins')), $benutzer);
        $this->assertSame($redakteure, $benutzer->getGroup());
        $this->assertTrue($benutzer->getIsAdmin());

        $abbildung->anwenden('ldap', new Fremdkennung('m', array('CN=Extern')), $benutzer);
        $this->assertSame($gaeste, $benutzer->getGroup());
        $this->assertFalse($benutzer->getIsAdmin());
    }
}
