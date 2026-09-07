<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * Charakterisierungstests für die Sync-Endpunkte `/api/all`, `/api/deleted` und `/api/count`.
 *
 * **Der wichtigste Test hier hält einen Defekt fest.** `/api/all` antwortet bedingungslos mit
 * HTTP 500 — ein Pfad in `Api::getAll()` trägt ein `../` zu viel und zeigt aus dem Repo heraus.
 * Der Fehler stammt aus dem Initialimport; Task `000-000-0007` behebt ihn. Bis dahin ist das
 * der Ist-Zustand, und die Abgrenzung von Epic `008` verlangt ausdrücklich, ihn festzuhalten
 * statt Wunschverhalten zu prüfen.
 *
 * Wer `000-000-0007` umsetzt, dreht `testAllWirftBedingungslos()` bewusst um — der Test ist
 * die Beschreibung des Defekts, nicht sein Einverständnis.
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

    public function testAllWirftBedingungslos(): void
    {
        // Ist-Zustand, siehe 000-000-0007: Api::getAll() baut
        //   __DIR__.'/../../../../custom/Entity/'
        // — vier Ebenen von lib/contentfly/Classes fuehren ueber das Repo hinaus. Der
        // DirectoryIterator wirft, bevor ueberhaupt Daten eingesammelt werden.
        [$status] = $this->postJson('/api/all', array(), $this->token());

        $this->assertSame(500, $status,
            'Der Sync-Endpunkt ist defekt — siehe Task 000-000-0007. Wird der Pfad korrigiert, '
            .'ist diese Zusicherung umzudrehen, nicht zu loeschen.');
    }

    public function testAllWirftAuchMitVorhandenenDaten(): void
    {
        // Belegt, dass es nicht am leeren Datenbestand liegt: Der Fehler tritt auf, bevor
        // ueberhaupt eine Entity gelesen wird.
        [$status] = $this->postJson('/api/all', array('lastModified' => '2000-01-01 00:00:00'), $this->token());

        $this->assertSame(500, $status);
    }

    public function testAllOhneTokenLiefertKeineDaten(): void
    {
        [$status, $body] = $this->postJson('/api/all', array());

        $this->assertSame(500, $status);
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

        $this->assertSame(500, $status);
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

        $this->assertSame(500, $status);
        $this->assertArrayNotHasKey('data', $body);
    }

    // ── excludeFromSync ────────────────────────────────────────────────────────────────

    public function testKeineEntitySetztExcludeFromSync(): void
    {
        // Story 012-005-0002 hat excludeFromSync als datenrelevant behalten — zu Recht, es
        // hat mit Api::getAll() einen echten Leser. Nur: **keine einzige Entity setzt es**,
        // im Framework nicht und in der Vorlage nicht. Die Wirkung laesst sich deshalb heute
        // nicht in beide Richtungen pruefen; der Endpunkt, an dem sie sichtbar wuerde, ist
        // ausserdem defekt (000-000-0007).
        //
        // Dieser Test haelt genau das fest: Alle Entities kommen ohne das Flag, also ist
        // "wird ausgenommen" derzeit kein erreichbarer Zustand. Setzt jemand das Flag,
        // schlaegt der Test an — und der zugehoerige Nachweis kann ergaenzt werden.
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
            .'Nachweis der Ausnahme in diesen Test — siehe 000-000-0007.');
    }
}
