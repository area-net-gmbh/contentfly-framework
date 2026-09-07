<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * Charakterisierungstests für `/api/query` und `/api/translations`.
 *
 * `/api/query` ist der **einzige lesende Endpunkt mit eigener Berechtigungsprüfung**: Wer
 * kein Admin ist, braucht eine Gruppe mit `apiQueryEnabled = 'enabled'`. Ein Endpunkt für
 * freie Abfragen, dessen Gating beim Kernel-Tausch unbemerkt wegfällt, wäre eine offene
 * Datenbank — deshalb sind alle drei Fälle hier festgehalten.
 *
 * `/api/translations` bedient die Mehrsprachigkeit. Sie ist in der Vorlage **nicht
 * konfiguriert** (`APP_LANGUAGES` ist leer, und es gibt keine konkrete `BaseI18n`-Entity),
 * weshalb sich hier nur festhalten lässt, wie der Endpunkt ohne i18n reagiert.
 */
class QueryApiTest extends IntegrationTestCase
{
    private const PASSWORT = 'test-nur-fuer-diesen-lauf';

    private string $tag = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tag = 'query-'.bin2hex(random_bytes(6));
        $this->pdo()->prepare(
            'INSERT INTO pim_tag (id, title, created, modified, views, isIntern)
             VALUES (:id, :titel, NOW(), NOW(), 0, 0)'
        )->execute(array('id' => $this->tag, 'titel' => 'Query-Probe'));
        $this->nachTestLoeschen('pim_tag', $this->tag);
    }

    /**
     * Legt eine Gruppe und einen Nicht-Admin darin an und liefert dessen Token.
     *
     * Das Passwort wird so gehasht, wie `User::setPass()` es tut: sha256 aus Passwort und
     * Salt. Der Aufbau laeuft ueber pdo() statt ueber die Schreib-API, damit diese Story
     * nicht von 008-002 abhaengt.
     */
    private function anmeldungAlsNichtAdmin(string $apiQueryEnabled): string
    {
        $lauf     = bin2hex(random_bytes(6));
        $gruppeId = 'qgrp-'.$lauf;
        $userId   = 'qusr-'.$lauf;
        $alias    = 'querytest-'.$lauf;
        $salt     = bin2hex(random_bytes(16));

        $this->pdo()->prepare(
            'INSERT INTO pim_group (id, name, tokenTimeout, apiQueryEnabled, created, modified, views, isIntern)
             VALUES (:id, :name, 60, :ape, NOW(), NOW(), 0, 0)'
        )->execute(array('id' => $gruppeId, 'name' => 'Querytest '.$lauf, 'ape' => $apiQueryEnabled));
        $this->nachTestLoeschen('pim_group', $gruppeId);

        $this->pdo()->prepare(
            'INSERT INTO pim_user (id, isAdmin, alias, pass, isActive, salt, created, modified, views, isIntern, group_id)
             VALUES (:id, 0, :alias, :pass, 1, :salt, NOW(), NOW(), 0, 0, :gruppe)'
        )->execute(array(
            'id'     => $userId,
            'alias'  => $alias,
            'pass'   => hash('sha256', self::PASSWORT.$salt),
            'salt'   => $salt,
            'gruppe' => $gruppeId,
        ));
        $this->nachTestLoeschen('pim_user', $userId);

        // Ohne eine Permission-Zeile scheitert der Nicht-Admin bereits an der Lesepruefung
        // der Entity — also an einem anderen Gate als dem, das dieser Test isolieren soll.
        $rechtId = 'qperm-'.$lauf;
        $this->pdo()->prepare(
            'INSERT INTO pim_permission (id, entityName, readable, writable, deletable, export,
                                         created, modified, views, isIntern, group_id)
             VALUES (:id, :entity, 1, 0, 0, 0, NOW(), NOW(), 0, 0, :gruppe)'
        )->execute(array('id' => $rechtId, 'entity' => 'PIM\\Tag', 'gruppe' => $gruppeId));
        $this->nachTestLoeschen('pim_permission', $rechtId);

        [, $body] = $this->postJson('/auth/login', array('alias' => $alias, 'pass' => self::PASSWORT));

        if (!isset($body['token'])) {
            $this->fail('Anmeldung des Testbenutzers fehlgeschlagen: '.json_encode($body));
        }

        return $body['token'];
    }

    // ── /api/query ─────────────────────────────────────────────────────────────────────

    public function testQueryAlsAdminIstErlaubtUndEchotDieParameter(): void
    {
        [$status, $body] = $this->postJson(
            '/api/query',
            array('select' => 'id', 'from' => 'PIM\\Tag'),
            $this->token()
        );

        $this->assertSame(200, $status);
        $this->assertSame(array('ts', 'params', 'data', 'version', 'hash'), array_keys($body),
            'query traegt als einziger Endpunkt die Anfrageparameter in der Antwort zurueck');
        $this->assertSame(array('select' => 'id', 'from' => 'PIM\\Tag'), $body['params']);
        $this->assertIsArray($body['data']);
    }

    public function testQueryFuerNichtAdminMitFreigegebenerGruppeIstErlaubt(): void
    {
        $token = $this->anmeldungAlsNichtAdmin('enabled');

        [$status] = $this->postJson('/api/query', array('select' => 'id', 'from' => 'PIM\\Tag'), $token);

        $this->assertSame(200, $status,
            'apiQueryEnabled = "enabled" gibt den Endpunkt fuer die Gruppe frei — '
            .'zusammen mit einer Permission-Zeile fuer die abgefragte Entity');
    }

    public function testQueryFuerNichtAdminOhneFreigabeIstVerboten(): void
    {
        // Der wichtigste Fall: Faellt dieses Gating beim Kernel-Tausch weg, steht die
        // Datenbank jedem angemeldeten Benutzer offen.
        $token = $this->anmeldungAlsNichtAdmin('disabled');

        [$status, $body] = $this->postJson('/api/query', array('select' => 'id', 'from' => 'PIM\\Tag'), $token);

        $this->assertNotSame(200, $status, 'Ohne apiQueryEnabled ist der Endpunkt gesperrt');
        $this->assertArrayNotHasKey('data', $body, 'Es fliessen keine Daten');
    }

    public function testQueryOhneSelectWirdAbgewiesen(): void
    {
        [$status] = $this->postJson('/api/query', array('from' => 'PIM\\Tag'), $this->token());

        $this->assertNotSame(200, $status, 'Api::getQuery() verlangt select und from');
    }

    public function testQueryOhneFromWirdAbgewiesen(): void
    {
        [$status] = $this->postJson('/api/query', array('select' => 'id'), $this->token());

        $this->assertNotSame(200, $status);
    }

    public function testQueryOhneTokenLiefertKeineDaten(): void
    {
        [$status, $body] = $this->postJson('/api/query', array('select' => 'id', 'from' => 'PIM\\Tag'));

        $this->assertSame(500, $status, 'Heute 500 statt 401 — siehe 000-000-0006');
        $this->assertArrayNotHasKey('data', $body);
    }

    // ── /api/translations ──────────────────────────────────────────────────────────────

    public function testTranslationsFuerEineEntityOhneI18nWirft(): void
    {
        // Ist-Zustand. Die Vorlage konfiguriert keine Sprachen (`APP_LANGUAGES` ist leer)
        // und bringt keine konkrete BaseI18n-Entity mit — nur die abstrakten Basisklassen.
        // Die Wirkung von i18n_universal ist damit heute nicht beobachtbar; sie gehoert in
        // einen Test, sobald ein Projekt oder die Vorlage Mehrsprachigkeit einschaltet.
        [$status] = $this->postJson('/api/translations', array('entity' => 'PIM\\Tag'), $this->token());

        $this->assertSame(500, $status,
            'Ohne konfigurierte Mehrsprachigkeit endet der Endpunkt im Fehler — siehe 000-000-0006 '
            .'fuer die Frage, warum das ein 500 und keine fachliche Antwort ist');
    }

    public function testTranslationsOhneTokenLiefertKeineDaten(): void
    {
        [$status, $body] = $this->postJson('/api/translations', array('entity' => 'PIM\\Tag'));

        $this->assertSame(500, $status);
        $this->assertArrayNotHasKey('data', $body);
    }

    public function testAppLanguagesIstInDerVorlageLeer(): void
    {
        // Haelt die Vorbedingung des Tests darueber fest: Waere APP_LANGUAGES gesetzt,
        // muesste /api/translations anders reagieren — und dieser Test schlaegt an.
        [$status, $roh] = $this->get('/api/config');

        $this->assertSame(200, $status, '/api/config ist die einzige Route ohne Token-Pflicht');

        $config = json_decode($roh, true);
        $this->assertSame(array(), $config['data']['languages'] ?? array(),
            'Die Vorlage konfiguriert keine Sprachen');
    }
}
