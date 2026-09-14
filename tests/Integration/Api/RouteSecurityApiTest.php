<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * Charakterisierungstests für die Absicherung der Routen und die Middleware der Vorlage.
 *
 * Der Schalter heißt **`Route::$isSecure`** und liegt in
 * `Classes/Controller/Provider/Base/CustomControllerProvider.php` — nicht `_secured` im
 * `RouteManager`, wie der Text von Epic `008` ursprünglich annahm; die Bezeichnung kommt im
 * Baum nicht vor (festgestellt in `012-006-0003`).
 *
 * Auf diese Semantik verlässt sich Epic `009` beim Kernel-Tausch: Ein Projekt baut damit
 * seine öffentlichen Endpunkte. Deshalb sind **beide** Richtungen festgehalten — dass eine
 * gesicherte Route abweist, und dass eine ungesicherte antwortet.
 */
class RouteSecurityApiTest extends IntegrationTestCase
{
    // ── isSecure = true ────────────────────────────────────────────────────────────────

    public function testEineGesicherteRouteWeistOhneTokenAb(): void
    {
        [$status, $body] = $this->postJson('/api/list', array('entity' => 'PIM\\Tag'));

        $this->assertSame(401, $status, 'Seit dem Stack-Wechsel (006-002-0003) der gemeinte Code — Symfony 4.4 behebt hier 000-000-0006');
        $this->assertArrayNotHasKey('data', $body, 'Entscheidend ist: es fliessen keine Daten');
    }

    /**
     * **GET, nicht POST** — geklaert mit `000-000-0020`.
     *
     * Diese Zusicherung rief `/api/schema` bis dahin per POST auf. Die Route ist aber seit
     * dem initialen Import als `$controllers->get('/schema', ...)` definiert; POST war **nie**
     * Teil des Vertrags. Auch jeder andere Aufrufer im Repo — 19 Stellen in 11 Testdateien —
     * benutzt GET.
     *
     * Dass es trotzdem lange durchging, lag an der Schwaeche der alten Zusicherung: Sie
     * pruefte `assertNotSame(500, ...)`, und ein „Method Not Allowed" ist 405. Erst seit dem
     * Stack-Wechsel kommt derselbe Fall als 500 heraus (`MethodNotAllowedHttpException`, vom
     * Statuscode her ueberschrieben — das ist `000-000-0006`), und damit fiel er auf.
     *
     * Die Zusicherung steht jetzt auf dem tatsaechlichen Ergebnis statt auf der Verneinung
     * eines einzelnen Fehlercodes. Der Zweck dieser Klasse verlangt das: Epic `009` verlaesst
     * sich auf die `isSecure`-Semantik, und „irgendetwas ausser 500" belegt sie nicht.
     */
    public function testEineGesicherteRouteAntwortetMitToken(): void
    {
        [$status] = $this->get('/api/schema', $this->token());

        $this->assertSame(200, $status, 'Mit Token laeuft die Pruefung durch');
    }

    public function testEineLeereListeKommtAls200(): void
    {
        // Umgedreht mit 000-000-0014. Der Test hiess testEineLeereListeKommtAls404 und hielt
        // fest, dass listAction() bei leerem Ergebnis mit HTTP 404 {"message":"Not found"}
        // antwortet — einer achten Antwortform, die mit keiner der sieben anderen etwas zu
        // tun hatte. Fuer einen Client waren "keine Treffer" und "Route gibt es nicht"
        // damit nicht unterscheidbar.
        //
        // PIM\\Nav ist nach einer frischen Installation leer — kein Loeschen noetig, das
        // wuerde den Datenbestand anderer Tests anfassen.
        [$status, $body] = $this->postJson('/api/list', array('entity' => 'PIM\\Nav'), $this->token());

        $this->assertSame(200, $status);
        $this->assertSame(array(), $body['data'], 'Eine leere Liste, keine Fehlermeldung');
        $this->assertSame(0, $body['totalItems']);
    }

    public function testEineUnbekannteEntityBleibtEin404MitBegruendung(): void
    {
        // Die Gegenprobe zum Test darueber: Der Fall, fuer den der 404 gedacht war, meldet
        // sich weiterhin — und zwar unterscheidbar, mit einer Begruendung im Rumpf.
        [$status, $body] = $this->postJson('/api/list', array('entity' => 'PIM\\GibtesNicht'), $this->token());

        $this->assertSame(404, $status);
        $this->assertSame('contentfly_general_unknown_entity', $body['message']);
    }

    // ── isSecure = false ───────────────────────────────────────────────────────────────

