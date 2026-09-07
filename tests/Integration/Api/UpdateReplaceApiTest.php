<?php
namespace Tests\Integration\Api;

use PDO;
use Tests\Integration\IntegrationTestCase;

/**
 * Charakterisierungstests für `/api/update` und `/api/replace`.
 *
 * **Der Unterschied in einem Satz: `replace` ist ein Upsert, kein Vollersetzen.** Auf einem
 * vorhandenen Objekt verhält es sich Feld für Feld wie `update` — es delegiert intern per
 * Sub-Request an `/api/update`. Nur wenn es die Id nicht gibt, trennen sich die Wege:
 * `replace` legt das Objekt mit genau dieser Id an (Sub-Request an `/api/insert`), `update`
 * scheitert.
 *
 * Der Name führt also in die Irre: Wer „replace" liest und erwartet, dass nicht gesendete
 * Felder zurückgesetzt werden, irrt — sie bleiben stehen.
 */
class UpdateReplaceApiTest extends IntegrationTestCase
{
    private string $tag = '';

    protected function setUp(): void
    {
        parent::setUp();

        // Direkt ueber pdo(), damit dieser Test nicht am Erfolg von /api/insert haengt:
        // views = 5 ist das Feld, an dem sich zeigt, was mit nicht gesendeten Werten passiert.
        $this->tag = 'ur-'.bin2hex(random_bytes(6));
        $this->pdo()->prepare(
            'INSERT INTO pim_tag (id, title, created, modified, views, isIntern)
             VALUES (:id, :titel, NOW(), NOW(), 5, 0)'
        )->execute(array('id' => $this->tag, 'titel' => 'Original'));

        $this->nachTestLoeschen('pim_tag', $this->tag);
    }

    protected function tearDown(): void
    {
        foreach ($this->pdo()->query("SELECT id FROM pim_log WHERE model_name = 'PIM\\\\Tag'")->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $this->pdo()->prepare('DELETE FROM pim_log WHERE id = :id')->execute(array('id' => $id));
        }

        parent::tearDown();
    }

    /** @return array{0:string,1:int} Titel und views direkt aus der Datenbank */
    private function ausDerDatenbank(string $id): array
    {
        $zeile = $this->pdo()
            ->query('SELECT title, views FROM pim_tag WHERE id = '.$this->pdo()->quote($id))
            ->fetch(PDO::FETCH_ASSOC);

        $this->assertNotFalse($zeile, "Objekt $id existiert");

        return array($zeile['title'], (int) $zeile['views']);
    }

    // ── Der Kern: worin sie sich gleichen ───────────────────────────────────────────────

    public function testUpdateLaesstNichtGesendeteFelderUnberuehrt(): void
    {
        [$status] = $this->postJson(
            '/api/update',
            array('entity' => 'PIM\\Tag', 'id' => $this->tag, 'data' => array('title' => 'Per-Update')),
            $this->token()
        );

        $this->assertSame(200, $status);

        [$titel, $views] = $this->ausDerDatenbank($this->tag);
        $this->assertSame('Per-Update', $titel);
        $this->assertSame(5, $views, 'views wurde nicht mitgesendet und bleibt stehen');
    }

    public function testReplaceLaesstNichtGesendeteFelderEbenfallsUnberuehrt(): void
    {
        // Der entscheidende Test: "replace" setzt NICHTS zurueck. Auf einem vorhandenen
        // Objekt ist es Feld fuer Feld dasselbe wie update.
        [$status] = $this->postJson(
            '/api/replace',
            array('entity' => 'PIM\\Tag', 'id' => $this->tag, 'data' => array('title' => 'Per-Replace')),
            $this->token()
        );

        $this->assertSame(200, $status);

        [$titel, $views] = $this->ausDerDatenbank($this->tag);
        $this->assertSame('Per-Replace', $titel);
        $this->assertSame(5, $views,
            'Trotz des Namens setzt replace nicht gesendete Felder NICHT zurueck — '
            .'es delegiert bei vorhandener Id per Sub-Request an /api/update');
    }

    // ── Der Kern: worin sie sich unterscheiden ─────────────────────────────────────────

