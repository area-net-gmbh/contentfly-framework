<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Der Wächter gegen das eigentliche Risiko einer Pipeline: **eine grüne Suite, die nichts
 * geprüft hat.**
 *
 * `IntegrationTestCase::setUp()` überspringt sauber, wenn `CONTENTFLY_TEST_BASE_URL` fehlt.
 * Lokal ist das genau richtig — wer schnell die Unit-Tests fahren will, soll nicht an einer
 * fehlenden Datenbank scheitern. In einer Pipeline ist dasselbe Verhalten eine Falle:
 * Vergisst jemand eine Variable, bricht der Testserver weg oder schlägt die Installation
 * fehl, meldet PHPUnit `OK, but some tests were skipped` — und der Job wird **grün**.
 *
 * Diese Klasse erbt bewusst **nicht** von `IntegrationTestCase`. Täte sie es, würde sie sich
 * unter genau den Bedingungen selbst überspringen, vor denen sie warnen soll.
 *
 * **Die Regel steht hier und nicht in der `.gitlab-ci.yml`.** Wer die Suite anderswo fährt —
 * in einem anderen CI, in einem Container, in der Pipeline nach Epic `006` — nimmt sie mit,
 * ohne sie neu zu erfinden.
 *
 * ## Wovor der Wächter nicht schützt
 * Er prüft die **Vorbedingungen** eines Integrationslaufs, nicht jeden denkbaren Übersprung.
 * Fügt jemand einem Test ein eigenes `markTestSkipped()` hinzu, fällt das hier nicht auf —
 * dafür bräuchte es `--fail-on-skipped` im Job, und das verlegte die Regel wieder aus der
 * Suite heraus. Die Vorbedingungen decken die realistischen Ausfälle ab: fehlende Variable,
 * toter Server, fehlgeschlagene Installation.
 */
class UmgebungsWaechterTest extends TestCase
{
    /**
     * Diese Variablen muss ein Integrationslauf haben.
     *
     * @var array<string,string> Name → warum ein Lauf ohne sie nichts wert ist
     */
    private const NOETIG = array(
        'CONTENTFLY_TEST_BASE_URL' =>
            'Ohne sie ueberspringt IntegrationTestCase::setUp() JEDEN Integrationstest. '
            .'Die Suite meldet dann "OK, but some tests were skipped" — und der Job wird gruen, '
            .'obwohl nichts geprueft wurde.',
        'CONTENTFLY_TEST_MAIL_TRAP' =>
            'Ohne sie ueberspringen sich die Tests aus MailApiTest, die den Endpunkt mit einer '
            .'Zieladresse aufrufen. Damit ist der Schutz gegen echten Mailversand nicht '
            .'nachgewiesen — und sobald /api/mail repariert ist (000-000-0016), koennte ein '
            .'Lauf tatsaechlich Mail verschicken.',
        'CONTENTFLY_TEST_ADMIN_PASS' =>
            'Ohne sie greift der Standardwert "admin", die Anmeldung scheitert und jeder '
            .'Integrationstest wird rot — mit der Meldung "Anmeldung fehlgeschlagen", die die '
            .'Ursache nicht nennt. Hier eingefordert, damit niemand danach sucht.',
    );

    /**
     * Läuft die Suite in einer Pipeline?
     *
     * `CI` setzen GitLab, GitHub Actions und die meisten anderen von sich aus. Ein
     * ausdrückliches `CI=false`, `CI=0` oder eine leere Variable zählt nicht.
     *
     * **Das ist zugleich die Hintertür:** Wer `CI=false` setzt, schaltet den Wächter ab. Das
     * ist Absicht — manche lokalen Werkzeuge setzen `CI=false`, und ein Wächter, der darauf
     * anspringt, wäre lästig statt nützlich. In GitLab lässt sich `CI` nicht überschreiben,
     * dort greift die Hintertür nicht.
     */
    private function inEinerPipeline(): bool
    {
        $wert = getenv('CI');

        return $wert !== false && !in_array(strtolower(trim((string) $wert)), array('', '0', 'false', 'off'), true);
    }

    private function nurInDerPipeline(): void
    {
        if (!$this->inEinerPipeline()) {
            $this->markTestSkipped(
                'Kein Pipeline-Lauf (CI ist nicht gesetzt) — lokal ist das Ueberspringen der '
                .'Integrationstests gewollt und dieser Waechter deshalb nicht zustaendig.'
            );
        }
    }