    public function testEineUngesicherteRouteAntwortetOhneToken(): void
    {
        // Die andere Richtung, und der eigentliche Vertrag: `custom/app.php` bindet
        //
        //     $controllerProvider->mount('api/v1/example/', …)->post('/bootstrap', false, …)
        //
        // Das zweite Argument ist isSecure. Auf false gesetzt, ist die Route ohne Token
        // erreichbar — so baut ein Projekt seine oeffentlichen Endpunkte. Faellt diese
        // Faehigkeit beim Kernel-Tausch weg, merkt es niemand, bis ein Projekt bricht.
        [$status, $body] = $this->postJson('/api/v1/example/bootstrap', array());

        $this->assertSame(200, $status, 'Ohne Token erreichbar, weil isSecure=false');

        // Und ein Befund nebenbei: Die Vorlage antwortet in ihrem EIGENEN Format — success,
        // status, i18n, data, errors, meta, timestamp — nicht im Envelope des Frameworks.
        // Ein Projekt ist an dessen Form also nicht gebunden.
        $this->assertSame(
            array('success', 'status', 'i18n', 'data', 'errors', 'meta', 'timestamp'),
            array_keys($body),
            'Die Vorlage bringt ihren eigenen Antwort-Envelope mit'
        );
        $this->assertTrue($body['success']);
    }

    public function testDieUngesicherteRouteAntwortetAuchMitToken(): void
    {
        [$status] = $this->postJson('/api/v1/example/bootstrap', array(), $this->token());

        $this->assertSame(200, $status, 'isSecure=false heisst "Token nicht noetig", nicht "Token verboten"');
    }

    // ── /api/config: die einzige GET-Route ohne Token-Pflicht ──────────────────────────

    public function testApiConfigIstOhneTokenErreichbar(): void
    {
        // Im ApiControllerProvider die einzige Route, die ohne ->before($checkAuth) gebunden
        // ist. Was sie preisgibt, gehoert damit zum oeffentlichen Teil der API.
        [$status, $roh] = $this->get('/api/config');

        $this->assertSame(200, $status);

        $config = json_decode($roh, true);

        // UMGEDREHT MIT 000-000-0010, nicht geloescht.
        //
        // Bis dahin hielt dieser Test fest, dass der oeffentliche Endpunkt weiterhin
        // customLogo bewirbt — eine Eigenschaft der Oberflaeche, die Epic 012 entfernt hat.
        // Der Kommentar lautete: "Festgehalten, nicht bereinigt — das waere ein eigener
        // Task." Das ist dieser Task.
        //
        // Der frontend-Schluessel ist ganz entfallen, nicht geleert: Ein Schluessel, der
        // nichts mehr traegt, laedt dazu ein, wieder etwas hineinzulegen. Vermerkt als
        // Breaking Change in an_project/docs/breaking-changes.md.
        $this->assertSame(array('devmode', 'version', 'hash'), array_keys($config),
            'Ein eigener Envelope, der siebte — ohne data und ohne ts');

        $this->assertArrayNotHasKey('frontend', $config,
            'Der oeffentliche Endpunkt bewirbt nichts mehr aus der geloeschten Oberflaeche');
    }

    // ── Die Middleware der Vorlage ─────────────────────────────────────────────────────

    public function testDerAfterHookAusCustomAppSetztDenReferrerPolicyHeader(): void
    {
        // custom/app.php registriert einen after-Hook, der diesen Header auf jede Antwort
        // setzt. Er ist der einfachste Nachweis, dass die Middleware der Vorlage ueberhaupt
        // greift — und damit, dass RouteManager und Hook-Reihenfolge zusammenspielen.
        [$status, , $kopf] = $this->get('/api/config');

        $this->assertSame(200, $status);
        $this->assertSame('strict-origin-when-cross-origin', $this->header($kopf, 'Referrer-Policy'));
    }

    public function testDerAfterHookGreiftAuchAufEinerGesichertenRoute(): void
    {
        [, , $kopf] = $this->get('/api/schema', $this->token());

        $this->assertSame('strict-origin-when-cross-origin', $this->header($kopf, 'Referrer-Policy'),
            'Die Middleware laeuft unabhaengig davon, ob die Route gesichert ist');
    }

    // ── I18nPermission über die API ────────────────────────────────────────────────────

    public function testDieSprachrechtePruefungIstUeberDieApiNichtAusloesbar(): void
    {
        // Api.php:122 prueft I18nPermission::isWritable() beim Loeschen, und
        // Api.php:248/465 pruefen isOnlyReadable() beim Schreiben. Alle drei brauchen eine
        // i18n-Entity und konfigurierte Sprachen — die Vorlage hat weder das eine noch das
        // andere (`APP_LANGUAGES` ist leer, es gibt nur die abstrakten BaseI18n-Klassen).
        //
        // Die Logik dahinter ist stattdessen als Unit-Test abgedeckt:
        // tests/Unit/Entity/GroupLanguagePermissionTest.php. Aendert sich die Vorbedingung,
        // schlaegt dieser Test an und fordert den Integrationsnachweis ein.
        [$status, $roh] = $this->get('/api/config');
        $this->assertSame(200, $status);

        $config = json_decode($roh, true);

        $this->assertArrayNotHasKey('languages', $config,
            'Ohne konfigurierte Sprachen meldet /api/config gar keine — es gibt also keine '
            .'Sprachrechte zu pruefen');
    }
}
