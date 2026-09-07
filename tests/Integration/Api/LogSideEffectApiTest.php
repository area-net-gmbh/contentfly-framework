<?php
namespace Tests\Integration\Api;

use PDO;
use Tests\Integration\IntegrationTestCase;

/**
 * Charakterisierungstests für die Log-Nebenwirkung jeder Schreiboperation.
 *
 * Jedes Insert, Update und Delete legt eine Zeile in `pim_log` an. Das ist die einzige
 * Nebenwirkung der Schreibseite, die **Daten persistiert, ohne in der Antwort sichtbar zu
 * sein** — und damit diejenige, die beim Kernel-Tausch am ehesten unbemerkt verschwindet.
 *
 * `model_label` ist der eigentliche Grund für diese Datei: Das Feld wird aus der
 * `labelProperty` der Entity gefüllt, und genau die stand in `012-005-0002` auf der
 * Streichliste. Behalten wurde sie, weil sie hier persistiert wird. Diese Tests sind der
 * Nachweis, dass die Entscheidung trägt.
 *
 * Gelesen wird über `pdo()`: Es gibt keinen API-Endpunkt, der Log-Zeilen ausliefert —
 * `PIM\Log` steht auf der Ausschlussliste von `getDeleted()` (siehe `008-001-0004`).
 */
class LogSideEffectApiTest extends IntegrationTestCase
{
    /** @var array<int,string> Ids, deren Log-Zeilen am Ende weggeraeumt werden. */
    private array $beobachtet = array();

