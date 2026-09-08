<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * Charakterisierungstests für die Baum-Endpunkte `/api/tree` und `/api/tree2`.
 *
 * Die beiden gehen völlig verschiedene Wege und liefern dieselben Daten in **unvereinbaren
 * Formen**: `tree` baut über die Entities und serialisiert wie der Rest der API, `tree2` liest
 * eine einzige SQL-Abfrage gegen `pim_tree` aus und reicht die Rohwerte durch. Das ist keine
 * Feinheit, sondern der Unterschied zwischen `"created": {"LOCAL": …, "ISO8601": …}` und
 * `"created": "2026-09-07 09:12:23"`.
 *
 * Zwei Zusicherungen hier schützen Entscheidungen aus `012-005-0003`:
 * die Feldauswahl von `tree2` und das Quoting seiner Spaltennamen.
 */
class TreeApiTest extends IntegrationTestCase
{
    private string $wurzel = '';
    private string $kind   = '';

    protected function setUp(): void
    {
        parent::setUp();

        $lauf         = bin2hex(random_bytes(6));
        $this->wurzel = 'tree-w-'.$lauf;
        $this->kind   = 'tree-k-'.$lauf;

        $this->ordnerAnlegen($this->wurzel, 'Wurzel', null, 1);
        $this->ordnerAnlegen($this->kind,   'Kind',   $this->wurzel, 2);
    }

    private function ordnerAnlegen(string $id, string $titel, ?string $elternId, int $sortierung): void
    {
        $this->pdo()->prepare(
            'INSERT INTO pim_tree (id, sorting, isActive, created, modified, views, isIntern, dtype, parent_id)
             VALUES (:id, :s, 1, NOW(), NOW(), 0, 0, :dtype, :parent)'
        )->execute(array('id' => $id, 's' => $sortierung, 'dtype' => 'folder', 'parent' => $elternId));

        $this->pdo()->prepare('INSERT INTO pim_folder (id, title) VALUES (:id, :titel)')
             ->execute(array('id' => $id, 'titel' => $titel));

        // pim_folder zuerst — pim_tree traegt den Primaerschluessel, auf den es zeigt.
        $this->nachTestLoeschen('pim_tree', $id);
        $this->nachTestLoeschen('pim_folder', $id);
    }

    /** Sucht einen Knoten in einem Baum, unabhaengig vom Namen des Kind-Schluessels. */
    private function knoten(array $baum, string $id, string $kindSchluessel): ?array
    {
        foreach ($baum as $eintrag) {
            if (($eintrag['id'] ?? null) === $id) {
                return $eintrag;
            }
            $treffer = $this->knoten($eintrag[$kindSchluessel] ?? array(), $id, $kindSchluessel);
            if ($treffer !== null) {
                return $treffer;
            }
        }

        return null;
    }

    // ── /api/tree ──────────────────────────────────────────────────────────────────────

    public function testTreeHaengtKinderAlsTreeChildsAn(): void
    {
        [$status, $body] = $this->postJson('/api/tree', array('entity' => 'PIM\\Folder'), $this->token());

        $this->assertSame(200, $status);
        $this->assertSame(array('ts', 'data', 'version', 'hash'), array_keys($body));

        $wurzel = $this->knoten($body['data'], $this->wurzel, 'treeChilds');
        $this->assertNotNull($wurzel, 'Die Wurzel liegt auf der obersten Ebene');
        $this->assertSame('Wurzel', $wurzel['title']);

        $kindIds = array_column($wurzel['treeChilds'], 'id');
        $this->assertContains($this->kind, $kindIds, 'Das Kind haengt unter treeChilds der Wurzel');
    }

    public function testTreeSerialisiertWieDerRestDerApi(): void
    {
        [, $body] = $this->postJson('/api/tree', array('entity' => 'PIM\\Folder'), $this->token());
        $wurzel   = $this->knoten($body['data'], $this->wurzel, 'treeChilds');

        $this->assertSame(
            array('LOCAL_TIME', 'LOCAL', 'ISO8601', 'TIMESTAMP'),
            array_keys($wurzel['created']),
            'tree liefert Datumsfelder als Vierergruppe — anders als tree2'
        );
        $this->assertIsBool($wurzel['isActive'], 'tree liefert echte Boolesche Werte');
    }

