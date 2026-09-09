<?php
namespace Custom\Command;

use Areanet\PIM\Classes\Command\CustomCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Ein Console-Command des Projekts — das Muster, das `custom/app.php` beschreibt.
 *
 * ERBT VON `CustomCommand`, NICHT VON SYMFONYS `Command` (009-004-0001). Bis dahin tat es
 * Letzteres und erwartete die Anwendung im Konstruktor. Der `ConsoleManager` nimmt aber
 * ausschliesslich `CustomCommand`, und deshalb war dieses Beispiel in `custom/app.php`
 * **nicht registriert** — es zeigte einen Weg, den das Framework so nicht anbietet.
 * `technical.md` hat den Widerspruch seit Epic 012 festgehalten.
 *
 * ENTSCHIEDEN WURDE, DAS BEISPIEL UMZUSTELLEN statt den Manager zu oeffnen. Der Grund ist der
 * `custom:`-Praefix: `CustomCommand::setName()` stellt ihn voran, damit ein Projekt-Command nie
 * mit einem des Frameworks kollidiert. Waere der Manager fuer jedes Symfony-Command offen, waere
 * der Praefix kein Versprechen mehr, sondern ein Angebot — und `appcms:install` liesse sich
 * ueberschreiben.
 *
 * Der Command heisst dadurch `custom:example:command:run`. Das ist der sichtbare Preis, und er
 * ist gewollt: Am Namen sieht man, wem der Command gehoert.
 *
 * DIE ANWENDUNG KOMMT NICHT MEHR AUS DEM KONSTRUKTOR, sondern aus `anwendung()`. Commands
 * werden registriert, bevor die Anwendung steht — siehe `ConsoleManager` —, also gibt es sie
 * beim Konstruieren noch gar nicht.
 */
class ExampleCommand extends CustomCommand
{
    protected function configure(): void
    {
        $this
            ->setName('example:command:run')
            ->setDescription('Beispiel-Command der Vorlage — tut nichts.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // $this->anwendung() liefert hier die Anwendung samt Container:
        //
        //     $em = $this->anwendung()['orm.em'];
        //
        $output->writeln('<info>Der Beispiel-Command der Vorlage ist gelaufen.</info>');

        return self::SUCCESS;
    }
}
