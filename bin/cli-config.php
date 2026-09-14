<?php
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
