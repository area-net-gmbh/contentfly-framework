<?php
namespace Tests\Unit\Kernel;

use Areanet\PIM\Controller\AuthController;
use PHPUnit\Framework\TestCase;

/**
 * Die Namensauflösung für LoginManager (013-001-0005).
 *
 * **Die Bedingung war verdreht.** `substr($name, 7) == 'Plugins'` schneidet *ab* Position 7,
 * statt die ersten sieben Zeichen zu prüfen. Für `Plugins\Auth\Ldap` ergibt das `\Auth\Ldap`;
 * die Bedingung griff nie, und der Name wurde fälschlich zu `Custom\Classes\Plugins\Auth\Ldap`.
 * LoginManager aus Plugins funktionierten dadurch nicht.
 *
 * **Einschränkung, ausdrücklich:** Dieser Pfad ist nicht end-to-end prüfbar. `plugins/` ist
 * leer, und ein Plugin nur für einen Test anzulegen hiesse, die Lücke mit Testcode zu füllen,
 * statt sie zu benennen. Geprüft wird deshalb die Auflösung selbst — genau die Stelle, die
 * falsch war. Dass ein aufgelöster Name danach über `class_exists()` und die
 * `LoginManager`-Prüfung läuft, deckt `LoginManagerApiTest` für den `Custom\Classes`-Weg ab.
 */
class LoginProviderAufloesungTest extends TestCase
{
    public function testEinNameMitPluginsPraefixBleibtWieErIst(): void
    {
        $this->assertSame('Plugins\Auth\Ldap', AuthController::providerKlasse('Plugins\Auth\Ldap'));
    }

    public function testJederAndereNameWirdUnterCustomClassesGesucht(): void
    {
        $this->assertSame('Custom\Classes\MeinLogin', AuthController::providerKlasse('MeinLogin'));
    }

    /**
     * Der Nachweis, dass der alte Fehler weg ist — und nicht nur, dass das Ergebnis stimmt.
     */
    public function testDerAlteFehlerIstWeg(): void
    {
        $alt = substr('Plugins\Auth\Ldap', 7) == 'Plugins'
            ? 'Plugins\Auth\Ldap'
            : 'Custom\Classes\\'.'Plugins\Auth\Ldap';

        $this->assertSame('Custom\Classes\Plugins\Auth\Ldap', $alt, 'So lief es vorher');
        $this->assertNotSame($alt, AuthController::providerKlasse('Plugins\Auth\Ldap'));
    }
}
