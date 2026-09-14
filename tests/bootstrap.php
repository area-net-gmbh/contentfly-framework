<?php
/**
 * Bootstrap for the test suite.
 *
 * Deliberately **not** `lib/contentfly/bootstrap.php`: that one builds the complete application,
 * requires a configured database and starts a session. For tests that check a single class this
 * is neither necessary nor wanted — a test run that does not start without a running container
 * does not get run.
 *
 * So only the minimum lives here: the autoloader and the constants framework classes expect when
 * they are loaded. Tests that need more fetch it themselves — see `tests/README.md`.
 */

/*
 * The same constant the entry points define (007-001-0002). It used to be called ROOT_DIR and
 * was computed by the framework from its own location; now whoever starts names it — here, the
 * suite.
 */
define('CONTENTFLY_PROJECT_DIR', dirname(__DIR__));

/*
 * ONE autoloader, not two (007-001-0003).
 *
 * `custom/vendor/autoload.php` used to be loaded here as well, mirrored from
 * `lib/contentfly/bootstrap.php` — with the guarantee from `006-004-0001` that the root wins on a
 * shared PSR-4 prefix. With the library package a project has exactly one tree, with the
 * framework inside it as a dependency; there is no precedence left to settle.
 *
 * The mirroring stays deliberate: a test run that loads differently from the application tests a
 * different application.
 */
require_once CONTENTFLY_PROJECT_DIR . '/vendor/autoload.php';

/*
 * The suite is an entry point like index.php and bin/console.php — so it passes the project
 * directory the same way (007-001-0002). Without this line every path access throws, and that is
 * intended: an unset project directory is a mistake of the caller, not a case for a silent
 * default.
 */
\Areanet\PIM\Classes\Kernel\Paths::set(CONTENTFLY_PROJECT_DIR);

require_once CONTENTFLY_PROJECT_DIR . '/lib/contentfly/version.php';
require_once CONTENTFLY_PROJECT_DIR . '/custom/version.php';

/*
 * `lib/contentfly/bootstrap.php` defines these constants from the configuration. Entity classes
 * read them in their mapping (`#[ORM\Column(type: APPCMS_ID_TYPE)]`), so they must be defined
 * before an entity is loaded — otherwise reading the metadata already dies. The values here are
 * test values, not configuration.
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
