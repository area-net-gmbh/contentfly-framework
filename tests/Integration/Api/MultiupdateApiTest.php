<?php
namespace Tests\Integration\Api;

use PDO;
use Tests\Integration\IntegrationTestCase;

/**
 * Charakterisierungstests für `/api/multiupdate`.
 *
 * **Zwei dieser Tests hielten einen Mangel fest und sind mit `000-000-0009` umgedreht.**
 * Der Endpunkt lief ohne Transaktion: Scheiterte ein Objekt mitten im Stapel, blieben die
 * vorher verarbeiteten geändert, die danach wurden nie angefasst, und die Antwort war ein
 * Fehler ohne Angabe, wie weit es kam.
 *
 * Seit `000-000-0009` gilt ganz oder gar nicht. `testTeilfehlerLaesstDasVorherigeGeschrieben()`
 * heisst jetzt `testTeilfehlerRolltDenGanzenStapelZurueck()` und prüft das Gegenteil dessen,
 * was es vorher zusicherte; `testDieAntwortNenntWederZeitstempelNochErgebnis()` ist zu
 * `testDieAntwortNenntZeitstempelUndDieGeaendertenObjekte()` geworden. Beide sind bewusst
 * umgeschrieben und nicht gelöscht — die alte Zusicherung steht im Verlauf.
 */
class MultiupdateApiTest extends IntegrationTestCase
{
    private string $ersterTag = '';
    private string $letzterTag = '';

    protected function setUp(): void
    {
        parent::setUp();

        $lauf = bin2hex(random_bytes(6));

        // 'a-' und 'z-', damit die Reihenfolge im Stapel unabhaengig von der Id-Sortierung
        // allein durch die Reihenfolge im Request bestimmt ist.
        $this->ersterTag  = $this->tagAnlegen('mu-a-'.$lauf, 'Erster');
        $this->letzterTag = $this->tagAnlegen('mu-z-'.$lauf, 'Letzter');
    }

    private function tagAnlegen(string $id, string $titel): string
    {
        $this->pdo()->prepare(
            'INSERT INTO pim_tag (id, title, created, modified, views, isIntern)
             VALUES (:id, :titel, NOW(), NOW(), 0, 0)'
        )->execute(array('id' => $id, 'titel' => $titel));

        $this->nachTestLoeschen('pim_tag', $id);

        return $id;
    }

    protected function tearDown(): void
    {
        foreach ($this->pdo()->query("SELECT id FROM pim_log WHERE model_name = 'PIM\\\\Tag'")->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $this->pdo()->prepare('DELETE FROM pim_log WHERE id = :id')->execute(array('id' => $id));
        }

        parent::tearDown();
    }

    private function titel(string $id): string
    {
        return (string) $this->pdo()
            ->query('SELECT title FROM pim_tag WHERE id = '.$this->pdo()->quote($id))
            ->fetchColumn();
    }

    // ── Erfolgsfall ────────────────────────────────────────────────────────────────────

    public function testMehrereObjekteWerdenInEinemAufrufGeaendert(): void
    {
        [$status] = $this->postJson('/api/multiupdate', array('objects' => array(
            array('entity' => 'PIM\\Tag', 'id' => $this->ersterTag,  'data' => array('title' => 'Erster-neu')),
            array('entity' => 'PIM\\Tag', 'id' => $this->letzterTag, 'data' => array('title' => 'Letzter-neu')),
        )), $this->token());

        $this->assertSame(200, $status);
        $this->assertSame('Erster-neu', $this->titel($this->ersterTag));
        $this->assertSame('Letzter-neu', $this->titel($this->letzterTag));
    }

    public function testDieAntwortNenntZeitstempelUndDieGeaendertenObjekte(): void
    {
        // Vorher der duennste Envelope aller Endpunkte: renderResponse(array()) — kein ts,
        // kein data, keine Liste der aktualisierten Ids. Seit 000-000-0009 zaehlt die Antwort
        // auf, was geschrieben wurde, in der Reihenfolge des Requests.
        [$status, $body] = $this->postJson('/api/multiupdate', array('objects' => array(
            array('entity' => 'PIM\\Tag', 'id' => $this->ersterTag,  'data' => array('title' => 'Envelope-Probe')),
            array('entity' => 'PIM\\Tag', 'id' => $this->letzterTag, 'data' => array('title' => 'Envelope-Probe-2')),
        )), $this->token());

        $this->assertSame(200, $status);
        $this->assertArrayHasKey('ts', $body);
        $this->assertSame(array(
            array('entity' => 'PIM\\Tag', 'id' => $this->ersterTag),
            array('entity' => 'PIM\\Tag', 'id' => $this->letzterTag),
        ), $body['data']);
    }

    // ── Teilfehler — der eigentliche Befund ────────────────────────────────────────────

