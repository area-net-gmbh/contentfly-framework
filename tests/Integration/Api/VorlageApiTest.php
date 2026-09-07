<?php
namespace Tests\Integration\Api;

use Tests\Integration\IntegrationTestCase;

/**
 * Charakterisierungstests für die Vorlage `custom/` — das Projekt, das dieses Framework
 * mitliefert.
 *
 * Epic `008` verlangt, dass auch „ein Projekt auf Basis dieses Frameworks funktioniert"
 * abgesichert ist. `custom/` **ist** dieses Projekt: die Vorlage, an der sich jedes neue und
 * jedes migrierende Projekt orientiert (Epic `007`). Bricht sie beim Kernel-Umbau, bricht sie
 * für alle.
 *
 * Geprüft ist die Naht zwischen Framework und Projekt an vier Stellen: die Beispiel-Entity
 * über die generischen Endpunkte, der Beispiel-Endpunkt aus `custom/app.php`, die Middleware
 * und der ausdrücklich *nicht* registrierte Console-Command.
 *
 * **Zwei Befunde betreffen die Vorlage selbst** und sind als `000-000-0017` notiert: Das Feld
 * `jsonExample` der Beispiel-Entity existiert für die API überhaupt nicht, und die einzige
 * verbliebene `@PIM`-Annotation der Vorlage — `@PIM\Select` — validiert nichts. Eine Vorlage,
 * die ein unbenutzbares Feld und eine wirkungslose Annotation vorführt, lehrt das Falsche.
 *
 * Ergänzt `RouteSecurityApiTest`, das den Beispiel-Endpunkt bereits von der Sicherheitsseite
 * abdeckt (ungesicherte Route, eigener Envelope, `Referrer-Policy`). Hier kommt der
 * inhaltliche Teil dazu.
 */
class VorlageApiTest extends IntegrationTestCase
{
    private const ENTITY = 'Core\\Example';

    /** @var array<int,string> Ids, deren Protokollzeilen tearDown() entfernt. */
    private array $protokollierteIds = array();

    /**
     * Entfernt die Protokollzeilen der angelegten Datensätze und übergibt dann an die Basis.
     *
     * Eigene Aufräumung, weil `nachTestLoeschen()` eine Zeile über ihre **eigene** Id
     * anmeldet — der Logeintrag steht aber unter `model_id`, und die entscheidende Zeile
     * entsteht erst **nach** der Anmeldung: `/api/delete` protokolliert seinerseits. Ohne
     * das wüchse `pim_log` mit jedem Lauf um eine Zeile; genau die Sorte Rest, gegen die
     * `000-000-0008` geschrieben wurde.
     */
    protected function tearDown(): void
    {
        foreach ($this->protokollierteIds as $id) {
            $this->pdo()->prepare('DELETE FROM pim_log WHERE model_id = :id')->execute(array('id' => $id));
        }

        $this->protokollierteIds = array();

        parent::tearDown();
    }

    /**
     * Legt einen Beispiel-Datensatz über `/api/insert` an und meldet Zeile und
     * Protokollzeilen zum Aufräumen.
     *
     * @return array{0:int,1:array} Status, Rumpf
     */
    private function beispielAnlegen(array $daten): array
    {
        [$status, $body] = $this->postJson(
            '/api/insert',
            array('entity' => self::ENTITY, 'data' => $daten),
            $this->token()
        );

        if (isset($body['id'])) {
            $this->nachTestLoeschen('example_entity', $body['id']);
            $this->protokollierteIds[] = $body['id'];
        }

        return array($status, $body);
    }

    private function schema(): array
    {
        [$status, $roh] = $this->get('/api/schema', $this->token());
        $this->assertSame(200, $status, 'Vorbedingung: das Schema ist abrufbar');

        return json_decode($roh, true);
    }

    // ── A: Die Beispiel-Entity im Schema ───────────────────────────────────────────────

