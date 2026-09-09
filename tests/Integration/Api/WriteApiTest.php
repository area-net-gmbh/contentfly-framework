<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * Charakterisierungstests für `/api/insert` und `/api/delete` — die beiden Enden des
 * Lebenszyklus.
 *
 * Anders als bei den Lesetests entstehen die Daten hier **über die API selbst**: Der
 * Schreibweg ist der Prüfgegenstand. Aufgeräumt wird trotzdem über `pdo()`, damit ein
 * gescheiterter Test die folgenden nicht mit einfärbt.
 */
class WriteApiTest extends IntegrationTestCase
{
    /** Legt einen Tag über die API an und meldet ihn zum Aufräumen an. */
    private function tagAnlegen(string $titel): array
    {
        [$status, $body] = $this->postJson(
            '/api/insert',
            array('entity' => 'PIM\\Tag', 'data' => array('title' => $titel)),
            $this->token()
        );

        $this->assertSame(200, $status, 'Vorbedingung: das Anlegen gelingt');

        $this->nachTestLoeschen('pim_tag', $body['id']);
        $this->logZeilenAufraeumen($body['id']);

        return $body;
    }

    /** Jede Schreiboperation hinterlaesst Log-Zeilen; die gehoeren mit weggeraeumt. */
    private function logZeilenAufraeumen(string $modelId): void
    {
        $ids = $this->pdo()
            ->query('SELECT id FROM pim_log WHERE model_id = '.$this->pdo()->quote($modelId))
            ->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($ids as $id) {
            $this->nachTestLoeschen('pim_log', $id);
        }
    }

    protected function tearDown(): void
    {
        // Log-Zeilen entstehen erst beim Schreiben — also nach dem Anmelden zum Aufraeumen.
        // Deshalb hier noch einmal nachfassen, bevor die Basis aufraeumt.
        foreach ($this->pdo()->query("SELECT id, model_id FROM pim_log WHERE model_name = 'PIM\\\\Tag'") as $zeile) {
            $this->pdo()->prepare('DELETE FROM pim_log WHERE id = :id')->execute(array('id' => $zeile['id']));
        }

        parent::tearDown();
    }

    // ── /api/insert ────────────────────────────────────────────────────────────────────

    public function testInsertLiefertDieErzeugteIdAufOberSterEbene(): void
    {
        $body = $this->tagAnlegen('Insert-Probe');

        $this->assertSame(array('ts', 'id', 'data', 'version', 'hash'), array_keys($body),
            'insert traegt die erzeugte id neben data auf oberster Ebene — anders als single und list');
        $this->assertSame($body['id'], $body['data']['id']);
    }

