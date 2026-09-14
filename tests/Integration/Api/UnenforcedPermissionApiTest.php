<?php
namespace Tests\Integration\Api;

use Areanet\PIM\Entity\Permission;
use Tests\Integration\IntegrationTestCase;

/**
 * Charakterisierungstests für zwei Dinge, die auf dem Weg nach draußen sind: eine Hintertür,
 * die `013-001` entfernt, und zwei Berechtigungsfelder, die veröffentlicht, aber nirgends
 * durchgesetzt werden.
 *
 * **`canExport` und `getExtended` waren der fünfte und sechste Fall** eines Musters, das sich
 * durch Epic `008` zieht: Felder, deren einzige Nutzer die gelöschte Oberfläche waren. Sie
 * standen im `permissions`-Block des Schemas und wurden an keiner Stelle geprüft.
 *
 * **Mit `000-000-0012` sind sie aus dem Schema entfernt**, und die Zusicherungen hier sind
 * bewusst umgedreht: Sie halten jetzt fest, dass die beiden Schlüssel *nicht* mehr kommen und
 * dass die Datenbankspalten unangetastet geblieben sind. Die Begründung steht im
 * Klassenkommentar von `Areanet\PIM\Classes\Permission`; kurz: Ein Recht, das der Server
 * veröffentlicht und nicht durchsetzt, sieht wie eine Zusicherung aus, ist aber keine.
 */
class UnenforcedPermissionApiTest extends IntegrationTestCase
{
    private function tag(string $titel): string
    {
        $id = 'ue-'.bin2hex(random_bytes(6));

        $this->pdo()->prepare(
            'INSERT INTO pim_tag (id, title, created, modified, views, isIntern)
             VALUES (:id, :titel, NOW(), NOW(), 0, 0)'
        )->execute(array('id' => $id, 'titel' => $titel));

        $this->nachTestLoeschen('pim_tag', $id);

        return $id;
    }

    // ── Das Master-Passwort ────────────────────────────────────────────────────────────

