<?php
/**
 * Bootstrap für die Testsuite.
 *
 * Bewusst **nicht** `lib/contentfly/bootstrap.php`: Der baut die komplette Silex-Anwendung
 * auf, verlangt eine konfigurierte Datenbank und startet eine Session. Für Tests, die eine
 * einzelne Klasse prüfen, ist das weder nötig noch erwünscht — ein Testlauf, der ohne
 * laufenden Container nicht startet, wird nicht ausgeführt.
 *
 * Hier steht deshalb nur das Minimum: die beiden Autoloader und die Konstanten, die
 * Framework-Klassen beim Laden erwarten. Tests, die mehr brauchen, holen es sich selbst —
 * siehe `tests/README.md`.
 */

const ROOT_DIR = __DIR__ . '/..';

/*
 * Dieselbe Reihenfolge wie in `lib/contentfly/bootstrap.php`, und aus demselben Grund: Root
 * zuerst, `custom/` ergänzend. Bei einem gemeinsamen PSR-4-Präfix gewinnt der Root — eine
 * Zusicherung seit `006-004-0001`, begründet in `an_project/docs/architecture.md` unter
 * *Key decisions*. Die Bedingung dahinter — keine Überschneidung — prüft
 * `tests/Unit/AutoloaderUeberschneidungTest.php`.
 *
 * Die Spiegelung ist Absicht: Ein Testlauf, der anders lädt als die Anwendung, prüft eine
 * andere Anwendung.
 */
require_once ROOT_DIR . '/vendor/autoload.php';

if (file_exists(ROOT_DIR . '/custom/vendor/autoload.php')) {
    require_once ROOT_DIR . '/custom/vendor/autoload.php';
}

require_once ROOT_DIR . '/lib/contentfly/version.php';
require_once ROOT_DIR . '/custom/version.php';

/*
 * Basisklasse der Integrationstests. Sie kommt nicht über den Autoloader: `custom/composer.json`
 * mappt `Custom\Tests\`, die Testklassen liegen aber unter `Tests\`. Und PHPUnit selbst lädt
 * nur Dateien, die auf `Test.php` enden — diese also nicht. Wenn Epic 006 die Autoload-Situation
 * aufräumt, kann diese Zeile durch ein PSR-4-Mapping ersetzt werden.
 */
require_once ROOT_DIR . '/tests/Integration/IntegrationTestCase.php';

/*
 * `lib/contentfly/bootstrap.php` setzt diese Konstanten aus der Konfiguration. Entity-Klassen
 * lesen sie in ihren Annotationen (`@ORM\Column(type=APPCMS_ID_TYPE)`), also müssen sie
 * definiert sein, bevor eine Entity geladen wird — sonst stirbt schon das Einlesen der
 * Metadaten. Die Werte hier sind Testwerte, keine Konfiguration.
 */
if (!defined('HOST')) {
    define('HOST', 'test');
}
if (!defined('APPCMS_ID_TYPE')) {
    define('APPCMS_ID_TYPE', 'string');
}
if (!defined('APPCMS_ID_STRATEGY')) {
    define('APPCMS_ID_STRATEGY', 'UUID');
}
