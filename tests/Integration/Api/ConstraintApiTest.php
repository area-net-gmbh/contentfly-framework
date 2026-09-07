<?php
namespace Tests\Integration\Api;

use PDO;
use Tests\Integration\IntegrationTestCase;

/**
 * Charakterisierungstests für die verbliebenen Nebenwirkungen der Schreibseite —
 * `unique`-Verletzungen und die Sortierung von `BaseSortable`-Entities.
 *
 * Dazu die ehrliche Buchführung über zwei Nebenwirkungen, die **heute keinen
 * Prüfgegenstand haben**: die `encoded`-Verschlüsselung und die OneJoin-Kaskade beim
 * Löschen. Für beide gibt es einen Test auf die *Vorbedingung* statt auf die Wirkung — er
 * schlägt an, sobald jemand eine passende Entity anlegt, und fordert damit den fehlenden
 * Nachweis ein, statt ihn stillschweigend ausfallen zu lassen.
 *
 * Dasselbe Muster wie bei `excludeFromSync` und `i18n_universal` in Story `008-001`. Dass es
 * sich häuft, ist selbst ein Befund: Das Framework trägt Funktionen, deren einzige Nutzer die
 * gelöschte Oberfläche oder Kundenprojekte waren.
 */
class ConstraintApiTest extends IntegrationTestCase
{
    /** @var array<int,string> */
    private array $beobachtet = array();

    private function tagAnlegen(string $titel): array
    {
        [$status, $body] = $this->postJson(
            '/api/insert',
            array('entity' => 'PIM\\Tag', 'data' => array('title' => $titel)),
            $this->token()
        );

        if ($status === 200) {
            $this->nachTestLoeschen('pim_tag', $body['id']);
            $this->beobachtet[] = $body['id'];
        }

        return array($status, $body);
    }

    protected function tearDown(): void
    {
        foreach ($this->beobachtet as $modelId) {
            $this->pdo()->prepare('DELETE FROM pim_log WHERE model_id = :id')->execute(array('id' => $modelId));
        }

        $this->beobachtet = array();

        parent::tearDown();
    }

    private function schema(): array
    {
        [$status, $roh] = $this->get('/api/schema', $this->token());
        $this->assertSame(200, $status);

        return json_decode($roh, true)['data'];
    }

    // ── unique ─────────────────────────────────────────────────────────────────────────

    public function testEineUniqueVerletzungWirdAbgewiesen(): void
    {
        // PIM\Tag.title traegt beides: @ORM\Column(unique=true) auf Datenbankebene und
        // @PIM\Config(unique=true) im Schema. Api prueft letzteres.
        $titel = 'Einzigartig-'.bin2hex(random_bytes(6));

        [$ersterStatus] = $this->tagAnlegen($titel);
        $this->assertSame(200, $ersterStatus, 'Der erste geht durch');

        [$zweiterStatus] = $this->tagAnlegen($titel);
        $this->assertSame(500, $zweiterStatus, 'Heute 500 statt 409 — siehe 000-000-0006');
    }

    public function testEineUniqueVerletzungLaesstKeineHalbeZeileZurueck(): void
    {
        // Die API-Antwort allein sagt darueber nichts — deshalb gegen die Datenbank geprueft.
        $titel = 'Einzigartig-'.bin2hex(random_bytes(6));

        $this->tagAnlegen($titel);
        $this->tagAnlegen($titel);

        $anzahl = (int) $this->pdo()
            ->query('SELECT COUNT(*) FROM pim_tag WHERE title = '.$this->pdo()->quote($titel))
            ->fetchColumn();

        $this->assertSame(1, $anzahl,
            'Nach dem fehlgeschlagenen zweiten Versuch steht genau eine Zeile da');
    }

    public function testDasSchemaWeistDieUniqueEigenschaftAus(): void
    {
        $this->assertTrue($this->schema()['PIM\\Tag']['properties']['title']['unique'],
            'unique ist eines der zehn Felder, die 012-005-0002 behalten hat');
    }

