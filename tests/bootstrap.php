<?php
/**
 * Bootstrap für die Testsuite.
 *
 * Bewusst **nicht** `lib/contentfly/bootstrap.php`: Der baut die komplette Anwendung
 * auf, verlangt eine konfigurierte Datenbank und startet eine Session. Für Tests, die eine
 * einzelne Klasse prüfen, ist das weder nötig noch erwünscht — ein Testlauf, der ohne
 * laufenden Container nicht startet, wird nicht ausgeführt.
 *
 * Hier steht deshalb nur das Minimum: die beiden Autoloader und die Konstanten, die
 * Framework-Klassen beim Laden erwarten. Tests, die mehr brauchen, holen es sich selbst —
 * siehe `tests/README.md`.
 */

/*
 * Dieselbe Konstante, die auch die Einstiegspunkte setzen (007-001-0002). Sie hiess bis dahin
 * ROOT_DIR und wurde vom Framework aus dessen eigener Lage gerechnet; jetzt benennt sie, wer
 * startet — hier also die Suite.
 */
define('CONTENTFLY_PROJEKT', dirname(__DIR__));

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
require_once CONTENTFLY_PROJEKT . '/vendor/autoload.php';

if (file_exists(CONTENTFLY_PROJEKT . '/custom/vendor/autoload.php')) {
    require_once CONTENTFLY_PROJEKT . '/custom/vendor/autoload.php';
}

/*
 * Die Suite ist ein Einstiegspunkt wie index.php und bin/console.php — also uebergibt sie das
 * Projektverzeichnis genauso (007-001-0002). Ohne diese Zeile wirft jeder Zugriff auf einen
 * Pfad, und genau das soll er: Ein nicht gesetztes Projektverzeichnis ist ein Fehler des
 * Aufrufers, kein Fall fuer einen stillen Standardwert.
 */
\Areanet\PIM\Classes\Kernel\Pfade::setzen(CONTENTFLY_PROJEKT);

require_once CONTENTFLY_PROJEKT . '/lib/contentfly/version.php';
require_once CONTENTFLY_PROJEKT . '/custom/version.php';

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
