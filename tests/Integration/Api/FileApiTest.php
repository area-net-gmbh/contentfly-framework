<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * Charakterisierungstests für die Datei-API — die drei Actions, die `012-003-0001` als
 * API-Funktion eingestuft hat: Upload, Auslieferung, Überschreiben.
 *
 * **Charakterisierung heißt: festhalten, was ist.** Auch das Fragwürdige. Diese Tests sollen
 * beim Kernel-Wechsel (Epic 009) anschlagen, wenn sich Verhalten ändert — sie beschreiben
 * nicht, wie die API aussehen sollte.
 *
 * Voraussetzungen (sonst wird übersprungen):
 * - `CONTENTFLY_TEST_BASE_URL` zeigt auf eine laufende, installierte Instanz
 * - `CONTENTFLY_TEST_ADMIN_PASS` ist das Passwort des Benutzers `admin`
 *
 * Siehe `tests/README.md`.
 */
class FileApiTest extends IntegrationTestCase
{

    public function testAnmeldungLiefertEinToken(): void
    {
        $this->assertNotEmpty($this->token());
        $this->assertMatchesRegularExpression('/^[0-9a-f]{128}$/', $this->token(), 'Der Token ist 64 Byte als Hex');
    }

    public function testUploadLegtEineDateiAnUndLiefertIhreId(): void
    {
        $antwort = $this->upload('probe.txt', "hallo contentfly\n", $this->token());

        $this->assertSame('File uploaded', $antwort['message'] ?? null);
        $this->assertNotEmpty($antwort['data']['id'] ?? null);
    }

    public function testHochgeladeneDateiLiegtByteGleichAufDerPlatte(): void
    {
        $inhalt  = "Zeile eins\nZeile zwei\n";
        $antwort = $this->upload('rueckgabe.txt', $inhalt, $this->token());

        $pfad = ROOT_DIR.'/data/files/'.$antwort['data']['id'].'/rueckgabe.txt';

        $this->assertFileExists($pfad, 'Der Upload legt die Datei unter data/files/<id>/<name> ab');
        $this->assertSame($inhalt, file_get_contents($pfad), 'Der Inhalt muss byte-gleich gespeichert werden');
    }

    /**
     * Die Auslieferung antwortet mit einem **Redirect** auf den Pfad unter `data/files/`,
     * nicht mit dem Dateiinhalt. Unter Apache greift danach die `.htaccess`, die vorhandene
     * Dateien direkt ausliefert.
     *
     * Der Redirect ist **relativ** aufgebaut und haengt an `Config::WEB_ROOT`, das
     * `bootstrap-web.php` aus `$_SERVER['PHP_SELF']` ableitet. Unter dem eingebauten
     * PHP-Server ergibt das einen anderen Wert als unter Apache, weshalb hier bewusst nur
     * der Redirect selbst geprueft wird und nicht, wo er landet. Der Ausbau zu einer
     * belastbaren Auslieferungspruefung gehoert zu Epic 008 (siehe Task 000-000-0006).
     */
    public function testAuslieferungAntwortetMitRedirectAufDieDatei(): void
    {
        $antwort = $this->upload('ausgeliefert.txt', "sichtbar\n", $this->token());

        [$status, , $kopf] = $this->get('/file/get/'.$antwort['data']['id']);
        $location = $this->kopfzeile($kopf, 'Location');

        $this->assertSame(301, $status);
        $this->assertStringContainsString('data/files/', (string) $location);
        $this->assertStringContainsString('ausgeliefert.txt', (string) $location);
    }

    public function testAuslieferungBrauchtKeinenToken(): void
    {
        // Bewusst so: getAction haengt in FileControllerProvider NICHT an checkAuth.
        // Wer die ID kennt, bekommt die Datei. Das ist heutiges Verhalten, kein Vorschlag.
        $antwort = $this->upload('oeffentlich.txt', "sichtbar\n", $this->token());

        [$status] = $this->get('/file/get/'.$antwort['data']['id']);

        $this->assertSame(301, $status, 'Ohne Token wird nicht abgewiesen, sondern weitergeleitet');
    }

    /**
     * Eine unbekannte ID endet heute mit **500**, nicht mit 404 — obwohl
     * `bootstrap-web.php` fuer `FileNotFoundException` ausdruecklich einen 404 vorsieht.
     * Der Debug-Exception-Handler faengt die Ausnahme vorher ab, auch mit APP_DEBUG=0.
     *
     * Charakterisierung heisst festhalten, was ist. Dass das falsch ist, steht in
     * Task 000-000-0006; dieser Test schlaegt an, sobald es sich aendert - dann ist die
     * Erwartung hier nachzuziehen.
     */
    public function testUnbekannteIdLiefertKeineDatei(): void
    {
        [$status] = $this->get('/file/get/00000000-0000-0000-0000-000000000000');

        $this->assertNotSame(200, $status);
        $this->assertSame(500, $status, 'Heutiges Verhalten - erwartet waere 404');
    }

