<?php
namespace Tests\Unit\Security;

use Areanet\PIM\Classes\Security\LoginThrottle;
use Areanet\PIM\Controller\AuthController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Die LoginThrottle, ohne HTTP (013-001-0003).
 *
 * Was hier steht, ist die Mechanik: zwei Achsen, ansteigende Verzoegerung, Ruecksetzen nur auf
 * der einen. Dass sie am Login auch wirklich haengt, misst `AnmeldebremseApiTest` gegen eine
 * laufende Instanz.
 *
 * Der Speicher ist ein `ArrayAdapter` — jeder Test bekommt einen frischen, es gibt nichts
 * aufzuraeumen, und die Zeit spielt keine Rolle: Kein Test wartet ein Fenster ab.
 */
class LoginThrottleTest extends TestCase
{
    /** Fuenf Fehlversuche pro Kennung und Minute. */
    private const GRENZE_KENNUNG = 5;

    /** Zwanzig pro IP und Minute — weiter gefasst, weil hinter einer Adresse viele sitzen. */
    private const GRENZE_IP = 20;

    private function bremse(): LoginThrottle
    {
        return new LoginThrottle(new ArrayAdapter());
    }

    // ── Die Achse Kennung ──────────────────────────────────────────────────────────────

    public function testFrischIstNichtsGebremst(): void
    {
        $this->assertNull($this->bremse()->retryAfter('admin', '10.0.0.1'));
    }

    public function testNachDerGrenzeWirdGebremst(): void
    {
        $bremse = $this->bremse();

        for ($i = 1; $i <= self::GRENZE_KENNUNG - 1; $i++) {
            $bremse->recordFailure('admin', '10.0.0.1');
            $this->assertNull($bremse->retryAfter('admin', '10.0.0.1'), "nach $i Fehlversuchen noch frei");
        }

        $bremse->recordFailure('admin', '10.0.0.1');

        $this->assertNotNull($bremse->retryAfter('admin', '10.0.0.1'));
    }

    /**
     * Die Achse Kennung greift unabhaengig von der Adresse — sonst waere sie durch einen
     * Adresswechsel zu umgehen, und genau dafuer gibt es Botnetze.
     */
    public function testDieKennungWirdAuchVonEinerAnderenAdresseAusGebremst(): void
    {
        $bremse = $this->bremse();

        for ($i = 1; $i <= self::GRENZE_KENNUNG; $i++) {
            $bremse->recordFailure('admin', '10.0.0.'.$i);
        }

        $this->assertNotNull($bremse->retryAfter('admin', '198.51.100.99'));
    }

    /**
     * Gross- und Kleinschreibung vervielfacht die Grenze nicht.
     */
    public function testDieSchreibweiseDerKennungUmgehtNichts(): void
    {
        $bremse = $this->bremse();

        for ($i = 1; $i <= self::GRENZE_KENNUNG; $i++) {
            $bremse->recordFailure('admin', '10.0.0.'.$i);
        }

        $this->assertNotNull($bremse->retryAfter('ADMIN', '198.51.100.99'));
    }

    public function testEineAndereKennungBleibtFrei(): void
    {
        $bremse = $this->bremse();

        for ($i = 1; $i <= self::GRENZE_KENNUNG; $i++) {
            $bremse->recordFailure('admin', '10.0.0.1');
        }

        $this->assertNull($bremse->retryAfter('redakteur', '10.0.0.1'));
    }

    // ── Die Achse IP ───────────────────────────────────────────────────────────────────

    /**
     * Wechselnde Kennungen von einer Adresse: Die Achse Kennung sieht jede nur einmal, die
     * Achse IP zaehlt sie alle.
     */
    public function testWechselndeKennungenVonEinerAdresseWerdenGebremst(): void
    {
        $bremse = $this->bremse();

        for ($i = 1; $i <= self::GRENZE_IP; $i++) {
            $bremse->recordFailure('niemand'.$i, '10.0.0.1');
        }

        $this->assertNotNull($bremse->retryAfter('nochjemand', '10.0.0.1'));
        $this->assertNull($bremse->retryAfter('nochjemand', '198.51.100.99'), 'Eine andere Adresse bleibt frei');
    }

    // ── Ruecksetzen ────────────────────────────────────────────────────────────────────

