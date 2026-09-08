<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * Charakterisierungstests für `POST /api/mail` — den einzigen Endpunkt der API mit
 * **Aussenwirkung**. Alles andere schreibt höchstens in die Datenbank.
 *
 * **Der Befund, der diesen Task bestimmt: der Endpunkt verschickt nichts. Er kann es nicht.**
 * `mailAction()` liest die Absenderadresse als *nackte Konstante*:
 *
 *     mail($mailto, $subject, $body, 'From: '.APP_MAILFROM);
 *
 * `APP_MAILFROM` ist im ganzen Baum nirgends per `define()` gesetzt — es gibt nur ein
 * gleichnamiges **Konfigurationsfeld** (`Classes/Config.php`), und das erreicht man über
 * `Adapter::getConfig()->APP_MAILFROM`, nicht als Konstante. Unter PHP 8 ist eine
 * undefinierte Konstante ein `Error`, kein Fallback auf ihren Namen wie unter PHP 7. Der
 * Aufruf endet also, bevor `mail()` überhaupt drankommt.
 *
 * Damit beantwortet sich die Frage des Tasks, wie der Erfolgsfall sicher zu prüfen wäre,
 * von selbst: **Es gibt keinen Erfolgsfall.** Festgehalten ist stattdessen, wo genau der
 * Endpunkt zerbricht — und dass davor nichts hinausgeht.
 *
 * Trotzdem hängt die Zusicherung „kein Versand" hier nicht an diesem Fehler. Sie darf nicht
 * daran hängen: Wer den Fehler behebt, würde sonst die Sicherung gleich mit entfernen. Der
 * Testlauf leitet deshalb `sendmail_path` des Testservers auf ein Fangskript um, und
 * `testDieVersandfalleIstScharf()` belegt beide Hälften — dass das Skript wirklich fängt, und
 * dass der Server wirklich *dieses* Skript benutzt. Ohne die zweite wäre ein leerer
 * Postausgang bloss ein Indiz. Eingerichtet wird das nach `tests/README.md`.
 *
 * Befunde aus dieser Datei sind als `000-000-0016` notiert — einschliesslich der Warnung,
 * dass eine blosse Reparatur der Konstanten ein **offenes Mail-Relais hinter einem Token**
 * ergäbe.
 */
class MailApiTest extends IntegrationTestCase
{
    /**
     * Das Verzeichnis der Versandfalle, oder `null`, wenn keine eingerichtet ist.
     *
     * `CONTENTFLY_TEST_MAIL_TRAP` zeigt auf ein Verzeichnis mit zwei Dateien: `sendmail`,
     * das der Testserver als `sendmail_path` benutzt, und `postausgang.log`, in das dieses
     * Skript schreibt, statt zuzustellen. Ohne die Variable werden alle Tests übersprungen,
     * die den Endpunkt mit einer Zieladresse aufrufen — nicht durchgewinkt.
     *
     * Einrichtung: `tests/README.md`.
     */
    private function fallenverzeichnis(): ?string
    {
        $pfad = getenv('CONTENTFLY_TEST_MAIL_TRAP') ?: null;

        return $pfad !== null ? rtrim($pfad, '/') : null;
    }

    /** Anzahl Bytes im Postausgang; eine fehlende Datei zählt als leer. */
    private function postausgangGroesse(string $verzeichnis): int
    {
        $log = $verzeichnis.'/postausgang.log';
        clearstatcache(true, $log);

        return is_file($log) ? (int) filesize($log) : 0;
    }

    /**
     * Springt ab, wenn keine Versandfalle eingerichtet ist, und liefert sonst Verzeichnis
     * und Ausgangsgrösse. Jeder Test, der `mailto` setzt, geht hierdurch.
     */
    private function versandfalleVorbereiten(): array
    {
        $verzeichnis = $this->fallenverzeichnis();

        if ($verzeichnis === null) {
            $this->markTestSkipped(
                'CONTENTFLY_TEST_MAIL_TRAP nicht gesetzt — ohne den Nachweis, dass der '
                .'Testserver nichts zustellt, wird dieser Test nicht ausgefuehrt. '
                .'Siehe tests/README.md.'
            );
        }

        return array($verzeichnis, $this->postausgangGroesse($verzeichnis));
    }

