<?php
namespace Tests\Unit\Kernel;

use Areanet\PIM\Classes\Kernel\Paths;
use Areanet\PIM\Classes\Kernel\Start;
use PHPUnit\Framework\TestCase;

/**
 * Die drei Abbruchwege der Tuer ins Framework (007-001-0003).
 *
 * **Warum sie Tests bekommen.** `Start` wirft, statt `exit` zu rufen, und der Grund steht in
 * der Klasse: Ein `exit` liesse sich nicht pruefen — und ein Abbruchweg, den kein Test betritt,
 * ist ein Abbruchweg, auf den man sich nicht verlassen kann. Dann waere die Zusicherung, dass
 * eine Fehlkonfiguration laut auffaellt, wieder nur ein Kommentar.
 *
 * **Geprueft wird ueber `web()`, nicht `console()`.** Beide gehen durch dieselbe Vorbereitung,
 * aber `console()` definiert `APPCMS_CONSOLE` — und eine Konstante laesst sich nicht wieder
 * zuruecknehmen. Ein Test, der sie setzt, veraendert jeden danach laufenden Test im selben
 * Prozess.
 */
class StartTest extends TestCase
{
    private string $wegwerf = '';

    protected function setUp(): void
    {
        $this->wegwerf = sys_get_temp_dir() . '/contentfly-start-' . bin2hex(random_bytes(6));
        mkdir($this->wegwerf . '/custom', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->aufraeumen($this->wegwerf);

        // Die Suite hat das Verzeichnis in tests/bootstrap.php gesetzt; zurueckgeben, sonst
        // laufen die folgenden Tests gegen eine leere Paths-Klasse.
        Paths::set(CONTENTFLY_PROJECT_DIR);
    }

    public function testEinVerzeichnisDasEsNichtGibtWirdAbgewiesen(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/does not exist/');

        Start::web($this->wegwerf . '/gibt-es-nicht');
    }

    /**
     * Fehlt die Konfiguration, sagt die Meldung das — und nennt den Pfad, an dem gesucht wurde.
     *
     * Vorher war es ein nacktes `require_once`. PHPs eigene Meldung („Failed opening
     * required …") nennt zwar den Pfad, aber nicht, dass es um die Konfiguration geht und wie
     * man zu ihr kommt.
     */
    public function testOhneKonfigurationBrichtDerStartMitEinerMeldungDarueberAb(): void
    {
        try {
            Start::web($this->wegwerf);
            $this->fail('Ohne custom/config.php darf der Start nicht durchlaufen.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('project configuration', $e->getMessage());
            $this->assertStringContainsString($this->wegwerf . '/custom/config.php', $e->getMessage());
            $this->assertStringContainsString('appcms:install', $e->getMessage());
        }
    }

    /**
     * Der Fall, der still schiefginge, wenn man ihn liesse.
     *
     * Ein liegengebliebenes `custom/vendor/` sieht aus wie etwas, das benutzt wird. Wuerde es
     * einfach nicht mehr geladen, fehlte dem Projekt eine Klasse — und die Meldung handelte von
     * dieser Klasse, nicht davon, dass ein ganzer Baum nicht mehr gilt.
     */
    public function testEinZweiterComposerBaumWirdAbgewiesenStattUebergangen(): void
    {
        file_put_contents($this->wegwerf . '/custom/config.php', '<?php');
        mkdir($this->wegwerf . '/custom/vendor', 0777, true);
        file_put_contents($this->wegwerf . '/custom/vendor/autoload.php', '<?php');

        try {
            Start::web($this->wegwerf);
            $this->fail('Ein zweiter Composer-Baum darf nicht stillschweigend uebergangen werden.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('custom/vendor', $e->getMessage());
            $this->assertStringContainsString('exactly ONE tree', $e->getMessage());
        }
    }

    /**
     * Und die Reihenfolge: Der zweite Baum wird VOR der fehlenden Konfiguration gemeldet?
     *
     * Nein — umgekehrt, und das ist Absicht. Ohne Konfiguration laeuft ohnehin nichts; sie ist
     * die Bedingung, die naeher am Start liegt. Dieser Test haelt die Reihenfolge fest, damit
     * niemand sie beim Umbauen unbemerkt dreht und eine Meldung eine andere verdeckt.
     */
    public function testOhneKonfigurationUndMitZweitemBaumGewinntDerZweiteBaum(): void
    {
        mkdir($this->wegwerf . '/custom/vendor', 0777, true);
        file_put_contents($this->wegwerf . '/custom/vendor/autoload.php', '<?php');

        try {
            Start::web($this->wegwerf);
            $this->fail('Der Start darf hier nicht durchlaufen.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('custom/vendor', $e->getMessage());
        }
    }

    private function aufraeumen(string $pfad): void
    {
        if (!is_dir($pfad)) {
            return;
        }

        $lauf = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($pfad, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($lauf as $eintrag) {
            if (!$eintrag instanceof \SplFileInfo) {
                continue;
            }

            $eintrag->isDir() ? rmdir($eintrag->getPathname()) : unlink($eintrag->getPathname());
        }

        rmdir($pfad);
    }
}
