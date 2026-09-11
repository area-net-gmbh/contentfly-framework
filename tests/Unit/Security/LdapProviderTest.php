<?php
namespace Tests\Unit\Security;

use Areanet\PIM\Classes\Security\Fremdkennung;
use Areanet\PIM\Classes\Security\LdapProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Ldap\Adapter\CollectionInterface;
use Symfony\Component\Ldap\Adapter\QueryInterface;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Exception\ConnectionException;
use Symfony\Component\Ldap\LdapInterface;

/**
 * Der LDAP-Provider (013-005-0001).
 *
 * **Einschränkung, ausdrücklich:** Geprüft wird gegen einen `LdapInterface`-Doppelgänger, nicht
 * gegen ein laufendes Verzeichnis. Das misst die eigene Logik — Bind-Reihenfolge, Maskierung des
 * Filters, was in die `Fremdkennung` geht — und **nicht**, dass ein echter Bind gegen ein Active
 * Directory funktioniert. So entschieden am 2026-09-11: Ein OpenLDAP-Dienst in der Testumgebung
 * stünde in keinem Verhältnis zu dem, was er zusätzlich belegte.
 */
class LdapProviderTest extends TestCase
{
    /** Alle Aufrufe, die der Provider am Verzeichnis gemacht hat — in ihrer Reihenfolge. */
    private array $aufrufe = array();

    private function einstellungen(array $abweichend = array()): array
    {
        return $abweichend + array(
            'base_dn'          => 'OU=Benutzer,DC=example,DC=invalid',
            'filter'           => '(sAMAccountName={kennung})',
            'gruppen_attribut' => 'memberOf',
            'search_dn'        => 'CN=dienst,DC=example,DC=invalid',
            'search_password'  => 'dienst-geheim',
        );
    }

    /**
     * Ein Verzeichnis-Doppelgänger.
     *
     * @param list<Entry>|null $treffer  null = die Suche wirft
     * @param list<string>     $bindFehlerFuer DNs, deren Bind scheitert
     */
    private function ldap(?array $treffer, array $bindFehlerFuer = array()): LdapInterface
    {
        $this->aufrufe = array();
        $aufrufe = &$this->aufrufe;

        $sammlung = $this->createMock(CollectionInterface::class);
        $sammlung->method('count')->willReturn($treffer === null ? 0 : count($treffer));
        $sammlung->method('offsetGet')->willReturnCallback(
            static fn ($i) => $treffer[$i] ?? null
        );

        $query = $this->createMock(QueryInterface::class);
        if ($treffer === null) {
            $query->method('execute')->willThrowException(new ConnectionException('Verzeichnis nicht erreichbar'));
        } else {
            $query->method('execute')->willReturn($sammlung);
        }

        $ldap = $this->createMock(LdapInterface::class);
        $ldap->method('bind')->willReturnCallback(
            static function (?string $dn = null, ?string $pass = null) use (&$aufrufe, $bindFehlerFuer) {
                $aufrufe[] = array('bind', $dn, $pass);

                if (in_array((string) $dn, $bindFehlerFuer, true)) {
                    throw new ConnectionException('Bind abgelehnt');
                }
            }
        );
        /*
         * `strtr()` und nicht `str_replace()` mit Arrays.
         *
         * Der erste Entwurf nahm `str_replace`, und der arbeitet die Paare NACHEINANDER ab: Die
         * letzte Regel (`\\` -> `\\5c`) maskierte noch einmal die Backslashes, die die
         * vorherigen gerade eingefuegt hatten — aus `\\28` wurde `\\5c28`. Ein Fehler im
         * Doppelgaenger, nicht im Provider, aber er haette den Maskierungstest unbrauchbar
         * gemacht. `strtr()` ersetzt in einem Durchgang.
         */
        $ldap->method('escape')->willReturnCallback(
            static fn (string $wert) => strtr($wert, array('\\' => '\\5c', '*' => '\\2a', '(' => '\\28', ')' => '\\29'))
        );
        $ldap->method('query')->willReturnCallback(
            static function (string $dn, string $filter) use (&$aufrufe, $query) {
                $aufrufe[] = array('query', $dn, $filter);

                return $query;
            }
        );

        return $ldap;
    }

