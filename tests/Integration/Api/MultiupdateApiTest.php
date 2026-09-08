<?php
namespace Tests\Integration\Api;

use PDO;
use Tests\Integration\IntegrationTestCase;

/**
 * Charakterisierungstests für `/api/multiupdate`.
 *
 * **Der wichtigste Test hier hält einen Mangel fest.** Der Endpunkt läuft ohne Transaktion:
 * Scheitert ein Objekt mitten im Stapel, bleiben die vorher verarbeiteten geändert, die
 * danach werden nie angefasst, und die Antwort ist ein nackter HTTP 500. Der Client kann
 * daraus nicht ableiten, in welchem Zustand seine Daten sind.
 *
 * Das ist der Ist-Zustand, und die Abgrenzung von Epic `008` verlangt ihn festzuhalten statt
 * die Transaktion zu prüfen, die man sich wünscht. Task `000-000-0009` entscheidet, was an
 * seine Stelle tritt; wer ihn umsetzt, dreht `testTeilfehlerLaesstDasVorherigeGeschrieben()`
 * bewusst um.
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

    public function testDieAntwortNenntWederZeitstempelNochErgebnis(): void
    {
        // Der duennste Envelope aller Endpunkte: multiupdateAction() gibt
        // renderResponse(array()) zurueck. Kein ts, kein data, keine Liste der
        // aktualisierten Ids — der Aufruf ist auch im Erfolgsfall nicht auswertbar.
        [, $body] = $this->postJson('/api/multiupdate', array('objects' => array(
            array('entity' => 'PIM\\Tag', 'id' => $this->ersterTag, 'data' => array('title' => 'Envelope-Probe')),
        )), $this->token());

        $this->assertSame(array('version', 'hash'), array_keys($body),
            'Siehe 000-000-0009: der Erfolgsfall meldet nicht, was er getan hat');
    }

    // ── Teilfehler — der eigentliche Befund ────────────────────────────────────────────

    public function testTeilfehlerLaesstDasVorherigeGeschrieben(): void
    {
        // Ist-Zustand, siehe 000-000-0009: keine Transaktion, kein Rollback.
        // Der Stapel bricht am zweiten Eintrag ab.
        [$status] = $this->postJson('/api/multiupdate', array('objects' => array(
            array('entity' => 'PIM\\Tag', 'id' => $this->ersterTag,  'data' => array('title' => 'Vor-dem-Fehler')),
            array('entity' => 'PIM\\Tag', 'id' => 'gibtesnicht',     'data' => array('title' => 'Scheitert')),
            array('entity' => 'PIM\\Tag', 'id' => $this->letzterTag, 'data' => array('title' => 'Nach-dem-Fehler')),
        )), $this->token());

        $this->assertSame(500, $status);

        $this->assertSame('Vor-dem-Fehler', $this->titel($this->ersterTag),
            'Das vor dem Fehler verarbeitete Objekt bleibt geaendert — es gibt keinen Rollback');
        $this->assertSame('Letzter', $this->titel($this->letzterTag),
            'Das Objekt nach dem Fehler wird nie erreicht');
    }

    public function testDieAntwortVerraetNichtWieWeitDerStapelKam(): void
    {
        // Die zweite Haelfte des Mangels: Der Client erhaelt einen nackten Fehler und kann
        // den Zustand seiner Daten nicht rekonstruieren.
        [$status, $body] = $this->postJson('/api/multiupdate', array('objects' => array(
            array('entity' => 'PIM\\Tag', 'id' => $this->ersterTag, 'data' => array('title' => 'Egal')),
            array('entity' => 'PIM\\Tag', 'id' => 'gibtesnicht',    'data' => array('title' => 'Scheitert')),
        )), $this->token());

        $this->assertSame(500, $status);
        $this->assertArrayNotHasKey('data', $body);
        $this->assertArrayNotHasKey('objects', $body,
            'Keine Liste, welche Objekte durchgingen — siehe 000-000-0009');
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
        [$status] = $this->postJson('/api/multiupdate', array('objects' => array()), $this->token());

        $this->assertSame(200, $status, 'Ein leerer Stapel laeuft durch, ohne etwas zu tun');
    }
}
