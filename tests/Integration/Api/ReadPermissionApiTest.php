<?php
namespace Tests\Integration\Api;

use Areanet\PIM\Entity\Permission;
use Tests\Integration\IntegrationTestCase;

/**
 * Charakterisierungstests für `Permission::isReadable()` in allen vier Stufen.
 *
 * Das ist die Stelle, an der ein Fehler beim Kernel-Tausch **nicht sichtbar bricht, sondern
 * still zu viel herausgibt**. Jeder Test hier prüft deshalb beide Richtungen: was sichtbar
 * sein muss *und* was es nicht sein darf. Ein Test, der nur die Sichtbarkeit belegt, würde
 * einen zu weit geöffneten Filter nicht bemerken.
 *
 * Die Stufen sind als Konstanten geschrieben, weil ihre Werte **nicht aufsteigend geordnet**
 * sind: `NONE` 0, `OWN` 1, `ALL` 2, `GROUP` 3.
 */
class ReadPermissionApiTest extends IntegrationTestCase
{
    private string $adminId = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminId = (string) $this->pdo()
            ->query("SELECT id FROM pim_user WHERE alias = 'admin'")
            ->fetchColumn();
    }

    /** Legt einen Tag an; $userCreated und $groups bestimmen, wer ihn sehen darf. */
    private function tag(string $titel, ?string $userCreated = null, ?string $groups = null, ?string $users = null): string
    {
        $id = 'rp-'.bin2hex(random_bytes(6));

        $this->pdo()->prepare(
            // `groups` ist in MySQL 8 ein reserviertes Wort — dieselbe Falle, die
            // 012-005-0003 in Api::getTree2() behoben hat.
            'INSERT INTO pim_tag (id, title, created, modified, views, isIntern, usercreated_id, `groups`, users)
             VALUES (:id, :titel, NOW(), NOW(), 0, 0, :uc, :grp, :usr)'
        )->execute(array('id' => $id, 'titel' => $titel, 'uc' => $userCreated, 'grp' => $groups, 'usr' => $users));

        $this->nachTestLoeschen('pim_tag', $id);

        return $id;
    }

    /** @return array<int,string> Die Ids, die /api/list fuer diesen Token liefert. */
    private function sichtbareIds(string $token): array
    {
        [$status, $body] = $this->postJson('/api/list', array('entity' => 'PIM\\Tag'), $token);

        $this->assertSame(200, $status);

        return array_column($body['data'], 'id');
    }

    // ── Stufe ALL ──────────────────────────────────────────────────────────────────────

    public function testMitStufeAllSindAlleObjekteSichtbar(): void
    {
        [$token, $userId] = $this->testbenutzer(array('PIM\\Tag' => array('readable' => Permission::ALL)));

        $eigener = $this->tag('Eigener', $userId);
        $fremder = $this->tag('Fremder', $this->adminId);

        $sichtbar = $this->sichtbareIds($token);

        $this->assertContains($eigener, $sichtbar);
        $this->assertContains($fremder, $sichtbar, 'ALL heisst alles, auch Fremdes');
    }

    // ── Stufe OWN ──────────────────────────────────────────────────────────────────────

    public function testMitStufeOwnIstNurEigenesSichtbar(): void
    {
        [$token, $userId] = $this->testbenutzer(array('PIM\\Tag' => array('readable' => Permission::OWN)));

        $eigener = $this->tag('Eigener', $userId);
        $fremder = $this->tag('Fremder', $this->adminId);

        $sichtbar = $this->sichtbareIds($token);

        $this->assertContains($eigener, $sichtbar);
        $this->assertNotContains($fremder, $sichtbar,
            'Die andere Richtung — ohne sie wuerde ein zu weit geoeffneter Filter nicht auffallen');
    }

    public function testMitStufeOwnMachtDieUsersListeEinObjektSichtbar(): void
    {
        // Api::getList() filtert auf "userCreated = ich ODER ich stehe in users" — dem
        // Virtualjoin aus Base, den 012-005-0001 als datenrelevant behalten hat.
        [$token, $userId] = $this->testbenutzer(array('PIM\\Tag' => array('readable' => Permission::OWN)));

        $geteilt = $this->tag('Geteilt', $this->adminId, null, $userId);

        $this->assertContains($geteilt, $this->sichtbareIds($token),
            'Ein fremdes Objekt wird sichtbar, wenn es mich in users fuehrt');
    }

    // ── Stufe GROUP ────────────────────────────────────────────────────────────────────

    public function testMitStufeGroupIstEigenesUndGruppenGeteiltesSichtbar(): void
    {
        [$token, $userId, $gruppeId] = $this->testbenutzer(array('PIM\\Tag' => array('readable' => Permission::GROUP)));

        $eigener      = $this->tag('Eigener', $userId);
        $gruppenTag   = $this->tag('Fuer die Gruppe', $this->adminId, $gruppeId);
        $unbeteiligt  = $this->tag('Unbeteiligt', $this->adminId);

        $sichtbar = $this->sichtbareIds($token);

        $this->assertContains($eigener, $sichtbar);
        $this->assertContains($gruppenTag, $sichtbar, 'Die eigene Gruppe steht in groups');
        $this->assertNotContains($unbeteiligt, $sichtbar, 'Ohne Bezug bleibt es unsichtbar');
    }

    // ── Kein Recht ─────────────────────────────────────────────────────────────────────

    public function testOhneLeserechtWirftDieListeStattEineLeereMengeZuLiefern(): void
    {
        // Die Frage, die der Task messen sollte: gefilterte Liste oder Fehler? Es ist ein
        // Fehler — Api::getList() wirft contentfly_general_permission_denied, statt eine
        // leere Liste zu liefern.
        [$token] = $this->testbenutzer(array('PIM\\User' => array('readable' => Permission::ALL)));

        $this->tag('Unerreichbar', $this->adminId);

        [$status, $body] = $this->postJson('/api/list', array('entity' => 'PIM\\Tag'), $token);

        $this->assertSame(403, $status, 'Seit dem Stack-Wechsel (006-002-0003) der gemeinte Code — Symfony 4.4 behebt hier 000-000-0006');
        $this->assertArrayNotHasKey('data', $body, 'Es fliessen keine Daten');
    }

    public function testOhneLeserechtWirftAuchSingle(): void
    {
        [$token] = $this->testbenutzer(array('PIM\\User' => array('readable' => Permission::ALL)));

        $tag = $this->tag('Unerreichbar', $this->adminId);

        [$status, $body] = $this->postJson(
            '/api/single',
            array('entity' => 'PIM\\Tag', 'id' => $tag),
            $token
        );

        $this->assertSame(403, $status,
            'Seit 006-002-0003 der gemeinte Code — Symfony 4.4 behebt hier 000-000-0006');
        $this->assertArrayNotHasKey('data', $body);
    }

    public function testEinBenutzerOhneGruppeSiehtNichts(): void
    {
        // Permission::is() liefert 0, sobald der Benutzer keiner Gruppe angehoert — noch
        // vor jeder Pruefung der Berechtigungszeilen.
        $id    = 'rp-nogrp-'.bin2hex(random_bytes(6));
        $salt  = bin2hex(random_bytes(16));

        $this->pdo()->prepare(
            'INSERT INTO pim_user (id, isAdmin, alias, pass, isActive, salt, created, modified, views, isIntern)
             VALUES (:id, 0, :alias, :pass, 1, :salt, NOW(), NOW(), 0, 0)'
        )->execute(array(
            'id' => $id, 'alias' => $id,
            'pass' => hash('sha256', self::TEST_PASSWORT.$salt), 'salt' => $salt,
        ));
        $this->nachTestLoeschen('pim_user', $id);

        [, $anmeldung] = $this->postJson('/auth/login', array('alias' => $id, 'pass' => self::TEST_PASSWORT));
        $this->assertArrayHasKey('token', $anmeldung);

        [$status] = $this->postJson('/api/list', array('entity' => 'PIM\\Tag'), $anmeldung['token']);

        $this->assertSame(403, $status,
            'Seit 006-002-0003 der gemeinte Code — Symfony 4.4 behebt hier 000-000-0006');
    }

    // ── Admin ──────────────────────────────────────────────────────────────────────────

    public function testEinAdminUebergehtAlleStufen(): void
    {
        // Permission::is() gibt fuer Admins 2 zurueck, bevor ueberhaupt eine Gruppe oder
        // eine Berechtigungszeile betrachtet wird.
        $fremder = $this->tag('Fremder', $this->adminId);

        $this->assertContains($fremder, $this->sichtbareIds($this->token()));
    }

    // ── pim_blocked bei verjointen Objekten ────────────────────────────────────────────

    public function testEinNichtLesbaresVerjointesObjektKommtAlsPimBlocked(): void
    {
        // PIM\Tag.userCreated ist ein join auf PIM\User. Wer Tags lesen darf, Benutzer aber
        // nicht, bekommt statt des Objekts nur dessen Id plus die Markierung — der Client
        // erfaehrt, DASS da etwas ist, aber nicht was. Das Verhalten steckt in JoinType und
        // ist ueber vier weitere Typ-Klassen dupliziert.
        [$token, $userId] = $this->testbenutzer(array('PIM\\Tag' => array('readable' => Permission::ALL)));

        $tag = $this->tag('Mit Ersteller', $userId);

        [$status, $body] = $this->postJson(
            '/api/single',
            array('entity' => 'PIM\\Tag', 'id' => $tag),
            $token
        );

        $this->assertSame(200, $status);
        $this->assertSame(
            array('id' => $userId, 'pim_blocked' => true),
            $body['data']['userCreated'],
            'Die Id wird durchgereicht, das Objekt nicht'
        );
    }

    public function testMitLeserechtAufDerZielentityKommtDasVerjointeObjektGanz(): void
    {
        // Die Gegenrichtung: mit Leserecht auf PIM\User faellt die Markierung weg.
        [$token, $userId] = $this->testbenutzer(array(
            'PIM\\Tag'  => array('readable' => Permission::ALL),
            'PIM\\User' => array('readable' => Permission::ALL),
        ));

        $tag = $this->tag('Mit Ersteller', $userId);

        [, $body] = $this->postJson(
            '/api/single',
            array('entity' => 'PIM\\Tag', 'id' => $tag),
            $token
        );

        $this->assertArrayNotHasKey('pim_blocked', $body['data']['userCreated']);
        $this->assertArrayHasKey('alias', $body['data']['userCreated']);
    }
}