    // ── In der Pipeline sind die Vorbedingungen Pflicht ────────────────────────────────

    /**
     * @dataProvider noetigeVariablen
     */
    public function testInEinerPipelineIstDieUmgebungsvariableGesetzt(string $name, string $warum): void
    {
        $this->nurInDerPipeline();

        $wert = getenv($name);

        $this->assertNotFalse(
            $wert,
            sprintf("%s ist in diesem Pipeline-Lauf nicht gesetzt.\n\n%s\n\nEinrichtung: tests/README.md.", $name, $warum)
        );
        $this->assertNotSame(
            '',
            trim((string) $wert),
            sprintf("%s ist gesetzt, aber leer.\n\n%s", $name, $warum)
        );
    }

    public static function noetigeVariablen(): array
    {
        $faelle = array();

        foreach (self::NOETIG as $name => $warum) {
            $faelle[$name] = array($name, $warum);
        }

        return $faelle;
    }

    // ── Gesetzt genügt nicht: es muss auch stimmen ─────────────────────────────────────

    public function testEineGesetzteBasisAdresseMussAuchAntworten(): void
    {
        // Der Punkt, an dem ein Waechter sonst in Sicherheit wiegt: Eine gesetzte Variable
        // sagt nichts darueber, ob dahinter etwas laeuft. Bricht der Testserver zwischen dem
        // Start und der Suite weg, ist CONTENTFLY_TEST_BASE_URL weiterhin gesetzt — und jeder
        // Integrationstest wuerde rot, aber mit einer Meldung ueber einen einzelnen Endpunkt
        // statt ueber die Umgebung.
        //
        // Dieser Test laeuft bewusst AUCH lokal, sobald die Variable gesetzt ist: Ein toter
        // Testserver ist auf der Entwicklungsmaschine genauso irrefuehrend.
        $adresse = getenv('CONTENTFLY_TEST_BASE_URL');

        if ($adresse === false || trim((string) $adresse) === '') {
            $this->nurInDerPipeline();
            $this->fail('CONTENTFLY_TEST_BASE_URL fehlt — siehe den Test darueber.');
        }

        $roh = @file_get_contents(rtrim((string) $adresse, '/').'/api/config');

        $this->assertNotFalse(
            $roh,
            sprintf(
                "Unter %s antwortet nichts.\n\n"
                ."Die Variable ist gesetzt, der Testserver aber nicht erreichbar. Ein Lauf in "
                ."diesem Zustand faerbt jeden Integrationstest rot und nennt dabei nie die "
                ."eigentliche Ursache.\n\nStart des Servers: tests/README.md bzw. "
                ."tools/ci/prepare-test-environment.sh.",
                $adresse
            )
        );

        $this->assertNotNull(
            json_decode((string) $roh, true),
            sprintf(
                "Unter %s/api/config antwortet etwas, aber es ist kein JSON.\n\n"
                ."Das deutet auf eine nicht abgeschlossene Installation hin — die Anwendung "
                ."liefert dann eine Fehlerseite statt der Konfiguration.",
                $adresse
            )
        );
    }

    public function testEineGesetzteVersandfalleMussEinFangskriptEnthalten(): void
    {
        // Dieselbe Ueberlegung fuer die Mail-Falle: Der Pfad kann gesetzt sein und ins Leere
        // zeigen. MailApiTest prueft das ebenfalls — aber erst, nachdem er den Endpunkt
        // aufgerufen hat. Hier faellt es vorher auf, und zwar mit einer Meldung ueber die
        // Umgebung statt ueber einen Endpunkt.
        $verzeichnis = getenv('CONTENTFLY_TEST_MAIL_TRAP');

        if ($verzeichnis === false || trim((string) $verzeichnis) === '') {
            $this->nurInDerPipeline();
            $this->fail('CONTENTFLY_TEST_MAIL_TRAP fehlt — siehe den Test weiter oben.');
        }

        $skript = rtrim((string) $verzeichnis, '/').'/sendmail';

        $this->assertFileExists(
            $skript,
            sprintf(
                "Die Versandfalle unter %s hat kein Fangskript.\n\n"
                ."Der Pfad ist gesetzt, das Skript fehlt — dann faengt nichts, und die "
                ."Zusicherung 'kein Testlauf verschickt Mail' ist nicht gedeckt.",
                $verzeichnis
            )
        );
        $this->assertTrue(is_executable($skript), 'Das Fangskript ist nicht ausfuehrbar.');
    }
}
