<?php
namespace Tests\Integration\Api;

use Areanet\PIM\Entity\Permission;
use Tests\Integration\IntegrationTestCase;

/**
 * Charakterisierungstests für `Permission::isWritable()` und `isDeletable()`.
 *
 * Die Gegenstücke zu `isReadable`, mit dem Unterschied, dass ein Fehler hier **Daten kostet**
 * statt sie preiszugeben. Deshalb prüft jede Zusicherung über eine abgewiesene Operation
 * zusätzlich die Datenbank: Ein HTTP 500 sagt nichts darüber, ob die Operation trotzdem wirkte.
 *
 * Die Stufen sind als Konstanten geschrieben — ihre Werte sind nicht aufsteigend geordnet
 * (`NONE` 0, `OWN` 1, `ALL` 2, `GROUP` 3).
 */
class WritePermissionApiTest extends IntegrationTestCase
{
    private string $adminId = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminId = (string) $this->pdo()
            ->query("SELECT id FROM pim_user WHERE alias = 'admin'")
            ->fetchColumn();
    }

    private function tag(string $titel, ?string $userCreated = null, ?string $groups = null): string
    {
        $id = 'wp-'.bin2hex(random_bytes(6));

        // `groups` gequotet — in MySQL 8 ein reserviertes Wort.
        $this->pdo()->prepare(
            'INSERT INTO pim_tag (id, title, created, modified, views, isIntern, usercreated_id, `groups`)
             VALUES (:id, :titel, NOW(), NOW(), 0, 0, :uc, :grp)'
        )->execute(array('id' => $id, 'titel' => $titel, 'uc' => $userCreated, 'grp' => $groups));

        $this->deleteAfterTest('pim_tag', $id);

        return $id;
    }

    private function titel(string $id): ?string
    {
        $wert = $this->pdo()
            ->query('SELECT title FROM pim_tag WHERE id = '.$this->pdo()->quote($id))
            ->fetchColumn();

        return $wert === false ? null : (string) $wert;
    }

    private function existiert(string $id): bool
    {
        return (int) $this->pdo()
            ->query('SELECT COUNT(*) FROM pim_tag WHERE id = '.$this->pdo()->quote($id))
            ->fetchColumn() === 1;
    }

    // ── insert: nur das Entity-Recht zählt ─────────────────────────────────────────────

    public function testInsertPruefetNurDasRechtAufDieEntity(): void
    {
        // Folgerichtig: Ein neues Objekt hat noch keinen Besitzer, an dem sich OWN oder
        // GROUP messen liessen. Api::insert() prueft deshalb bei Zeile 248 nur, ob
        // ueberhaupt Schreibrecht besteht.
        [$token] = $this->createTestUser(array('PIM\\Tag' => array(
            'readable' => Permission::ALL, 'writable' => Permission::OWN,
        )));

        [$status, $body] = $this->postJson(
            '/api/insert',
            array('entity' => 'PIM\\Tag', 'data' => array('title' => 'Mit OWN angelegt')),
            $token
        );

        $this->assertSame(200, $status, 'OWN reicht zum Anlegen — es gibt noch nichts Fremdes');
        $this->deleteAfterTest('pim_tag', $body['id']);
        $this->pdo()->prepare('DELETE FROM pim_log WHERE model_id = :id')->execute(array('id' => $body['id']));
    }

    public function testOhneSchreibrechtEntstehtNichts(): void
    {
        [$token] = $this->createTestUser(array('PIM\\Tag' => array('readable' => Permission::ALL)));

        $titel = 'Darf-nicht-entstehen-'.bin2hex(random_bytes(4));

        [$status] = $this->postJson(
            '/api/insert',
            array('entity' => 'PIM\\Tag', 'data' => array('title' => $titel)),
            $token
        );

        $this->assertSame(403, $status, 'Seit dem Stack-Wechsel (006-002-0003) der gemeinte Code — Symfony 4.4 behebt hier 000-000-0006');

        $anzahl = (int) $this->pdo()
            ->query('SELECT COUNT(*) FROM pim_tag WHERE title = '.$this->pdo()->quote($titel))
            ->fetchColumn();
        $this->assertSame(0, $anzahl, 'Gegen die Datenbank geprueft — der Statuscode allein sagt nichts');
    }

    // ── update: die Objekt-Zugehörigkeit wird geprüft ──────────────────────────────────

    public function testMitStufeOwnLaesstSichDasEigeneObjektAendern(): void
    {
        [$token, $userId] = $this->createTestUser(array('PIM\\Tag' => array(
            'readable' => Permission::ALL, 'writable' => Permission::OWN,
        )));

        $eigener = $this->tag('Original', $userId);

        [$status] = $this->postJson(
            '/api/update',
            array('entity' => 'PIM\\Tag', 'id' => $eigener, 'data' => array('title' => 'Geaendert')),
            $token
        );

        $this->assertSame(200, $status);
        $this->assertSame('Geaendert', $this->titel($eigener));
        $this->pdo()->prepare('DELETE FROM pim_log WHERE model_id = :id')->execute(array('id' => $eigener));
    }

    public function testMitStufeOwnBleibtEinFremdesObjektUnveraendert(): void
    {
        // Die Frage, die dieser Task klaeren sollte: Prueft Api::update() auch die
        // Zugehoerigkeit des Objekts, oder kennt es nur das Recht auf die Entity?
        // Antwort: Es prueft (Api.php:452). Hier ist keine Luecke.
        [$token] = $this->createTestUser(array('PIM\\Tag' => array(
            'readable' => Permission::ALL, 'writable' => Permission::OWN,
        )));

        $fremder = $this->tag('Fremd-Original', $this->adminId);

        [$status] = $this->postJson(
            '/api/update',
            array('entity' => 'PIM\\Tag', 'id' => $fremder, 'data' => array('title' => 'Uebergriff')),
            $token
        );

        $this->assertSame(403, $status,
            'Seit 006-002-0003 der gemeinte Code — Symfony 4.4 behebt hier 000-000-0006');
        $this->assertSame('Fremd-Original', $this->titel($fremder),
            'Der alte Wert steht noch da — gegen die Datenbank geprueft');
    }

    public function testMitStufeGroupZaehltDieGruppeDesObjekts(): void
    {
        [$token, , $gruppeId] = $this->createTestUser(array('PIM\\Tag' => array(
            'readable' => Permission::ALL, 'writable' => Permission::GROUP,
        )));

        $geteilt     = $this->tag('Geteilt-Original', $this->adminId, $gruppeId);
        $unbeteiligt = $this->tag('Unbeteiligt-Original', $this->adminId);

        [$statusGeteilt] = $this->postJson(
            '/api/update',
            array('entity' => 'PIM\\Tag', 'id' => $geteilt, 'data' => array('title' => 'Geteilt-neu')),
            $token
        );
        [$statusFremd] = $this->postJson(
            '/api/update',
            array('entity' => 'PIM\\Tag', 'id' => $unbeteiligt, 'data' => array('title' => 'Uebergriff')),
            $token
        );

        $this->assertSame(200, $statusGeteilt);
        $this->assertSame('Geteilt-neu', $this->titel($geteilt));

        $this->assertSame(403, $statusFremd,
            'Seit 006-002-0003 der gemeinte Code — Symfony 4.4 behebt hier 000-000-0006');
        $this->assertSame('Unbeteiligt-Original', $this->titel($unbeteiligt),
            'Ohne Gruppenbezug bleibt das Objekt unangetastet');

        $this->pdo()->prepare('DELETE FROM pim_log WHERE model_id = :id')->execute(array('id' => $geteilt));
    }

    // ── delete ─────────────────────────────────────────────────────────────────────────

    public function testOhneLoeschrechtBleibtDasObjektBestehen(): void
    {
        [$token] = $this->createTestUser(array('PIM\\Tag' => array(
            'readable' => Permission::ALL, 'writable' => Permission::ALL,
        )));

        $tag = $this->tag('Unloeschbar', $this->adminId);

        [$status] = $this->postJson('/api/delete', array('entity' => 'PIM\\Tag', 'id' => $tag), $token);

        $this->assertSame(403, $status,
            'Seit 006-002-0003 der gemeinte Code — Symfony 4.4 behebt hier 000-000-0006');
        $this->assertTrue($this->existiert($tag),
            'Schreibrecht allein berechtigt nicht zum Loeschen — gegen die Datenbank geprueft');
    }

    public function testMitLoeschrechtOwnBleibtEinFremdesObjektBestehen(): void
    {
        [$token, $userId] = $this->createTestUser(array('PIM\\Tag' => array(
            'readable' => Permission::ALL, 'deletable' => Permission::OWN,
        )));

        $eigener = $this->tag('Eigener', $userId);
        $fremder = $this->tag('Fremder', $this->adminId);

        [$statusFremd]  = $this->postJson('/api/delete', array('entity' => 'PIM\\Tag', 'id' => $fremder), $token);
        [$statusEigen]  = $this->postJson('/api/delete', array('entity' => 'PIM\\Tag', 'id' => $eigener), $token);

        $this->assertSame(403, $statusFremd,
            'Seit 006-002-0003 der gemeinte Code — Symfony 4.4 behebt hier 000-000-0006');
        $this->assertTrue($this->existiert($fremder), 'Das fremde Objekt ist noch da');

        $this->assertSame(200, $statusEigen);
        $this->assertFalse($this->existiert($eigener), 'Das eigene ist weg');

        $this->pdo()->prepare('DELETE FROM pim_log WHERE model_id = :id')->execute(array('id' => $eigener));
    }

    // ── Die Sonderregel: sich selbst darf man immer ────────────────────────────────────

    public function testMitStufeOwnDarfSichEinBenutzerImmerSelbstAendern(): void
    {
        // Api.php:452 traegt eine dritte Bedingung, die den beiden anderen Pruefungen
        // hinzugefuegt ist: `&& $object != $this->app['auth.user']`. Ein Benutzer faellt
        // damit nie unter die OWN-Sperre fuer sich selbst — auch dann nicht, wenn er sich
        // nicht selbst angelegt hat.
        [$token, $userId] = $this->createTestUser(array('PIM\\User' => array(
            'readable' => Permission::ALL, 'writable' => Permission::OWN,
        )));

        [$status] = $this->postJson(
            '/api/update',
            array('entity' => 'PIM\\User', 'id' => $userId, 'data' => array('isIntern' => true)),
            $token
        );

        $this->assertSame(200, $status,
            'Der Testbenutzer wurde vom Test angelegt, nicht von sich selbst — und darf sich '
            .'trotzdem aendern');

        $this->pdo()->prepare('DELETE FROM pim_log WHERE model_id = :id')->execute(array('id' => $userId));
    }

    // ── MultijoinType: nicht auslösbar ─────────────────────────────────────────────────

    public function testDieSchreibpruefungInMultijoinTypeIstNichtAusloesbar(): void
    {
        // MultijoinType prueft an zwei Stellen (Zeilen 190 und 230) das Schreibrecht auf die
        // Zielentity — aber nur im `mappedBy`-Zweig, der `acceptFrom` voraussetzt.
        //
        // Im Framework und in der Vorlage traegt **keine einzige Eigenschaft** ein
        // `acceptFrom`. Der einzige Multijoin ist PIM\File.tags, und der hat keines. Der
        // Code-Pfad hat damit keinen Ausloeser.
        //
        // Entsteht eine bidirektionale Multijoin-Beziehung, schlaegt dieser Test an. Dann
        // gehoert hierher der Nachweis, dass ohne Schreibrecht auf die Zielentity ein
        // AccessDeniedHttpException kommt.
        [$status, $roh] = $this->get('/api/schema', $this->token());
        $this->assertSame(200, $status);

        $mitAcceptFrom = array();
        foreach (json_decode($roh, true)['data'] as $entity => $eintrag) {
            if ($entity === '_hash' || !isset($eintrag['properties'])) {
                continue;
            }
            foreach ($eintrag['properties'] as $name => $config) {
                if (!empty($config['acceptFrom'])) {
                    $mitAcceptFrom[] = $entity.'.'.$name;
                }
            }
        }

        $this->assertSame(array(), $mitAcceptFrom,
            'Ohne acceptFrom greift die Schreibpruefung in MultijoinType nicht. Aendert sich '
            .'das, gehoert der Nachweis in diesen Test.');
    }

    // ── Admin ──────────────────────────────────────────────────────────────────────────

    public function testEinAdminSchreibtUndLoeschtOhneBerechtigungszeile(): void
    {
        $tag = $this->tag('Admin-Probe', $this->adminId);

        [$statusUpdate] = $this->postJson(
            '/api/update',
            array('entity' => 'PIM\\Tag', 'id' => $tag, 'data' => array('title' => 'Admin-geaendert')),
            $this->token()
        );
        $this->assertSame(200, $statusUpdate);

        [$statusDelete] = $this->postJson('/api/delete', array('entity' => 'PIM\\Tag', 'id' => $tag), $this->token());
        $this->assertSame(200, $statusDelete);
        $this->assertFalse($this->existiert($tag));

        $this->pdo()->prepare('DELETE FROM pim_log WHERE model_id = :id')->execute(array('id' => $tag));
    }
}
