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
     * Dateien direkt ausliefert; unter dem Testserver tut das `tests/router.php`.
     *
     * **Seit `000-000-0006` steht das Ziel fest.** Vorher leitete `bootstrap-web.php`
     * `Config::WEB_ROOT` bei jedem Request aus `$_SERVER['PHP_SELF']` ab; unter dem
     * eingebauten PHP-Server kam dabei `/index.php/file/get/data/files/…` heraus — ein Pfad
     * ins Leere. Deshalb liess sich hier nur pruefen, DASS umgeleitet wird, nicht WOHIN. Jetzt
     * kommt der Mountpunkt aus der Konfiguration, und `testAuslieferungLiefertDenInhalt()`
     * folgt der Umleitung bis zur Datei.
     */
    public function testAuslieferungAntwortetMitRedirectAufDieDatei(): void
    {
        $antwort = $this->upload('ausgeliefert.txt', "sichtbar\n", $this->token());

        [$status, , $kopf] = $this->get('/file/get/'.$antwort['data']['id']);
        $location = $this->kopfzeile($kopf, 'Location');

        // 301, nicht 302: getAction() ruft `$this->app->redirect($redirectUri, 301)` — so seit
        // dem initialen Import, und so in der README dokumentiert. Die Zusicherung stand bis
        // 000-000-0019 auf 302; siehe den Kommentar an testAuslieferungBrauchtKeinenToken.
        $this->assertSame(301, $status);
        $this->assertSame(
            '/data/files/'.$antwort['data']['id'].'/ausgeliefert.txt',
            (string) $location,
            'Absolut ab WEB_ROOT, nicht mehr aus PHP_SELF abgeleitet — 000-000-0006'
        );
    }

    /**
     * Die Auslieferung end-to-end: hochladen, der Umleitung folgen, den Inhalt vergleichen.
     *
     * **Das war bis `000-000-0006` nicht pruefbar**, und das ist der Grund, warum es diesen
     * Test gibt: Ein Testnetz, das die Auslieferung nicht abdeckt, laesst beim Kernel-Wechsel
     * (Epic `009`) genau die Funktion ungeprueft, die jeder Client braucht. Der Redirect selbst
     * sagt darueber nichts — er kann formal richtig aussehen und trotzdem ins Leere zeigen,
     * und genau das tat er.
     */
    public function testAuslieferungLiefertDenInhalt(): void
    {
        $inhalt  = "erste Zeile\nzweite Zeile\n";
        $antwort = $this->upload('e2e.txt', $inhalt, $this->token());

        $ch = curl_init(self::$baseUrl.'/file/get/'.$antwort['data']['id']);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
        ));
        $rumpf  = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->assertSame(200, $status, 'Der Umleitung gefolgt kommt die Datei, kein Irrlaeufer');
        $this->assertSame($inhalt, $rumpf, 'Byte-gleich zu dem, was hochgeladen wurde');
    }

    /**
     * **Warum hier 301 steht und eine Zeitlang 302 stand** (`000-000-0019`).
     *
     * `006-002-0006` hat beide Auslieferungs-Zusicherungen von 301 auf 302 gesetzt, als
     * „Testerwartung an den neuen Stack nachziehen". Das war eine Fehldiagnose: Zu diesem
     * Zeitpunkt war der Upload durch den `UploadedFile`-Bruch bereits kaputt, `data.id` kam
     * als `null` zurueck, und der Aufruf ging an `/file/get/` **ohne Id**. Dort antwortet
     * nicht die Auslieferung, sondern die WEB_ROOT-Umleitung auf `/` — mit 302.
     *
     * Gemessen wurde also ein Symptom des Upload-Defekts und als neues Symfony-4.4-Verhalten
     * festgeschrieben. `getAction()` ruft unveraendert `redirect($redirectUri, 301)`, seit dem
     * initialen Import; die README beschreibt es ebenso.
     *
     * Der Fall ist die Illustration zu der Regel aus `an_project/docs/technical.md`: Eine
     * Testanpassung ist ein Verhaltenswechsel und braucht eine Begruendung. Wird sie
     * vorgenommen, um einen roten Test gruen zu bekommen, schreibt sie den Defekt fest.
     */
    public function testAuslieferungBrauchtKeinenToken(): void
    {
        // Bewusst so: getAction haengt in FileControllerProvider NICHT an checkAuth.
        // Wer die ID kennt, bekommt die Datei. Das ist heutiges Verhalten, kein Vorschlag.
        $antwort = $this->upload('oeffentlich.txt', "sichtbar\n", $this->token());

        [$status] = $this->get('/file/get/'.$antwort['data']['id']);

        $this->assertSame(301, $status, 'Ohne Token wird nicht abgewiesen, sondern weitergeleitet');
    }

    /**
     * Eine unbekannte ID endet mit **404**.
     *
     * Der Test hielt eine Zeitlang 500 fest, weil der Debug-Exception-Handler die
     * `FileNotFoundException` vor dem Handler der Anwendung abfing. Der Stack-Wechsel
     * (`006-002-0003`) hat das behoben, `000-000-0006` die verbliebene Haelfte auf der
     * API-Seite.
     */
    public function testUnbekannteIdLiefertKeineDatei(): void
    {
        [$status] = $this->get('/file/get/00000000-0000-0000-0000-000000000000');

        $this->assertNotSame(200, $status);
        $this->assertSame(404, $status,
            'Seit 006-002-0003 der gemeinte Code — Symfony 4.4 behebt hier 000-000-0006');
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
     * **Der vorhergesagte Fall ist eingetreten und behoben** (`000-000-0019`).
     *
     * Dieser Test hiess bis dahin `testUploadFunktioniertUeberDenRohenFilesArrayPfad` und
     * trug die Warnung, der Upload nehme ein rohes `$_FILES`-Array entgegen statt eines
     * `UploadedFile`. Das funktionierte **zufaellig**: PHP 8.1 ergaenzt `$_FILES` um den
     * Schluessel `full_path`, die Erkennung in HttpFoundation 3.4 (`FileBag::$fileKeys`)
     * vergleicht die Schluessel exakt, scheitert daran und reichte das rohe Array durch —
     * genau das, was `uploadAction()` mit `$file['name']` erwartete.
     *
     * Mit `006-002-0003` (Symfony 3.4 -> 4.4) kam dort ein `UploadedFile` an, und der
     * Array-Zugriff wurde zum Fatal Error. Wortwoertlich an der vorhergesagten Stelle.
     * `uploadAction()` liest die vier Werte seitdem ueber die `UploadedFile`-API.
     *
     * **Der Test selbst ist unveraendert** — dieselben zwei Zusicherungen wie zuvor. Nur
     * Name und Kommentar beschrieben einen Mechanismus, den es nicht mehr gibt. Was er
     * prueft, ist die Zusicherung, an der der Bruch damals sichtbar wurde: dass die
     * gemeldete Dateigroesse durchkommt.
     */
    public function testUploadUebernimmtDieGemeldeteDateigroesse(): void
    {
        $antwort = $this->upload('bild.txt', str_repeat('x', 1024), $this->token());

        $this->assertNotEmpty($antwort['data']['id'] ?? null);
        $this->assertSame(1024, $antwort['data']['size'] ?? null, 'Die Groesse kommt aus der Angabe des Clients');
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

        $ergebnis = json_decode((string) $antwort, true) ?: array();

        $this->hochgeladeneDateiAufraeumen($ergebnis['data']['id'] ?? null);

        return $ergebnis;
    }

    /**
     * Meldet alles an, was ein gelungener Upload hinterlaesst — Datei-Zeile, die dabei
     * entstandenen Log-Zeilen und das Verzeichnis unter `data/files/`.
     *
     * Ohne das wuchs die Testumgebung mit jedem Lauf um 8 Dateien und 17 Datenbankzeilen
     * (Task `000-000-0008`). Bei einem abgewiesenen Upload gibt es keine Id — dann ist
     * nichts anzumelden.
     */
    private function hochgeladeneDateiAufraeumen(?string $id): void
    {
        if ($id === null) {
            return;
        }

        foreach ($this->pdo()->query('SELECT id FROM pim_log WHERE model_id = '.$this->pdo()->quote($id))->fetchAll(\PDO::FETCH_COLUMN) as $logId) {
            $this->nachTestLoeschen('pim_log', $logId);
        }

        $this->nachTestLoeschen('pim_file', $id);
        $this->nachTestVerzeichnisLoeschen(ROOT_DIR.'/data/files/'.$id);
    }
}
