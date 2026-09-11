<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Der Wächter über die zugesicherten `$app[...]`-Schlüssel (007-003-0002).
 *
 * ## Warum es ihn gibt
 * Die Entscheidung vom 2026-09-11 sichert den `ArrayAccess`-Zugriff dauerhaft zu — und eine
 * Zusicherung, die niemand prüft, ist keine. Der teuerste Befund aus Epic `006` war von dieser
 * Art: eine Bedingung, auf die man sich verliess, ohne dass sie jemand nachsah.
 *
 * ## Er prüft beide Richtungen, und die zweite ist die wichtigere
 * - **Jeder zugesicherte Schlüssel ist da.** Fällt einer weg, ist das ein Bruch für jedes
 *   Bestandsprojekt.
 * - **Jeder registrierte Schlüssel ist eingeordnet** — zugesichert oder ausdrücklich intern.
 *   Ohne diese Richtung wüchse die Liste auseinander: Ein neuer Schlüssel käme dazu, niemand
 *   entschiede, ob er öffentlich ist, und ein Projekt benutzte ihn auf eigenes Risiko, ohne es
 *   zu wissen.
 *
 * ## Warum als Unterprozess
 * Der Container entsteht erst, wenn `bootstrap.php` gelaufen ist — und der definiert Konstanten,
 * liest die Konfiguration und baut die halbe Anwendung auf. Das im PHPUnit-Prozess zu tun
 * veränderte jeden danach laufenden Test. Der Unterprozess ist dieselbe Lösung, die
 * `SystemControllerApiTest` und `AuthApiTest` für ihre Console-Aufrufe benutzen.
 *
 * ## Warum eine Datenbank nötig ist
 * Drei der zugesicherten Schlüssel stehen in einem `if ($app['is_installed'])`. Ohne
 * Installation wären sie folgerichtig nicht da, und der Test könnte seine wichtigste Aussage
 * nicht treffen. Er erbt deshalb von `IntegrationTestCase` und überspringt sich sauber, wenn
 * keine Umgebung steht.
 */
class ContainerSchluesselTest extends IntegrationTestCase
{
    /**
     * Immer verfügbar — unabhängig davon, ob installiert ist und ob jemand angemeldet ist.
     *
     * @var array<int,string>
     */
    private const IMMER = array(
        'is_installed', 'debug', 'database', 'mailer', 'routeManager', 'consoleManager',
        'request_stack', 'dispatcher', 'auth.user', 'anmeldeanbieter',
    );

    /**
     * Erst wenn `appcms:install` gelaufen ist.
     *
     * Sie stehen in einem `if ($app['is_installed'])`, und das ist Absicht: `appcms:install`
     * muss selbst laufen können, bevor es eine Datenbank gibt.
     *
     * @var array<int,string>
     */
    private const ERST_INSTALLIERT = array('db', 'dbs', 'orm.em');

    /**
     * Erst nach der Anmeldung — vorher gibt es ihn **nicht**, nicht `null`.
     *
     * Gesetzt wird er in `BaseControllerProvider`, nachdem ein Request sich ausgewiesen hat.
     * In einem Console-Lauf gibt es ihn nie, und genau deshalb steht er nicht in `IMMER`.
     *
     * @var array<int,string>
     */
    private const ERST_ANGEMELDET = array('auth.token');

    /**
     * Interne Verdrahtung — existiert, ist aber nicht zugesichert.
     *
     * @var array<int,string>
     */
    private const INTERN = array(
        'dbs.options', 'auth', 'console', 'helper', 'loginbremse', 'anmeldetreiber',
        'benutzerbereitstellung', 'gruppenabbildung', 'tokenhandler', 'thumbnailSettings',
        'schema', 'typeManager', 'pluginManager',
        // Die Verdrahtung des HttpKernels aus Classes/Kernel/Application — sie gehoert der
        // Anwendung, nicht dem Projekt.
        'kernel', 'resolver', 'argument_resolver',
    );

    public function testJederZugesicherteSchluesselIstDa(): void
    {
        $vorhanden = $this->schluesselDesContainers();

        foreach (array_merge(self::IMMER, self::ERST_INSTALLIERT) as $schluessel) {
            $this->assertContains(
                $schluessel,
                $vorhanden,
                sprintf(
                    'Der zugesicherte Schluessel "%s" fehlt im Container. Das ist ein Bruch fuer '
                    .'jedes Bestandsprojekt — siehe an_project/docs/dev-guide.md.',
                    $schluessel
                )
            );
        }
    }