    public function testInsertErzeugtEineGuid(): void
    {
        $body = $this->tagAnlegen('Guid-Probe');

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $body['id'],
            'Die Installation lief mit --db-strategy=guid'
        );
    }

    public function testInsertSetztCreatedModifiedUndUserCreated(): void
    {
        $body = $this->tagAnlegen('Automatik-Probe');
        $daten = $body['data'];

        foreach (array('created', 'modified') as $feld) {
            $this->assertSame(
                array('LOCAL_TIME', 'LOCAL', 'ISO8601', 'TIMESTAMP'),
                array_keys($daten[$feld]),
                "$feld ist automatisch gesetzt und kommt als Vierergruppe"
            );
            $this->assertGreaterThan(0, $daten[$feld]['TIMESTAMP']);
        }

        $adminId = (string) $this->pdo()->query("SELECT id FROM pim_user WHERE alias = 'admin'")->fetchColumn();
        $this->assertSame(array('id' => $adminId), $daten['userCreated'],
            'userCreated wird auf den angemeldeten Benutzer gesetzt');
    }

    public function testDasAngelegteObjektIstUeberSingleAbrufbar(): void
    {
        $body = $this->tagAnlegen('Abruf-Probe');

        [$status, $single] = $this->postJson(
            '/api/single',
            array('entity' => 'PIM\\Tag', 'id' => $body['id']),
            $this->token()
        );

        $this->assertSame(200, $status);
        $this->assertSame('Abruf-Probe', $single['data']['title']);
    }

    public function testInsertUndSingleStellenBoolescheWerteUnterschiedlichDar(): void
    {
        // Ist-Zustand und inkonsistent: Die Antwort von insert reicht den Rohwert durch
        // (isIntern als 0), waehrend single ueber die Typ-Klassen serialisiert (false).
        $body = $this->tagAnlegen('Boolean-Probe');

        [, $single] = $this->postJson(
            '/api/single',
            array('entity' => 'PIM\\Tag', 'id' => $body['id']),
            $this->token()
        );

        $this->assertSame(0, $body['data']['isIntern'], 'insert liefert den Integer');
        $this->assertFalse($single['data']['isIntern'], 'single liefert den Booleschen Wert');
    }

    public function testInsertOhneDatenWirdAbgewiesen(): void
    {
        [$status] = $this->postJson('/api/insert', array('entity' => 'PIM\\Tag'), $this->token());

        $this->assertSame(500, $status, 'Heute 500 statt 400 — siehe 000-000-0006');
    }

    public function testInsertMitUnbekannterEntityWirdAbgewiesen(): void
    {
        [$status] = $this->postJson(
            '/api/insert',
            array('entity' => 'PIM\\GibtesNicht', 'data' => array('title' => 'egal')),
            $this->token()
        );

        $this->assertSame(404, $status,
            'Seit 006-002-0003 der gemeinte Code — Symfony 4.4 behebt hier 000-000-0006');
    }

    public function testInsertOhneTokenLegtNichtsAn(): void
    {
        [$status] = $this->postJson(
            '/api/insert',
            array('entity' => 'PIM\\Tag', 'data' => array('title' => 'Ohne-Token'))
        );

        $this->assertSame(401, $status, 'Seit dem Stack-Wechsel (006-002-0003) der gemeinte Code — Symfony 4.4 behebt hier 000-000-0006');

        $anzahl = (int) $this->pdo()
            ->query("SELECT COUNT(*) FROM pim_tag WHERE title = 'Ohne-Token'")
            ->fetchColumn();
        $this->assertSame(0, $anzahl, 'Ohne Token entsteht kein Objekt — gegen die Datenbank geprueft');
    }

    // ── /api/delete ────────────────────────────────────────────────────────────────────

    public function testDeleteEntferntDasObjekt(): void
    {
        $body = $this->tagAnlegen('Loesch-Probe');

        [$status, $antwort] = $this->postJson(
            '/api/delete',
            array('entity' => 'PIM\\Tag', 'id' => $body['id']),
            $this->token()
        );

        $this->assertSame(200, $status);
        $this->assertSame(array('ts', 'id', 'version', 'hash'), array_keys($antwort),
            'delete liefert die id zurueck, aber kein data');
        $this->assertSame($body['id'], $antwort['id']);

        $anzahl = (int) $this->pdo()
            ->query('SELECT COUNT(*) FROM pim_tag WHERE id = '.$this->pdo()->quote($body['id']))
            ->fetchColumn();
        $this->assertSame(0, $anzahl, 'Die Zeile ist wirklich weg — gegen die Datenbank geprueft');
    }

    public function testNachDemLoeschenLiefertSingleEinen404(): void
    {
        $body = $this->tagAnlegen('Nachher-Probe');
        $this->postJson('/api/delete', array('entity' => 'PIM\\Tag', 'id' => $body['id']), $this->token());

        [$status, $single] = $this->postJson(
            '/api/single',
            array('entity' => 'PIM\\Tag', 'id' => $body['id']),
            $this->token()
        );

        // Umgedreht mit 000-000-0006: dasselbe wie bei einer nie existierenden Id — vorher das
        // `headers`-Artefakt mit 200, jetzt ein 404. Der Test hiess bis dahin
        // testNachDemLoeschenLiefertSingleDasLeereHeadersArtefakt.
        $this->assertSame(404, $status);
        $this->assertArrayNotHasKey('data', $single);
    }

    public function testDeleteMitUnbekannterIdWirdAbgewiesen(): void
    {
        [$status] = $this->postJson(
            '/api/delete',
            array('entity' => 'PIM\\Tag', 'id' => 'gibtesnicht'),
            $this->token()
        );

        // Seit 000-000-0006 der gemeinte Code. Vorher lief doUpdate()/doDelete() an der
        // eigenen Nicht-gefunden-Pruefung vorbei, weil getSingle() eine JsonResponse
        // zurueckgab, und starb weiter unten an einem TypeError.
        $this->assertSame(404, $status);
    }

    public function testDeleteOhneTokenLoeschtNichts(): void
    {
        $body = $this->tagAnlegen('Geschuetzt-Probe');

        [$status] = $this->postJson('/api/delete', array('entity' => 'PIM\\Tag', 'id' => $body['id']));

        $this->assertSame(401, $status, 'Seit dem Stack-Wechsel (006-002-0003) der gemeinte Code — Symfony 4.4 behebt hier 000-000-0006');

        $anzahl = (int) $this->pdo()
            ->query('SELECT COUNT(*) FROM pim_tag WHERE id = '.$this->pdo()->quote($body['id']))
            ->fetchColumn();
        $this->assertSame(1, $anzahl, 'Ohne Token bleibt das Objekt bestehen');
    }
}