    private function eintrag(array $attribute = array('memberOf' => array('CN=Redaktion,DC=example,DC=invalid'))): Entry
    {
        return new Entry('CN=Max Mustermann,OU=Benutzer,DC=example,DC=invalid', $attribute);
    }

    private function request(?string $alias = 'mmustermann', ?string $pass = 'geheim'): Request
    {
        $daten = array();
        if ($alias !== null) { $daten['alias'] = $alias; }
        if ($pass !== null)  { $daten['pass']  = $pass; }

        return new Request(array(), $daten);
    }

    // ── Der gelungene Weg ──────────────────────────────────────────────────────────────

    public function testEineGelungeneAnmeldungLiefertKennungUndGruppen(): void
    {
        $provider = new LdapProvider($this->ldap(array($this->eintrag())), $this->einstellungen());

        $fremd = $provider->pruefen($this->request());

        $this->assertInstanceOf(Fremdkennung::class, $fremd);
        $this->assertSame('mmustermann', $fremd->kennung);
        $this->assertSame(array('CN=Redaktion,DC=example,DC=invalid'), $fremd->gruppen);
    }

    /**
     * **Suchen, dann binden** — und zwar mit dem DN aus dem Verzeichnis, nicht mit einem
     * zusammengesetzten.
     *
     * Der direkte Bind käme ohne Dienstkonto aus, funktioniert aber nur, solange alle Benutzer
     * flach in einer OU liegen. Im Active Directory tun sie das nicht, und angemeldet wird mit
     * `sAMAccountName`, der im DN gar nicht vorkommt.
     */
    public function testDerProviderBindetZweimalUndSuchtDazwischen(): void
    {
        $provider = new LdapProvider($this->ldap(array($this->eintrag())), $this->einstellungen());
        $provider->pruefen($this->request());

        $this->assertSame('bind', $this->aufrufe[0][0]);
        $this->assertSame('CN=dienst,DC=example,DC=invalid', $this->aufrufe[0][1], 'Erst das Dienstkonto');

        $this->assertSame('query', $this->aufrufe[1][0]);
        $this->assertSame('OU=Benutzer,DC=example,DC=invalid', $this->aufrufe[1][1]);

        $this->assertSame('bind', $this->aufrufe[2][0]);
        $this->assertSame('CN=Max Mustermann,OU=Benutzer,DC=example,DC=invalid', $this->aufrufe[2][1],
            'Dann der DN aus dem Verzeichnis');
        $this->assertSame('geheim', $this->aufrufe[2][2]);
    }

    public function testOhneDienstkontoWirdAnonymGesucht(): void
    {
        $provider = new LdapProvider(
            $this->ldap(array($this->eintrag())),
            $this->einstellungen(array('search_dn' => null, 'search_password' => null))
        );
        $provider->pruefen($this->request());

        $this->assertSame(array('bind', null, null), $this->aufrufe[0]);
    }

    // ── Das leere Passwort ─────────────────────────────────────────────────────────────

    /**
     * **Die wichtigste Zusicherung dieser Klasse.**
     *
     * LDAP kennt den „unauthenticated bind": Ein Bind mit gültigem DN und leerem Passwort gilt
     * als erfolgreich — er bedeutet „ich will mich nicht anmelden", nicht „das Passwort stimmt".
     * Wer das als Anmeldung liest, lässt jeden herein, dessen Kennung er kennt.
     *
     * Der Test prüft deshalb beides: dass abgelehnt wird, **und** dass das Verzeichnis gar nicht
     * erst gefragt wurde.
     */
    public function testEinLeeresPasswortWirdAbgewiesenOhneDasVerzeichnisZuFragen(): void
    {
        $provider = new LdapProvider($this->ldap(array($this->eintrag())), $this->einstellungen());

        $this->assertNull($provider->pruefen($this->request('mmustermann', '')));
        $this->assertSame(array(), $this->aufrufe, 'Kein einziger Aufruf am Verzeichnis');
    }