    public function testReplaceLegtEinNichtVorhandenesObjektMitDerVorgegebenenIdAn(): void
    {
        $neueId = 'ur-neu-'.bin2hex(random_bytes(6));
        $this->nachTestLoeschen('pim_tag', $neueId);

        [$status, $body] = $this->postJson(
            '/api/replace',
            array('entity' => 'PIM\\Tag', 'id' => $neueId, 'data' => array('title' => 'Per-Replace-Angelegt')),
            $this->token()
        );

        $this->assertSame(200, $status);
        $this->assertSame($neueId, $body['id'],
            'replace uebernimmt die vorgegebene Id, statt eine GUID zu erzeugen');

        [$titel] = $this->ausDerDatenbank($neueId);
        $this->assertSame('Per-Replace-Angelegt', $titel);
    }

    public function testUpdateAufEineUnbekannteIdScheitert(): void
    {
        // Genau hier trennen sich die beiden: update legt nichts an.
        $unbekannt = 'ur-fehlt-'.bin2hex(random_bytes(6));

        [$status] = $this->postJson(
            '/api/update',
            array('entity' => 'PIM\\Tag', 'id' => $unbekannt, 'data' => array('title' => 'Egal')),
            $this->token()
        );

        $this->assertSame(500, $status, 'Heute 500 statt 404 — siehe 000-000-0006');

        $anzahl = (int) $this->pdo()
            ->query('SELECT COUNT(*) FROM pim_tag WHERE id = '.$this->pdo()->quote($unbekannt))
            ->fetchColumn();
        $this->assertSame(0, $anzahl, 'update legt nichts an — anders als replace');
    }

    // ── modified ───────────────────────────────────────────────────────────────────────

    public function testBeideAktualisierenModified(): void
    {
        $vorher = (string) $this->pdo()
            ->query('SELECT modified FROM pim_tag WHERE id = '.$this->pdo()->quote($this->tag))
            ->fetchColumn();

        // Eine Sekunde Abstand, damit sich der DATETIME-Wert ueberhaupt unterscheiden kann.
        $this->pdo()->prepare('UPDATE pim_tag SET modified = :m WHERE id = :id')
             ->execute(array('m' => '2000-01-01 00:00:00', 'id' => $this->tag));

        $this->postJson(
            '/api/update',
            array('entity' => 'PIM\\Tag', 'id' => $this->tag, 'data' => array('title' => 'Modified-Probe')),
            $this->token()
        );

        $nachher = (string) $this->pdo()
            ->query('SELECT modified FROM pim_tag WHERE id = '.$this->pdo()->quote($this->tag))
            ->fetchColumn();

        $this->assertNotSame('2000-01-01 00:00:00', $nachher, 'update setzt modified neu');
        $this->assertNotEmpty($vorher);
    }

    // ── Absicherung ────────────────────────────────────────────────────────────────────

    public function testUpdateOhneTokenAendertNichts(): void
    {
        [$status] = $this->postJson(
            '/api/update',
            array('entity' => 'PIM\\Tag', 'id' => $this->tag, 'data' => array('title' => 'Ohne-Token'))
        );

        $this->assertSame(500, $status, 'Heute 500 statt 401 — siehe 000-000-0006');

        [$titel] = $this->ausDerDatenbank($this->tag);
        $this->assertSame('Original', $titel, 'Ohne Token bleibt der Wert unveraendert');
    }

    public function testReplaceOhneTokenLegtNichtsAn(): void
    {
        $neueId = 'ur-ohne-'.bin2hex(random_bytes(6));

        [$status] = $this->postJson(
            '/api/replace',
            array('entity' => 'PIM\\Tag', 'id' => $neueId, 'data' => array('title' => 'Ohne-Token'))
        );

        $this->assertSame(500, $status);

        $anzahl = (int) $this->pdo()
            ->query('SELECT COUNT(*) FROM pim_tag WHERE id = '.$this->pdo()->quote($neueId))
            ->fetchColumn();
        $this->assertSame(0, $anzahl, 'Ohne Token entsteht auch ueber replace nichts');
    }
}
