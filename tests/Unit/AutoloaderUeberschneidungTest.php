<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * **Dieser Test ist umgedreht worden (007-001-0003) — er ist nicht gelöscht.**
 *
 * ## Was er bis dahin bewachte
 * Die Entscheidung aus `006-004-0001`: zwei Composer-Bäume, Root zuerst, `custom/` ergänzend;
 * bei einem gemeinsamen PSR-4-Präfix gewinnt der Root. Die Bedingung dafür war, dass sich die
 * Bäume nicht überschneiden — und sie war jahrelang verletzt, ohne dass es auffiel: `psr/log`
 * lag in 1.1.3 und 3.0.2 gleichzeitig im Prozess, dazu `symfony/polyfill-ctype` und
 * `-mbstring` in unvereinbaren Ständen und ein handkopiertes `PHPMailer\PHPMailer\`, das in
 * keiner `installed.json` stand. Aufgefallen ist es erst, als `006-001-0002` beide Bäume
 * ausgezählt hat.
 *
 * ## Warum die Bedingung weggefallen ist
 * Mit `007-001` wird das Framework ein Bibliothekspaket. Ein Projekt hat dann **einen** Baum,
 * in dem Contentfly als Abhängigkeit liegt — es gibt keine zwei Bäume mehr, zwischen denen
 * eine Rangfolge zu regeln wäre. **Der Ersatz ist stärker als die Zusicherung, die er ablöst:**
 * Die alte Regel hielt eine Überschneidung *fern*, solange dieser Test die Bedingung prüfte.
 * Composer *verweigert* unvereinbare Constraints beim Auflösen. Der `psr/log`-Fall kann nicht
 * mehr entstehen.
 *
 * ## Was er jetzt bewacht
 * Dass es dabei bleibt. Ein Test, der eine weggefallene Bedingung bewacht hat, bewacht danach,
 * dass sie weggefallen **bleibt** — sonst kehrt der zweite Baum zurück, ohne dass jemand die
 * Entscheidung von `007-001-0001` noch einmal trifft.
 *
 * Gelöscht wäre er das Falsche gewesen: Der Fall, den er aufgedeckt hat, ist der teuerste
 * Einzelbefund aus Epic `006`, und die Erinnerung daran gehört an die Stelle, an der jemand
 * rückgängig machen würde, was ihn behoben hat.
 */
class AutoloaderUeberschneidungTest extends TestCase
{
    private const WURZEL = __DIR__ . '/../..';

    public function testDerRootBaumExistiertUndIstNichtLeer(): void
    {
        $vendor = self::WURZEL . '/vendor';

        self::assertDirectoryExists(
            $vendor,
            'Ohne vendor/ laeuft nichts — "composer install" fehlt (an_project/docs/runbook.md, Schritt 1).'
        );

        $psr4 = $vendor . '/composer/autoload_psr4.php';

        self::assertFileExists($psr4, 'Der Autoloader des Root-Baums fehlt.');

        /** @var array<string,mixed> $karte */
        $karte = require $psr4;

        self::assertNotEmpty(
            $karte,
            'Die PSR-4-Karte des Root-Baums ist leer. Dann pruefen die Tests darunter nichts '
            .'und waeren aus dem falschen Grund gruen.'
        );

        self::assertArrayHasKey(
            'Areanet\\PIM\\',
            $karte,
            'Das Framework ist im Autoloader des Projekts nicht eingetragen — genau das ist '
            .'der Zustand, den 007-001 herstellt: Contentfly liegt IM Baum des Projekts.'
        );
    }

    /**
     * Kein zweiter Baum unter `custom/`.
     *
     * Geprüft wird das Verzeichnis auf der Platte, nicht das Manifest: Ein
     * `custom/composer.json` ohne `require` schadet nicht, ein installiertes
     * `custom/vendor/autoload.php` schon — es sähe aus, als würde es benutzt.
     */
    public function testEsGibtKeinenZweitenComposerBaum(): void
    {
        self::assertFileDoesNotExist(
            self::WURZEL . '/custom/vendor/autoload.php',
            "Unter custom/vendor/ liegt wieder ein Composer-Baum.\n\n"
            ."Seit 007-001 hat ein Projekt genau einen: Das Framework ist eine Abhaengigkeit\n"
            ."darin, kein zweiter Baum daneben. Was hier liegt, wird nicht geladen — und still\n"
            ."liegen zu lassen, was nicht geladen wird, ist der Fehler, gegen den diese Regel\n"
            ."gebaut ist.\n\n"
            ."Die Entscheidung steht in an_project/docs/architecture.md (Key decisions,\n"
            ."2026-09-11); Start::keinZweiterBaum() weist denselben Zustand zur Laufzeit ab."
        );
    }

    /**
     * Was einen Autoloader laden darf, und warum.
     *
     * @var array<string,string> Datei → Begruendung
     */
    private const ERLAUBT = array(
        'Classes/Plugin.php' =>
            'Ein Plugin bringt seinen eigenen vendor/-Baum mit, und initComposer() laedt ihn. '
            .'Das ist NICHT der Autoloader des Frameworks, sondern der eines Projektbestandteils '
            .'— das Framework laedt ihn stellvertretend. Aufgefallen ist es diesem Test beim '
            .'Umdrehen (007-001-0003): Die Entscheidung aus 007-001-0001 sprach von "einem Baum '
            .'je Projekt" und hatte die Plugins nicht bedacht. Ob es dabei bleibt, entscheidet '
            .'007-001-0004 zusammen mit der Frage, was aus plugins/ wird.',
    );

    /**
     * Und das Framework laedt seinen eigenen Autoloader nicht mehr.
     *
     * Das ist die andere Haelfte von `007-001-0003` und der Grund, warum das Paket ueberhaupt
     * in `vendor/` liegen kann: Ein Paket wird vom Autoloader geladen, es laedt ihn nicht.
     * Ohne diesen Test bliebe die Umkehr eine Sache des Kommentars.
     *
     * **Gesucht wird ein `require`/`include`, nicht das Wort.** Eine Zeile, die einen
     * Autoloader-Pfad nur PRUEFT — `Start::keinZweiterBaum()` tut genau das —, laedt nichts;
     * sie zu melden hiesse, die Pruefung gegen sich selbst zu richten.
     */
    public function testDasFrameworkLaedtKeinenAutoloader(): void
    {
        $gefunden = array();
        $wurzel   = self::WURZEL . '/lib/contentfly';

        $lauf = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($wurzel));

        foreach ($lauf as $eintrag) {
            if (!$eintrag instanceof \SplFileInfo || $eintrag->getExtension() !== 'php') {
                continue;
            }

            foreach (explode("\n", (string) file_get_contents($eintrag->getPathname())) as $nummer => $zeile) {
                $getrimmt = ltrim($zeile);

                if ($getrimmt === '' || str_starts_with($getrimmt, '*')
                    || str_starts_with($getrimmt, '//') || str_starts_with($getrimmt, '/*')) {
                    continue;
                }

                if (preg_match('/\b(require|include)(_once)?\b.*autoload\.php/', $zeile) !== 1) {
                    continue;
                }

                $relativ = substr($eintrag->getPathname(), strlen($wurzel) + 1);

                if (isset(self::ERLAUBT[$relativ])) {
                    continue;
                }

                $gefunden[] = sprintf('%s:%d — %s', $relativ, $nummer + 1, trim($zeile));
            }
        }

        self::assertSame(array(), $gefunden, implode("\n", array_merge(
            array(
                'Das Framework laedt einen Autoloader. Damit kann es nicht als Paket unter',
                'vendor/ liegen: Um diese Datei zu finden, braeuchte man den Autoloader, den',
                'sie selbst erst laedt (007-001-0003).',
                '',
            ),
            $gefunden,
            array('', 'Der Einstiegspunkt laedt ihn und ruft dann Classes\\Kernel\\Start.')
        )));
    }

    /**
     * Und die Ausnahmeliste ueberwacht sich selbst — wie die Gates aus `006-005`.
     */
    public function testJedeAusnahmeWirdNochGebraucht(): void
    {
        $tot = array();

        foreach (array_keys(self::ERLAUBT) as $relativ) {
            $pfad = self::WURZEL . '/lib/contentfly/' . $relativ;

            if (!is_file($pfad)) {
                $tot[] = sprintf('%s — die Datei gibt es nicht mehr.', $relativ);
                continue;
            }

            if (preg_match('/\b(require|include)(_once)?\b.*autoload\.php/', (string) file_get_contents($pfad)) !== 1) {
                $tot[] = sprintf('%s — dort wird gar kein Autoloader mehr geladen.', $relativ);
            }
        }

        self::assertSame(array(), $tot, implode("\n", array_merge(
            array('Diese Ausnahmen treffen nichts mehr und gehoeren aus ERLAUBT gestrichen:', ''),
            $tot
        )));
    }
}