    public function testDieBeispielEntityErscheintImSchemaUnterIhremUnterverzeichnis(): void
    {
        // Zugleich der Nachweis, dass die Unterverzeichnis-Struktur custom/Entity/Core/
        // traegt — genau der Punkt, an dem Api::getAll() bis 000-000-0007 gescheitert ist.
        // Der Kurzname setzt sich aus Verzeichnis und Klasse zusammen, nicht aus dem vollen
        // Namensraum: "Core\Example", nicht "Custom\Entity\Core\Example".
        $schema = $this->schema();

        $this->assertArrayHasKey(self::ENTITY, $schema['data']);
        $this->assertArrayHasKey(self::ENTITY, $schema['permissions'],
            'Auch im permissions-Block — eine Projekt-Entity wird nicht anders behandelt');

        $this->assertSame('example_entity', $schema['data'][self::ENTITY]['settings']['dbname']);
    }

    public function testDieSelectOptionenDerVorlageStehenImSchema(): void
    {
        // @PIM\Select(options="provisioning,active,…") wird zu einer Liste aus id/name-Paaren.
        // Das ist die einzige verbliebene @PIM-Annotation der Vorlage nach 012-005 — was sie
        // leistet, gehoert deshalb genau festgehalten. Was sie NICHT leistet, steht weiter
        // unten.
        $state = $this->schema()['data'][self::ENTITY]['properties']['state'];

        $this->assertSame('select', $state['type']);
        $this->assertSame(
            array('provisioning', 'active', 'trial_expired', 'suspended', 'deactivated'),
            array_column($state['options'], 'id')
        );
        $this->assertSame(array_column($state['options'], 'id'), array_column($state['options'], 'name'),
            'id und name sind derselbe Wert — die Optionen tragen keine Beschriftung');
        $this->assertSame('active', $state['default'], 'Der Standardwert kommt aus der ORM-Spalte');
    }

    public function testDasJsonFeldDerVorlageHatKeinenTypUndFehltDamitImSchema(): void
    {
        // Befund, notiert in 000-000-0017.
        //
        // custom/Entity/Core/Example.php fuehrt ein Feld mit @ORM\Column(type="json") vor.
        // Der TypeManager kennt aber keinen json-Typ — lib/contentfly/Classes/Types/ hat
        // zwanzig Typen, keiner davon deckt ihn ab. Das Feld faellt deshalb still aus dem
        // Schema heraus: kein Eintrag, keine Warnung, kein Hinweis.
        //
        // Die Spalte existiert in der Datenbank, das Feld existiert in der Entity — fuer die
        // API existiert es nicht. Wer sich an der Vorlage orientiert und ein json-Feld baut,
        // laeuft in dieselbe Wand.
        $properties = $this->schema()['data'][self::ENTITY]['properties'];

        $this->assertArrayNotHasKey('jsonExample', $properties,
            'Das Feld fehlt im Schema vollstaendig — nicht einmal als leerer Eintrag');
        $this->assertArrayHasKey('boolExample', $properties,
            'Waehrend die uebrigen Felder derselben Entity da sind');

        $spalten = $this->pdo()->query('SHOW COLUMNS FROM example_entity')->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertContains('jsonExample', $spalten, 'und die Spalte sehr wohl existiert');
    }

    // ── A: Die Beispiel-Entity über die generischen Endpunkte ──────────────────────────