    // ── Die Sicherung prüft sich selbst ────────────────────────────────────────────────

    public function testDieVersandfalleIstScharf(): void
    {
        // Der Test, ohne den alle folgenden wertlos waeren: Ein Postausgang, der leer
        // bleibt, beweist nichts, solange nicht feststeht, dass ueberhaupt etwas darin
        // landen koennte. Deshalb loest dieser Test die Falle einmal absichtlich aus.
        //
        // Der Aufruf laeuft in einem eigenen PHP-Prozess mit demselben sendmail_path, den
        // der Testserver benutzt — der PHPUnit-Prozess selbst hat ihn nicht.
        [$verzeichnis, $vorher] = $this->versandfalleVorbereiten();

        $skript = $verzeichnis.'/sendmail';
        $this->assertFileExists($skript, 'Das Fangskript liegt neben dem Postausgang');
        $this->assertTrue(is_executable($skript), 'und ist ausfuehrbar');

        $befehl = sprintf(
            '%s -d sendmail_path=%s -r %s 2>/dev/null',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($skript),
            escapeshellarg('mail("falle@example.invalid", "Selbsttest", "Rumpf");')
        );
        exec($befehl);

        $nachher = $this->postausgangGroesse($verzeichnis);
        $this->assertGreaterThan($vorher, $nachher,
            'Die Falle faengt — was der Endpunkt zustellen wollte, landete hier');

        // Und die zweite Haelfte des Nachweises: dass der **Testserver** dieses Skript
        // benutzt und nicht den echten MTA. Ohne sie waere der leere Postausgang bloss ein
        // Indiz — der Server koennte auch ohne die Umleitung gestartet worden sein.
        // Der Diagnosepfad kommt aus tests/router.php und existiert in keiner Installation.
        [$statusDiagnose, $roh] = $this->get('/__test/sendmail-path');
        $this->assertSame(200, $statusDiagnose,
            'Der Testserver laeuft ueber tests/router.php');

        // Verglichen wird die DATEI, nicht die Schreibweise: Auf macOS ist /tmp ein Symlink
        // auf /private/tmp, und wer den Server mit dem einen und die Suite mit dem anderen
        // Pfad startet, hat trotzdem dasselbe Skript. Ein woertlicher Vergleich meldete hier
        // einen Fehler, wo keiner ist — und einer, der leicht als "Test kaputt" abgetan wird.
        $gemeldet = json_decode($roh, true)['sendmail_path'];

        $this->assertSame(
            realpath($skript),
            realpath((string) $gemeldet),
            sprintf(
                "Der Testserver stellt nicht ueber dieses Fangskript zu.\n"
                ."  erwartet: %s\n  gemeldet: %s\n\n"
                ."Laeuft womoeglich noch ein aelterer Testserver auf demselben Port?",
                $skript,
                $gemeldet
            )
        );

        // Den Selbsttest wieder herausschneiden, damit die folgenden Tests von einem
        // sauberen Ausgangsstand ausgehen.
        $log = $verzeichnis.'/postausgang.log';
        file_put_contents($log, substr((string) file_get_contents($log), 0, $vorher));

        $this->assertSame($vorher, $this->postausgangGroesse($verzeichnis),
            'und der Ausgangsstand ist wiederhergestellt');
    }

    // ── Die Absicherung ────────────────────────────────────────────────────────────────

    public function testOhneTokenWirdAbgewiesen(): void
    {
        // Wie jede gesicherte /api-Route: heute 500 statt 401 (000-000-0006). Entscheidend
        // ist, dass die Anfrage den Controller nicht erreicht — sonst waere der Endpunkt ein
        // offener Mailversand.
        [$status, $body] = $this->postJson('/api/mail', array('mailto' => 'niemand@example.invalid'));

        $this->assertSame(500, $status, 'Heute 500 statt 401 — siehe 000-000-0006');
        $this->assertNotSame(array('message' => 'No mailto address'), $body,
            'Die Pruefung greift vor dem Controller — nicht erst in mailAction()');
    }

