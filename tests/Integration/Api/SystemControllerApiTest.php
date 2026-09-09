<?php
namespace Tests\Integration\Api;

use Areanet\PIM\Controller\SystemController;
use Tests\Integration\IntegrationTestCase;

/**
 * Charakterisierungstests für `POST /system/do` — den Systemendpunkt und die Token-Verwaltung.
 *
 * Der Controller hat **eine** Route und verteilt von dort dynamisch:
 *
 *     $method = $request->get('method');
 *     if(!method_exists($this, $method)){ throw new \Exception(…); }
 *     return new JsonResponse(array('method' => …, 'datetime' => …, 'message' => $this->$method($request)));
 *
 * Der Request bestimmt also, welche Methode läuft, und das Tor ist `method_exists` — keine
 * Erlaubnisliste. Was dahinter liegt, ist mit Epic `009` auszutauschen; vorher gehört es
 * festgehalten. Die vier Token-Methoden hängen zusätzlich an Story `013-003` (JWT und
 * Widerruf) — was dort ersetzt wird, ist hier zuerst beschrieben.
 *
 * **Diese Tests halten fest, was ist — nicht, was sein sollte.** Fünf Befunde aus dieser
 * Datei sind als `000-000-0015` notiert und werden dort repariert; die Tests darauf sind
 * dann bewusst umzudrehen.
 */
class SystemControllerApiTest extends IntegrationTestCase
{
    /** Die Id des Administrators — `addToken` braucht einen gültigen Benutzer. */
    private function adminId(): string
    {
        $id = $this->pdo()->query('SELECT id FROM pim_user WHERE isAdmin = 1 ORDER BY created LIMIT 1')->fetchColumn();

        $this->assertNotFalse($id, 'Vorbedingung: die Testdatenbank hat einen Administrator');

        return (string) $id;
    }

    /** Ruft `/system/do` mit der übergebenen Methode auf. */
    private function systemDo(string $methode, array $weitere = array(), ?string $token = null): array
    {
        return $this->postJson('/system/do', array_merge(array('method' => $methode), $weitere), $token ?? $this->token());
    }

    /**
     * Legt über `addToken` einen API-Token an und meldet Zeile und Logeintrag zum Aufräumen.
     *
     * @return array{0:int,1:array} Status, Rumpf
     */
    private function tokenAnlegen(string $tokenString, string $referrer = 'https://test.example'): array
    {
        [$status, $body] = $this->systemDo('addToken', array(
            'referrer' => $referrer,
            'token'    => $tokenString,
            'user'     => $this->adminId(),
        ));

        $zeile = $this->pdo()->prepare('SELECT id FROM pim_token WHERE token = :t');
        $zeile->execute(array('t' => $tokenString));

        if ($id = $zeile->fetchColumn()) {
            $this->nachTestLoeschen('pim_token', (string) $id);

            $log = $this->pdo()->prepare('SELECT id FROM pim_log WHERE model_name = :n AND model_id = :i');
            $log->execute(array('n' => 'PIM\\Token', 'i' => (string) $id));

            foreach ($log->fetchAll(\PDO::FETCH_COLUMN) as $logId) {
                $this->nachTestLoeschen('pim_log', (string) $logId);
            }
        }

        return array($status, $body);
    }

    // ── A: Die Absicherung ─────────────────────────────────────────────────────────────

    public function testOhneTokenWirdAbgewiesen(): void
    {
        [$status] = $this->postJson('/system/do', array('method' => 'listTokens'));

        $this->assertSame(401, $status);
    }

    public function testEinUngueltigerTokenWirdAbgewiesen(): void
    {
        [$status] = $this->systemDo('listTokens', array(), 'diesen-token-gibt-es-nicht');

        $this->assertSame(401, $status);
    }

    public function testEinNichtAdminMitGueltigemTokenWirdAbgewiesen(): void
    {
        // Der before-Hook verlangt beides: gueltiger Token UND isAdmin. Der Testbenutzer hat
        // einen frischen Token — er scheitert allein an der zweiten Bedingung.
        [$token] = $this->testbenutzer();

        [$statusAndernorts] = $this->get('/api/schema', $token);
        $this->assertSame(200, $statusAndernorts, 'Vorbedingung: der Token selbst ist gueltig');

        [$status] = $this->systemDo('listTokens', array(), $token);

        $this->assertSame(401, $status, 'Nicht-Admin wird abgewiesen — seit Symfony 4.4 mit 401 statt 403');
    }