    public function testTreePropertiesSchraenktDieFeldmengeEin(): void
    {
        [, $body] = $this->postJson(
            '/api/tree',
            array('entity' => 'PIM\\Folder', 'properties' => array('id', 'title')),
            $this->token()
        );

        $wurzel = $this->knoten($body['data'], $this->wurzel, 'treeChilds');

        $this->assertNotNull($wurzel);
        $this->assertSame('Wurzel', $wurzel['title']);
        $this->assertArrayNotHasKey('views', $wurzel, 'Nicht angeforderte Felder fehlen');
    }

    public function testTreeOhneTokenLiefertKeineDaten(): void
    {
        [$status, $body] = $this->postJson('/api/tree', array('entity' => 'PIM\\Folder'));

        $this->assertSame(401, $status, 'Seit dem Stack-Wechsel (006-002-0003) der gemeinte Code — Symfony 4.4 behebt hier 000-000-0006');
        $this->assertArrayNotHasKey('data', $body);
    }

    // ── /api/tree2 ─────────────────────────────────────────────────────────────────────

    public function testTree2HaengtKinderAlsChildsAnUndTraegtParent(): void
    {
        [$status, $body] = $this->postJson('/api/tree2', array('entity' => 'PIM\\Folder'), $this->token());

        $this->assertSame(200, $status);
        $this->assertSame(array('ts', 'data', 'version', 'hash'), array_keys($body));

        $wurzel = $this->knoten($body['data'], $this->wurzel, 'childs');
        $this->assertNotNull($wurzel);
        $this->assertSame(array('id' => null), $wurzel['parent'], 'Die Wurzel hat kein Elternteil');
        $this->assertSame(1, $wurzel['sorting']);

        $kind = $this->knoten($body['data'], $this->kind, 'childs');
        $this->assertNotNull($kind, 'Das Kind haengt unter childs — nicht treeChilds wie bei /api/tree');
        $this->assertSame(array('id' => $this->wurzel), $kind['parent']);
    }

    public function testTree2ReichtDieRohwerteDerDatenbankDurch(): void
    {
        // Der harte Unterschied zu /api/tree: keine Serialisierung ueber die Typ-Klassen.
        [, $body] = $this->postJson('/api/tree2', array('entity' => 'PIM\\Folder'), $this->token());
        $wurzel   = $this->knoten($body['data'], $this->wurzel, 'childs');

        $this->assertIsString($wurzel['created'],
            'tree2 liefert das Datum als SQL-String, nicht als Vierergruppe');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $wurzel['created']);
        $this->assertSame(1, $wurzel['isActive'], 'Boolesche Werte kommen als Integer durch');
    }

    public function testTree2LiefertAlleSkalarenFelder(): void
    {
        // Schuetzt 012-005-0003: Die Spaltenauswahl kam frueher aus `showInList` — der
        // Listenposition der geloeschten Oberflaeche. Seither sind es alle skalaren Felder.
        [, $body] = $this->postJson('/api/tree2', array('entity' => 'PIM\\Folder'), $this->token());
        $wurzel   = $this->knoten($body['data'], $this->wurzel, 'childs');

        foreach (array('title', 'isActive', 'created', 'modified', 'views', 'isIntern', 'sorting') as $feld) {
            $this->assertArrayHasKey($feld, $wurzel, "tree2 liefert alle skalaren Felder ($feld)");
        }
    }

    public function testTree2QuotetSpaltennamenUndVertraegtDasReservierteWortGroups(): void
    {
        // Regressionsschutz aus 012-005-0003: Ohne Backticks bricht die Abfrage, sobald
        // `groups` in der Auswahl landet — in MySQL 8 ein reserviertes Wort. PIM\Folder erbt
        // von BaseTree und traegt damit `users` und `groups`; ein erfolgreicher Abruf ist
        // der Beweis, dass gequotet wird.
        [$status, $body] = $this->postJson('/api/tree2', array('entity' => 'PIM\\Folder'), $this->token());

        $this->assertSame(200, $status, 'Ohne Quoting waere das ein SQL-Syntaxfehler');

        $wurzel = $this->knoten($body['data'], $this->wurzel, 'childs');
        $this->assertArrayHasKey('groups', $wurzel, 'Das reservierte Wort ist Teil der Auswahl');
        $this->assertArrayHasKey('users', $wurzel);
    }

    public function testTree2OhneTokenLiefertKeineDaten(): void
    {
        [$status, $body] = $this->postJson('/api/tree2', array('entity' => 'PIM\\Folder'));

        $this->assertSame(401, $status, 'Seit dem Stack-Wechsel (006-002-0003) der gemeinte Code — Symfony 4.4 behebt hier 000-000-0006');
        $this->assertArrayNotHasKey('data', $body);
    }
}
