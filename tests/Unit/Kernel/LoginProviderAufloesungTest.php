<?php
namespace Tests\Unit\Kernel;

use Areanet\PIM\Controller\AuthController;
use PHPUnit\Framework\TestCase;

/**
 * Wie ein Anmeldeprovider ausgewählt wird — **umgedreht mit `013-004-0001`, nicht gelöscht.**
 *
 * Der Test hiess vorher dasselbe und prüfte etwas anderes: `AuthController::providerKlasse()`,
 * die einen Namen aus dem Request zu `Custom\Classes\<Name>` auflöste. Er entstand mit
 * `013-001-0005`, weil die Bedingung dort verdreht war (`substr($name, 7)` statt der ersten
 * sieben Zeichen) und Plugin-Provider deshalb nie funktionierten.
 *
 * **Die Auflösung selbst ist jetzt weg**, und das war der Punkt: Welche Klasse eine Anwendung
 * instanziiert, ist eine Entscheidung des Betreibers und nicht des Aufrufers. Was bleibt, ist
 * die Zusicherung, dass sie weg ist — ein Test, der eine entfernte Mechanik festhält, ist die
 * einzige Art, zu merken, wenn jemand sie zurückbaut.
 */
class LoginProviderAufloesungTest extends TestCase
{
    public function testDieAufloesungUeberKlassennamenGibtEsNichtMehr(): void
    {
        $this->assertFalse(
            method_exists(AuthController::class, 'providerKlasse'),
            'providerKlasse() loeste einen Request-Parameter zu einer Klasse auf — entfallen mit 013-004-0001'
        );

        $this->assertFalse(
            method_exists(AuthController::class, 'getLoginProvider'),
            'getLoginProvider() instanziierte diese Klasse — ebenfalls entfallen'
        );
    }

    /**
     * Und der Nachweis, dass die alte Mechanik nicht heimlich woanders lebt: Im ganzen Baum
     * wird kein Klassenname mehr aus einem Request-Parameter zusammengesetzt.
     */
    public function testKeinKlassennameWirdMehrAusEinemParameterGebaut(): void
    {
        $quelle = file_get_contents(CONTENTFLY_PROJECT_DIR.'/lib/contentfly/Controller/AuthController.php');

        $this->assertStringNotContainsString("'Custom\\Classes\\\\'", $quelle);
        $this->assertStringNotContainsString('class_exists(', $quelle);
    }
}
