<?php
namespace Tests\Integration\Api;

use Areanet\PIM\Entity\Permission;
use Tests\Integration\IntegrationTestCase;

/**
 * Charakterisierungstests für zwei Dinge, die auf dem Weg nach draußen sind: eine Hintertür,
 * die `013-001` entfernt, und zwei Berechtigungsfelder, die veröffentlicht, aber nirgends
 * durchgesetzt werden.
 *
 * **`canExport` und `getExtended` sind der fünfte und sechste Fall** eines Musters, das sich
 * durch Epic `008` zieht: Felder, deren einzige Nutzer die gelöschte Oberfläche waren. Sie
 * stehen im `permissions`-Block des Schemas (`Api.php:1369-1370` und `1451-1452`) und werden
 * an keiner Stelle geprüft — `canExport`s Konsument war der `ExportController`, gelöscht in
 * `012-001-0003`.
 *
 * Anders als beim gestrichenen `readonly` haben beide eine **Datenbankspalte**
 * (`pim_permission.export`, `pim_permission.extended`), und ein Client könnte sie aus dem
 * Schema lesen und selbst anwenden. Deshalb sind sie nicht einfach tot — nur wirkungslos.
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

    public function testDasMasterPasswortIstInDerVorlageNichtGesetzt(): void
    {
        // AuthController prueft:
        //
        //     $globalPass = Adapter::getConfig()->APP_MASTER_PASSWORD;
        //     if($globalPass){ … $globalPass != $request->get('pass') … }
        //
        // Ist es gesetzt, genuegt es **fuer jeden Benutzer**. Story 013-001 entfernt das
        // ersatzlos: "Ein Schalter, der Vollzugriff gewaehrt, ist auch ausgeschaltet eine
        // Hintertuer."
        //
        // Ein Integrationstest kann die Konfiguration zur Laufzeit nicht aendern — pruefbar
        // ist deshalb die Vorbedingung, nicht die Wirkung. Dass eine falsche Anmeldung
        // scheitert (AuthApiTest), gilt *nur* deshalb, weil kein Master-Passwort gesetzt ist.
        //
        // Wer 013-001 umsetzt, dreht diesen Test bewusst um — der Standardwert faellt dann
        // ersatzlos weg.
        $vorlage = file_get_contents(ROOT_DIR.'/lib/contentfly/Classes/Config.php');

        $this->assertMatchesRegularExpression(
            '/\$APP_MASTER_PASSWORD\s*=\s*null;/',
            $vorlage,
            'Der Standardwert ist null — die Hintertuer ist zu, aber vorhanden. Siehe 013-001.'
        );
    }

    public function testEineFalscheAnmeldungScheitertSolangeKeinMasterPasswortGesetztIst(): void
    {
        [$status, $body] = $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => 'falsch'));

        $this->assertSame(401, $status);
        $this->assertArrayNotHasKey('token', $body,
            'Gilt nur, weil APP_MASTER_PASSWORD nicht gesetzt ist — siehe den Test darueber');
    }

    // ── canExport und getExtended: veröffentlicht ──────────────────────────────────────

    public function testDerPermissionsBlockDesSchemasFuehrtExportUndExtended(): void
    {
        [$status, $roh] = $this->get('/api/schema', $this->token());
        $this->assertSame(200, $status);

        $rechte = json_decode($roh, true)['permissions'];

        $this->assertSame(
            array('readable', 'writable', 'deletable', 'export', 'extended'),
            array_keys($rechte['PIM\\Tag']),
            'Fuenf Felder je Entity — zwei davon ohne Durchsetzungspunkt'
        );
    }

    public function testDieBeidenFelderKommenAuchFuerEinenNichtAdminAn(): void
    {
        [$token] = $this->testbenutzer(array('PIM\\Tag' => array(
            'readable' => Permission::ALL,
            'export'   => 0,
            'extended' => '{"felder":["title"]}',
        )));

        [, $roh] = $this->get('/api/schema', $token);
        $rechte  = json_decode($roh, true)['permissions']['PIM\\Tag'];

        $this->assertFalse($rechte['export'], 'Der gesetzte Wert kommt beim Client an');
        $this->assertSame(array('felder' => array('title')), (array) $rechte['extended'],
            'extended wird als JSON dekodiert durchgereicht');
    }

    public function testCanExportKollabiertDieVierStufenAufEinBoolean(): void
    {
        // Waehrend readable, writable und deletable ihren Stufenwert als Integer melden
        // (0 bis 3), hat canExport eine EIGENE Implementierung — nicht Permission::is():
        //
        //     if($user->getIsAdmin()) return true;
        //     …
        //     return ($permission->getExport() == 2);
        //
        // Die Spalte ist ein Integer mit derselben Vierstufen-Semantik, aber nur ALL (2)
        // gilt als erlaubt. Und weil die Konstanten nicht aufsteigend geordnet sind, ergibt
        // ausgerechnet GROUP (3) ein false — wer "mehr als ALL" meint, sperrt sich aus.
        [$tokenAll]   = $this->testbenutzer(array('PIM\\Tag' => array('readable' => Permission::ALL, 'export' => Permission::ALL)));
        [$tokenGroup] = $this->testbenutzer(array('PIM\\Tag' => array('readable' => Permission::ALL, 'export' => Permission::GROUP)));

        [, $rohAll]   = $this->get('/api/schema', $tokenAll);
        [, $rohGroup] = $this->get('/api/schema', $tokenGroup);

        $this->assertTrue(json_decode($rohAll, true)['permissions']['PIM\\Tag']['export'],
            'ALL (2) ist der einzige Wert, der true ergibt');
        $this->assertFalse(json_decode($rohGroup, true)['permissions']['PIM\\Tag']['export'],
            'GROUP (3) ergibt false — obwohl die Zahl groesser ist als ALL');

        // Zum Vergleich: readable meldet seinen Stufenwert unveraendert als Integer.
        $this->assertSame(Permission::ALL, json_decode($rohAll, true)['permissions']['PIM\\Tag']['readable']);
    }

    // ── canExport und getExtended: nicht durchgesetzt ──────────────────────────────────

    public function testOhneExportRechtLaesstSichTrotzdemAllesTunWasDieApiAnbietet(): void
    {
        // Der Nachweis der Wirkungslosigkeit: export = 0, und der Benutzer kann dennoch
        // lesen, schreiben und loeschen. Es gibt keinen Endpunkt, der das Recht prueft —
        // der ExportController, der es getan haette, ist mit 012-001-0003 gefallen.
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