    public function testDieAbsichtDesHooksKommtSeitDemStackWechselAnDenClientDurch(): void
    {
        // **Umgedreht mit 006-002-0006.** Bis Symfony 3.4 hielt dieser Test fest, dass die
        // Absicht des Hooks NICHT ankommt:
        //
        //     new AccessDeniedHttpException('…', null, 401)
        //
        // Das dritte Argument ist der Exception-Code, nicht der Statuscode, und
        // AccessDeniedHttpException hat 403 fest verdrahtet — beim Client kam 403 an.
        //
        // Unter Symfony 4.4 kommt 401 durch. Die Absicht des Codes wird erfuellt; der
        // Nebenbefund aus 008-004-0003 hat sich mit dem Stack-Wechsel erledigt.
        $quelle = file_get_contents(ROOT_DIR.'/lib/contentfly/Classes/Controller/Provider/Base/SystemControllerProvider.php');

        $this->assertStringContainsString("AccessDeniedHttpException('Zugriff verweigert', null, 401)", $quelle,
            'Die Absicht im Code ist 401');

        [$status] = $this->postJson('/system/do', array('method' => 'listTokens'));
        $this->assertSame(401, $status, 'Und seit Symfony 4.4 kommt sie auch an');
    }

    public function testNurPostIstErlaubt(): void
    {
        [$status] = $this->get('/system/do?method=listTokens', $this->token());

        // 405 seit 000-000-0006. Vorher 302: Der Fehler-Handler leitete ohne
        // JSON-Content-Type auf `/` um. Die MethodNotAllowedHttpException traegt ihren Code
        // in getStatusCode(), nicht in getCode() — deshalb kam er vorher auch dann nicht
        // durch, wenn die Umleitung nicht griff.
        $this->assertSame(405, $status);
    }

    // ── B: Der Dispatch und seine Antwortform ──────────────────────────────────────────