    public function testOhneKennungWirdAbgewiesen(): void
    {
        $provider = new LdapProvider($this->ldap(array($this->eintrag())), $this->einstellungen());

        $this->assertNull($provider->pruefen($this->request(null, 'geheim')));
        $this->assertNull($provider->pruefen($this->request('   ', 'geheim')));
        $this->assertSame(array(), $this->aufrufe);
    }

    // ── Die Maskierung ─────────────────────────────────────────────────────────────────

    /**
     * Ohne Maskierung trägt eine Kennung wie `admin)(|(objectClass=*` den Filter um —
     * LDAP-Injection, dasselbe Muster wie SQL-Injection und genauso alt.
     */
    public function testDieKennungWirdInDenFilterMaskiert(): void
    {
        $provider = new LdapProvider($this->ldap(array($this->eintrag())), $this->einstellungen());
        $provider->pruefen($this->request('admin)(|(objectClass=*', 'geheim'));

        $filter = $this->aufrufe[1][2];

        $this->assertStringNotContainsString('(|(objectClass=', $filter, 'Der Filter ist nicht umgebogen');
        $this->assertStringContainsString('\\28', $filter, 'Die Klammer ist maskiert');
    }

    // ── Abweisungen, alle gleich ───────────────────────────────────────────────────────

    public function testEineUnbekannteKennungWirdAbgewiesen(): void
    {
        $provider = new LdapProvider($this->ldap(array()), $this->einstellungen());

        $this->assertNull($provider->pruefen($this->request()));
    }

    /**
     * Zwei Treffer heissen, dass der Filter nicht eindeutig ist. Dann zu raten, welcher gemeint
     * war, wäre die schlechteste aller Antworten.
     */
    public function testZweiTrefferWerdenAbgewiesen(): void
    {
        $provider = new LdapProvider($this->ldap(array($this->eintrag(), $this->eintrag())), $this->einstellungen());

        $this->assertNull($provider->pruefen($this->request()));
    }

    public function testEinFalschesPasswortWirdAbgewiesen(): void
    {
        $ldap = $this->ldap(
            array($this->eintrag()),
            array('CN=Max Mustermann,OU=Benutzer,DC=example,DC=invalid')
        );

        $this->assertNull((new LdapProvider($ldap, $this->einstellungen()))->pruefen($this->request()));
    }

    public function testEinNichtErreichbaresVerzeichnisWirdAbgewiesen(): void
    {
        $provider = new LdapProvider($this->ldap(null), $this->einstellungen());

        $this->assertNull($provider->pruefen($this->request()));
    }

    public function testEinAbgelehntesDienstkontoWirdAbgewiesen(): void
    {
        $ldap = $this->ldap(array($this->eintrag()), array('CN=dienst,DC=example,DC=invalid'));

        $this->assertNull((new LdapProvider($ldap, $this->einstellungen()))->pruefen($this->request()));
    }

    // ── Gruppen ────────────────────────────────────────────────────────────────────────

    public function testOhneGruppenattributKommenKeineGruppen(): void
    {
        $provider = new LdapProvider($this->ldap(array($this->eintrag(array()))), $this->einstellungen());

        $fremd = $provider->pruefen($this->request());

        $this->assertInstanceOf(Fremdkennung::class, $fremd);
        $this->assertSame(array(), $fremd->gruppen);
    }

    /**
     * Was das Verzeichnis liefert, geht **unverändert** weiter. Abgebildet wird es von
     * `Gruppenabbildung` (`013-004-0003`) — hier etwas umzuschreiben hiesse, die Abbildung an
     * zwei Stellen zu haben.
     */
    public function testDieGruppenGehenUnveraendertWeiter(): void
    {
        $roh = array('CN=Redaktion,OU=Gruppen,DC=example,DC=invalid', 'CN=Alle,DC=example,DC=invalid');
        $provider = new LdapProvider($this->ldap(array($this->eintrag(array('memberOf' => $roh)))), $this->einstellungen());

        $this->assertSame($roh, $provider->pruefen($this->request())->gruppen);
    }
}