    /** @return array<int, array{mode:string,model_name:string,model_label:?string}> */
    private function logZeilen(string $modelId): array
    {
        return $this->pdo()
            ->query('SELECT mode, model_name, model_label FROM pim_log
                     WHERE model_id = '.$this->pdo()->quote($modelId).' ORDER BY created, mode')
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Legt einen Tag ueber die API an und merkt ihn zum Aufraeumen vor. */
    private function tagAnlegen(string $titel): string
    {
        [$status, $body] = $this->postJson(
            '/api/insert',
            array('entity' => 'PIM\\Tag', 'data' => array('title' => $titel)),
            $this->token()
        );

        $this->assertSame(200, $status, 'Vorbedingung: das Anlegen gelingt');

        $this->nachTestLoeschen('pim_tag', $body['id']);
        $this->beobachtet[] = $body['id'];

        return $body['id'];
    }

    protected function tearDown(): void
    {
        // Erst hier bekannt: Log-Zeilen entstehen waehrend des Tests, nicht davor.
        foreach ($this->beobachtet as $modelId) {
            $this->pdo()->prepare('DELETE FROM pim_log WHERE model_id = :id')->execute(array('id' => $modelId));
        }

        $this->beobachtet = array();

        parent::tearDown();
    }

    // ── Die drei Modi des Lebenszyklus ─────────────────────────────────────────────────

    public function testInsertSchreibtEineLogZeileMitModusINS(): void
    {
        $id = $this->tagAnlegen('Log-INS');

        $zeilen = $this->logZeilen($id);

        $this->assertCount(1, $zeilen);
        $this->assertSame('INS', $zeilen[0]['mode']);
        $this->assertSame('PIM\\Tag', $zeilen[0]['model_name']);
    }

    public function testUpdateSchreibtEineLogZeileMitModusUPT(): void
    {
        $id = $this->tagAnlegen('Log-UPT');

        $this->postJson(
            '/api/update',
            array('entity' => 'PIM\\Tag', 'id' => $id, 'data' => array('title' => 'Log-UPT-geaendert')),
            $this->token()
        );

        $modi = array_column($this->logZeilen($id), 'mode');
        $this->assertContains('UPT', $modi);
    }

    public function testDeleteSchreibtEineLogZeileMitModusDEL(): void
    {
        $id = $this->tagAnlegen('Log-DEL');

        $this->postJson('/api/delete', array('entity' => 'PIM\\Tag', 'id' => $id), $this->token());

        $modi = array_column($this->logZeilen($id), 'mode');
        $this->assertContains('DEL', $modi);
    }

    public function testDerGanzeLebenszyklusHinterlaesstDreiZeilen(): void
    {
        $id = $this->tagAnlegen('Log-Zyklus');
        $this->postJson('/api/update', array('entity' => 'PIM\\Tag', 'id' => $id, 'data' => array('title' => 'Log-Zyklus-2')), $this->token());
        $this->postJson('/api/delete', array('entity' => 'PIM\\Tag', 'id' => $id), $this->token());

        $modi = array_column($this->logZeilen($id), 'mode');
        sort($modi);

        $this->assertSame(array('DEL', 'INS', 'UPT'), $modi,
            'Anlegen, Aendern und Loeschen hinterlassen je genau eine Zeile');
    }

    public function testDerZeitstempelDerLogZeilenHatNurSekundenaufloesung(): void
    {
        // pim_log.created ist ein DATETIME ohne Nachkommastellen. Ein Lebenszyklus, der
        // innerhalb einer Sekunde ablaeuft — bei je einem API-Aufruf der Normalfall —
        // hinterlaesst deshalb Zeilen mit identischem Zeitstempel, und aus dem Protokoll
        // allein laesst sich dann nicht sagen, was zuerst geschah. Siehe 000-000-0013.
        //
        // Geprueft wird die **Aufloesung**, nicht die Koinzidenz: Eine erste Fassung dieses
        // Tests behauptete, zwei aufeinanderfolgende Aufrufe truegen denselben Zeitstempel.
        // Das stimmt nur, solange sie keine Sekundengrenze ueberschreiten — der Test war in
        // etwa jedem dreissigsten Lauf rot.
        $id = $this->tagAnlegen('Zeitstempel-Probe');

        $zeitstempel = (string) $this->pdo()
            ->query('SELECT created FROM pim_log WHERE model_id = '.$this->pdo()->quote($id))
            ->fetchColumn();

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            $zeitstempel,
            'Keine Nachkommastellen — zwei Operationen in derselben Sekunde sind nicht '
            .'unterscheidbar'
        );
    }

    // ── model_label — der Schutz fuer die Entscheidung aus 012-005-0002 ────────────────

    public function testModelLabelWirdAusDerLabelPropertyGefuellt(): void
    {
        // PIM\Tag traegt @PIM\Config(labelProperty="title"). Genau dieses Feld stand in
        // 012-005-0002 auf der Streichliste und wurde behalten, weil sein Wert hier in die
        // Datenbank geschrieben wird. Faellt labelProperty weg, bleibt model_label leer —
        // und ein Log-Eintrag sagt nur noch, DASS etwas passierte, nicht WOMIT.
        $id = $this->tagAnlegen('Ein sprechender Titel');

        $zeilen = $this->logZeilen($id);

        $this->assertSame('Ein sprechender Titel', $zeilen[0]['model_label'],
            'model_label kommt aus der labelProperty der Entity — siehe 012-005-0002');
    }

    public function testModelLabelHaeltDenWertZumZeitpunktDerOperation(): void
    {
        $id = $this->tagAnlegen('Titel-vorher');
        $this->postJson(
            '/api/update',
            array('entity' => 'PIM\\Tag', 'id' => $id, 'data' => array('title' => 'Titel-nachher')),
            $this->token()
        );

        $zeilen = $this->logZeilen($id);
        $nachModus = array_column($zeilen, 'model_label', 'mode');

        $this->assertSame('Titel-vorher', $nachModus['INS']);
        $this->assertSame('Titel-nachher', $nachModus['UPT'],
            'Jede Zeile haelt den Wert fest, der zum Zeitpunkt ihrer Operation galt');
    }

    // ── USERDEL ────────────────────────────────────────────────────────────────────────

    public function testEntziehenEinesBenutzersSchreibtEineUSERDELZeile(): void
    {
        // USERDEL entsteht, wenn einem Objekt ein Benutzer aus der users-Liste entzogen
        // wird — dem Virtualjoin aus Base, den 012-005-0001 als datenrelevant behalten hat.
        $adminId = (string) $this->pdo()->query("SELECT id FROM pim_user WHERE alias = 'admin'")->fetchColumn();
        $id      = $this->tagAnlegen('Userdel-Probe');

        $this->postJson(
            '/api/update',
            array('entity' => 'PIM\\Tag', 'id' => $id, 'data' => array('users' => array(array('id' => $adminId)))),
            $this->token()
        );

        $this->postJson(
            '/api/update',
            array('entity' => 'PIM\\Tag', 'id' => $id, 'data' => array('users' => array())),
            $this->token()
        );

        $modi = array_column($this->logZeilen($id), 'mode');

        $this->assertContains('USERDEL', $modi,
            'Das Entziehen eines Benutzers wird als eigener Modus protokolliert');
    }

    // ── Was NICHT protokolliert wird ───────────────────────────────────────────────────

    public function testEinAbgewiesenerSchreibversuchHinterlaesstKeineLogZeile(): void
    {
        $id = $this->tagAnlegen('Kein-Log-Probe');
        $vorher = count($this->logZeilen($id));

        [$status] = $this->postJson(
            '/api/update',
            array('entity' => 'PIM\\Tag', 'id' => $id, 'data' => array('title' => 'Ohne-Token'))
        );

        $this->assertSame(500, $status);
        $this->assertCount($vorher, $this->logZeilen($id),
            'Ohne Token entsteht weder eine Aenderung noch ein Protokolleintrag');
    }
}