    // ── Sortierung ─────────────────────────────────────────────────────────────────────

    public function testBaseSortableEntitiesWerdenAlsSortierbarGefuehrt(): void
    {
        $einstellungen = $this->schema()['PIM\\Option']['settings'];

        $this->assertTrue($einstellungen['isSortable']);
        $this->assertSame('sorting', $einstellungen['sortBy'],
            'Api::getSchema() setzt das fuer BaseSortable, unabhaengig von der Annotation');
        $this->assertSame('ASC', $einstellungen['sortOrder']);
    }

    public function testSortRestrictToWirdAusDerAnnotationUebernommen(): void
    {
        // sortRestrictTo ist laut dem Befund aus 008-001-0002 das EINZIGE der drei
        // Sortier-Felder mit einem echten Leser im Framework (JoinBidirectionalType).
        // sortBy und sortOrder stehen nur im Schema und werden von keinem Leser angewandt.
        $this->assertSame('group', $this->schema()['PIM\\Option']['settings']['sortRestrictTo'],
            'PIM\\Option traegt @PIM\\Config(sortRestrictTo="group") — die Sortierung laeuft '
            .'je Optionsgruppe, nicht global');

        $this->assertNull($this->schema()['PIM\\Nav']['settings']['sortRestrictTo'],
            'PIM\\Nav erbt zwar von BaseSortable, schraenkt aber nicht ein');
    }

    // ── Die beiden Lücken ──────────────────────────────────────────────────────────────

    public function testKeineEntityNutztDieEncodedVerschluesselung(): void
    {
        // encoded ist eines der zehn Felder, die 012-005-0002 behalten hat, und es hat mit
        // StringType/TextareaType echte Leser. Nur: **keine Entity setzt es**, und
        // SECURITY_CIPHER_KEY steht standardmaessig auf null — StringType wuerde selbst dann
        // werfen. Die Verschluesselung ist heute nicht ausloesbar.
        //
        // Setzt jemand das Flag, schlaegt dieser Test an. Dann gehoert hierher der Nachweis,
        // dass der Wert in der Datenbank verschluesselt liegt und ueber die API im Klartext
        // zurueckkommt — geprueft an der Datenbank, nicht an der Antwort.
        $mitFlag = array();

        foreach ($this->schema() as $entity => $eintrag) {
            if ($entity === '_hash' || !isset($eintrag['properties'])) {
                continue;
            }
            foreach ($eintrag['properties'] as $name => $config) {
                if (!empty($config['encoded'])) {
                    $mitFlag[] = $entity.'.'.$name;
                }
            }
        }

        $this->assertSame(array(), $mitFlag,
            'Heute nutzt keine Entity encoded=true. Aendert sich das, gehoert der Nachweis '
            .'der Verschluesselung in diesen Test.');
    }

    public function testEsGibtKeineOnejoinEigenschaftFuerDieLoeschKaskade(): void
    {
        // Api::delete() entfernt verjointe Objekte vom Typ onejoin mit. Es gibt keine
        // einzige @ORM\OneToOne-Beziehung im Framework oder in der Vorlage — der Code-Pfad
        // hat keinen Ausloeser.
        //
        // Entsteht eine solche Beziehung, schlaegt dieser Test an. Dann gehoert hierher der
        // Nachweis, dass das verjointe Objekt beim Loeschen des Elternobjekts mit verschwindet.
        $onejoins = array();

        foreach ($this->schema() as $entity => $eintrag) {
            if ($entity === '_hash' || !isset($eintrag['properties'])) {
                continue;
            }
            foreach ($eintrag['properties'] as $name => $config) {
                if (($config['type'] ?? null) === 'onejoin') {
                    $onejoins[] = $entity.'.'.$name;
                }
            }
        }

        $this->assertSame(array(), $onejoins,
            'Heute gibt es keine onejoin-Eigenschaft. Aendert sich das, gehoert der Nachweis '
            .'der Loesch-Kaskade in diesen Test.');
    }
}