    public function testDieBeispielEntityIstUeberDieGenerischenEndpunkteBenutzbar(): void
    {
        // Der eigentliche Vertrag der Vorlage: Eine Projekt-Entity braucht keinen eigenen
        // Controller und keine Sonderbehandlung. Anlegen, lesen, listen, loeschen — alles
        // ueber dieselben Endpunkte wie die Framework-Entities.
        [$status, $angelegt] = $this->beispielAnlegen(array(
            'name'        => 'Vorlagenprobe',
            'slug'        => 'vorlagenprobe-'.bin2hex(random_bytes(4)),
            'state'       => 'suspended',
            'boolExample' => true,
        ));

        $this->assertSame(200, $status, 'anlegen');
        $this->assertNotEmpty($angelegt['id']);

        [$statusSingle, $einzeln] = $this->postJson(
            '/api/single',
            array('entity' => self::ENTITY, 'id' => $angelegt['id']),
            $this->token()
        );
        $this->assertSame(200, $statusSingle, 'lesen');
        $this->assertSame('Vorlagenprobe', $einzeln['data']['name']);
        $this->assertSame('suspended', $einzeln['data']['state']);
        $this->assertTrue($einzeln['data']['boolExample']);

        [$statusList, $liste] = $this->postJson(
            '/api/list',
            array('entity' => self::ENTITY),
            $this->token()
        );
        $this->assertSame(200, $statusList, 'listen');
        $this->assertContains($angelegt['id'], array_column($liste['data'], 'id'));

        [$statusDelete] = $this->postJson(
            '/api/delete',
            array('entity' => self::ENTITY, 'id' => $angelegt['id']),
            $this->token()
        );
        $this->assertSame(200, $statusDelete, 'loeschen');

        $rest = $this->pdo()->prepare('SELECT COUNT(*) FROM example_entity WHERE id = :id');
        $rest->execute(array('id' => $angelegt['id']));
        $this->assertSame('0', (string) $rest->fetchColumn(), 'Die Zeile ist wirklich weg');
    }

    public function testEineProjektEntityWirdWieJedeAndereProtokolliert(): void
    {
        // Der Logeintrag traegt den Kurznamen mit Unterverzeichnis und Log::INSERTED — die
        // Projekt-Entity laeuft durch dieselbe Protokollierung wie PIM\Tag.
        [, $angelegt] = $this->beispielAnlegen(array('name' => 'Protokollprobe'));

        $log = $this->pdo()->prepare('SELECT mode, model_name FROM pim_log WHERE model_id = :id');
        $log->execute(array('id' => $angelegt['id']));
        $eintrag = $log->fetch(\PDO::FETCH_ASSOC);

        $this->assertSame('INS', $eintrag['mode']);
        $this->assertSame(self::ENTITY, $eintrag['model_name']);
    }

    public function testDasJsonFeldLaesstSichWederSchreibenNochLesen(): void
    {
        // Die Folge des Befunds oben, an der API beobachtet: Ein Schreibversuch scheitert mit
        // "unbekannte Eigenschaft", und beim Lesen fehlt das Feld in der Antwort. Fuer einen
        // Client der Vorlage ist jsonExample schlicht nicht vorhanden.
        //
        // Notiert in 000-000-0017.
        [$status] = $this->postJson(
            '/api/insert',
            array('entity' => self::ENTITY, 'data' => array('name' => 'Json', 'jsonExample' => array('a' => 1))),
            $this->token()
        );
        $this->assertSame(500, $status, 'ContentflyException: contentfly_general_unknown_property');

        $verirrt = $this->pdo()->query("SELECT COUNT(*) FROM example_entity WHERE name = 'Json'")->fetchColumn();
        $this->assertSame('0', (string) $verirrt, 'Der gescheiterte Insert hinterlaesst nichts');

        [, $angelegt] = $this->beispielAnlegen(array('name' => 'Ohne-Json'));

        [, $einzeln] = $this->postJson(
            '/api/single',
            array('entity' => self::ENTITY, 'id' => $angelegt['id']),
            $this->token()
        );

        $this->assertArrayNotHasKey('jsonExample', $einzeln['data'],
            'Auch beim Lesen taucht das Feld nicht auf');
        $this->assertArrayHasKey('boolExample', $einzeln['data'],
            'Vergleichsfeld mit einem bekannten Typ — das kommt an');
    }

