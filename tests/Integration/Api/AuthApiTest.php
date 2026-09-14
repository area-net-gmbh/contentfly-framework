<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * Charakterisierungstests für die Token-Authentifizierung.
 *
 * Nach dem Entfernen der sessionbasierten Anmeldung (Story `012-004`) ist der Token der
 * **einzige** Weg. Ein Fehler darin wäre eine Sicherheitslücke, kein Komfortproblem — deshalb
 * hält diese Suite fest, was heute gilt, und schlägt an, sobald sich daran etwas ändert.
 *
 * Voraussetzungen wie in `FileApiTest`: `CONTENTFLY_TEST_BASE_URL` und
 * `CONTENTFLY_TEST_ADMIN_PASS`, sonst wird übersprungen. Siehe `tests/README.md`.
 */
class AuthApiTest extends IntegrationTestCase
{
    // ── Anmeldung ──────────────────────────────────────────────────────────────────────

    public function testAnmeldungMitKorrektenDatenLiefertEinToken(): void
    {
        [, $body] = $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => $this->pass()));

        $this->assertSame('Login successful', $body['message'] ?? null);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{128}$/', $body['token'] ?? '', 'Token ist 64 Byte als Hex');
        $this->assertTrue($body['user']['isAdmin'] ?? false);
    }

    public function testAnmeldungMitFalschemPasswortLiefertKeinToken(): void
    {
        [$status, $body] = $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => 'falsch'));

        $this->assertSame(401, $status);
        $this->assertArrayNotHasKey('token', $body);
    }

    public function testAnmeldungMitUnbekanntemBenutzerLiefertKeinToken(): void
    {
        [$status, $body] = $this->postJson('/auth/login', array('alias' => 'gibtesnicht', 'pass' => 'egal'));

        $this->assertSame(401, $status);
        $this->assertArrayNotHasKey('token', $body);
    }

    /**
     * Die Fehlermeldung unterscheidet heute zwischen „Benutzername unbekannt" und
     * „Passwort falsch". Das verrät einem Angreifer, welche Kennungen existieren.
     *
     * Festgehalten als heutiges Verhalten — behoben wird es in Story `013-001`
     * (Auth-Härtung). Ändert sich die Meldung dort, ist dieser Test nachzuziehen.
     */
    public function testFehlermeldungVerraetObDerBenutzerExistiert(): void
    {
        [, $unbekannt] = $this->postJson('/auth/login', array('alias' => 'gibtesnicht', 'pass' => 'egal'));
        [, $falsch]    = $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => 'falsch'));

        $this->assertNotSame(
            $unbekannt['message'] ?? null,
            $falsch['message'] ?? null,
            'Heute unterschiedliche Meldungen - siehe 013-001'
        );
    }

    // ── Zugriffsschutz ─────────────────────────────────────────────────────────────────

    public function testGeschuetzteRouteMitGueltigemTokenLiefert200(): void
    {
        $token = $this->login();

        [$status] = $this->get('/api/schema', $token);

        $this->assertSame(200, $status);
    }

    public function testGeschuetzteRouteOhneTokenLiefertKeineDaten(): void
    {
        [$status, $body] = $this->get('/api/schema', null);

        $this->assertNotSame(200, $status);
        // Zweimal umgedreht. Erst hielt der Test 500 fest — der Debug-Exception-Handler fing
        // die Ausnahme vor dem Handler der Anwendung ab. Dann 302: Der Handler leitete ohne
        // JSON-Content-Type auf `/` um, und dieser Aufruf schickt keinen mit. Seit
        // 000-000-0006 gibt es diese Umleitung nicht mehr — es gibt kein Zuhause, in das man
        // einen Browser schicken koennte, seit die Oberflaeche entfallen ist —, und der Code
        // der Ausnahme kommt durch.
        $this->assertSame(401, $status);
        $this->assertStringNotContainsString('"data"', $body);
    }

    public function testGeschuetzteRouteMitErfundenemTokenLiefertKeineDaten(): void
    {
        [$status] = $this->get('/api/schema', str_repeat('a', 128));

        $this->assertNotSame(200, $status);
    }

    public function testAbmeldenMachtDenTokenUnbrauchbar(): void
    {
        $token = $this->login();

        [$vorher] = $this->get('/api/schema', $token);
        $this->assertSame(200, $vorher, 'Vor dem Abmelden gilt der Token');

        $this->get('/auth/logout', $token);

        [$nachher] = $this->get('/api/schema', $token);
        $this->assertNotSame(200, $nachher, 'Nach dem Abmelden darf derselbe Token nicht mehr gelten');
    }

    // ── Passwort-Hashing (013-001-0001) ────────────────────────────────────────────────

    public function testEinAltesPasswortWirdBeimLoginUmgeschluesselt(): void
    {
        // `testbenutzer()` legt den Benutzer zwar mit einem SHA-256-Hash an, MELDET IHN ABER
        // GLEICH AN, um den Token zu liefern — und damit ist er schon umgeschluesselt, bevor
        // dieser Test etwas sieht. (Nebenbei heisst das: Jeder Test, der den Helfer benutzt,
        // laeuft ueber den Altformat-Zweig. Er ist also breit abgedeckt, nur nicht zugesichert.)
        //
        // Fuer die Zusicherung wird der alte Hash deshalb ausdruecklich wiederhergestellt.
        [, $userId] = $this->testbenutzer();
        $this->altenHashSetzen($userId);

        $vorher = $this->passHashLesen($userId);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $vorher,
            'Vorbedingung: der Hash liegt im alten SHA-256-Format');

        [$status] = $this->postJson('/auth/login', array(
            'alias' => $this->aliasZu($userId),
            'pass'  => self::TEST_PASSWORT,
        ));
        $this->assertSame(200, $status, 'Ein Bestandspasswort meldet sich weiterhin an');

        $nachher = $this->passHashLesen($userId);
        $this->assertStringStartsWith('$', $nachher,
            'und der Hash ist danach ersetzt — password_hash() beginnt mit $');
        $this->assertNotSame($vorher, $nachher);
    }

    public function testNachDemUmschluesselnGehtDieAnmeldungWeiterhin(): void
    {
        // Ein umgeschluesselter Hash muss beim naechsten Mal ueber den NEUEN Zweig geprueft
        // werden. Faende `isPass()` dort nicht zurecht, waere der Benutzer nach genau einem
        // erfolgreichen Login ausgesperrt — und der Test darueber waere trotzdem gruen.
        [, $userId] = $this->testbenutzer();
        $this->altenHashSetzen($userId);
        $alias = $this->aliasZu($userId);

        $this->postJson('/auth/login', array('alias' => $alias, 'pass' => self::TEST_PASSWORT));

        [$status, $body] = $this->postJson('/auth/login', array(
            'alias' => $alias,
            'pass'  => self::TEST_PASSWORT,
        ));

        $this->assertSame(200, $status);
        $this->assertNotEmpty($body['token'] ?? null);
    }

    public function testEinFalschesPasswortScheitertAuchNachDemUmschluesseln(): void
    {
        [, $userId] = $this->testbenutzer();
        $this->altenHashSetzen($userId);
        $alias = $this->aliasZu($userId);

        $this->postJson('/auth/login', array('alias' => $alias, 'pass' => self::TEST_PASSWORT));

        [$status] = $this->postJson('/auth/login', array('alias' => $alias, 'pass' => 'falsch'));

        $this->assertSame(401, $status);
    }

    /** Schreibt den alten SHA-256-Hash zurueck, wie ihn ein Bestandsprojekt traegt. */
    private function altenHashSetzen(string $userId): void
    {
        $s = $this->pdo()->prepare('SELECT salt FROM pim_user WHERE id = :id');
        $s->execute(array('id' => $userId));
        $salt = (string) $s->fetchColumn();

        $this->pdo()->prepare('UPDATE pim_user SET pass = :pass WHERE id = :id')->execute(array(
            'pass' => hash('sha256', self::TEST_PASSWORT.$salt),
            'id'   => $userId,
        ));
    }

    private function passHashLesen(string $userId): string
    {
        $s = $this->pdo()->prepare('SELECT pass FROM pim_user WHERE id = :id');
        $s->execute(array('id' => $userId));

        return (string) $s->fetchColumn();
    }

    private function aliasZu(string $userId): string
    {
        $s = $this->pdo()->prepare('SELECT alias FROM pim_user WHERE id = :id');
        $s->execute(array('id' => $userId));

        return (string) $s->fetchColumn();
    }

    public function testJedeAnmeldungLiefertEinenNeuenToken(): void
    {
        $this->assertNotSame($this->login(), $this->login(), 'Tokens werden pro Anmeldung erzeugt, nicht wiederverwendet');
    }

    // ── Regressionsschutz für 012-004 ──────────────────────────────────────────────────

    /**
     * Der eigentliche Zweck dieser Suite: Nach dem Entfernen der sessionbasierten Anmeldung
     * darf **kein** Request mehr eine PHP-Session starten.
     *
     * Solange eine Session lief, hielt PHP einen exklusiven Lock auf ihrer Datei bis zum
     * Skriptende — und weil alle Tabs eines Nutzers dieselbe PHPSESSID teilen, liefen dessen
     * gleichzeitige API-Aufrufe nacheinander statt parallel. Kehrt die Session zurück, kehrt
     * das Verhalten zurück, und niemand würde es bemerken.
     */
    public function testKeinRequestStartetEinePhpSession(): void
    {
        $pfade = array(
            array('POST', '/auth/login'),
            array('GET',  '/api/schema'),
            array('GET',  '/file/get/00000000-0000-0000-0000-000000000000'),
        );

        foreach ($pfade as [$methode, $pfad]) {
            $kopf = $methode === 'POST'
                ? $this->postJson($pfad, array('alias' => 'admin', 'pass' => $this->pass()))[2]
                : $this->get($pfad, null)[2];

            $this->assertStringNotContainsStringIgnoringCase(
                'PHPSESSID',
                $kopf,
                $methode.' '.$pfad.' darf kein Session-Cookie setzen'
            );
        }
    }

    // ── Der Token steht nur noch gehasht in der Tabelle (013-001-0004) ─────────────────

    /**
     * Der Nachweis, um den es in `013-001-0004` geht.
     *
     * Vorher lagen in `pim_token.token` 128 Hex im Klartext. Ein Lesezugriff auf die Datenbank
     * — ein Backup, eine SQL-Injection, ein Dump im Ticketsystem — uebergab damit saemtliche
     * laufenden Sitzungen, sofort verwendbar.
     */
    public function testDerAusgelieferteTokenStehtNichtInDerTabelle(): void
    {
        $token = $this->login();

        $zaehlen = $this->pdo()->prepare('SELECT COUNT(*) FROM pim_token WHERE token = :t');

        $zaehlen->execute(array('t' => $token));
        $this->assertSame('0', (string) $zaehlen->fetchColumn(), 'Der Klartext steht nirgends');

        $zaehlen->execute(array('t' => hash('sha256', $token)));
        $this->assertSame('1', (string) $zaehlen->fetchColumn(), 'sein SHA-256 genau einmal');
    }

    /**
     * Und die andere Haelfte: Die Anmeldung funktioniert unveraendert.
     *
     * Ein gehashter Token, mit dem sich niemand mehr anmelden kann, waere kein Fortschritt.
     */
    public function testDerAusgelieferteTokenFunktioniertWeiterhin(): void
    {
        $token = $this->login();

        [$status] = $this->get('/api/schema', $token);
        $this->assertSame(200, $status);

        [$abmelden] = $this->get('/auth/logout', $token);
        $this->assertSame(200, $abmelden);

        [$danach] = $this->get('/api/schema', $token);
        $this->assertNotSame(200, $danach, 'Nach dem Abmelden ist er weg');
    }

    /**
     * Der Hash selbst ist kein Token.
     *
     * Wer ihn aus der Tabelle oder aus `listTokens` abschreibt und vorzeigt, kommt nicht durch:
     * Er wuerde beim Pruefen ein zweites Mal gehasht.
     */
    public function testDerHashLaesstSichNichtAlsTokenVorzeigen(): void
    {
        $token = $this->login();

        [$status] = $this->get('/api/schema', hash('sha256', $token));

        $this->assertNotSame(200, $status);
    }

    // ── Die entfallenen Routen unter /api (013-001-0005) ──────────────────────────────

    /**
     * `POST /api/login` und `POST /api/logout` gibt es nicht.
     *
     * Sie waren registriert und zeigten auf `api.controller:loginAction` und `:logoutAction` —
     * Methoden, die es im `ApiController` nicht gibt und nie gab. Erreicht haben sie den Router
     * trotzdem nie: `RouteCollector` zählt je Provider durch, `/api/login` hiess `login_0` und
     * wurde beim Mounten von `/auth/login` gleichen Namens verdrängt.
     *
     * Beides ist mit `013-001-0005` behoben — die Namen tragen jetzt den Mountpunkt, und die
     * beiden toten Routen sind entfernt statt umgebogen. Geprüft wird, dass sie sich verhalten
     * wie jeder andere unbekannte Pfad: dieselbe Antwort, kein Sonderfall.
     */
    public function testDieRoutenUnterApiGibtEsNicht(): void
    {
        [$unbekannt] = $this->postJson('/api/gibtsnicht-'.bin2hex(random_bytes(4)), array());

        foreach (array('/api/login', '/api/logout') as $pfad) {
            [$status] = $this->postJson($pfad, array('alias' => 'admin', 'pass' => $this->pass()));

            $this->assertSame($unbekannt, $status, $pfad.' antwortet wie jeder unbekannte Pfad');
        }
    }

    /**
     * Und die Gegenprobe: Die Routen unter `/auth` funktionieren weiterhin.
     *
     * Sie sind es, die den Namensvetter verdrängt haben — an ihnen musste sich beim Aufräumen
     * nichts ändern.
     */
    public function testDieRoutenUnterAuthFunktionierenWeiterhin(): void
    {
        [$status, $body] = $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => $this->pass()));
        $this->assertSame(200, $status);

        [$abmelden] = $this->get('/auth/logout', $body['token']);
        $this->assertSame(200, $abmelden);
    }

    // ── Alle fünf Tokenquellen am laufenden System (013-002-0004) ─────────────────────

    /**
     * Der Nachweis für den Umstieg: Jede Quelle öffnet eine geschützte Route.
     *
     * Vier davon sind geerbt — `BaseControllerProvider::checkToken()` las sie, und Bestandsclients
     * schicken sie. Ohne sie bräche jeder bestehende Ionic-Client beim Update. Die fünfte,
     * `Authorization: Bearer`, ist neu und der Weg, auf den alles zuläuft.
     *
     * `TokenquellenTest` misst dasselbe ohne HTTP; hier geht es darum, dass es **verdrahtet**
     * ist.
     */
    public function testJedeTokenquelleOeffnetEineGeschuetzteRoute(): void
    {
        $token = $this->login();

        $ueberKopfzeile = array(
            'Authorization: Bearer' => 'Authorization: Bearer '.$token,
            'appcms-token'          => 'appcms-token: '.$token,
            'X-XSRF-TOKEN'          => 'X-XSRF-TOKEN: '.$token,
        );

        foreach ($ueberKopfzeile as $name => $kopfzeile) {
            $this->assertSame(200, $this->getMitKopfzeile('/api/schema', $kopfzeile), $name);
        }

        $this->assertSame(200, $this->getMitKopfzeile('/api/schema?_token='.$token, null),
            '_token im Query-String');

        [$status] = $this->postJson('/api/count', array('entity' => 'PIM\User', '_token' => $token));
        $this->assertSame(200, $status, '_token im Rumpf');
    }

    public function testOhneTokenBleibtDieGeschuetzteRouteZu(): void
    {
        $this->assertNotSame(200, $this->getMitKopfzeile('/api/schema', null));
    }

    /**
     * Ein Token, den es nicht gibt, öffnet nichts — über jede Quelle.
     */
    public function testEinErfundenerTokenOeffnetKeineQuelle(): void
    {
        $erfunden = bin2hex(random_bytes(64));

        $this->assertNotSame(200, $this->getMitKopfzeile('/api/schema', 'Authorization: Bearer '.$erfunden));
        $this->assertNotSame(200, $this->getMitKopfzeile('/api/schema', 'appcms-token: '.$erfunden));
        $this->assertNotSame(200, $this->getMitKopfzeile('/api/schema?_token='.$erfunden, null));
    }

    /** Ein GET mit genau einer selbst gewählten Kopfzeile — die Basisklasse schickt immer `appcms-token`. */
    private function getMitKopfzeile(string $pfad, ?string $kopfzeile): int
    {
        $ch = curl_init(getenv('CONTENTFLY_TEST_BASE_URL').$pfad);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $kopfzeile === null ? array() : array($kopfzeile),
        ));
        curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $status;
    }

    // ── Ausstellung von JWT (013-003-0001) ────────────────────────────────────────────

    /**
     * **Ohne Anforderung ändert sich nichts.**
     *
     * Die Zusage dieser Story: Ein Bestandsclient merkt nichts. Deshalb entscheidet der
     * Aufrufer je Anfrage und nicht ein Konfigurationsschalter, der die Antwort für alle auf
     * einmal kippen würde.
     */
    public function testOhneAnforderungBleibtEsBeimOpaquenToken(): void
    {
        [, $body] = $this->postJson('/auth/login', array('alias' => 'admin', 'pass' => $this->pass()));

        $this->assertMatchesRegularExpression('/^[0-9a-f]{128}$/', $body['token'] ?? '');
        $this->assertArrayNotHasKey('refreshToken', $body);
        $this->assertArrayNotHasKey('expiresIn', $body);
    }

    public function testAufAnforderungLiefertDerLoginEinJwtUndEinRefreshToken(): void
    {
        $body = $this->jwtAnmeldung();

        $this->assertCount(3, explode('.', $body['token']), 'Drei punktgetrennte Segmente');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{128}$/', $body['refreshToken'] ?? '',
            'Das Refresh-Token ist ein gewoehnlicher opaquer Token');
        $this->assertGreaterThan(0, $body['expiresIn'] ?? 0);
        $this->assertLessThanOrEqual(900, $body['expiresIn']);
    }

    public function testDasAusgestellteJwtOeffnetEineGeschuetzteRoute(): void
    {
        $body = $this->jwtAnmeldung();

        $this->assertSame(200, $this->getMitKopfzeile('/api/schema', 'Authorization: Bearer '.$body['token']));
        $this->assertSame(200, $this->getMitKopfzeile('/api/schema', 'appcms-token: '.$body['token']),
            'Auch ueber die Altquellen — die Verzweigung entscheidet nach der Form, nicht nach der Quelle');
    }

    /**
     * **Ein Refresh-Token ist kein Zugangstoken.**
     *
     * Es ist eine gewöhnliche Zeile in `pim_token`, und der opaque Zweig nahm bis `013-003-0001`
     * jede Zeile an. Ein Refresh-Token gilt länger als ein Access-JWT — das ist sein Zweck —,
     * und ohne diese Trennung wäre es ein langlebiger Generalschlüssel für die ganze API.
     */
    public function testDasRefreshTokenOeffnetKeineGeschuetzteRoute(): void
    {
        $body = $this->jwtAnmeldung();

        $this->assertNotSame(200, $this->getMitKopfzeile('/api/schema', 'appcms-token: '.$body['refreshToken']));
        $this->assertNotSame(200, $this->getMitKopfzeile('/api/schema', 'Authorization: Bearer '.$body['refreshToken']));
    }

    /**
     * Der Claim-Satz am ausgelieferten Token — gelesen, nicht angenommen.
     *
     * Rollen, Gruppen und Berechtigungen können sich ändern, während der Token gilt. Stünden sie
     * darin, wirkte eine Rechteänderung erst nach dessen Ablauf.
     */
    public function testDasAusgestellteJwtTraegtNurDenFestgelegtenClaimSatz(): void
    {
        $body   = $this->jwtAnmeldung();
        $claims = json_decode(base64_decode(strtr(explode('.', $body['token'])[1], '-_', '+/')), true);

        $namen = array_keys($claims);
        sort($namen);

        $this->assertSame(array('exp', 'iat', 'iss', 'jti', 'sub'), $namen);
        $this->assertSame('admin', $claims['sub']);
        $this->assertSame('contentfly', $claims['iss']);
    }

    /** Meldet sich mit `tokenType: jwt` an und liefert den Antwortrumpf. */
    private function jwtAnmeldung(): array
    {
        [$status, $body] = $this->postJson('/auth/login', array(
            'alias'     => 'admin',
            'pass'      => $this->pass(),
            'tokenType' => 'jwt',
        ));

        if ($status !== 200 || !isset($body['token'], $body['refreshToken'])) {
            $this->fail('JWT-Anmeldung fehlgeschlagen: '.json_encode($body));
        }

        return $body;
    }

    // ── Der Refresh-Weg (013-003-0002) ────────────────────────────────────────────────

    public function testEinRefreshTokenLiefertEinFrischesAccessJwt(): void
    {
        $anmeldung = $this->jwtAnmeldung();

        [$status, $body] = $this->postJson('/auth/refresh', array('refreshToken' => $anmeldung['refreshToken']));

        $this->assertSame(200, $status);
        $this->assertCount(3, explode('.', $body['token'] ?? ''));
        $this->assertNotSame($anmeldung['token'], $body['token'], 'Ein frisches Token, nicht dasselbe');
        $this->assertSame(200, $this->getMitKopfzeile('/api/schema', 'Authorization: Bearer '.$body['token']));
    }

    /**
     * **Rotation: Das vorgezeigte Refresh-Token gilt danach nicht mehr.**
     *
     * Ein Refresh-Token, das mehrfach gilt, ist ein langlebiges Geheimnis — wer es abgreift,
     * holt sich damit beliebig lange frische Zugangstokens, und niemand sieht es. Wird es bei
     * jedem Gebrauch getauscht, fällt ein zweiter Gebrauch auf.
     */
    public function testDasVorgezeigteRefreshTokenWirdErsetzt(): void
    {
        $anmeldung = $this->jwtAnmeldung();

        [, $erstes] = $this->postJson('/auth/refresh', array('refreshToken' => $anmeldung['refreshToken']));
        $this->assertNotSame($anmeldung['refreshToken'], $erstes['refreshToken'] ?? null, 'Ein neues Refresh-Token');

        [$zweiter] = $this->postJson('/auth/refresh', array('refreshToken' => $anmeldung['refreshToken']));
        $this->assertSame(401, $zweiter, 'Das alte gilt nicht mehr');

        [$mitNeuem] = $this->postJson('/auth/refresh', array('refreshToken' => $erstes['refreshToken']));
        $this->assertSame(200, $mitNeuem, 'Das neue schon');
    }

    /**
     * Ein Access-JWT taugt nicht als Refresh-Token — die Gegenrichtung zu
     * `testDasRefreshTokenOeffnetKeineGeschuetzteRoute`.
     */
    public function testEinAccessJwtTaugtNichtAlsRefreshToken(): void
    {
        $anmeldung = $this->jwtAnmeldung();

        [$status] = $this->postJson('/auth/refresh', array('refreshToken' => $anmeldung['token']));

        $this->assertSame(401, $status);
    }

    /**
     * Ein opaques Anmeldetoken auch nicht: Es ist eine `pim_token`-Zeile ohne `purpose`.
     */
    public function testEinOpaquesAnmeldetokenTaugtNichtAlsRefreshToken(): void
    {
        $opaque = $this->login();

        [$status] = $this->postJson('/auth/refresh', array('refreshToken' => $opaque));

        $this->assertSame(401, $status);
    }

    public function testEinUnbekanntesRefreshTokenWirdAbgewiesen(): void
    {
        [$status, $body] = $this->postJson('/auth/refresh', array('refreshToken' => bin2hex(random_bytes(64))));

        $this->assertSame(401, $status);
        $this->assertArrayNotHasKey('token', $body);
    }

    public function testOhneRefreshTokenWirdAbgewiesen(): void
    {
        [$status] = $this->postJson('/auth/refresh', array());

        $this->assertSame(401, $status);
    }

    /**
     * Alle Fehlschläge sehen gleich aus.
     *
     * Wer hier unterscheidet, sagt einem Angreifer, welcher seiner Versuche näher dran war.
     */
    public function testJederFehlschlagAmRefreshSiehtGleichAus(): void
    {
        $anmeldung = $this->jwtAnmeldung();

        [, $unbekannt] = $this->postJson('/auth/refresh', array('refreshToken' => bin2hex(random_bytes(64))));
        [, $falscheArt] = $this->postJson('/auth/refresh', array('refreshToken' => $anmeldung['token']));
        [, $ohne]      = $this->postJson('/auth/refresh', array());

        $this->assertSame($unbekannt['message'], $falscheArt['message']);
        $this->assertSame($unbekannt['message'], $ohne['message']);
    }

    /**
     * Ein gesperrter Benutzer bekommt kein neues Access-JWT.
     *
     * Das ist der Fall, den das Refresh-Modell tragen muss: Der Zugang endet spätestens mit dem
     * laufenden Access-Token, weil danach niemand mehr ein neues bekommt.
     */
    public function testEinGesperrterBenutzerBekommtKeinNeuesAccessJwt(): void
    {
        [, $userId] = $this->testbenutzer();

        [$status, $anmeldung] = $this->postJson('/auth/login', array(
            'alias'     => $this->aliasZu($userId),
            'pass'      => self::TEST_PASSWORT,
            'tokenType' => 'jwt',
        ));
        $this->assertSame(200, $status);

        $sperren = $this->pdo()->prepare('UPDATE pim_user SET isActive = 0 WHERE id = :id');
        $sperren->execute(array('id' => $userId));

        [$nachSperrung] = $this->postJson('/auth/refresh', array('refreshToken' => $anmeldung['refreshToken']));

        $this->assertSame(401, $nachSperrung);
    }

    // ── Widerruf (013-003-0003) ───────────────────────────────────────────────────────

    /**
     * **Der Kern der Story.** Nach dem Abmelden gilt das Access-JWT nicht mehr — obwohl sein
     * `exp` noch in der Zukunft liegt.
     *
     * Ohne die Sperrliste wäre das nicht so: Ein zustandsloses Token lässt sich nicht
     * zurückrufen, solange es gilt. Bei einem Token, das jemand abgegriffen hat, ist genau das
     * der Schaden.
     */
    public function testNachDemAbmeldenGiltDasAccessJwtNichtMehr(): void
    {
        $anmeldung = $this->jwtAnmeldung();
        $jwt       = $anmeldung['token'];

        $this->assertSame(200, $this->getMitKopfzeile('/api/schema', 'Authorization: Bearer '.$jwt));
        $this->assertGreaterThan(0, $anmeldung['expiresIn'], 'Das Token gilt noch');

        [$abmelden] = $this->get('/auth/logout', $jwt);
        $this->assertSame(200, $abmelden);

        $this->assertNotSame(200, $this->getMitKopfzeile('/api/schema', 'Authorization: Bearer '.$jwt),
            'Dasselbe, noch nicht abgelaufene Token oeffnet nichts mehr');

        $this->sperrlisteAufraeumenLassen();
    }

    /**
     * Das mitgeschickte Refresh-Token wird entzogen.
     *
     * Sonst holt sich der Inhaber gleich ein neues Access-JWT, und die Sperre war umsonst. Der
     * Client muss es mitschicken, weil das Access-JWT nicht sagt, zu welcher Refresh-Zeile es
     * gehört — die Verbindung stünde sonst als sechster Claim darin, und der Claim-Satz ist
     * absichtlich klein.
     */
    public function testDasAbmeldenEntziehtDasMitgeschickteRefreshToken(): void
    {
        $anmeldung = $this->jwtAnmeldung();

        [$abmelden] = $this->get('/auth/logout?refreshToken='.$anmeldung['refreshToken'], $anmeldung['token']);
        $this->assertSame(200, $abmelden);

        [$status] = $this->postJson('/auth/refresh', array('refreshToken' => $anmeldung['refreshToken']));
        $this->assertSame(401, $status, 'Das Refresh-Token ist weg');

        $this->sperrlisteAufraeumenLassen();
    }

    /**
     * Ohne mitgeschicktes Refresh-Token bleibt es stehen — und das ist die dokumentierte Lage,
     * kein Versehen.
     */
    public function testOhneMitgeschicktesRefreshTokenBleibtEsBestehen(): void
    {
        $anmeldung = $this->jwtAnmeldung();

        $this->get('/auth/logout', $anmeldung['token']);

        [$status] = $this->postJson('/auth/refresh', array('refreshToken' => $anmeldung['refreshToken']));
        $this->assertSame(200, $status, 'Es verfaellt ueber sein eigenes Zeitlimit, nicht beim Abmelden');

        $this->sperrlisteAufraeumenLassen();
    }

    /**
     * **Nur das eigene.** Ohne diese Prüfung wäre `logout` ein Endpunkt, mit dem ein beliebiger
     * angemeldeter Benutzer fremde Sitzungen beenden könnte.
     */
    public function testEinFremdesRefreshTokenLaesstSichNichtAbmelden(): void
    {
        [, $userId] = $this->testbenutzer();

        [, $fremd] = $this->postJson('/auth/login', array(
            'alias'     => $this->aliasZu($userId),
            'pass'      => self::TEST_PASSWORT,
            'tokenType' => 'jwt',
        ));

        $eigene = $this->jwtAnmeldung();

        $this->get('/auth/logout?refreshToken='.$fremd['refreshToken'], $eigene['token']);

        [$status] = $this->postJson('/auth/refresh', array('refreshToken' => $fremd['refreshToken']));
        $this->assertSame(200, $status, 'Das fremde Refresh-Token gilt weiterhin');

        $this->sperrlisteAufraeumenLassen();
    }

    /**
     * **Eine Benutzersperrung wirkt schon ohne Sperrliste sofort — gemessen.**
     *
     * Der Story-Text nannte die Sperrung als Anwendungsfall der Liste. Sie ist es seit
     * `013-002-0001` nicht mehr: Der JWT-Zweig gibt sein `UserBadge` ohne eigenen Lader zurück,
     * also lädt der `Benutzerlader` den Benutzer aus `pim_user` und weist einen gesperrten mit
     * derselben Ausnahme ab wie einen unbekannten.
     *
     * Der Test steht hier, damit die Zusicherung nicht unbelegt dasteht — und damit auffällt,
     * wenn jemand den Ladeweg umbaut und dabei die Sperrung mit abschaltet.
     */
    public function testEineBenutzersperrungWirktSofortUndOhneSperrliste(): void
    {
        [, $userId] = $this->testbenutzer();

        [, $anmeldung] = $this->postJson('/auth/login', array(
            'alias'     => $this->aliasZu($userId),
            'pass'      => self::TEST_PASSWORT,
            'tokenType' => 'jwt',
        ));

        $this->assertSame(200, $this->getMitKopfzeile('/api/schema', 'Authorization: Bearer '.$anmeldung['token']));

        $sperren = $this->pdo()->prepare('UPDATE pim_user SET isActive = 0 WHERE id = :id');
        $sperren->execute(array('id' => $userId));

        $this->assertSame(0, (int) $this->pdo()->query('SELECT COUNT(*) FROM pim_revoked_token')->fetchColumn(),
            'Kein Eintrag in der Sperrliste — die Sperrung wirkt ohne sie');
        $this->assertNotSame(200, $this->getMitKopfzeile('/api/schema', 'Authorization: Bearer '.$anmeldung['token']));
    }

    /**
     * Die Sperrliste bleibt klein: Ein Eintrag verfällt mit dem Token, das er sperrt, und
     * `appcms:token:cleanup` räumt ihn weg — kein zweiter Aufräumweg.
     */
    public function testGegenstandsloseSperrEintraegeWerdenAufgeraeumt(): void
    {
        $jti = 'test-'.bin2hex(random_bytes(8));

        $this->pdo()->prepare(
            'INSERT INTO pim_revoked_token (jti, expiresAt, created) VALUES (:j, :e, :c)'
        )->execute(array(
            'j' => $jti,
            'e' => (new \DateTime('-1 hour'))->format('Y-m-d H:i:s'),
            'c' => (new \DateTime('-2 hours'))->format('Y-m-d H:i:s'),
        ));

        $this->sperrlisteAufraeumenLassen();

        $zaehlen = $this->pdo()->prepare('SELECT COUNT(*) FROM pim_revoked_token WHERE jti = :j');
        $zaehlen->execute(array('j' => $jti));

        $this->assertSame('0', (string) $zaehlen->fetchColumn());
    }

    /**
     * Räumt die Sperrliste nach einem Test wieder leer.
     *
     * Die Einträge sind Reste eines Abmeldens und stören nachfolgende Tests nicht — aber
     * `testEineBenutzersperrungWirktSofortUndOhneSperrliste` zählt sie, und eine leere Liste ist
     * die einzige Aussage, die dieser Test treffen kann.
     */
    private function sperrlisteAufraeumenLassen(): void
    {
        $this->pdo()->exec('DELETE FROM pim_revoked_token WHERE expiresAt < NOW()');

        exec(sprintf(
            '%s %s appcms:token:cleanup 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(self::konsole())
        ));

        $this->pdo()->exec('DELETE FROM pim_revoked_token');
    }
}
