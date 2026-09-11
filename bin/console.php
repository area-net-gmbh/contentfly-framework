<?php
set_time_limit(0);

define('APPCMS_CONSOLE', true);

// Das Projektverzeichnis wird benannt, nicht gerechnet (007-001-0002).
define('CONTENTFLY_PROJEKT', dirname(__DIR__));

require_once __DIR__.'/../lib/contentfly/bootstrap.php';

use Doctrine\DBAL\Tools\Console\ConnectionProvider\SingleConnectionProvider;
use Doctrine\ORM\Tools\Console\EntityManagerProvider\SingleManagerProvider;

// Doctrine-Commands brauchen eine konfigurierte Datenbank. Auf einem frischen Checkout gibt es
// die noch nicht: bootstrap.php registriert DBAL und ORM nur, wenn is_installed true ist. Ohne
// diese Bedingung stirbt die Konsole an $app['db'] — und ausgerechnet `appcms:install`, das die
// Installation erst herstellt, waere nie erreichbar.
if ($app['is_installed']) {

    /*
     * PROVIDER STATT HelperSet (009-005-0003).
     *
     * Bis DBAL 2 bekamen die Commands ihre Verbindung ueber ein HelperSet mit
     * `Doctrine\DBAL\Tools\Console\Helper\ConnectionHelper`. Die Klasse gibt es in DBAL 3 nicht
     * mehr — `bin/console.php` starb daran in Zeile 14, und damit war die ganze Konsole
     * unbenutzbar, `appcms:install` eingeschlossen.
     *
     * An ihre Stelle tritt der ConnectionProvider, den DBAL 3 den Commands in den Konstruktor
     * gibt. Fuer die ORM-Commands gilt dasselbe mit dem EntityManagerProvider; das dortige
     * HelperSet gibt es zwar noch, ist aber deprecated, und zwei Wege nebeneinander waeren
     * einer zu viel.
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
        // FUENF COMMANDS SIND MIT ORM 3 ENTFALLEN (010-003-0002), gezaehlt statt geschaetzt:
        // ConvertDoctrine1Schema, ConvertMapping, EnsureProductionSettings, GenerateEntities
        // und GenerateRepositories. Von den sechzehn frueher registrierten laufen elf weiter.
        //
        // Entfernt statt auskommentiert — wie ImportCommand in 009-005-0003. Ein
        // auskommentierter Command sieht aus wie etwas, das zurueckkommt.
        //
        // Was sie taten und was an ihre Stelle tritt: ConvertMapping und GenerateEntities
        // erzeugten Mapping-Dateien und Entity-Klassen aus einer Datenbank — dieser Weg ist in
        // ORM 3 aufgegeben; GenerateRepositories erzeugte Repository-Ruempfe, die man in einer
        // Zeile selbst schreibt; EnsureProductionSettings prueft Einstellungen, die es so nicht
        // mehr gibt; ConvertDoctrine1Schema stammte aus einer Doctrine-Generation vor dieser.
        new \Doctrine\ORM\Tools\Console\Command\GenerateProxiesCommand($entityManager),
        new \Doctrine\ORM\Tools\Console\Command\InfoCommand($entityManager),
        new \Doctrine\ORM\Tools\Console\Command\RunDqlCommand($entityManager),
        new \Doctrine\ORM\Tools\Console\Command\ValidateSchemaCommand($entityManager),

        // ImportCommand ist in DBAL 3 ersatzlos entfallen (009-005-0003). Es las eine
        // SQL-Datei ein; wer das braucht, nimmt den mysql-Client. Entfernt statt
        // auskommentiert: Ein auskommentierter Command sieht aus wie etwas, das zurueckkommt.
        new \Doctrine\DBAL\Tools\Console\Command\ReservedWordsCommand($verbindung),
        new \Doctrine\DBAL\Tools\Console\Command\RunSqlCommand($verbindung)
    ));
}

$app['console']->run();
