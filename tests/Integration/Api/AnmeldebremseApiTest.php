<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * Die LoginThrottle am laufenden Login (013-001-0003).
 *
 * `LoginThrottleTest` misst die Mechanik ohne HTTP. Hier geht es um die andere Haelfte: dass
 * sie an `/auth/login` tatsaechlich haengt, dass sie vor der Passwortpruefung greift und dass
 * ihre Antwort nichts ueber die Kennung verraet.
 *
 * DER SPEICHER WIRD VOR UND NACH JEDEM TEST GELEERT. Die Bremse zaehlt ueber Requests hinweg —
 * ohne Aufraeumen truege ein Test seine verbrauchten Versuche in den naechsten, und die
 * Reihenfolge der Suite entschiede ueber das Ergebnis. Umgekehrt sollen die zwanzig
 * Fehlversuche aus dem IP-Test nicht die uebrige Suite ausbremsen, die sich von derselben
 * Adresse aus anmeldet.
 *
 * Das setzt voraus, dass Testlauf und Testserver auf derselben Maschine liegen — so baut
 * `tools/ci/prepare-test-environment.sh` die Umgebung auf, lokal wie in der Pipeline. Und dass
 * `APP_CACHE_DRIVER` auf `filesystem` steht, der Vorgabe; sonst liegt der Zaehler in apcu oder
 * memcached und dieses Verzeichnis ist leer. Beides wird geprueft, statt es anzunehmen.
 */
class AnmeldebremseApiTest extends IntegrationTestCase
{
    /** Fuenf Fehlversuche pro Kennung und Minute — wie in LoginThrottle::TIERS_IDENTIFIER. */
    private const GRENZE_KENNUNG = 5;

    /** Zwanzig pro IP und Minute. */
    private const GRENZE_IP = 20;

    private const MELDUNG = 'Zu viele Anmeldeversuche. Bitte später erneut versuchen.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->bremsspeicherLeeren();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    // ── Pro Kennung ────────────────────────────────────────────────────────────────────

