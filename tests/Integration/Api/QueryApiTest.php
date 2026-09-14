<?php
namespace Tests\Integration\Api;

use Areanet\PIM\Entity\Permission;
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
    private string $tag = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tag = 'query-'.bin2hex(random_bytes(6));
        $this->pdo()->prepare(
            'INSERT INTO pim_tag (id, title, created, modified, views, isIntern)
             VALUES (:id, :titel, NOW(), NOW(), 0, 0)'
        )->execute(array('id' => $this->tag, 'titel' => 'Query-Probe'));
        $this->deleteAfterTest('pim_tag', $this->tag);
    }

    /**
     * Meldet einen Nicht-Admin an, dessen Gruppe die Abfrage-API erlaubt oder nicht.
     *
     * Die Permission-Zeile fuer PIM\Tag ist noetig, weil ein Nicht-Admin sonst schon an der
     * Lesepruefung der Entity scheitert — also an einem anderen Gate als dem, das dieser
     * Test isolieren soll.
     */
    private function anmeldungAlsNichtAdmin(string $apiQueryEnabled): string
    {
        [$token] = $this->createTestUser(
            array('PIM\\Tag' => array('readable' => Permission::ALL)),
            array('apiQueryEnabled' => $apiQueryEnabled)
        );

        return $token;
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

        $this->assertSame(401, $status, 'Seit dem Stack-Wechsel (006-002-0003) der gemeinte Code — Symfony 4.4 behebt hier 000-000-0006');
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

        $this->assertSame(401, $status,
            'Seit 006-002-0003 der gemeinte Code — Symfony 4.4 behebt hier 000-000-0006');
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