    public function testTeilfehlerRolltDenGanzenStapelZurueck(): void
    {
        // Umgedreht mit 000-000-0009. Vorher hielt dieser Test fest, dass 'Vor-dem-Fehler'
        // stehen bleibt; jetzt darf genau das nicht passieren.
        [$status] = $this->postJson('/api/multiupdate', array('objects' => array(
            array('entity' => 'PIM\\Tag', 'id' => $this->ersterTag,  'data' => array('title' => 'Vor-dem-Fehler')),
            array('entity' => 'PIM\\Tag', 'id' => 'gibtesnicht',     'data' => array('title' => 'Scheitert')),
            array('entity' => 'PIM\\Tag', 'id' => $this->letzterTag, 'data' => array('title' => 'Nach-dem-Fehler')),
        )), $this->token());

        // Heute 500 statt der 404, die doUpdate() wirft — siehe 000-000-0006: die Ausnahme
        // erreicht den Fehlerhandler der Anwendung nicht. Der Code ist unveraendert der von
        // vorher; was sich mit 000-000-0009 aendert, steht in der Datenbank.
        $this->assertSame(500, $status);

        $this->assertSame('Erster', $this->titel($this->ersterTag),
            'Das vor dem Fehler verarbeitete Objekt ist zurueckgerollt');
        $this->assertSame('Letzter', $this->titel($this->letzterTag),
            'Das Objekt nach dem Fehler wird weiterhin nie erreicht');
    }

    public function testEinFehlerHinterlaesstAuchKeineProtokollzeile(): void
    {
        // Die Rueckabwicklung muss auch das mitnehmen, was doUpdate() nebenbei schreibt:
        // pim_log bekommt je Aenderung eine Zeile. Bliebe sie stehen, behauptete das
        // Protokoll eine Aenderung, die es nicht mehr gibt.
        $vorher = (int) $this->pdo()->query("SELECT COUNT(*) FROM pim_log WHERE model_name = 'PIM\\\\Tag'")->fetchColumn();

        $this->postJson('/api/multiupdate', array('objects' => array(
            array('entity' => 'PIM\\Tag', 'id' => $this->ersterTag, 'data' => array('title' => 'Wird-zurueckgerollt')),
            array('entity' => 'PIM\\Tag', 'id' => 'gibtesnicht',    'data' => array('title' => 'Scheitert')),
        )), $this->token());

        $nachher = (int) $this->pdo()->query("SELECT COUNT(*) FROM pim_log WHERE model_name = 'PIM\\\\Tag'")->fetchColumn();

        $this->assertSame($vorher, $nachher, 'Kein Log-Eintrag ueberlebt den Rollback');
    }

    public function testDerFehlerfallMeldetKeineGeaendertenObjekte(): void
    {
        // Die Antwort im Fehlerfall wird hier bewusst NICHT umgebaut: der Rumpf ist Sache von
        // 000-000-0006 (er kommt heute gar nicht aus dem Fehlerhandler der Anwendung), die
        // Vereinheitlichung der Envelopes ist 000-000-0014. Was 000-000-0009 zusichert, steht
        // in der Datenbank — und genau das wird hier geprueft.
        [$status, $body] = $this->postJson('/api/multiupdate', array('objects' => array(
            array('entity' => 'PIM\\Tag', 'id' => $this->ersterTag, 'data' => array('title' => 'Egal')),
            array('entity' => 'PIM\\Tag', 'id' => 'gibtesnicht',    'data' => array('title' => 'Scheitert')),
        )), $this->token());

        $this->assertSame(500, $status);
        $this->assertArrayNotHasKey('data', $body,
            'Die Fehlerantwort behauptet keine Aenderung');
        $this->assertSame('Erster', $this->titel($this->ersterTag),
            'Und es gab auch keine — der Stapel ist als Ganzes gescheitert');
    }

    // ── Absicherung ────────────────────────────────────────────────────────────────────

    public function testMultiupdateOhneTokenAendertNichts(): void
    {
        [$status] = $this->postJson('/api/multiupdate', array('objects' => array(
            array('entity' => 'PIM\\Tag', 'id' => $this->ersterTag, 'data' => array('title' => 'Ohne-Token')),
        )));

        $this->assertSame(401, $status, 'Seit dem Stack-Wechsel (006-002-0003) der gemeinte Code — Symfony 4.4 behebt hier 000-000-0006');
        $this->assertSame('Erster', $this->titel($this->ersterTag),
            'Ohne Token bleibt der Wert unveraendert — gegen die Datenbank geprueft');
    }

    public function testLeererStapelIstKeinFehler(): void
    {
        [$status, $body] = $this->postJson('/api/multiupdate', array('objects' => array()), $this->token());

        $this->assertSame(200, $status, 'Ein leerer Stapel laeuft durch, ohne etwas zu tun');
        $this->assertSame(array(), $body['data'], 'und meldet eine leere Liste, keine fehlende');
    }

    public function testEinFehlendesObjectsIstEinFehlerUndKeinLeerlauf(): void
    {
        // Vorher lief foreach ueber null durch und der Aufruf endete mit 200. Seit die Antwort
        // aufzaehlt, was geschrieben wurde, waere das eine falsche Auskunft (000-000-0009).
        [$status] = $this->postJson('/api/multiupdate', array(), $this->token());

        $this->assertSame(500, $status);
    }
}
