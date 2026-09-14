<?php
set_time_limit(0);

/*
 * Built the same way as index.php (007-001-0003): load the autoloader, name the project
 * directory, call Start. No path into the framework code. Start defines `APPCMS_CONSOLE` itself.
 */
require_once dirname(__DIR__) . '/vendor/autoload.php';

$app = \Areanet\PIM\Classes\Kernel\Start::console(dirname(__DIR__));

use Doctrine\DBAL\Tools\Console\ConnectionProvider\SingleConnectionProvider;
use Doctrine\ORM\Tools\Console\EntityManagerProvider\SingleManagerProvider;

// Doctrine commands need a configured database. A fresh checkout does not have one yet:
// bootstrap.php only registers DBAL and ORM when is_installed is true. Without this condition the
// console dies on $app['db'] — and `appcms:install`, of all commands, which creates the
// installation in the first place, would never be reachable.
if ($app['is_installed']) {

    /*
     * PROVIDERS INSTEAD OF A HelperSet (009-005-0003).
     *
     * Up to DBAL 2 the commands got their connection through a HelperSet with
     * `Doctrine\DBAL\Tools\Console\Helper\ConnectionHelper`. That class no longer exists in
     * DBAL 3 — `bin/console.php` died on it in line 14, which made the whole console unusable,
     * `appcms:install` included.
     *
     * It is replaced by the ConnectionProvider that DBAL 3 passes to the commands' constructors.
     * The same applies to the ORM commands with the EntityManagerProvider; the HelperSet there
     * still exists but is deprecated, and two ways side by side would be one too many.
     */
    $verbindung   = new SingleConnectionProvider($app['db']);
    $entityManager = new SingleManagerProvider($app['orm.em']);

    $app['console']->addCommands(array(
        new \Doctrine\ORM\Tools\Console\Command\ClearCache\MetadataCommand($entityManager),
        new \Doctrine\ORM\Tools\Console\Command\ClearCache\QueryCommand($entityManager),
        new \Doctrine\ORM\Tools\Console\Command\ClearCache\ResultCommand($entityManager),
        new \Doctrine\ORM\Tools\Console\Command\SchemaTool\CreateCommand($entityManager),
        new \Doctrine\ORM\Tools\Console\Command\SchemaTool\DropCommand($entityManager),
        new \Doctrine\ORM\Tools\Console\Command\SchemaTool\UpdateCommand($entityManager),
        // FIVE COMMANDS WERE REMOVED WITH ORM 3 (010-003-0002), counted, not estimated:
        // ConvertDoctrine1Schema, ConvertMapping, EnsureProductionSettings, GenerateEntities
        // and GenerateRepositories. Of the sixteen previously registered, eleven keep working.
        //
        // Removed rather than commented out — like ImportCommand in 009-005-0003. A
        // commented-out command looks like something that is coming back.
        //
        // What they did and what replaces them: ConvertMapping and GenerateEntities generated
        // mapping files and entity classes from a database — ORM 3 abandoned that approach;
        // GenerateRepositories generated repository stubs you write yourself in one line;
        // EnsureProductionSettings checks settings that no longer exist in that form;
        // ConvertDoctrine1Schema dates from a Doctrine generation before this one.
        new \Doctrine\ORM\Tools\Console\Command\GenerateProxiesCommand($entityManager),
        new \Doctrine\ORM\Tools\Console\Command\InfoCommand($entityManager),
        new \Doctrine\ORM\Tools\Console\Command\RunDqlCommand($entityManager),
        new \Doctrine\ORM\Tools\Console\Command\ValidateSchemaCommand($entityManager),

        // ImportCommand was removed from DBAL 3 without replacement (009-005-0003). It read an
        // SQL file; whoever needs that uses the mysql client. Removed rather than commented
        // out: a commented-out command looks like something that is coming back.
        new \Doctrine\DBAL\Tools\Console\Command\ReservedWordsCommand($verbindung),
        new \Doctrine\DBAL\Tools\Console\Command\RunSqlCommand($verbindung)
    ));
}

$app['console']->run();