    public function testNurPostIstErlaubt(): void
    {
        [$status] = $this->get('/api/mail?mailto=niemand@example.invalid', $this->token());

        $this->assertSame(405, $status);
    }

    // ── Der fehlende mailto ────────────────────────────────────────────────────────────

    public function testOhneMailtoKommtEineEigeneFehlermeldung(): void
    {
        // Einer der wenigen Faelle, in denen das Framework eine eigene Fehlermeldung
        // formuliert, statt eine Ausnahme durchzureichen — und deshalb der einzige Pfad
        // dieses Endpunkts, der ueberhaupt eine saubere JSON-Antwort liefert.
        [$status, $body] = $this->postJson('/api/mail', array(), $this->token());

        $this->assertSame(500, $status, '500, obwohl es ein Eingabefehler ist — also 400 waere');
        $this->assertSame(array('message' => 'No mailto address'), $body,
            'Genau diese Nutzlast, englisch, ohne data-Huelle');
    }

    /**
     * @dataProvider leereAdressen
     */
    public function testJederFalsyWertGiltAlsFehlendeAdresse($wert, string $was): void
    {
        // Die Pruefung ist `if(!$mailto)`, kein Vergleich mit null und keine Validierung.
        // Deshalb faellt auch die Zeichenkette "0" durch — in PHP falsy. Eine E-Mail-Adresse
        // "0" gibt es nicht, der Fall ist also harmlos; festgehalten ist er, weil dasselbe
        // Muster an anderen Stellen des Frameworks ueber echte Werte stolpert.
        [$status, $body] = $this->postJson('/api/mail', array('mailto' => $wert), $this->token());

        $this->assertSame(500, $status, $was);
        $this->assertSame(array('message' => 'No mailto address'), $body, $was);
    }

    public static function leereAdressen(): array
    {
        return array(
            'leere Zeichenkette' => array('', 'leere Zeichenkette'),
            'die Null als Text'  => array('0', 'die Zeichenkette "0" ist falsy'),
            'die Zahl Null'      => array(0, 'die Zahl 0'),
            'null'               => array(null, 'null'),
            'false'              => array(false, 'false'),
        );
    }

    // ── Der Aufruf mit Adresse: der eigentliche Befund ─────────────────────────────────

    public function testMitAdresseScheitertDerEndpunktAnEinerUndefiniertenKonstanten(): void
    {
        [$verzeichnis, $vorher] = $this->versandfalleVorbereiten();

        [$status, $body, $kopf] = $this->postJson('/api/mail', array(
            'mailto'  => 'niemand@example.invalid',
            'subject' => 'Charakterisierung',
            'data'    => array('Name' => 'Muster', 'Ort' => 'Nirgendwo'),
        ), $this->token());

        $this->assertSame(500, $status);
        $this->assertSame(array(), $body, 'Kein JSON — der Fatal Error liefert die HTML-Seite');
        $this->assertStringContainsString('text/html', (string) $this->kopfzeile($kopf, 'Content-Type'));

        $this->assertSame($vorher, $this->postausgangGroesse($verzeichnis),
            'Nichts hat den Zustellweg erreicht');
    }

