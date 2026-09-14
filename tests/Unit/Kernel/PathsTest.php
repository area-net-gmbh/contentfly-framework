<?php
namespace Tests\Unit\Kernel;

use Areanet\PIM\Classes\Kernel\Paths;
use PHPUnit\Framework\TestCase;

/**
 * Der Waechter gegen die Rueckkehr von `ROOT_DIR` (007-001-0002).
 *
 * ## Der Anlass
 * `lib/contentfly/bootstrap.php` rechnete das Projektverzeichnis aus der Lage des Frameworks:
 * `const ROOT_DIR = __DIR__ . '/../..'`. Das stimmt, solange das Framework unter `lib/` im
 * Projekt liegt — und wird falsch, sobald es als Paket unter `vendor/` liegt.
 *
 * **Falsch auf die stille Art:** Der gerechnete Pfad existiert dann nicht, aber es GIBT ihn.
 * `file_exists()` gibt `false` zurueck, und die Folgemeldung handelt von einer fehlenden Datei
 * statt von einer falschen Wurzel. Wer das Paket zum ersten Mal einbindet, sucht an der
 * falschen Stelle.
 *
 * ## Warum der zweite Teil noetig ist
 * Die Klasse allein genuegt nicht. Sie hilft nur, solange niemand daneben wieder einen
 * `__DIR__`-Sprung aus `lib/contentfly/` heraus schreibt — und das ist die naheliegendste Art,
 * "schnell" an eine Datei im Projekt zu kommen. Der zweite Teil dieses Tests sucht danach.
 */
class PathsTest extends TestCase
{
    /**
     * Ein Sprung nach oben, der das Paket verlaesst, ist erlaubt — aber nur fuer das Paket.
     *
     * `Paths::package()` tut genau das mit `dirname(__DIR__, 4)`, und das ist richtig: Eine Datei
     * darf ihr eigenes Paket finden. Sie darf nur nicht daraus schliessen, wo das PROJEKT liegt.
     * Der Eintrag steht deshalb hier und nicht als Ausnahme im Suchmuster.
     *
     * @var array<string,string> Datei → warum der Sprung dort richtig ist
     */
    private const ERLAUBT = array(
        'Classes/Kernel/Paths.php' =>
            'Paths::package() leitet das Verzeichnis des Frameworks aus der eigenen Lage ab. Das '
            .'ist die eine Stelle, an der das richtig ist — und der Grund, warum es eine eigene '
            .'Methode ist statt eines Ausdrucks an zwanzig Stellen.',
    );

    protected function tearDown(): void
    {
        // Die Suite hat den Wert in tests/bootstrap.php gesetzt; wer ihn hier wegnimmt, gibt
        // ihn zurueck, sonst laufen die folgenden Tests gegen eine leere Klasse.
        Paths::set(CONTENTFLY_PROJECT_DIR);
    }

    public function testOhneGesetztesVerzeichnisWirftJederZugriff(): void
    {
        Paths::reset();

        $this->assertFalse(Paths::isSet());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/project directory has not been set/');

        Paths::project();
    }

    /**
     * Der Unterschied, um den es in diesem Task geht.
     *
     * Ein Verzeichnis, das es nicht gibt, wird beim SETZEN abgewiesen — nicht erst beim ersten
     * Zugriff auf eine Datei darunter. Sonst handelte die Meldung wieder von der Datei.
     */
    public function testEinVerzeichnisDasEsNichtGibtWirdBeimSetzenAbgewiesen(): void
    {
        Paths::reset();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/does not exist/');

        Paths::set(CONTENTFLY_PROJECT_DIR . '/dieses-verzeichnis-gibt-es-nicht');
    }

    public function testDiePfadeHaengenAmUebergebenenVerzeichnis(): void
    {
        Paths::reset();
        Paths::set(CONTENTFLY_PROJECT_DIR);

        $this->assertSame(realpath(CONTENTFLY_PROJECT_DIR), Paths::project());
        $this->assertSame(Paths::project() . '/custom',  Paths::custom());
        $this->assertSame(Paths::project() . '/data',    Paths::data());
        $this->assertSame(Paths::project() . '/plugins', Paths::plugins());
    }