    public function testDieAntwortTraegtMethodeZeitstempelUndErgebnis(): void
    {
        [$status, $body] = $this->systemDo('flushSchemaCache');

        $this->assertSame(200, $status);
        $this->assertSame(array('method', 'datetime', 'message'), array_keys($body),
            'Genau drei Schluessel, in dieser Reihenfolge');
        $this->assertSame('flushSchemaCache', $body['method'], 'Die angeforderte Methode wird zurueckgespiegelt');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $body['datetime'],
            'Format Y-m-d H:i:s, ohne Zeitzone');
    }

    public function testDerSystemEndpunktBenutztEineEigeneAntwortform(): void
    {
        // Die /api-Endpunkte antworten mit data/totalItems/version/hash, /system/do mit
        // method/datetime/message. Zwei Formen im selben Framework — festgehalten unter
        // 000-000-0014 (Vereinheitlichung der Envelopes).
        [$statusSystem, $system] = $this->systemDo('generateToken');
        [$statusApi, $api]       = $this->postJson('/api/list', array('entity' => 'PIM\\User'), $this->token());

        $this->assertSame(200, $statusSystem);
        $this->assertSame(200, $statusApi, 'Vorbedingung: beide Aufrufe gelingen');

        $this->assertSame(array('method', 'datetime', 'message'), array_keys($system));
        $this->assertSame(array('data', 'totalItems', 'version', 'hash'), array_keys($api));
    }

    public function testEineUnbekannteMethodeEndetInEinerHtmlFehlerseite(): void
    {
        // doAction wirft eine nackte \Exception. Silex macht daraus 500 und liefert die
        // HTML-Fehlerseite aus — kein JSON, obwohl der Aufrufer JSON angefordert hat.
        // **Nachgezogen mit 006-002-0006.** Bis Symfony 3.4 lieferte Silex hier die
        // HTML-Fehlerseite, obwohl der Aufrufer JSON angefordert hatte. Seit 4.4 greift der
        // Fehler-Handler aus bootstrap-web.php und antwortet mit JSON — eine Verbesserung,
        // und ein weiteres Stueck von 000-000-0006.
        [$status, $body] = $this->systemDo('gibtEsNicht');

        $this->assertSame(500, $status);
        $this->assertSame('Methode gibtEsNicht nicht verfügbar.', $body['message'] ?? null,
            'Die Meldung der Exception kommt jetzt als JSON beim Client an');
    }

    public function testEineFehlendeMethodeEndetEbenfallsInEinemFehler(): void
    {
        // Ohne 'method' bekommt method_exists() null als zweites Argument — unter PHP 8.3
        // eine Deprecation, ab PHP 9 ein TypeError. Das Ergebnis ist heute wie dort 500,
        // aber aus unterschiedlichem Grund. Fuer die Zielplattform PHP 8.5 relevant.
        [$status] = $this->postJson('/system/do', array(), $this->token());

        $this->assertSame(500, $status);
    }

    /**
     * **Umgedreht mit `000-000-0015`, nicht geloescht.**
     *
     * Der Test hiess `testDasTorIstMethodExistsUndNichtEineErlaubnisliste()`: Erreichbar war
     * alles, was `method_exists()` bejahte — auch `setEM` und `__construct` aus
     * `BaseController`. Sie wurden aufgerufen und scheiterten erst an ihrer Typpruefung; die
     * Grenze zog die Signatur, nicht der Endpunkt.
     *
     * Jetzt zieht sie eine ausgeschriebene Liste.
     */
    public function testDasTorIstEineErlaubnislisteUndNichtMethodExists(): void
    {
        $this->assertTrue(method_exists(SystemController::class, 'setEM'),
            'Vorbedingung: die Methode gibt es weiterhin');

        [$statusSetEm]     = $this->systemDo('setEM');
        [$statusConstruct] = $this->systemDo('__construct');

        $this->assertSame(500, $statusSetEm, 'Abgewiesen am Tor, nicht am Typ');
        $this->assertSame(500, $statusConstruct);

        // Der Unterschied zu vorher steht in der Meldung: Sie kommt jetzt aus doAction,
        // nicht aus einer Typpruefung tief in der Basisklasse.
        [, $body] = $this->postJson('/system/do', array('method' => 'setEM'), $this->token());
        $this->assertSame('Methode setEM nicht verfügbar.', $body['message'] ?? null);
    }

    /**
     * **Umgedreht mit `000-000-0015`, nicht geloescht.**
     *
     * Der Test hiess `testDoActionRuftSichSelbstAufUndWirdDeshalbNichtScharfGeprueft()` und
     * pruefte die **Ursache** statt der Wirkung: `doAction` ist public, bestand
     * `method_exists()` und schickte den Controller in eine Endlosrekursion. Mit dem
     * Standard-Limit endete sie nach ~0,2 s in einem Fatal Error, **ohne** Limit gar nicht —
     * der Ausgang haengt an einer Einstellung ausserhalb der Suite, deshalb wurde sie nie
     * ausgeloest.
     *
     * Jetzt laesst sie sich gefahrlos ausloesen: Die Erlaubnisliste kennt `doAction` nicht.
     * `doAction` ist weiterhin public — das muss es sein, Silex ruft es als Route auf.
     */
    public function testDoActionRuftSichNichtMehrSelbstAuf(): void
    {
        $doAction = new \ReflectionMethod(SystemController::class, 'doAction');
        $this->assertTrue($doAction->isPublic(),
            'weiterhin oeffentlich — Silex ruft es als Route auf');

        [$status, $body] = $this->postJson('/system/do', array('method' => 'doAction'), $this->token());

        $this->assertSame(500, $status);
        $this->assertSame('Methode doAction nicht verfügbar.', $body['message'] ?? null,
            'Abgewiesen, statt sich selbst aufzurufen');
    }

    // ── C: Die Token-Verwaltung ────────────────────────────────────────────────────────

    public function testGenerateTokenLiefertHexZeichenUndSchreibtNichts(): void
    {
        // Erst anmelden, dann zaehlen: /auth/login legt selbst eine Zeile in pim_token an.
        $this->token();

        $vorher = $this->pdo()->query('SELECT COUNT(*) FROM pim_token')->fetchColumn();

        [$status, $body] = $this->systemDo('generateToken');

        $this->assertSame(200, $status);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{128}$/', $body['message'],
            '64 Zufallsbytes als Hex — der Wert wird nur erzeugt, nicht hinterlegt');
        $this->assertSame($vorher, $this->pdo()->query('SELECT COUNT(*) FROM pim_token')->fetchColumn(),
            'generateToken legt keine Zeile an; das tut erst addToken');
    }

    public function testAddTokenLegtEineZeileAnUndProtokolliertSie(): void
    {
        $wert = 'test-'.bin2hex(random_bytes(16));

        [$status, $body] = $this->tokenAnlegen($wert, 'https://addtoken.example');

        $this->assertSame(200, $status);
        $this->assertSame(
            array('id', 'token', 'referrer', 'user'),
            array_keys($body['message']),
            'Die Antwort traegt die Zeile samt eingebettetem Benutzer'
        );
        $this->assertSame($wert, $body['message']['token']);
        $this->assertSame('https://addtoken.example', $body['message']['referrer']);
        $this->assertSame(array('id', 'alias', 'active'), array_keys($body['message']['user']),
            'Vom Benutzer werden drei Felder gespiegelt — kein Passwort, kein Salt');

        $zeile = $this->pdo()->prepare('SELECT token, referrer, user_id FROM pim_token WHERE id = :id');
        $zeile->execute(array('id' => $body['message']['id']));
        $gefunden = $zeile->fetch(\PDO::FETCH_ASSOC);

        $this->assertSame($wert, $gefunden['token'], 'Der Token steht im Klartext in der Tabelle');
        $this->assertSame($this->adminId(), $gefunden['user_id']);
    }

    /**
     * **Umgedreht mit `000-000-0015`, nicht geloescht.**
     *
     * Der Test hiess `testAddTokenSchreibtDenLogeintragMitEinemDeutschenModusStattDerKonstanten()`:
     * `addToken` setzte `'Erstellt'`, `deleteToken` `'Gelöscht'`. In `pim_log.mode` standen
     * damit zwei Vokabulare nebeneinander, und wer nach `Log::INSERTED` filterte, fand die
     * Token-Vorgaenge nicht.
     *
     * **Der Altbestand bleibt, wie er ist** — bewusst. `pim_log` ist ein Protokoll; alte
     * Zeilen nachtraeglich umzuschreiben hiesse, die Aufzeichnung zu aendern. Wer historisch
     * auswertet, sucht fuer Token-Vorgaenge vor diesem Stand nach den deutschen Werten. Der
     * Vermerk steht in `an_project/docs/breaking-changes.md`.
     */
    public function testAddTokenSchreibtDenLogeintragMitDerKonstanten(): void
    {
        $wert = 'test-'.bin2hex(random_bytes(16));

        [, $body] = $this->tokenAnlegen($wert);

        $log = $this->pdo()->prepare('SELECT mode, model_name, model_label FROM pim_log WHERE model_name = :n AND model_id = :i');
        $log->execute(array('n' => 'PIM\\Token', 'i' => (string) $body['message']['id']));
        $eintrag = $log->fetch(\PDO::FETCH_ASSOC);

        $this->assertSame('INS', $eintrag['mode'], "Log::INSERTED, nicht 'Erstellt'");
        $this->assertSame('PIM\\Token', $eintrag['model_name']);
        $this->assertSame($wert, $eintrag['model_label'],
            'Der Token steht im Klartext im Protokoll — das gehoert zu 013-003');
    }

    public function testAddTokenBrauchtReferrerTokenUndBenutzer(): void
    {
        $this->token();

        $vorher = $this->pdo()->query('SELECT COUNT(*) FROM pim_token')->fetchColumn();

        [$ohneAlles]    = $this->systemDo('addToken');
        [$ohneBenutzer] = $this->systemDo('addToken', array('referrer' => 'https://x.example', 'token' => 'unvollstaendig'));

        $this->assertSame(500, $ohneAlles);
        $this->assertSame(500, $ohneBenutzer);
        $this->assertSame($vorher, $this->pdo()->query('SELECT COUNT(*) FROM pim_token')->fetchColumn(),
            'Ein unvollstaendiger Aufruf hinterlaesst nichts');
    }

    public function testAddTokenLehntEinenUnbekanntenBenutzerAb(): void
    {
        [$status] = $this->systemDo('addToken', array(
            'referrer' => 'https://x.example',
            'token'    => 'test-'.bin2hex(random_bytes(8)),
            'user'     => 'diesen-benutzer-gibt-es-nicht',
        ));

        $this->assertSame(500, $status);
    }

    public function testEinBereitsVergebenerTokenWirdAbgelehnt(): void
    {
        $wert = 'test-'.bin2hex(random_bytes(16));

        [$erster] = $this->tokenAnlegen($wert);
        $this->assertSame(200, $erster, 'Vorbedingung');

        [$zweiter] = $this->systemDo('addToken', array(
            'referrer' => 'https://zweitversuch.example',
            'token'    => $wert,
            'user'     => $this->adminId(),
        ));

        $this->assertSame(500, $zweiter, 'pim_token.token ist unique — der zweite Versuch scheitert');
    }

    public function testListTokensZeigtNurTokenMitReferrer(): void
    {
        // Die Abfrage lautet "WHERE token.referrer <> ''". Anmeldetoken aus /auth/login haben
        // keinen Referrer (NULL) und tauchen deshalb nicht auf — deshalb kann diese Methode
        // den Token des laufenden Tests auch nicht preisgeben. Die Trennung ist ein
        // Nebeneffekt der Abfrage, keine ausdrueckliche Regel.
        $wert = 'test-'.bin2hex(random_bytes(16));
        $this->tokenAnlegen($wert, 'https://listtokens.example');

        [$status, $body] = $this->systemDo('listTokens');

        $this->assertSame(200, $status);

        $werte = array_column($body['message'], 'token');
        $this->assertContains($wert, $werte, 'Der API-Token mit Referrer wird gelistet');
        $this->assertNotContains($this->token(), $werte, 'Der Anmeldetoken des Testlaufs nicht');

        foreach ($body['message'] as $eintrag) {
            $this->assertSame(array('id', 'token', 'referrer', 'user'), array_keys($eintrag));
            $this->assertNotSame('', $eintrag['referrer']);
        }
    }

    /**
     * **Umgedreht mit `000-000-0015`, nicht geloescht.**
     *
     * Der Test hiess `testDeleteTokenIstDurchEinenFalschenNamensraumUnbrauchbar()`. Die
     * Methode suchte in `Areanet\Contently\Entity\Token` — „Contently" statt „PIM", die
     * einzige Stelle im ganzen Baum mit diesem Namen. Doctrine kannte die Klasse nicht, und
     * die Methode endete vor ihrer ersten fachlichen Zeile: **Ein API-Token liess sich ueber
     * die API nicht wieder loswerden.**
     *
     * Der alte Test forderte das Umdrehen woertlich ein — „dann muss die Zeile verschwinden
     * und ein Logeintrag mit `Log::DELETED` entstehen". Genau das steht hier.
     *
     * Der Rest der Methode lief bis dahin **nie**. Geprueft wird deshalb nicht nur der
     * Repository-Aufruf, sondern was danach kommt.
     */
    public function testDeleteTokenEntferntDieZeileUndProtokolliertEs(): void
    {
        $wert = 'test-'.bin2hex(random_bytes(16));

        [, $body] = $this->tokenAnlegen($wert);
        $id       = $body['message']['id'];

        [$status] = $this->systemDo('deleteToken', array('id' => $id));
        $this->assertSame(200, $status);

        $zeile = $this->pdo()->prepare('SELECT COUNT(*) FROM pim_token WHERE id = :id');
        $zeile->execute(array('id' => $id));
        $this->assertSame('0', (string) $zeile->fetchColumn(), 'Die Zeile ist weg');

        $log = $this->pdo()->prepare(
            'SELECT mode, model_label FROM pim_log WHERE model_name = :n AND model_id = :i AND mode = :m'
        );
        $log->execute(array('n' => 'PIM\\Token', 'i' => (string) $id, 'm' => 'DEL'));
        $eintrag = $log->fetch(\PDO::FETCH_ASSOC);

        $this->assertNotFalse($eintrag, 'und ein Logeintrag mit Log::DELETED steht da');
        $this->assertSame($wert, $eintrag['model_label']);
    }

    public function testDeleteTokenMeldetEinenUnbekanntenToken(): void
    {
        // Die zweite Haelfte, die der Task verlangt: Der Fall "Token unbekannt". Er lief
        // vorher aus demselben Grund nie — die Methode kam gar nicht bis zur Pruefung.
        [$status, $body] = $this->postJson(
            '/system/do',
            array('method' => 'deleteToken', 'id' => 'gibtesnicht-'.bin2hex(random_bytes(4))),
            $this->token()
        );

        $this->assertSame(500, $status);
        $this->assertSame('Token ungültig', $body['message'] ?? null);
    }

    public function testDerAnmeldeTokenDesLaufsUeberstehtDieTokenMethoden(): void
    {
        // Die Zusicherung, die diese Datei gegenueber dem Rest der Suite gibt: Was hier mit
        // Token geschieht, beruehrt den Token des laufenden Tests nicht.
        $this->systemDo('generateToken');
        $this->systemDo('listTokens');
        $this->tokenAnlegen('test-'.bin2hex(random_bytes(16)));

        [$status] = $this->get('/api/schema', $this->token());

        $this->assertSame(200, $status, 'Der Anmeldetoken gilt weiterhin');
    }

    /**
     * Der Aufraeumweg zu Punkt 5 aus `000-000-0015`.
     *
     * `pim_token` wuchs unbegrenzt: Aufgeraeumt wurde nur traege, wenn ein abgelaufener Token
     * noch einmal vorgezeigt wurde. Wer den Browser schliesst, hinterlaesst eine Zeile fuer
     * immer.
     *
     * **Der Task liess offen, ob `013-003` die Tabelle ohnehin ersetzt. Tut es nicht:** Die
     * Story behaelt den opaquen DB-Token ausdruecklich als Refresh-Token. Also braucht es den
     * Aufraeumlauf — `appcms:token:cleanup`.
     *
     * Geprueft wird ueber die Datenbank, nicht ueber den Command-Aufruf: Die Suite laeuft
     * gegen einen Testserver, der Command in einem eigenen Prozess. Was zaehlt, ist die
     * Rechnung, nach der er entscheidet — und die ist dieselbe wie in `checkToken()`.
     */
    public function testAbgelaufeneAnmeldetokenLassenSichAufraeumen(): void
    {
        $benutzerId = $this->pdo()->query("SELECT id FROM pim_user WHERE alias = 'admin'")->fetchColumn();

        // Ein abgelaufener Anmeldetoken (kein referrer) und ein API-Token (mit referrer).
        $abgelaufen = 'alt-'.bin2hex(random_bytes(16));
        $apiToken   = 'api-'.bin2hex(random_bytes(16));

        // pim_token.id ist eine Integer-Spalte mit Auto-Increment — anders als die Entities,
        // die von Base erben und eine GUID tragen. Die Id kommt deshalb von MySQL.
        $einfuegen = $this->pdo()->prepare(
            'INSERT INTO pim_token (user_id, token, referrer, created, modified)'
            .' VALUES (:u, :t, :r, :c, :m)'
        );
        $alt = (new \DateTime('-30 days'))->format('Y-m-d H:i:s');

        $einfuegen->execute(array('u' => $benutzerId, 't' => $abgelaufen, 'r' => null, 'c' => $alt, 'm' => $alt));
        $idAbgelaufen = $this->pdo()->lastInsertId();

        $einfuegen->execute(array('u' => $benutzerId, 't' => $apiToken, 'r' => 'https://example.invalid', 'c' => $alt, 'm' => $alt));
        $idApi = $this->pdo()->lastInsertId();

        $this->nachTestLoeschen('pim_token', $idAbgelaufen);
        $this->nachTestLoeschen('pim_token', $idApi);

        $ausgabe = array();
        exec(
            sprintf('%s %s appcms:token:cleanup 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg(ROOT_DIR.'/bin/console.php')),
            $ausgabe
        );

        $zaehlen = $this->pdo()->prepare('SELECT COUNT(*) FROM pim_token WHERE id = :id');

        $zaehlen->execute(array('id' => $idAbgelaufen));
        $this->assertSame('0', (string) $zaehlen->fetchColumn(),
            'Der abgelaufene Anmeldetoken ist weg — '.implode(' ', $ausgabe));

        $zaehlen->execute(array('id' => $idApi));
        $this->assertSame('1', (string) $zaehlen->fetchColumn(),
            'Der API-Token mit referrer bleibt: Er verfaellt nicht ueber die Zeit');
    }

    public function testJedeAnmeldungLegtEineZeileAnDieNurBeimNaechstenGebrauchVerfaellt(): void
    {
        // Gehoert hierher, weil pim_token die Tabelle ist, die dieser Controller verwaltet —
        // und weil listTokens sie ausdruecklich **nicht** zeigt (kein Referrer).
        //
        // /auth/login legt je Anmeldung eine Zeile an. Aufgeraeumt wird nur traege, in
        // BaseControllerProvider::checkToken(): Wird ein abgelaufener Token noch einmal
        // vorgezeigt, verschwindet er. Ein Token, den niemand wieder benutzt — der Normalfall
        // beim Schliessen des Browsers — bleibt unbegrenzt stehen. Es gibt keinen
        // Aufraeumlauf, kein Console-Command und keinen Endpunkt dafuer.
        //
        // Befund, notiert in 000-000-0015. Story 013-003 (JWT und Widerruf) loest das Problem
        // vermutlich ohnehin auf; bis dahin ist es festgehalten.
        $vorher = (int) $this->pdo()->query('SELECT COUNT(*) FROM pim_token')->fetchColumn();

        $frisch = $this->login();

        $nachher = (int) $this->pdo()->query('SELECT COUNT(*) FROM pim_token')->fetchColumn();
        $this->assertSame($vorher + 1, $nachher, 'Die Anmeldung hinterlaesst eine Zeile');

        $zeile = $this->pdo()->prepare('SELECT id, referrer FROM pim_token WHERE token = :t');
        $zeile->execute(array('t' => $frisch));
        $gefunden = $zeile->fetch(\PDO::FETCH_ASSOC);

        $this->nachTestLoeschen('pim_token', (string) $gefunden['id']);

        $this->assertNull($gefunden['referrer'],
            'Ohne Referrer — deshalb unterliegt er dem Timeout und taucht nicht in listTokens auf');

        $quelle = file_get_contents(ROOT_DIR.'/lib/contentfly/Classes/Controller/Provider/BaseControllerProvider.php');
        $this->assertStringContainsString('$app[\'orm.em\']->remove($token);', $quelle,
            'Entfernt wird nur innerhalb von checkToken — also nur, wenn der Token erneut vorgezeigt wird');
    }

    // ── flushSchemaCache ───────────────────────────────────────────────────────────────

    public function testFlushSchemaCacheMeldetErfolgUndLaesstDasSchemaLesbar(): void
    {
        [$status, $body] = $this->systemDo('flushSchemaCache');

        $this->assertSame(200, $status);
        $this->assertSame('Schema-Cache wurde geleert!', $body['message']);

        [$statusSchema] = $this->get('/api/schema', $this->token());
        $this->assertSame(200, $statusSchema, 'Das Schema wird danach neu aufgebaut');
    }

    public function testFlushSchemaCacheMeldetErfolgAuchWennEsNichtsZuLoeschenGibt(): void
    {
        // Die Methode raeumt zwei Dinge: die Datei data/cache/schema.cache und die
        // Doctrine-Caches. Die Datei entsteht aber nur, wenn APP_ENABLE_SCHEMA_CACHE an ist
        // — und die Vorlage schaltet sie aus. Unter der ausgelieferten Konfiguration ist der
        // erste Teil also folgenlos; die Meldung lautet trotzdem unveraendert "geleert!".
        $vorlage = file_get_contents(ROOT_DIR.'/custom/config.php');

        $this->assertMatchesRegularExpression(
            '/APP_ENABLE_SCHEMA_CACHE\s*=\s*false;/',
            $vorlage,
            'Vorbedingung: die Vorlage schaltet den Schema-Cache aus'
        );

        $this->assertFileDoesNotExist(ROOT_DIR.'/data/cache/schema.cache');

        [, $body] = $this->systemDo('flushSchemaCache');

        $this->assertSame('Schema-Cache wurde geleert!', $body['message'],
            'Die Meldung sagt nicht, ob ueberhaupt etwas da war');
    }

    // ── D: Das Notschloss ──────────────────────────────────────────────────────────────

    /**
     * **Umgedreht mit `000-000-0015`, nicht geloescht.**
     *
     * Der Test hiess `testValidateORMStehtInDerAusnahmelisteExistiertAberNicht()`: Das
     * Notschloss liess `validateORM` und `updateDatabase` **ohne Token und ohne Adminrecht**
     * durch — und die erste der beiden gab es im Controller nicht. Eine Ausnahme ins Leere.
     *
     * **Gestrichen statt wiederhergestellt.** Doctrine braechte mit `SchemaValidator` alles
     * mit, und der Import steht noch oben in der Datei — aber eine wiederhergestellte Methode
     * waere ein zweiter Endpunkt ohne Token und ohne Adminrecht. Ein Notschloss soll so klein
     * sein wie moeglich.
     */
    public function testDasNotschlossKenntNurNochUpdateDatabase(): void
    {
        $this->assertFalse(method_exists(SystemController::class, 'validateORM'),
            'validateORM existiert weiterhin nicht');

        $quelle = file_get_contents(ROOT_DIR.'/lib/contentfly/Classes/Controller/Provider/Base/SystemControllerProvider.php');
        $this->assertStringNotContainsString("== 'validateORM'", $quelle,
            'und steht nicht mehr in der Ausnahmeliste');
        // Die Schreibweise hat sich mit 009-003-0001 geaendert — Request::get() ist in
        // Symfony 7.4 deprecated, gelesen wird jetzt aus dem request-Beutel. Die Bedingung
        // selbst ist dieselbe, und genau sie ist gemeint.
        $this->assertStringContainsString("all()['method'] ?? null) == 'updateDatabase'", $quelle,
            'updateDatabase bleibt — ein kaputtes Schema muss reparierbar sein');

        [$status] = $this->systemDo('validateORM');
        $this->assertSame(500, $status, 'und wird als unbekannte Methode abgewiesen');
    }

    public function testDasNotschlossGreiftNurBeiEinerInvalidFieldNameException(): void
    {
        // Der Ausnahmezweig haengt an genau einer Doctrine-Ausnahme: Faellt eine Spalte weg,
        // die checkToken() liest, kommt eine InvalidFieldNameException — und dann ist
        // updateDatabase **ohne Token und ohne Adminrecht** erreichbar (seit 000-000-0015 nur
        // noch diese eine Methode; validateORM stand hier ebenfalls und existierte nicht). Der
        // Sinn ist erkennbar (ein kaputtes Schema muss reparierbar bleiben, ohne dass man
        // sich anmelden kann); der Preis ist eine ungesicherte Schreiboperation auf dem
        // Schema.
        //
        // **Warum hier kein scharfer Test steht:** Um den Zweig auszuloesen, muesste der Test
        // das Schema der gemeinsamen Testdatenbank absichtlich beschaedigen — und dann darauf
        // hoffen, dass updateDatabase es vollstaendig wiederherstellt. Scheitert er auf
        // halbem Weg, ist die Datenbank fuer jeden folgenden Test kaputt. Das Testnetz soll
        // Vertrauen schaffen, nicht die Umgebung riskieren; deshalb wird hier die Bedingung
        // festgehalten und nicht die Wirkung erzwungen.
        //
        // Wer Epic 009 umsetzt, findet an diesem Test, was der neue Kernel entweder
        // nachbilden oder bewusst streichen muss.
        $quelle = file_get_contents(ROOT_DIR.'/lib/contentfly/Classes/Controller/Provider/Base/SystemControllerProvider.php');

        $this->assertStringContainsString('catch(InvalidFieldNameException $e)', $quelle,
            'Nur diese eine Ausnahme oeffnet das Notschloss');
        $this->assertStringContainsString('throw $e;', $quelle,
            'Jede andere Methode fliegt weiter — das Schloss oeffnet nur fuer diese eine');
    }
}
