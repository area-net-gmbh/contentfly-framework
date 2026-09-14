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
        // Immer DA, aber null, solange nicht installiert ist — siehe
        // testDerEntityManagerIstDaAberNullSolangeNichtInstalliertIst().
        'orm.em',
    );

    /**
     * Erst wenn `appcms:install` gelaufen ist.
     *
     * Sie stehen in einem `if ($app['is_installed'])`, und das ist Absicht: `appcms:install`
     * muss selbst laufen können, bevor es eine Datenbank gibt.
     *
     * @var array<int,string>
     */
    private const ERST_INSTALLIERT = array('db', 'dbs');

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
        'dbs.options', 'auth', 'console', 'helper', 'loginbremse', 'tokenAuthenticator',
        'benutzerbereitstellung', 'gruppenabbildung', 'tokenHandler', 'thumbnailSettings',
        'schema', 'typeManager', 'pluginManager',
        // Die Verdrahtung des HttpKernels aus Classes/Kernel/Application — sie gehoert der
        // Anwendung, nicht dem Projekt.
        'kernel', 'resolver', 'argument_resolver',
    );

    public function testJederZugesicherteSchluesselIstDa(): void
    {
        $vorhanden = $this->schluesselDesContainers();

        foreach (self::IMMER as $schluessel) {
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
     * Und die drei, die an der Installation hängen — in **beide** Richtungen.
     *
     * **Das ist aus einem Fehlgriff entstanden.** Mein erster Entwurf behauptete schlicht, `db`,
     * `dbs` und `orm.em` seien da. Das stimmt nur, solange `custom/config.php` zufällig die
     * Zugangsdaten eines Testlaufs trägt — steht dort die ausgelieferte Vorlage, ist die
     * Anwendung nicht installiert, und der Test wurde rot, ohne dass am Framework etwas falsch
     * gewesen wäre.
     *
     * Jetzt fragt er den Zustand und prüft die passende Hälfte. **Die zweite Hälfte ist die
     * eigentliche Zusicherung:** Auf einem frischen Checkout gibt es die drei *nicht*, und das
     * ist Absicht — `appcms:install` muss laufen können, bevor es eine Datenbank gibt.
     */
    public function testDieDreiDatenbankSchluesselHaengenAnDerInstallation(): void
    {
        $vorhanden   = $this->schluesselDesContainers();
        $installiert = $this->istInstalliert();

        foreach (self::ERST_INSTALLIERT as $schluessel) {
            if ($installiert) {
                $this->assertContains(
                    $schluessel,
                    $vorhanden,
                    sprintf('Die Anwendung ist installiert, aber "%s" fehlt.', $schluessel)
                );

                continue;
            }

            $this->assertNotContains(
                $schluessel,
                $vorhanden,
                sprintf(
                    'Die Anwendung ist NICHT installiert, aber "%s" ist da. Dann liefe '
                    .'appcms:install gegen einen halb aufgebauten Container.',
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
     * **`orm.em` ist immer da — aber `null`, solange nicht installiert ist.**
     *
     * Der Fund dieses Tasks, und er korrigiert die Liste aus `007-003-0002`: Dort stand
     * `orm.em` bei den dreien, die es erst nach der Installation gibt. Das stimmt nicht —
     * `bootstrap.php` hat einen `else`-Zweig, der ihn auf `null` setzt.
     *
     * **Der Unterschied ist für ein Projekt eine Falle.** „Fehlt" meldet sich mit einer
     * Exception, die den Namen nennt. `null` meldet sich mit
     * *Call to a member function createQueryBuilder() on null* — einer Meldung, die von der
     * Methode handelt und nicht davon, dass nichts installiert ist.
     *
     * Dasselbe gilt für `auth.user`: da, aber `null`, solange niemand angemeldet ist.
     */
    public function testDerEntityManagerIstDaAberNullSolangeNichtInstalliertIst(): void
    {
        $this->assertContains(
            'orm.em',
            $this->schluesselDesContainers(),
            'orm.em muss immer vorhanden sein — auch ohne Installation, dann als null.'
        );

        if ($this->istInstalliert()) {
            $this->assertNotSame('NULL', $this->ausDemContainer('orm.em'),
                'Die Anwendung ist installiert — dann darf orm.em nicht null sein.');

            return;
        }

        $this->assertSame('NULL', $this->ausDemContainer('orm.em'),
            'Ohne Installation ist orm.em null, nicht abwesend — bootstrap.php setzt ihn im else-Zweig.');
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

        if (!$this->istInstalliert()) {
            $this->markTestSkipped(
                'Ohne Installation registriert das Framework nur einen Teil seiner Schluessel — '
                .'dann sagt diese Richtung nichts.'
            );
        }

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
        if (!$this->istInstalliert()) {
            $this->markTestSkipped(
                'Ohne Installation fehlen die Schluessel hinter is_installed, und der Test '
                .'meldete sie faelschlich als gegenstandslos.'
            );
        }

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
     * Ist die Anwendung installiert?
     *
     * Der Container beantwortet es selbst — `is_installed` ist einer der zugesicherten
     * Schlüssel und immer da.
     */
    private function istInstalliert(): bool
    {
        // var_export(true, true) ist 'true' — nicht '1'. Mein erster Vergleich stand auf '1'
        // und hielt damit JEDEN Zustand fuer "nicht installiert" (007-003-0003).
        return $this->ausDemContainer('is_installed') === 'true';
    }

    /**
     * Alle Schlüssel, die das Framework **registriert**.
     *
     * **Zwei Quellen, und jede beantwortet, was die andere nicht kann.**
     *
     * `Container::keys()` liefert, was beim Aufbau tatsächlich registriert wurde — die
     * verlässlichere Auskunft, weil sie den ausgeführten Code sieht und keinen Suchausdruck
     * braucht. *(Nachgetragen mit `007-003-0003`: In `0002` hatte ich stattdessen den
     * Quelltext geparst, ohne zu bemerken, dass der Container sich selbst aufzählen kann.
     * `tests/Unit/Kernel/ContainerTest.php` prüft die Methode seit `008-004`.)*
     *
     * Der Quelltext bleibt daneben, weil `keys()` nur sieht, was **beim Aufbau** entsteht.
     * `auth.token` wird erst gesetzt, wenn ein Request sich ausgewiesen hat — im aufgebauten
     * Container gibt es ihn nicht, und ohne die zweite Quelle fiele er aus der Einordnung
     * heraus.
     *
     * @return array<int,string>
     */
    private function registrierteSchluessel(): array
    {
        $wurzel   = dirname(__DIR__, 2);
        $gefunden = array();

        // Was beim Aufbau entsteht — die verlaessliche Quelle.
        foreach ($this->schluesselDesContainers(true) as $schluessel) {
            $gefunden[$schluessel] = true;
        }

        // Und was spaeter dazukommt: auth.token wird erst nach der Anmeldung gesetzt.
        $quellen = array(
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

        /*
         * ROUTEN-CONTROLLER SIND KEINE EINZUORDNENDEN SCHLUESSEL (007-003-0003).
         *
         * Jeder gemountete Controller bekommt einen Eintrag `<praefix>.controller` — das
         * Framework legt `api.controller`, `auth.controller`, `file.controller` und
         * `system.controller` an, und ein Projekt legt fuer jede eigene Route einen weiteren
         * an. Die Vorlage erzeugt so `api/v1/example/.controller`.
         *
         * Sie sind Verdrahtung des ControllerResolvers, kein Dienst, den jemand liest, und
         * ihre Namen haengen an den Routen des Projekts. Eine Liste koennte sie gar nicht
         * fuehren.
         *
         * GEFUNDEN HAT SIE keys(): Der Quelltext-Parser aus 007-003-0002 sah sie nicht, weil
         * sie zur Laufzeit entstehen. Das ist der Grund, warum diese Quelle die bessere ist.
         */
        $schluessel = array_filter(
            array_keys($gefunden),
            static fn (string $name): bool => !str_ends_with($name, '.controller')
        );

        return array_values($schluessel);
    }

    /**
     * Einen einzelnen Wert aus dem aufgebauten Container holen, als Zeichenkette.
     *
     * `var_export`-Form, damit sich `null` von `''` und von `false` unterscheiden lässt — genau
     * darauf kommt es bei `orm.em` und `auth.user` an.
     */
    private function ausDemContainer(string $schluessel): string
    {
        $projekt = self::anwendungsverzeichnis();

        $skript = <<<'PHP'
$app = \Areanet\PIM\Classes\Kernel\Start::console($argv[1]);
echo isset($app[$argv[2]]) ? var_export($app[$argv[2]], true) : '__FEHLT__';
PHP;

        $befehl = sprintf(
            '%s -r %s %s %s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg('require ' . var_export($projekt . '/vendor/autoload.php', true) . '; ' . $skript),
            escapeshellarg($projekt),
            escapeshellarg($schluessel)
        );

        $ausgabe = array();
        $code    = 0;
        exec($befehl, $ausgabe, $code);

        $roh = trim(implode("\n", $ausgabe));

        $this->assertSame(0, $code, "Der Container liess sich nicht aufbauen:\n" . $roh);

        return $roh;
    }

    /**
     * Die Schlüssel des aufgebauten Containers — in einem eigenen Prozess erfragt.
     *
     * @return array<int,string>
     */
    private function schluesselDesContainers(bool $alle = false): array
    {
        $projekt = self::anwendungsverzeichnis();

        $skript = <<<'PHP'
$app = \Areanet\PIM\Classes\Kernel\Start::console($argv[1]);

if ($argv[2] === '*') {
    echo implode(',', $app->keys());
    return;
}

$gefunden = [];
foreach ($argv[2] === '' ? [] : explode(',', $argv[2]) as $schluessel) {
    if (isset($app[$schluessel])) {
        $gefunden[] = $schluessel;
    }
}
echo implode(',', $gefunden);
PHP;

        $kandidaten = $alle ? array('*') : array_merge(
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