    /**
     * Das Verzeichnis des Pakets ist unabhaengig vom Projekt — auch das gehoert geprueft.
     *
     * Waeren beide dasselbe, haette sich am alten Zustand nichts geaendert; er saehe nur anders
     * aus. Heute fallen sie zusammen, weil das Framework noch im Projekt liegt; die Zusicherung
     * ist, dass `paket()` OHNE ein gesetztes Projektverzeichnis auskommt.
     */
    public function testDasPaketverzeichnisBrauchtKeinProjekt(): void
    {
        Paths::reset();

        $this->assertDirectoryExists(Paths::package());
        $this->assertFileExists(Paths::package() . '/bootstrap.php');
    }

    /**
     * Kein `__DIR__`-Sprung aus `lib/contentfly/` heraus — ausser den benannten.
     *
     * Gesucht wird nach beidem: `__DIR__ . '/..'` in jeder Schreibweise und `dirname(__DIR__)`
     * mit oder ohne Tiefe. Beides verlaesst das Verzeichnis der Datei, und beides war der Weg,
     * auf dem `ROOT_DIR` entstanden ist.
     */
    public function testKeinSprungAusDemFrameworkHeraus(): void
    {
        $verdaechtig = array();

        foreach ($this->frameworkdateien() as $relativ => $pfad) {
            if (isset(self::ERLAUBT[$relativ])) {
                continue;
            }

            foreach (explode("\n", (string) file_get_contents($pfad)) as $nummer => $zeile) {
                if ($this->istKommentar($zeile)) {
                    continue;
                }

                if (preg_match('/__DIR__\s*\.\s*[\'"]\s*\/\.\./', $zeile) === 1
                    || preg_match('/dirname\s*\(\s*__DIR__/', $zeile) === 1) {
                    $verdaechtig[] = sprintf('%s:%d — %s', $relativ, $nummer + 1, trim($zeile));
                }
            }
        }

        $this->assertSame(array(), $verdaechtig, implode("\n", array_merge(
            array(
                'Diese Stellen im Framework rechnen sich einen Pfad ausserhalb ihres eigenen',
                'Verzeichnisses aus. Genau so ist ROOT_DIR entstanden, und genau das geht schief,',
                'sobald das Framework als Paket unter vendor/ liegt (007-001-0002):',
                '',
            ),
            $verdaechtig,
            array(
                '',
                'Wer das Projektverzeichnis braucht, nimmt Paths::project() (custom(), daten(),',
                'plugins()); wer das Paket braucht, Paths::package().',
            )
        )));
    }

    /**
     * Und die Ausnahmeliste ueberwacht sich selbst — wie bei den Gates aus `006-005`.
     */
    public function testJedeAusnahmeWirdNochGebraucht(): void
    {
        $dateien = $this->frameworkdateien();
        $tot     = array();

        foreach (array_keys(self::ERLAUBT) as $relativ) {
            if (!isset($dateien[$relativ])) {
                $tot[] = sprintf('%s — die Datei gibt es nicht mehr.', $relativ);
                continue;
            }

            $inhalt = (string) file_get_contents($dateien[$relativ]);

            if (preg_match('/__DIR__\s*\.\s*[\'"]\s*\/\.\./', $inhalt) !== 1
                && preg_match('/dirname\s*\(\s*__DIR__/', $inhalt) !== 1) {
                $tot[] = sprintf('%s — dort wird gar nicht mehr gesprungen.', $relativ);
            }
        }

        $this->assertSame(array(), $tot, implode("\n", array_merge(
            array('Diese Ausnahmen treffen nichts mehr und gehoeren aus ERLAUBT gestrichen:', ''),
            $tot
        )));
    }

    /**
     * @return array<string,string> Pfad relativ zu lib/contentfly/ → voller Pfad
     */
    private function frameworkdateien(): array
    {
        $wurzel  = Paths::package();
        $dateien = array();

        $lauf = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($wurzel));

        foreach ($lauf as $eintrag) {
            if (!$eintrag instanceof \SplFileInfo || $eintrag->getExtension() !== 'php') {
                continue;
            }

            $dateien[substr($eintrag->getPathname(), strlen($wurzel) + 1)] = $eintrag->getPathname();
        }

        $this->assertNotEmpty($dateien, 'Unter lib/contentfly/ steht keine PHP-Datei — dann prueft dieser Test nichts.');

        return $dateien;
    }

    private function istKommentar(string $zeile): bool
    {
        $getrimmt = ltrim($zeile);

        return $getrimmt === ''
            || str_starts_with($getrimmt, '*')
            || str_starts_with($getrimmt, '//')
            || str_starts_with($getrimmt, '/*');
    }
}