    public function testUploadOhneTokenWirdAbgewiesen(): void
    {
        $antwort = $this->upload('verboten.txt', "nein\n", null);

        $this->assertNotSame('File uploaded', $antwort['message'] ?? null, 'Ohne Token darf kein Upload gelingen');
    }

    public function testUeberschreibenErsetztDenInhaltDesZiels(): void
    {
        // Beide Dateien tragen denselben Namen - das ist Vorbedingung, siehe naechster Test.
        $quelle = $this->upload('gleich.txt', "neuer inhalt\n", $this->token())['data']['id'];
        $ziel   = $this->upload('gleich.txt', "alter inhalt\n", $this->token())['data']['id'];

        [, $antwort] = $this->postJson('/file/overwrite', array('sourceId' => $quelle, 'destId' => $ziel), $this->token());

        $this->assertSame('File overwritten', $antwort['message'] ?? null);

        $zielPfad = ROOT_DIR.'/data/files/'.$ziel.'/gleich.txt';
        $this->assertFileExists($zielPfad);
        $this->assertSame(
            "neuer inhalt\n",
            file_get_contents($zielPfad),
            'Das Ziel traegt nach dem Ueberschreiben den Inhalt der Quelle'
        );

        $this->assertDirectoryDoesNotExist(
            ROOT_DIR.'/data/files/'.$quelle,
            'Die Quelle wird verschoben, nicht kopiert - ihr Verzeichnis verschwindet'
        );
    }

    /**
     * Ueberschreiben verlangt, dass Quelle und Ziel **denselben Dateinamen** tragen
     * (`FileController::overwriteAction()`). Sonst bricht es mit einer FileNotFoundException ab
     * - einer irrefuehrenden Meldung fuer eine Namenspruefung, aber es ist das heutige
     * Verhalten und Clients koennen sich darauf verlassen.
     */
    public function testUeberschreibenVerlangtGleicheDateinamen(): void
    {
        $quelle = $this->upload('eins.txt', "neuer inhalt\n", $this->token())['data']['id'];
        $ziel   = $this->upload('zwei.txt', "alter inhalt\n", $this->token())['data']['id'];

        $this->postJson('/file/overwrite', array('sourceId' => $quelle, 'destId' => $ziel), $this->token());

        $this->assertSame(
            "alter inhalt\n",
            file_get_contents(ROOT_DIR.'/data/files/'.$ziel.'/zwei.txt'),
            'Bei verschiedenen Namen bleibt das Ziel unangetastet'
        );
    }

    /**
     * Der Upload nimmt ein rohes `$_FILES`-Array entgegen, kein `UploadedFile`.
     *
     * Das funktioniert heute **zufaellig**: PHP 8.1 ergaenzt `$_FILES` um den Schluessel
     * `full_path`; die Erkennung in HttpFoundation 3.4 (`FileBag::$fileKeys`) vergleicht die
     * Schluessel exakt, scheitert daran und reicht das rohe Array durch — genau das, was
     * `FileController::uploadAction()` mit `$file['name']` und `$file['tmp_name']` erwartet.
     *
     * Auf einem aktuellen Symfony liefert `$request->files->get()` ein `UploadedFile`, und der
     * Array-Zugriff wird zum Fatal Error. Dieser Test haelt fest, dass der Upload heute
     * funktioniert — schlaegt er nach dem Kernel-Wechsel fehl, ist es genau diese Stelle.
     */
    public function testUploadFunktioniertUeberDenRohenFilesArrayPfad(): void
    {
        $antwort = $this->upload('bild.txt', str_repeat('x', 1024), $this->token());

        $this->assertNotEmpty($antwort['data']['id'] ?? null);
        $this->assertSame(1024, $antwort['data']['size'] ?? null, 'Die Groesse kommt aus $_FILES["size"]');
    }

    // ── Hilfsmittel ────────────────────────────────────────────────────────────────────

    private function upload(string $name, string $inhalt, ?string $token): array
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cf-test-');
        file_put_contents($tmp, $inhalt);

        $ch = curl_init(self::$baseUrl.'/file/upload');
        curl_setopt_array($ch, array(
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => array('file' => new \CURLFile($tmp, 'text/plain', $name)),
            CURLOPT_HTTPHEADER     => $token ? array('appcms-token: '.$token) : array(),
        ));
        $antwort = curl_exec($ch);
        curl_close($ch);
        unlink($tmp);

        return json_decode((string) $antwort, true) ?: array();
    }
}
