<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * Charakterisierungstests für die lesenden Endpunkte `/api/single` und `/api/list`.
 *
 * **Charakterisierung heißt: festhalten, was ist** — auch das Fragwürdige. Zwei der hier
 * festgehaltenen Merkwürdigkeiten sind inzwischen behoben und die Zusicherungen bewusst
 * umgedreht: Ein Zugriff ohne Token endet seit dem Stack-Wechsel (`006-002-0003`) mit 401
 * statt 500, und eine unbekannte Id liefert seit `000-000-0006` einen 404 statt eines 200 mit
 * leerem `headers`-Objekt. Was bleibt, beschreibt weiter den Ist-Zustand, den Epic `009` beim
 * Kernel-Tausch reproduzieren muss.
 *
 * Testdaten entstehen über `pdo()`, nicht über die Schreib-Endpunkte: Story `008-001` soll
 * nicht von `008-002` abhängen, und eine Vorbedingung über den ungeprüften Schreibpfad würde
 * die Aussagekraft der Lesetests untergraben.
 */
class ReadApiTest extends IntegrationTestCase
{
    private string $tagA = '';
    private string $tagB = '';
    private string $adminId = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminId = (string) $this->pdo()
            ->query("SELECT id FROM pim_user WHERE alias = 'admin'")
            ->fetchColumn();

