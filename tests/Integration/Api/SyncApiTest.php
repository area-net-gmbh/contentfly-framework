<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * Charakterisierungstests für die Sync-Endpunkte `/api/all`, `/api/deleted` und `/api/count`.
 *
 * `/api/all` antwortete bis Task `000-000-0007` bedingungslos mit HTTP 500 — ein Pfad in
 * `Api::getAll()` trug ein `../` zu viel und zeigte aus dem Repo heraus. Die Tests hielten das
 * als Ist-Zustand fest; mit dem Fix sind sie **umgedreht** worden, wie ihr Kommentar es
 * vorsah. Sie beschreiben jetzt den Sync-Vertrag, nicht mehr seinen Ausfall.
 */
class SyncApiTest extends IntegrationTestCase
{
    private string $tag = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tag = 'sync-'.bin2hex(random_bytes(6));

        $this->pdo()->prepare(
            'INSERT INTO pim_tag (id, title, created, modified, views, isIntern)
             VALUES (:id, :titel, NOW(), NOW(), 0, 0)'
        )->execute(array('id' => $this->tag, 'titel' => 'Sync-Probe'));

        $this->nachTestLoeschen('pim_tag', $this->tag);
    }

    // ── /api/all ───────────────────────────────────────────────────────────────────────

    public function testAllLiefertDieDatenAllerSynchronisierbarenEntities(): void
    {
        [$status, $body] = $this->postJson('/api/all', array(), $this->token());

        $this->assertSame(200, $status, 'Bis 000-000-0007 war das ein HTTP 500');
        $this->assertSame(array('lastModified', 'data', 'version', 'hash'), array_keys($body),
            'all traegt lastModified statt ts — der naechste eigene Envelope');
        $this->assertArrayHasKey('PIM\\Tag', $body['data'],
            'Seit 000-000-0007 bestimmt das Schema die Entities, nicht mehr eine fest '
            .'verdrahtete Liste aus File, User und Group');
        $this->assertContains($this->tag, array_column($body['data']['PIM\\Tag'], 'id'));
    }

    public function testAllSchliesstDieselbenEntitiesAusWieDeleted(): void
    {
        // Der Kern von 000-000-0007: Vorher meldete getDeleted() Loeschungen fuer Entities,
        // die getAll() nie ausgeliefert hat — ein Client erfuhr vom Verschwinden von Objekten,
        // die er nie bekommen hatte. Beide nutzen jetzt dieselbe Ausschlussliste.
        [, $body] = $this->postJson('/api/all', array(), $this->token());

        foreach (array('PIM\\Folder', 'PIM\\Token', 'PIM\\Group', 'PIM\\Log', 'PIM\\Permission') as $ausgeschlossen) {
            $this->assertArrayNotHasKey($ausgeschlossen, $body['data'],
                "$ausgeschlossen steht auf der Ausschlussliste beider Sync-Haelften");
        }
    }

    public function testAllOhneTokenLiefertKeineDaten(): void
    {
        [$status, $body] = $this->postJson('/api/all', array());

        $this->assertSame(401, $status,
            'Seit 006-002-0003 der gemeinte Code — Symfony 4.4 behebt hier 000-000-0006');
        $this->assertArrayNotHasKey('data', $body, 'Ohne Token fliessen keine Daten');
    }

    // ── /api/deleted ───────────────────────────────────────────────────────────────────

    public function testDeletedLiefertEineListeImStandardEnvelope(): void
    {
        [$status, $body] = $this->postJson('/api/deleted', array(), $this->token());

        $this->assertSame(200, $status);
        $this->assertSame(array('ts', 'data', 'version', 'hash'), array_keys($body));
        $this->assertIsArray($body['data']);
    }

    public function testDeletedLiefertEineFlacheListeRoherLogZeilen(): void
    {
        // /api/deleted liest die zweite Haelfte des Sync-Vertrags aus pim_log. Die Antwort
        // ist **keine** nach Entity gruppierte Struktur, sondern eine flache Liste der
        // Spalten model_name und model_id — array_merge ueber die Treffer je Entity.
        $logId = 'synclog-'.bin2hex(random_bytes(6));
        $this->logZeile($logId, 'PIM\\Tag', $this->tag);

        [$status, $body] = $this->postJson('/api/deleted', array(), $this->token());

        $this->assertSame(200, $status);
        $this->assertNotEmpty($body['data'], 'Die geloeschte Zeile wird gemeldet');

        $treffer = array_values(array_filter(
            $body['data'],
            fn (array $z): bool => ($z['model_id'] ?? null) === $this->tag
        ));

        $this->assertCount(1, $treffer);
        $this->assertSame(array('model_name', 'model_id'), array_keys($treffer[0]),
            'Je Zeile kommen genau diese beiden Spalten — roh aus pim_log');
        $this->assertSame('PIM\\Tag', $treffer[0]['model_name']);
    }

    public function testDeletedSchliesstEineFesteListeVonEntitiesAus(): void
    {
        // Neben excludeFromSync fuehrt Api::getDeleted() eine **zweite, fest verdrahtete**
        // Ausschlussliste: Folder, Token, Group, ThumbnailSetting, Permission, Nav, NavItem
        // und Log werden nie gemeldet. Das steht in keiner Annotation und in keiner
        // Konfiguration — nur im Code.
        $logId  = 'synclog-'.bin2hex(random_bytes(6));
        $ordner = 'sync-f-'.bin2hex(random_bytes(6));
        $this->logZeile($logId, 'PIM\\Folder', $ordner);

        [, $body] = $this->postJson('/api/deleted', array(), $this->token());

        $ids = array_column($body['data'], 'model_id');
        $this->assertNotContains($ordner, $ids,
            'PIM\\Folder steht auf der fest verdrahteten Ausschlussliste von getDeleted()');
    }

    private function logZeile(string $logId, string $entityName, string $modelId): void
    {
        // Gebundene Parameter statt eingesetzter Zeichenketten: Der Entity-Name traegt einen
        // Backslash, und der ueberlebt keine der drei Escaping-Ebenen zuverlaessig.
        $this->pdo()->prepare(
            'INSERT INTO pim_log (id, model_id, model_name, mode, created, modified, views, isIntern)
             VALUES (:id, :modelId, :modelName, :mode, NOW(), NOW(), 0, 0)'
        )->execute(array(
            'id' => $logId, 'modelId' => $modelId, 'modelName' => $entityName, 'mode' => 'DEL',
        ));

        $this->nachTestLoeschen('pim_log', $logId);
    }

    public function testDeletedOhneTokenLiefertKeineDaten(): void
    {
        [$status, $body] = $this->postJson('/api/deleted', array());

        $this->assertSame(401, $status,
            'Seit 006-002-0003 der gemeinte Code — Symfony 4.4 behebt hier 000-000-0006');
        $this->assertArrayNotHasKey('data', $body);
    }

    // ── /api/count ─────────────────────────────────────────────────────────────────────

    public function testCountIstEineGlobaleStatistikKeinGefilterterZaehler(): void
    {
        // Ueberraschend, aber der Ist-Zustand: /api/count zaehlt nicht die Treffer einer
        // Abfrage, sondern liefert eine Bestandsuebersicht ueber Datensaetze und Dateien.
        [$status, $body] = $this->postJson('/api/count', array('entity' => 'PIM\\Tag'), $this->token());

        $this->assertSame(200, $status);
        $this->assertSame(array('ts', 'data', 'version', 'hash'), array_keys($body));
        $this->assertSame(
            array('dataCount', 'filesCount', 'filesSize', 'details'),
            array_keys($body['data']),
            'count liefert eine Statistik, keine Trefferzahl zu Filtern'
        );
    }

    public function testCountWeistDieAnzahlJeEntityInDetailsAus(): void
    {
        [, $body] = $this->postJson('/api/count', array('entity' => 'PIM\\Tag'), $this->token());

        $this->assertArrayHasKey('PIM\\Tag', $body['data']['details']);
        $this->assertGreaterThanOrEqual(1, $body['data']['details']['PIM\\Tag'],
            'Der im setUp angelegte Tag ist mitgezaehlt');
        $this->assertIsInt($body['data']['dataCount']);
    }

    public function testCountOhneTokenLiefertKeineDaten(): void
    {
        [$status, $body] = $this->postJson('/api/count', array('entity' => 'PIM\\Tag'));

        $this->assertSame(401, $status,
            'Seit 006-002-0003 der gemeinte Code — Symfony 4.4 behebt hier 000-000-0006');
        $this->assertArrayNotHasKey('data', $body);
    }

    // ── excludeFromSync ────────────────────────────────────────────────────────────────

    public function testKeineEntitySetztExcludeFromSync(): void
    {
        // Story 012-005-0002 hat excludeFromSync als datenrelevant behalten mit der
        // Begruendung "steuert die Sync-API". Das war nur halb richtig: Bis 000-000-0007
        // wurde das Feld ausschliesslich in getCount() geprueft — es wirkte auf die
        // Bestandsstatistik, nie auf den Endpunkt, nach dem es benannt ist. Seit dem Fix
        // prueft getAll() es ebenfalls.
        //
        // Was bleibt: **keine einzige Entity setzt das Flag**, im Framework nicht und in der
        // Vorlage nicht. Die Wirkung ist damit im laufenden Betrieb nicht beobachtbar — sie
        // ist in 000-000-0007 durch eine Gegenprobe belegt worden. Setzt jemand das Flag,
        // schlaegt dieser Test an und fordert den regulaeren Nachweis ein.
        [$status, $roh] = $this->get('/api/schema', $this->token());
        $this->assertSame(200, $status);

        $schema = json_decode($roh, true)['data'];

        $mitFlag = array();
        foreach ($schema as $name => $eintrag) {
            if ($name === '_hash' || !isset($eintrag['settings']['excludeFromSync'])) {
                continue;
            }
            if ($eintrag['settings']['excludeFromSync']) {
                $mitFlag[] = $name;
            }
        }

        $this->assertSame(array(), $mitFlag,
            'Heute setzt keine Entity excludeFromSync. Aendert sich das, gehoert der '
            .'Nachweis der Ausnahme in diesen Test.');
    }
}
