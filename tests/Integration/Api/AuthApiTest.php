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
     * trotzdem nie: `Routensammlung` zählt je Provider durch, `/api/login` hiess `login_0` und
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
}