    /**
     * Und der Token gibt es **noch nicht** — das ist die Zusicherung, nicht ihr Gegenteil.
     *
     * Ein Projekt, das `$app['auth.token']` ausserhalb eines angemeldeten Requests liest,
     * bekommt eine Exception und keinen stillen `null`-Wert. Dass das so bleibt, gehört
     * festgehalten.
     */
    public function testDerTokenGibtEsErstNachDerAnmeldung(): void
    {
        $this->assertNotContains(
            'auth.token',
            $this->schluesselDesContainers(),
            'auth.token darf im frisch aufgebauten Container NICHT stehen — er wird erst gesetzt, '
            .'wenn ein Request sich ausgewiesen hat.'
        );
    }

    /**
     * Jeder registrierte Schlüssel ist eingeordnet.
     *
     * **Die wichtigere Richtung.** Ohne sie wüchse die Liste auseinander: Ein neuer Schlüssel
     * käme dazu, niemand entschiede, ob er öffentlich ist, und ein Projekt benutzte ihn auf
     * eigenes Risiko, ohne es zu wissen.
     */
    public function testJederRegistrierteSchluesselIstEingeordnet(): void
    {
        $eingeordnet = array_merge(
            self::IMMER,
            self::ERST_INSTALLIERT,
            self::ERST_ANGEMELDET,
            self::INTERN
        );

        $unbekannt = array_values(array_diff($this->registrierteSchluessel(), $eingeordnet));

        sort($unbekannt);

        $this->assertSame(array(), $unbekannt, implode("\n", array_merge(
            array(
                'Diese Schluessel registriert das Framework, ohne dass jemand entschieden hat,',
                'ob sie oeffentlich sind (007-003-0002):',
                '',
            ),
            $unbekannt,
            array(
                '',
                'Entweder in die Liste im dev-guide aufnehmen und hier unter IMMER bzw.',
                'ERST_INSTALLIERT eintragen — oder als INTERN kennzeichnen. Beides ist eine',
                'Entscheidung; keine zu treffen ist die einzige Variante, die schiefgeht.',
            )
        )));
    }