    public function testDieSelectAnnotationPruefteNichtsWasSieAuflistet(): void
    {
        // Zweiter Befund, notiert in 000-000-0017.
        //
        // Die Optionen aus @PIM\Select stehen im Schema, aber niemand vergleicht einen
        // Schreibwert damit. "gibtsnicht" wird angenommen und landet in der Spalte. Die
        // Annotation ist reine Metadatenlieferung fuer einen Client — ihr einziger Konsument
        // war die geloeschte Oberflaeche.
        //
        // Dasselbe Muster wie bei canExport und getExtended (UnenforcedPermissionApiTest):
        // veroeffentlicht, aber nirgends durchgesetzt. Hier waere es besonders leicht zu
        // beheben, weil die erlaubten Werte direkt daneben stehen.
        $ungueltig = 'gibtsnicht-'.bin2hex(random_bytes(3));

        [$status, $angelegt] = $this->beispielAnlegen(array('name' => 'Select', 'state' => $ungueltig));

        $this->assertSame(200, $status, 'Der Schreibvorgang gelingt — trotz unbekannter Option');

        $gespeichert = $this->pdo()->prepare('SELECT state FROM example_entity WHERE id = :id');
        $gespeichert->execute(array('id' => $angelegt['id']));

        $this->assertSame($ungueltig, $gespeichert->fetchColumn(),
            'und der Wert steht unveraendert in der Spalte');
    }

    // ── B: Der Beispiel-Endpunkt ───────────────────────────────────────────────────────

    public function testDerBeispielEndpunktLiefertDenInhaltDerVorlage(): void
    {
        // Die Sicherheitsseite deckt RouteSecurityApiTest ab (ungesicherte Route, eigener
        // Envelope). Hier der Inhalt: Was ApiResponseService::success() aus den Argumenten
        // des Controllers macht.
        [$status, $body] = $this->postJson('/api/v1/example/bootstrap', array());

        $this->assertSame(200, $status);
        $this->assertTrue($body['success']);
        $this->assertSame(200, $body['status'], 'Der Statuscode steht zusaetzlich im Rumpf');

        $this->assertSame(
            array('message', 'key', 'parameters', 'translations'),
            array_keys($body['i18n']),
            'Der i18n-Block der Vorlage — Meldung und Uebersetzungsschluessel getrennt'
        );
        $this->assertSame('Configuration loaded successfully', $body['i18n']['message']);
        $this->assertSame('core.config.loaded', $body['i18n']['key']);

        $this->assertSame(array(), $body['data'], 'Der Beispiel-Controller liefert bewusst nichts');
        $this->assertNull($body['errors']);
        $this->assertNull($body['meta']);
    }

