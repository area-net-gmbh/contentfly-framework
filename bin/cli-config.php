<?php
/**
 * Einstiegspunkt fuer Doctrines eigenes `vendor/bin/doctrine`.
 *
 * BIS 010-003-0002 STAND HIER `ConsoleRunner::createHelperSet($app['orm.em'])`. Die Methode
 * ist mit ORM 3 entfallen — sie gehoerte zum HelperSet-Weg, den schon `009-005-0003` fuer die
 * eigene Konsole durch Provider ersetzt hat. ORM 3 erwartet an dieser Stelle einen
 * `EntityManagerProvider`.
 *
 * WER DIESE DATEI BRAUCHT: `vendor/bin/doctrine`, nicht `bin/console.php`. Die eigene Konsole
 * registriert ihre Doctrine-Commands selbst und kommt hier nie vorbei.
 */

use Doctrine\ORM\Tools\Console\EntityManagerProvider\SingleManagerProvider;

/*
 * Gleich gebaut wie index.php und bin/console.php (007-001-0003).
 */
require_once dirname(__DIR__) . '/vendor/autoload.php';

$app = \Areanet\PIM\Classes\Kernel\Start::konsole(dirname(__DIR__));

return new SingleManagerProvider($app['orm.em']);