    /**
     * Und umgekehrt: Jeder eingeordnete Schlüssel wird auch wirklich registriert.
     *
     * **Die Regel, an der die Gates aus `006-005` hängen**, hier angewandt: Ein Eintrag, der
     * nichts mehr trifft, macht den Lauf rot. Ohne diese Richtung bliebe ein Schlüssel in der
     * Liste stehen, nachdem er aus dem Framework verschwunden ist — und ein Projekt verliesse
     * sich auf eine Zusicherung ins Leere.
     */
    public function testJederEingeordneteSchluesselWirdAuchRegistriert(): void
    {
        $registriert = $this->registrierteSchluessel();

        $tot = array();

        foreach (array(
            'IMMER'            => self::IMMER,
            'ERST_INSTALLIERT' => self::ERST_INSTALLIERT,
            'ERST_ANGEMELDET'  => self::ERST_ANGEMELDET,
            'INTERN'           => self::INTERN,
        ) as $liste => $schluessel) {
            foreach ($schluessel as $einer) {
                if (!in_array($einer, $registriert, true)) {
                    $tot[] = sprintf('%s: %s', $liste, $einer);
                }
            }
        }

        sort($tot);

        $this->assertSame(array(), $tot, implode("
", array_merge(
            array(
                'Diese Schluessel stehen in einer Liste, werden aber nirgends mehr registriert.',
                'Eine Zusicherung ins Leere ist schlimmer als keine (007-003-0002):',
                '',
            ),
            $tot
        )));
    }

    /**
     * Und die Listen hier stimmen mit dem `dev-guide` überein.
     *
     * Sie stehen an zwei Stellen, und zwei Stellen laufen auseinander. Dieselbe Regel wie bei
     * den Gates aus `006-005`: Ein Eintrag, der nichts mehr trifft, macht den Lauf rot.
     */
    public function testDieListenStimmenMitDemDevGuideUeberein(): void
    {
        $doku = (string) file_get_contents(
            dirname(__DIR__, 2) . '/an_project/docs/dev-guide.md'
        );

        foreach (array_merge(self::IMMER, self::ERST_INSTALLIERT, self::ERST_ANGEMELDET) as $schluessel) {
            $this->assertStringContainsString(
                '`' . $schluessel . '`',
                $doku,
                sprintf('Der zugesicherte Schluessel "%s" steht nicht im dev-guide.', $schluessel)
            );
        }

        foreach (self::INTERN as $schluessel) {
            $this->assertStringContainsString(
                '`' . $schluessel . '`',
                $doku,
                sprintf('Der interne Schluessel "%s" ist im dev-guide nicht als solcher genannt.', $schluessel)
            );
        }
    }

    /**
     * Alle Schlüssel, die das Framework **registriert** — aus dem Quelltext gelesen.
     *
     * **Zwei Quellen für zwei Fragen, und das ist kein Umweg.** Der laufende Container kann
     * sagen, ob ein Schlüssel *da* ist; er kann nicht sagen, ob jemand einen *neuen* angelegt
     * hat, denn gefragt wird immer nur nach den bekannten. Für „ist alles eingeordnet" muss
     * die Quelle der Quelltext sein.
     *
     * Gelesen wird `bootstrap.php` — dort steht der grösste Teil — und
     * `Classes/Kernel/Application.php`, wo `request_stack` und `dispatcher` im Konstruktor
     * entstehen.
     *
     * @return array<int,string>
     */
    private function registrierteSchluessel(): array
    {
        $wurzel   = dirname(__DIR__, 2);
        $gefunden = array();

        $quellen = array(
            $wurzel . '/lib/contentfly/bootstrap.php',
            $wurzel . '/lib/contentfly/Classes/Kernel/Application.php',
            // Hier wird auth.token gesetzt, nachdem ein Request sich ausgewiesen hat.
            $wurzel . '/lib/contentfly/Classes/Controller/Provider/BaseControllerProvider.php',
        );

        foreach ($quellen as $pfad) {
            $this->assertFileExists($pfad);

            $inhalt = (string) file_get_contents($pfad);

            // `$app['x'] = …` im Bootstrap, `$this['x'] = …` im Konstruktor der Anwendung.
            preg_match_all(
                '/(?:\$app|\$this)\[\x27([a-zA-Z0-9_.]+)\x27\]\s*=[^=]/',
                $inhalt,
                $funde
            );

            foreach ($funde[1] as $schluessel) {
                $gefunden[$schluessel] = true;
            }
        }

        $this->assertNotEmpty($gefunden, 'Es wurde kein einziger Schluessel gefunden — dann prueft dieser Test nichts.');

        return array_keys($gefunden);
    }

    /**
     * Die Schlüssel des aufgebauten Containers — in einem eigenen Prozess erfragt.
     *
     * @return array<int,string>
     */
    private function schluesselDesContainers(): array
    {
        $projekt = self::anwendungsverzeichnis();

        $skript = <<<'PHP'
$app = \Areanet\PIM\Classes\Kernel\Start::konsole($argv[1]);
$gefunden = [];
foreach ($argv[2] === '' ? [] : explode(',', $argv[2]) as $schluessel) {
    if (isset($app[$schluessel])) {
        $gefunden[] = $schluessel;
    }
}
echo implode(',', $gefunden);
PHP;

        $kandidaten = array_merge(
            self::IMMER,
            self::ERST_INSTALLIERT,
            self::ERST_ANGEMELDET,
            self::INTERN
        );

        $befehl = sprintf(
            '%s -r %s %s %s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg('require ' . var_export($projekt . '/vendor/autoload.php', true) . '; ' . $skript),
            escapeshellarg($projekt),
            escapeshellarg(implode(',', $kandidaten))
        );

        $ausgabe = array();
        $code    = 0;
        exec($befehl, $ausgabe, $code);

        $roh = trim(implode("\n", $ausgabe));

        $this->assertSame(
            0,
            $code,
            "Der Container liess sich nicht aufbauen:\n" . $roh
        );

        return $roh === '' ? array() : explode(',', $roh);
    }
}