    public function testDerZeitstempelDerVorlageIstFeinerAlsDerDesFrameworks(): void
    {
        // Ein Unterschied, der beim Vereinheitlichen der Envelopes (000-000-0014) auffallen
        // wird: Die Vorlage liefert ISO 8601 mit Millisekunden und Zeitzone, das Framework
        // "Y-m-d H:i:s" ohne beides. Zwei Formate in einer Antwortkette.
        [, $vorlage] = $this->postJson('/api/v1/example/bootstrap', array());
        [, $framework] = $this->postJson('/api/list', array('entity' => 'PIM\\User'), $this->token());

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}\+\d{2}:\d{2}$/',
            $vorlage['timestamp'],
            'Vorlage: ISO 8601 mit Millisekunden'
        );
        $this->assertArrayNotHasKey('timestamp', $framework,
            'Framework: gar kein timestamp in dieser Antwort');
    }

    // ── C: Die Middleware ──────────────────────────────────────────────────────────────

    public function testDieHooksDerVorlageLaufenUeberhaupt(): void
    {
        // Der after-Hook ist die beobachtbare Haelfte: Er setzt Referrer-Policy, und der
        // Header kommt an. Damit steht fest, dass custom/app.php geladen und ausgefuehrt
        // wird — und weil beide Hooks in derselben Datei im selben Durchlauf registriert
        // werden, ist damit auch der before-Hook registriert.
        [$status, , $kopf] = $this->get('/api/config');

        $this->assertSame(200, $status);
        $this->assertSame('strict-origin-when-cross-origin', $this->kopfzeile($kopf, 'Referrer-Policy'),
            'Der after-Hook der Vorlage greift');
    }

    public function testDerBeforeHookDerVorlageHinterlaesstKeineBeobachtbareSpur(): void
    {
        // Die andere Haelfte, und ehrlich gesagt: Sie ist von aussen nicht pruefbar. Der
        // before-Hook der Vorlage tut genau eines —
        //
        //     $app['request.startedAt'] = microtime(true);
        //
        // — und niemand liest diesen Wert. Kein Endpunkt gibt ihn aus, kein Header traegt
        // ihn, keine Antwort haengt davon ab.
        //
        // Das ist **kein Mangel des Tests, sondern eine Aussage ueber die Vorlage**: Sie
        // fuehrt das before-Muster an einem Beispiel vor, das seine eigene Wirkung nicht
        // zeigt. Der after-Hook macht es besser — an ihm sieht man, was ein Hook bewirkt.
        //
        // Geprueft ist deshalb, was pruefbar ist: dass der Hook registriert wird, und dass
        // die Vorlage die Reihenfolge als bedeutsam beschreibt. Wer Epic 009 umsetzt, findet
        // hier, was der neue Kernel nachbilden muss.
        $vorlage = file_get_contents(ROOT_DIR.'/custom/app.php');

        $this->assertStringContainsString('$app->before(function (Request $request) use ($app) {', $vorlage,
            'Der before-Hook ist registriert');
        $this->assertStringContainsString("\$app['request.startedAt'] = microtime(true);", $vorlage,
            'und setzt einen Wert, den nichts liest');
        $this->assertStringContainsString('Die Reihenfolge der Registrierung ist die Ausführungsreihenfolge', $vorlage,
            'Die Vorlage beschreibt die Reihenfolge als bedeutsam — mit einem Hook nicht pruefbar');
    }

    // ── D: Was die Vorlage bewusst nicht kann ──────────────────────────────────────────

    public function testDerBeispielCommandIstAbsichtlichNichtRegistriert(): void
    {
        // Festgehalten, damit beim Kernel-Umbau nicht "repariert" wird, was bewusst so steht.
        //
        // custom/Command/ExampleCommand.php erbt von Symfony\…\Command und erwartet $app im
        // Konstruktor. Der ConsoleManager nimmt ausschliesslich CustomCommand-Nachfahren —
        // diese Basisklasse praefixt den Namen mit "custom:", damit Projekt-Commands nie mit
        // denen des Frameworks kollidieren. Der Beispiel-Command passt nicht auf diesen Weg
        // und ist in custom/app.php deshalb ausdruecklich NICHT registriert.
        $command = file_get_contents(ROOT_DIR.'/custom/Command/ExampleCommand.php');
        $this->assertStringContainsString('class ExampleCommand extends Command', $command,
            'Er erbt von Symfony\\…\\Command, nicht von CustomCommand');
        $this->assertStringContainsString("->setName('example:command:run')", $command,
            'und traegt deshalb auch keinen custom:-Praefix');

        $vorlage = file_get_contents(ROOT_DIR.'/custom/app.php');
        $this->assertStringContainsString('bewusst nicht registriert', $vorlage,
            'Die Vorlage vermerkt es selbst');
        $this->assertStringNotContainsString('addCommand(new \\Custom\\Command\\ExampleCommand', $vorlage,
            'und registriert ihn folgerichtig nicht');

        // Und die Gegenprobe an der Konsole selbst.
        $ausgabe = array();
        exec(sprintf('%s %s list 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg(ROOT_DIR.'/bin/console.php')), $ausgabe);
        $alles = implode("\n", $ausgabe);

        $this->assertStringContainsString('appcms:install', $alles, 'Vorbedingung: die Liste ist gekommen');
        $this->assertStringNotContainsString('example:command:run', $alles,
            'Der Beispiel-Command taucht nicht auf');
    }
}