    public function testDasMasterPasswortGibtEsNichtMehr(): void
    {
        // UMGEDREHT MIT 013-001-0002, wie der alte Test es angekuendigt hat.
        //
        // Er hiess `testDasMasterPasswortIstInDerVorlageNichtGesetzt` und sicherte zu, dass der
        // Standardwert `null` ist — "die Hintertuer ist zu, aber vorhanden". Sie ist jetzt weg:
        // `APP_MASTER_PASSWORD` existiert weder in `Classes/Config.php` noch im
        // `AuthController`.
        //
        // Geprueft wird der Quelltext und nicht das Verhalten, weil es kein Verhalten mehr gibt
        // — man kann nichts konfigurieren, was es nicht gibt. Das ist der Unterschied zwischen
        // "abgeschaltet" und "entfernt", und genau darum ging es.
        foreach (array('lib/contentfly/Classes/Config.php', 'lib/contentfly/Controller/AuthController.php') as $datei) {
            $quelle = file_get_contents(CONTENTFLY_PROJECT_DIR.'/'.$datei);

            // Der Name darf in ERKLAERUNGEN stehen — sie beschreiben, was entfallen ist.
            $ohneKommentare = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $quelle);

            $this->assertStringNotContainsString('APP_MASTER_PASSWORD', (string) $ohneKommentare,
                $datei.' nennt das Master-Passwort nur noch in Erklaerungen, nicht im Code');
        }
    }

    public function testEineFalscheAnmeldungScheitert(): void
    {
        // Der Zusatz "solange kein Master-Passwort gesetzt ist" ist mit 013-001-0002 entfallen.
        // Vorher galt diese Zusicherung nur unter einer Bedingung, die eine Konfigurationszeile
        // aufheben konnte. Jetzt gilt sie.
        [$status, $body] = $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => 'falsch'));

        $this->assertSame(401, $status);
        $this->assertArrayNotHasKey('token', $body);
    }

    // ── canExport und getExtended: nicht mehr veröffentlicht ───────────────────────────

    public function testDerPermissionsBlockFuehrtNurNochDieDreiDurchgesetztenRechte(): void
    {
        // Umgedreht mit 000-000-0012. Der Test hiess
        // testDerPermissionsBlockDesSchemasFuehrtExportUndExtended und hielt fuenf Schluessel
        // fest, von denen zwei keinen Durchsetzungspunkt hatten.
        [$status, $roh] = $this->get('/api/schema', $this->token());
        $this->assertSame(200, $status);

        $rechte = json_decode($roh, true)['permissions'];

        $this->assertSame(
            array('readable', 'writable', 'deletable'),
            array_keys($rechte['PIM\\Tag']),
            'Drei Felder je Entity — und jedes davon wird geprueft'
        );
    }

    public function testAuchFuerEinenNichtAdminKommenDieBeidenFelderNichtMehr(): void
    {
        // Der Benutzer bekommt beide Spalten ausdruecklich gesetzt. Trotzdem taucht nichts
        // davon im Schema auf: Der Wert steht in der Datenbank, die API behauptet nichts
        // mehr darueber.
        [$token] = $this->testbenutzer(array('PIM\\Tag' => array(
            'readable' => Permission::ALL,
            'export'   => 0,
            'extended' => '{"felder":["title"]}',
        )));

        [, $roh] = $this->get('/api/schema', $token);
        $rechte  = json_decode($roh, true)['permissions']['PIM\\Tag'];

        $this->assertArrayNotHasKey('export', $rechte);
        $this->assertArrayNotHasKey('extended', $rechte);
        $this->assertSame(Permission::ALL, $rechte['readable'], 'Die drei anderen sind unveraendert da');
    }

    public function testDieSpaltenBleibenErhaltenUndLesbar(): void
    {
        // Der Gegenbeweis zum Entfernen: Es sind nur die Schluessel im Schema gefallen, nicht
        // die Daten. Ein Bestandsprojekt hat in pim_permission.export und .extended
        // moeglicherweise Werte stehen; die wegzuwerfen waere die nicht umkehrbare Richtung.
        [, , $gruppeId] = $this->testbenutzer(array('PIM\\Tag' => array(
            'readable' => Permission::ALL,
            'export'   => Permission::ALL,
            'extended' => '{"felder":["title"]}',
        )));

        $zeile = $this->pdo()
            ->query('SELECT export, extended FROM pim_permission WHERE group_id = '.$this->pdo()->quote($gruppeId))
            ->fetch(\PDO::FETCH_ASSOC);

        $this->assertSame(Permission::ALL, (int) $zeile['export']);
        $this->assertSame('{"felder":["title"]}', $zeile['extended']);
    }

    // ── Die Spalten bewirken nach wie vor nichts ───────────────────────────────────────

    public function testOhneExportRechtLaesstSichTrotzdemAllesTunWasDieApiAnbietet(): void
    {
        // Der Nachweis der Wirkungslosigkeit: export = 0, und der Benutzer kann dennoch
        // lesen, schreiben und loeschen. Es gibt keinen Endpunkt, der das Recht prueft —
        // der ExportController, der es getan haette, ist mit 012-001-0003 gefallen.
        //
        // Der Test bleibt nach 000-000-0012 unveraendert stehen, und das ist Absicht: Er hielt
        // vorher einen Widerspruch fest (die API veroeffentlicht ein Recht und ignoriert es)
        // und haelt jetzt eine Aussage fest (die Spalte ist Daten des Projekts, sonst nichts).
        [$token] = $this->testbenutzer(array('PIM\\Tag' => array(
            'readable'  => Permission::ALL,
            'writable'  => Permission::ALL,
            'deletable' => Permission::ALL,
            'export'    => 0,
        )));

        $this->tag('Trotz-Exportsperre');

        [$statusList] = $this->postJson('/api/list', array('entity' => 'PIM\\Tag'), $token);
        $this->assertSame(200, $statusList, 'Lesen geht');

        [$statusInsert, $angelegt] = $this->postJson(
            '/api/insert',
            array('entity' => 'PIM\\Tag', 'data' => array('title' => 'Trotz-Exportsperre-neu')),
            $token
        );
        $this->assertSame(200, $statusInsert, 'Schreiben geht');
        $this->nachTestLoeschen('pim_tag', $angelegt['id']);

        [$statusDelete] = $this->postJson(
            '/api/delete',
            array('entity' => 'PIM\\Tag', 'id' => $angelegt['id']),
            $token
        );
        $this->assertSame(200, $statusDelete, 'Loeschen geht — export=0 aendert an nichts etwas');

        $this->pdo()->prepare('DELETE FROM pim_log WHERE model_id = :id')->execute(array('id' => $angelegt['id']));
    }

    public function testEinExtendedEintragAendertDieAntwortNicht(): void
    {
        // extended traegt eine JSON-Struktur, die einmal die Maske einschraenken sollte.
        // Ein Client kann sie aus dem Schema lesen; die API selbst wertet sie nicht aus —
        // die Antwort enthaelt alle Felder, unabhaengig davon, was dort steht.
        [$token] = $this->testbenutzer(array('PIM\\Tag' => array(
            'readable' => Permission::ALL,
            'extended' => '{"nurDieseFelder":["id"]}',
        )));

        $tag = $this->tag('Extended-Probe');

        [$status, $body] = $this->postJson(
            '/api/single',
            array('entity' => 'PIM\\Tag', 'id' => $tag),
            $token
        );

        $this->assertSame(200, $status);
        $this->assertArrayHasKey('title', $body['data'],
            'Trotz extended kommt das volle Objekt — das Feld wird nicht ausgewertet');
        $this->assertArrayHasKey('created', $body['data']);
    }
}