    public function testNachFuenfFehlversuchenWirdDieKennungGebremst(): void
    {
        for ($i = 1; $i <= self::GRENZE_KENNUNG; $i++) {
            [$status] = $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => 'falsch'.$i));
            $this->assertSame(401, $status, "Versuch $i muss noch als Fehlversuch beantwortet werden");
        }

        [$status, $body, $kopf] = $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => 'falsch'));

        $this->assertSame(429, $status);
        $this->assertSame(self::MELDUNG, $body['message'] ?? null);
        $this->assertArrayNotHasKey('token', $body);
        $this->assertNotNull($this->kopfzeile($kopf, 'Retry-After'), 'Wie lange zu warten ist, steht in der Antwort');
    }

    /**
     * DIE BREMSE GREIFT VOR DER PASSWORTPRUEFUNG.
     *
     * Nachgewiesen mit dem RICHTIGEN Passwort: Wer gebremst ist, bekommt 429 und keinen Token,
     * obwohl die Zugangsdaten stimmen. Stuende die Bremse hinter der Pruefung, kaeme hier eine
     * 200 zurueck — und jeder abgewiesene Versuch kostete weiterhin einen Argon2id-Durchlauf.
     */
    public function testWerGebremstIstKommtAuchMitRichtigemPasswortNichtDurch(): void
    {
        for ($i = 1; $i <= self::GRENZE_KENNUNG; $i++) {
            $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => 'falsch'.$i));
        }

        [$status, $body] = $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => $this->pass()));

        $this->assertSame(429, $status);
        $this->assertArrayNotHasKey('token', $body);
    }

    /**
     * Die Bremse darf kein Orakel dafuer sein, welche Konten es gibt.
     *
     * Eine erfundene Kennung wird genauso gebremst wie eine echte, mit derselben Antwort. Waere
     * es anders, muesste ein Angreifer nur zaehlen, ab wann gebremst wird, um die Benutzerliste
     * abzugreifen.
     */
    public function testDieBremseVerraetNichtObDieKennungExistiert(): void
    {
        for ($i = 1; $i <= self::GRENZE_KENNUNG; $i++) {
            [$status] = $this->postJson('/auth/login', array('alias' => 'gibtesnicht', 'pass' => 'egal'.$i));
            $this->assertSame(401, $status);
        }
        [$statusUnbekannt, $unbekannt] = $this->postJson('/auth/login', array('alias' => 'gibtesnicht', 'pass' => 'egal'));

        for ($i = 1; $i <= self::GRENZE_KENNUNG; $i++) {
            $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => 'falsch'.$i));
        }
        [$statusEcht, $echt] = $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => 'falsch'));

        $this->assertSame(429, $statusUnbekannt, 'Auch eine erfundene Kennung wird gebremst');
        $this->assertSame(429, $statusEcht);
        $this->assertSame($echt['message'] ?? null, $unbekannt['message'] ?? null, 'Dieselbe Antwort fuer beide');
    }

    // ── Pro IP ─────────────────────────────────────────────────────────────────────────

    /**
     * Wechselnde Kennungen von derselben Adresse.
     *
     * Jede Kennung wird nur einmal probiert, die Achse Kennung bindet also nie — gebremst wird
     * hier allein ueber die Adresse. Genau dieser Fall lief unter `CHECK_LOGIN_INTERVAL`
     * vollkommen ungebremst durch, denn das Intervall galt pro Benutzer.
     */
    public function testWechselndeKennungenVonDerselbenAdresseWerdenGebremst(): void
    {
        for ($i = 1; $i <= self::GRENZE_IP; $i++) {
            [$status] = $this->postJson('/auth/login', array('alias' => 'niemand'.$i, 'pass' => 'egal'));
            $this->assertSame(401, $status, "Versuch $i muss noch als Fehlversuch beantwortet werden");
        }

        [$status, $body] = $this->postJson('/auth/login', array('alias' => 'nochjemand', 'pass' => 'egal'));

        $this->assertSame(429, $status);
        $this->assertSame(self::MELDUNG, $body['message'] ?? null);
    }

    // ── Ruecksetzen ────────────────────────────────────────────────────────────────────

    /**
     * Eine gelungene Anmeldung loescht den Zaehler der Kennung.
     *
     * Gemessen ueber die Grenze hinweg: Vier Fehlversuche, dann eine gelungene Anmeldung, dann
     * noch einmal vier Fehlversuche. Ohne Ruecksetzen waere spaetestens der zweite Durchgang
     * bei acht Fehlversuchen und damit gebremst.
     */
    public function testEineGelungeneAnmeldungLoeschtDenZaehler(): void
    {
        for ($i = 1; $i <= self::GRENZE_KENNUNG - 1; $i++) {
            $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => 'falsch'.$i));
        }

        [$status, $body] = $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => $this->pass()));
        $this->assertSame(200, $status);
        $this->assertArrayHasKey('token', $body);

        for ($i = 1; $i <= self::GRENZE_KENNUNG - 1; $i++) {
            [$status] = $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => 'falsch'.$i));
            $this->assertSame(401, $status, "Nach dem Ruecksetzen muss Versuch $i wieder durchgelassen werden");
        }

        [$status] = $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => $this->pass()));
        $this->assertSame(200, $status, 'Und die Anmeldung geht danach weiterhin');
    }

    // ── Aufraeumen ─────────────────────────────────────────────────────────────────────

    /**
     * Wie die geerbte Fassung, aber mit einer harten Vorbedingung.
     *
     * Die Basisklasse raeumt still auf, wenn sie kann — fuer sie ist es Hygiene. Hier ist es
     * die Voraussetzung der Messung: Laege der Speicher woanders, pruefte diese Klasse gegen
     * einen Zaehler, den sie nicht kennt, und waere gruen, ohne etwas zu belegen.
     */
    protected function bremsspeicherLeeren(): void
    {
        // Das Datenverzeichnis DER ANWENDUNG, nicht das der Suite (007-001-0005) — siehe
        // IntegrationTestCase::datenverzeichnis().
        $daten = self::datenverzeichnis();

        if (!is_dir($daten.'/cache')) {
            $this->markTestSkipped(
                'data/cache/ ist von hier aus nicht erreichbar — Testlauf und Testserver liegen '
                .'offenbar nicht auf derselben Maschine.'
            );
        }

        $this->verzeichnisEntfernen($daten.'/cache/login-throttle');
    }
}
