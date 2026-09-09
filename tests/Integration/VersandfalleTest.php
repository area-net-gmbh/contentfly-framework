<?php
namespace Tests\Integration;

/**
 * Prüft die **Versandfalle** des Testlaufs — die Sicherung, die verhindert, dass ein Testlauf
 * echte Mail verschickt.
 *
 * `tools/ci/prepare-test-environment.sh` startet den Testserver mit einem Fangskript als
 * `sendmail_path`. Dieser Test belegt beide Hälften: dass das Skript wirklich fängt, und dass
 * der Server wirklich *dieses* Skript benutzt. Ohne die zweite wäre ein leerer Postausgang
 * bloss ein Indiz.
 *
 * **Herkunft: `MailApiTest`, aufgelöst mit `000-000-0016`.** Die Datei charakterisierte
 * `POST /api/mail`, den einzigen Endpunkt der API mit Aussenwirkung — und stellte fest, dass er
 * seit dem Sprung auf PHP 8 gar nichts verschickte: `mailAction()` las `APP_MAILFROM` als nackte
 * Konstante, die es nie gab. Der Endpunkt ist mit `000-000-0016` entfernt, und mit ihm die neun
 * Tests, die sein defektes Verhalten festhielten.
 *
 * **Dieser eine ist geblieben, und das ist Absicht.** Die Zusicherung „kein Testlauf verschickt
 * Mail" hing nie an `/api/mail`: `$app['mailer']` bleibt als Dienst bestehen, ein Projekt kann
 * darüber senden, und `custom/app.php` ist die Vorlage, in die es das einbaut. Eine Sicherung
 * mit dem Endpunkt zu entfernen, den sie zufällig zuerst absicherte, hiesse sie in dem Moment
 * aufzugeben, in dem niemand mehr hinsieht.
 *
 * `UmgebungsWaechterTest` verlangt `CONTENTFLY_TEST_MAIL_TRAP` unter `CI` weiterhin.
 * Einrichtung: `tests/README.md`.
 */
class VersandfalleTest extends IntegrationTestCase
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
}
