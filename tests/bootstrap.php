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

require_once ROOT_DIR . '/vendor/autoload.php';

if (file_exists(ROOT_DIR . '/custom/vendor/autoload.php')) {
    require_once ROOT_DIR . '/custom/vendor/autoload.php';
}

require_once ROOT_DIR . '/lib/contentfly/version.php';
require_once ROOT_DIR . '/custom/version.php';

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