        // Die Ids sind bewusst gegenlaeufig zu den Titeln vergeben: 'a-…' traegt 'Zeta',
        // 'z-…' traegt 'Alpha'. Damit unterscheiden sich die beiden moeglichen Sortierungen
        // — nach id absteigend und nach title aufsteigend — im Ergebnis eindeutig.
        $lauf = bin2hex(random_bytes(6));
        $this->tagB = $this->tagAnlegen('a-'.$lauf, 'Zeta',  null);
        $this->tagA = $this->tagAnlegen('z-'.$lauf, 'Alpha', $this->adminId);
    }

    private function tagAnlegen(string $id, string $titel, ?string $userCreated): string
    {

        $this->pdo()->prepare(
            'INSERT INTO pim_tag (id, title, created, modified, views, isIntern, usercreated_id)
             VALUES (:id, :titel, NOW(), NOW(), 0, 0, :uc)'
        )->execute(array('id' => $id, 'titel' => $titel, 'uc' => $userCreated));

        $this->nachTestLoeschen('pim_tag', $id);

        return $id;
    }

    // ── /api/single ────────────────────────────────────────────────────────────────────

    public function testSingleLiefertDasObjektImStandardEnvelope(): void
    {
        [$status, $body] = $this->postJson(
            '/api/single',
            array('entity' => 'PIM\\Tag', 'id' => $this->tagA),
            $this->token()
        );

        $this->assertSame(200, $status);
        $this->assertSame(array('ts', 'data', 'version', 'hash'), array_keys($body),
            'Der Envelope von /api/single — beachte: ohne totalItems, anders als /api/list');
        $this->assertSame($this->tagA, $body['data']['id']);
        $this->assertSame('Alpha', $body['data']['title']);
    }

    public function testDatumsfelderKommenAlsViererGruppe(): void
    {
        [, $body] = $this->postJson(
            '/api/single',
            array('entity' => 'PIM\\Tag', 'id' => $this->tagA),
            $this->token()
        );

        foreach (array('created', 'modified') as $feld) {
            $this->assertSame(
                array('LOCAL_TIME', 'LOCAL', 'ISO8601', 'TIMESTAMP'),
                array_keys($body['data'][$feld]),
                "Jedes datetime-Feld kommt als diese vier Darstellungen ($feld)"
            );
            $this->assertIsInt($body['data'][$feld]['TIMESTAMP']);
        }
    }

    public function testVerschachteltesObjektTraegtAlleEigenschaften(): void
    {
        // Seit 012-005-0003 sind verschachtelte Objekte nicht mehr auf die Listenspalten
        // der geloeschten Oberflaeche beschraenkt; begrenzt wird nur ueber DB_NESTED_LEVELS.
        [, $body] = $this->postJson(
            '/api/single',
            array('entity' => 'PIM\\Tag', 'id' => $this->tagA),
            $this->token()
        );

        $verjoint = $body['data']['userCreated'];

        $this->assertSame($this->adminId, $verjoint['id']);
        foreach (array('alias', 'isActive', 'isAdmin', 'isIntern', 'loginManager') as $feld) {
            $this->assertArrayHasKey($feld, $verjoint,
                "Verschachtelte Objekte liefern alle Eigenschaften, nicht nur die id ($feld)");
        }
        $this->assertArrayNotHasKey('pass', $verjoint, 'Der Passwort-Hash wird nicht ausgeliefert');
    }

    public function testUnbekannteIdLiefert404(): void
    {
        // Umgedreht mit 000-000-0006. Vorher hiess dieser Test
        // testUnbekannteIdLiefert200MitLeeremHeadersObjekt und hielt fest, dass der Endpunkt
        // mit 200 und `data: {"headers": {}}` antwortet — dem Artefakt aus einer JsonResponse,
        // die Api::getSingle() als "nicht gefunden" zurueckgab und singleAction() als Nutzlast
        // weiterreichte.
        [$status, $body] = $this->postJson(
            '/api/single',
            array('entity' => 'PIM\\Tag', 'id' => 'gibtesnicht'),
            $this->token()
        );

        $this->assertSame(404, $status);
        $this->assertSame('contentfly_general_not_found', $body['message']);
        $this->assertArrayNotHasKey('data', $body);
    }

    public function testUnbekannteEntityLiefert500(): void
    {
        // Ebenfalls 000-000-0006: die Ausnahme wird nicht in eine API-Antwort uebersetzt.
        [$status] = $this->postJson(
            '/api/single',
            array('entity' => 'PIM\\GibtesNicht', 'id' => 'egal'),
            $this->token()
        );

        $this->assertSame(404, $status,
            'Seit 006-002-0003 der gemeinte Code — Symfony 4.4 behebt hier 000-000-0006');
    }

    public function testSingleOhneTokenLiefert500(): void
    {
        // Der Zugriffsschutz greift, die Antwort ist nur die falsche: 500 statt 401.
        // In 012-004-0003 bereits so festgehalten, hier fuer /api/single wiederholt.
        [$status, $body] = $this->postJson(
            '/api/single',
            array('entity' => 'PIM\\Tag', 'id' => $this->tagA)
        );

        $this->assertSame(401, $status, 'Seit dem Stack-Wechsel (006-002-0003) der gemeinte Code — Symfony 4.4 behebt hier 000-000-0006');
        $this->assertArrayNotHasKey('data', $body, 'Ohne Token fliessen keine Daten');
    }

    // ── /api/list ──────────────────────────────────────────────────────────────────────

    public function testListLiefertEinenAnderenEnvelopeAlsSingle(): void
    {
        [$status, $body] = $this->postJson('/api/list', array('entity' => 'PIM\\Tag'), $this->token());

        $this->assertSame(200, $status);
        $this->assertSame(array('data', 'totalItems', 'version', 'hash'), array_keys($body),
            'list traegt totalItems, aber kein ts — single umgekehrt. Inkonsistent, aber Ist-Zustand.');
        $this->assertGreaterThanOrEqual(2, $body['totalItems']);
    }

    public function testListSortiertOhneOrderParameterNachIdAbsteigend(): void
    {
        // Der Ist-Zustand, und er ueberrascht: Api::getList() wertet sortBy/sortOrder der
        // Entity NICHT aus. Ohne `order` im Request bleibt es bei `ORDER BY id DESC`.
        // PIM\Tag traegt sortBy="title", sortOrder="ASC" — wirkungslos fuer diese Antwort.
        [, $body] = $this->postJson('/api/list', array('entity' => 'PIM\\Tag'), $this->token());

        $ids = array_values(array_intersect(
            array_column($body['data'], 'id'),
            array($this->tagA, $this->tagB)
        ));

        $this->assertSame(array($this->tagA, $this->tagB), $ids,
            'Ohne order-Parameter sortiert die Liste nach id absteigend — die Entity-Settings '
            .'sortBy/sortOrder bleiben unbeachtet');
    }

    public function testSortByUndSortOrderStehenImSchemaWirkenAberNichtAufDieAntwort(): void
    {
        // Festgehalten, weil 012-005-0002 diese beiden Felder als "Sortierung der
        // API-Antworten" behalten hat. Sie stehen im Schema und ein Client kann sie lesen,
        // aber kein Leser im Framework wendet sie an — anders als sortRestrictTo, das
        // JoinBidirectionalType tatsaechlich auswertet.
        [, $schema] = $this->postJson('/api/list', array('entity' => 'PIM\\Tag'), $this->token());
        [$status, $roh] = $this->get('/api/schema', $this->token());

        $this->assertSame(200, $status);
        $einstellungen = json_decode($roh, true)['data']['PIM\\Tag']['settings'];

        $this->assertSame('title', $einstellungen['sortBy']);
        $this->assertSame('ASC', $einstellungen['sortOrder']);

        $titel = array_values(array_intersect(
            array_column($schema['data'], 'title'),
            array('Alpha', 'Zeta')
        ));
        $this->assertSame(array('Alpha', 'Zeta'), $titel,
            'Zufall waere hier nicht erkennbar: Alpha liegt auf der hoeheren id und kommt '
            .'deshalb bei id-DESC zuerst — nicht wegen sortBy="title"');
    }

    public function testOrderParameterBestimmtDieReihenfolge(): void
    {
        [, $body] = $this->postJson(
            '/api/list',
            array('entity' => 'PIM\\Tag', 'order' => array('title' => 'ASC')),
            $this->token()
        );

        $titel = array_values(array_intersect(
            array_column($body['data'], 'title'),
            array('Alpha', 'Zeta')
        ));

        $this->assertSame(array('Alpha', 'Zeta'), $titel,
            'Die Sortierung kommt aus dem Request, nicht aus den Entity-Settings');
    }

    public function testPropertiesSchraenktDieFeldmengeEin(): void
    {
        [, $body] = $this->postJson(
            '/api/list',
            array('entity' => 'PIM\\Tag', 'properties' => array('id', 'title')),
            $this->token()
        );

        $this->assertNotEmpty($body['data']);
        foreach ($body['data'] as $eintrag) {
            $this->assertSame(array('id', 'title'), array_keys($eintrag),
                'Mit properties kommen genau die angeforderten Felder');
        }
    }

    public function testPartialSelectLiefertDieLabelPropertyDesVerjointenZiels(): void
    {
        // Schuetzt die Entscheidung aus 012-005-0002: labelProperty stand auf der
        // Streichliste, bleibt aber — Api::getList() nimmt genau dieses Feld des
        // verjointen Ziels mit in den partial-Select. PIM\User traegt labelProperty="alias".
        [, $body] = $this->postJson(
            '/api/list',
            array('entity' => 'PIM\\Tag', 'properties' => array('id', 'title', 'userCreated')),
            $this->token()
        );

        $mitBenutzer = array_values(array_filter(
            $body['data'],
            fn (array $e): bool => $e['id'] === $this->tagA
        ));

        $this->assertCount(1, $mitBenutzer);
        $this->assertSame($this->adminId, $mitBenutzer[0]['userCreated']['id']);
        $this->assertSame('admin', $mitBenutzer[0]['userCreated']['alias'],
            'Die labelProperty des Ziels ist im partial-Select enthalten — siehe 012-005-0002');
    }

    public function testListOhneTokenLiefertKeineDaten(): void
    {
        [$status, $body] = $this->postJson('/api/list', array('entity' => 'PIM\\Tag'));

        $this->assertSame(401, $status, 'Seit dem Stack-Wechsel (006-002-0003) der gemeinte Code — Symfony 4.4 behebt hier 000-000-0006');
        $this->assertArrayNotHasKey('data', $body);
    }
}