    public function testDieUrsacheIstEineKonstanteDieEsNichtGibt(): void
    {
        // Der Beleg im Quelltext, damit der Test oben nicht bloss "irgendein 500" prueft.
        //
        // PHP loest einen unqualifizierten Konstantennamen erst im aktuellen Namensraum auf,
        // dann global — der Fehler lautet deshalb
        //
        //     Undefined constant "Areanet\PIM\Controller\APP_MAILFROM"
        //
        // Unter PHP 7 waere derselbe Ausdruck noch als Zeichenkette "APP_MAILFROM"
        // durchgegangen (mit einer Notice) und haette einen unsinnigen, aber funktionierenden
        // From-Kopf ergeben. Der Endpunkt hat also einmal funktioniert; er ist mit dem
        // Sprung auf PHP 8 gestorben, ohne dass es jemandem auffiel.
        $controller = file_get_contents(ROOT_DIR.'/lib/contentfly/Controller/ApiController.php');

        $this->assertStringContainsString("'From: '.APP_MAILFROM", $controller,
            'Gelesen wird eine Konstante, nicht das Konfigurationsfeld');

        $this->assertMatchesRegularExpression(
            '/public\s+\$APP_MAILFROM\s*=/',
            file_get_contents(ROOT_DIR.'/lib/contentfly/Classes/Config.php'),
            'Waehrend APP_MAILFROM als Konfigurationsfeld sehr wohl existiert'
        );
    }

    public function testKeineDatenGehenHinausAuchNichtBeiMehrerenAufrufen(): void
    {
        // Die Zusicherung dieser Datei gegenueber allem anderen: Ein Testlauf verschickt
        // keine Mail. Geprueft, nicht angenommen — der Postausgang des Fangskripts bleibt
        // ueber mehrere Aufrufe hinweg unveraendert, auch bei einem Empfaenger, der wie eine
        // echte Adresse aussieht.
        [$verzeichnis, $vorher] = $this->versandfalleVorbereiten();

        foreach (array('a@example.invalid', 'b@example.invalid', 'admin@localhost') as $empfaenger) {
            $this->postJson('/api/mail', array('mailto' => $empfaenger), $this->token());
        }

        $this->assertSame($vorher, $this->postausgangGroesse($verzeichnis),
            'Nach drei Aufrufen ist der Postausgang unveraendert');
    }

    // ── Was der Erfolgsfall zusagen würde ──────────────────────────────────────────────

    public function testDieErfolgsantwortIstHeuteNichtErreichbarUndWaereDoppeltKodiert(): void
    {
        // Nicht beobachtbar, deshalb am Quelltext festgehalten: Der Rueckgabewert wird erst
        // zu JSON kodiert und dann als *Zeichenkette* in das data-Feld gelegt —
        //
        //     $data = json_encode($return);
        //     return $this->renderResponse(array('data' => $data));
        //
        // Ein Client bekaeme also {"data":"{\"mailto\":…}"} und muesste zweimal dekodieren.
        // Jeder andere Endpunkt legt sein Objekt direkt in data. Gehoert zu 000-000-0014.
        $controller = file_get_contents(ROOT_DIR.'/lib/contentfly/Controller/ApiController.php');

        $this->assertMatchesRegularExpression(
            '/\$data\s*=\s*json_encode\(\$return\);\s*\n\s*return \$this->renderResponse\(array\(\'data\' => \$data\)\);/',
            $controller,
            'Die doppelte Kodierung steht so im Code'
        );
    }

    public function testDerRumpfWuerdeAusDataMitTabulatorenGebautWerden(): void
    {
        // Ebenfalls nur am Quelltext pruefbar, weil der Aufruf vorher abbricht. Der Rumpf
        // entsteht als "name:\t\t\tvalue\n" je Eintrag — ohne Trennung von Feldnamen und
        // Wert, ohne Kodierung, ohne Begrenzung. Ein Wert mit Zeilenumbruechen und eigenen
        // Kopfzeilen laesst sich damit nicht von echten Kopfzeilen unterscheiden.
        //
        // Das ist der Grund, warum 000-000-0016 ausdruecklich davor warnt, hier nur die
        // Konstante zu reparieren: Der Endpunkt nimmt Empfaenger, Betreff und Inhalt
        // unveraendert vom Aufrufer entgegen.
        $controller = file_get_contents(ROOT_DIR.'/lib/contentfly/Controller/ApiController.php');

        $this->assertStringContainsString('$body .= $name.":\t\t\t".$value."\n";', $controller);
        $this->assertStringNotContainsString('filter_var($mailto', $controller,
            'Die Empfaengeradresse wird nicht validiert');
    }
}
