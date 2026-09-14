<?php
namespace Tests\Unit\Kernel;

use Areanet\PIM\Classes\Kernel\Paths;
use PHPUnit\Framework\TestCase;

/**
 * Die Paketgrenze, geprüft am Manifest (007-001-0004).
 *
 * ## Warum das geprüft wird und nicht nur dasteht
 * Seit `007-001-0004` gibt es zwei Manifeste: `lib/contentfly/composer.json` beschreibt das
 * Bibliothekspaket `areanet/contentfly`, das Manifest im Wurzelverzeichnis beschreibt das
 * Projekt. **Die Grenze zwischen beiden ist eine Entscheidung, keine Eigenschaft der Dateien** —
 * und eine Entscheidung, die niemand prüft, hält genau bis zum nächsten `composer require` an
 * der falschen Stelle.
 *
 * Der teuerste Befund aus Epic `006` war von dieser Art: Die Bedingung, an der die
 * Autoloader-Entscheidung hing, war jahrelang verletzt, weil sie niemand prüfte.
 */
class PaketmanifestTest extends TestCase
{
    /**
     * Die Version steht an zwei Stellen — also gehört geprüft, dass sie dieselbe ist.
     *
     * `lib/contentfly/version.php` trägt `APP_VERSION`, das Manifest ein `version`-Feld. Das
     * Feld ist bei einem `path`-Repository nötig: Ohne es leitet Composer die Version aus dem
     * Git-Zweig ab, und die Auflösung hinge dann daran, wie der Branch gerade heisst.
     *
     * Das Manifest sagt genau das zu — dieser Test löst die Zusage ein.
     */
    public function testDieVersionImManifestStimmtMitVersionPhpUeberein(): void
    {
        $manifest = $this->paketmanifest();

        $this->assertArrayHasKey('version', $manifest,
            'Ohne version-Feld leitet Composer die Version des path-Pakets aus dem Git-Zweig ab.');

        $this->assertSame(
            APP_VERSION,
            $manifest['version'],
            "version in lib/contentfly/composer.json und APP_VERSION in lib/contentfly/version.php\n"
            ."laufen auseinander. Wer eine aendert, aendert beide."
        );
    }

    /**
     * Das Paket trägt genau einen Namensraum: seinen eigenen.
     *
     * Bis `007-001-0004` trug **ein** Manifest drei — `Areanet\PIM\`, `Custom\` und `Plugins\`.
     * Genau diese Vermischung machte das Update unmöglich: Wer eine neue Frameworkversion
     * wollte, bekam sie nur, indem er den Baum überschrieb, in dem auch sein eigener Code lag.
     */
    public function testDasPaketTraegtNurDenNamensraumDesFrameworks(): void
    {
        $manifest = $this->paketmanifest();

        $this->assertSame(
            array('Areanet\\PIM\\'),
            array_keys($manifest['autoload']['psr-4'] ?? array()),
            'Das Bibliothekspaket darf nur seinen eigenen Namensraum führen.'
        );

        $this->assertSame('library', $manifest['type'] ?? null);
        $this->assertSame('areanet/contentfly', $manifest['name'] ?? null);
    }

    /**
     * Und das Projekt trägt seine — aber nicht den des Frameworks.
     *
     * Stünde `Areanet\PIM\` auch hier, gäbe es zwei Wege zu denselben Klassen, und welcher
     * gewinnt, entschiede die Ladereihenfolge. Das ist der Fall, gegen den `006-004-0001`
     * gebaut war, nur eine Ebene höher.
     */
    public function testDasProjektFuehrtDenNamensraumDesFrameworksNicht(): void
    {
        $manifest = $this->projektmanifest();
        $psr4     = $manifest['autoload']['psr-4'] ?? array();

        $this->assertArrayNotHasKey('Areanet\\PIM\\', $psr4,
            'Das Framework kommt über das Paket, nicht über einen zweiten Autoload-Eintrag.');

        $this->assertArrayHasKey('areanet/contentfly', $manifest['require'] ?? array(),
            'Das Projekt muss das Framework als Abhängigkeit führen.');
    }

    /**
     * Ein Bibliothekspaket liefert keine Werkzeuge mit.
     *
     * Wer eine Bibliothek einbindet, erbt ihre `require`-Angaben — aber PHPUnit, PHPStan und
     * Rector gehören in das Repo, in dem entwickelt wird, nicht in jede Installation, die das
     * Framework benutzt.
     */
    public function testDasPaketLiefertKeineWerkzeugeMit(): void
    {
        $manifest = $this->paketmanifest();

        $this->assertArrayNotHasKey('require-dev', $manifest,
            'require-dev des Frameworks gehört in das Manifest des Entwicklungs-Repos.');

        $this->assertArrayNotHasKey('platform', $manifest['config'] ?? array(),
            'Eine Bibliothek darf ihrem Konsumenten keine Plattformversion vorschreiben.');
    }

    /**
     * Und es steht wirklich dort, wo Paths::package() hinzeigt.
     *
     * Ohne diese Prüfung könnte der Test oben aus dem falschen Grund grün sein — etwa weil er
     * ein Manifest liest, das gar nicht das des Pakets ist.
     */
    public function testDasManifestLiegtInDerPaketwurzel(): void
    {
        $this->assertFileExists(Paths::package() . '/composer.json');
        $this->assertFileExists(Paths::package() . '/version.php');
        $this->assertFileExists(Paths::package() . '/bootstrap.php');
    }

    /** @return array<string,mixed> */
    private function paketmanifest(): array
    {
        return $this->lesen(Paths::package() . '/composer.json');
    }

    /** @return array<string,mixed> */
    private function projektmanifest(): array
    {
        return $this->lesen(Paths::project() . '/composer.json');
    }

    /** @return array<string,mixed> */
    private function lesen(string $pfad): array
    {
        $this->assertFileExists($pfad);

        $daten = json_decode((string) file_get_contents($pfad), true);

        $this->assertIsArray($daten, sprintf('%s ist kein gueltiges JSON.', $pfad));

        return $daten;
    }
}