    public function testEineGelungeneAnmeldungLoeschtDenZaehlerDerKennung(): void
    {
        $bremse = $this->bremse();

        for ($i = 1; $i <= self::GRENZE_KENNUNG; $i++) {
            $bremse->recordFailure('admin', '10.0.0.1');
        }
        $this->assertNotNull($bremse->retryAfter('admin', '10.0.0.1'));

        $bremse->reset('admin');

        $this->assertNull($bremse->retryAfter('admin', '10.0.0.1'));
    }

    /**
     * Der Zaehler der Adresse bleibt stehen.
     *
     * Sonst genuegte dem Angreifer ein einziges gueltiges Konto — sein eigenes —, um sich nach
     * jedem Block wieder freizuschalten.
     */
    public function testDerZaehlerDerAdresseBleibtStehen(): void
    {
        $bremse = $this->bremse();

        for ($i = 1; $i <= self::GRENZE_IP; $i++) {
            $bremse->recordFailure('niemand'.$i, '10.0.0.1');
        }

        $bremse->reset('nochjemand');

        $this->assertNotNull($bremse->retryAfter('nochjemand', '10.0.0.1'));
    }

    // ── Die Pruefung verbraucht nichts ─────────────────────────────────────────────────

    /**
     * Wuerde `wartezeit()` selbst zaehlen, verlaengerte jeder abgewiesene Versuch die Sperre —
     * der Block liefe nie ab, und ein Angreifer koennte ein fremdes Konto dauerhaft sperren,
     * indem er weiter gegen die geschlossene Tuer laeuft.
     */
    public function testDiePruefungVerbrauchtNichts(): void
    {
        $bremse = $this->bremse();

        for ($i = 1; $i <= self::GRENZE_KENNUNG - 1; $i++) {
            $bremse->recordFailure('admin', '10.0.0.1');
        }

        for ($i = 1; $i <= 50; $i++) {
            $this->assertNull($bremse->retryAfter('admin', '10.0.0.1'));
        }
    }

    // ── Ansteigende Verzoegerung ───────────────────────────────────────────────────────

    /**
     * Drei Fenster uebereinander: eine Minute, eine Viertelstunde, eine Stunde.
     *
     * Gemessen wird nur die Achse Kennung — jeder Fehlversuch kommt von einer anderen Adresse,
     * damit die Achse IP nicht mitzaehlt und die laengere der beiden Wartezeiten meldet.
     */
    public function testDieWartezeitWaechstMitDerHartnaeckigkeit(): void
    {
        $bremse = $this->bremse();

        for ($i = 1; $i <= 5; $i++) {
            $bremse->recordFailure('admin', '10.0.'.$i.'.1');
        }
        $ersteStufe = $bremse->retryAfter('admin', null);

        for ($i = 6; $i <= 20; $i++) {
            $bremse->recordFailure('admin', '10.0.'.$i.'.1');
        }
        $zweiteStufe = $bremse->retryAfter('admin', null);

        for ($i = 21; $i <= 50; $i++) {
            $bremse->recordFailure('admin', '10.0.'.$i.'.1');
        }
        $dritteStufe = $bremse->retryAfter('admin', null);

        $this->assertNotNull($ersteStufe);
        $this->assertNotNull($zweiteStufe);
        $this->assertNotNull($dritteStufe);
        $this->assertGreaterThan($ersteStufe, $zweiteStufe);
        $this->assertGreaterThan($zweiteStufe, $dritteStufe);
    }

    // ── Was entfallen ist ──────────────────────────────────────────────────────────────

    /**
     * `CHECK_LOGIN_INTERVAL` war eine `false`-Konstante — der Zweig dahinter lief nie.
     *
     * Der Test steht hier und nicht als Kommentar, weil ein toter Zweig, den man stehen laesst,
     * beim naechsten Lesen wie eine vorhandene Sicherung aussieht.
     */
    public function testDieAltenIntervallKonstantenGibtEsNichtMehr(): void
    {
        $konstanten = (new \ReflectionClass(AuthController::class))->getConstants();

        $this->assertArrayNotHasKey('CHECK_LOGIN_INTERVAL', $konstanten);
        $this->assertArrayNotHasKey('MIN_LOGIN_INTERVAL', $konstanten);
    }
}
