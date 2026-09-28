<?php

/*
 * COMMAND LINE ONLY — CHECKED BEFORE ANYTHING IS LOADED (015-000-0004).
 *
 * In the documented layout `bin/` sits next to `index.php` in the document root, and until now
 * nothing kept the web off it. Under a web SAPI with `register_argc_argv` — PHP's own default
 * whenever no `php.ini` is loaded, as in the official Docker images — Symfony's `ArgvInput`
 * reads `$_SERVER['argv']` out of the request. Every registered command therefore ran without
 * any authentication: `appcms:setup`, `dbal:run-sql`, `orm:run-dql`, `orm:schema-tool:drop`.
 *
 * THE CHECK SITS BEFORE THE AUTOLOADER, not behind it. What stops here has loaded no framework
 * code at all — and needs none in order to refuse.
 *
 * `Start::console()` checks the same thing again. That is not duplication but a division of
 * labour: here an entry point answers the web, there the kernel refuses any future caller.
 */
if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    http_response_code(403);
    exit(1);
}

/**
 * Entry point for Doctrine's own `vendor/bin/doctrine`.
 *
 * UNTIL 010-003-0002 THIS SAID `ConsoleRunner::createHelperSet($app['orm.em'])`. The method was
 * removed with ORM 3 — it belonged to the HelperSet approach that `009-005-0003` had already
 * replaced with providers for the application's own console. ORM 3 expects an
 * `EntityManagerProvider` at this point.
 *
 * WHO NEEDS THIS FILE: `vendor/bin/doctrine`, not `bin/console.php`. The application's own
 * console registers its Doctrine commands itself and never passes through here.
 */

use Doctrine\ORM\Tools\Console\EntityManagerProvider\SingleManagerProvider;

/*
 * Built the same way as index.php and bin/console.php (007-001-0003).
 */
require_once dirname(__DIR__) . '/vendor/autoload.php';

$app = \Areanet\PIM\Classes\Kernel\Start::console(dirname(__DIR__));

return new SingleManagerProvider($app['orm.em']);
