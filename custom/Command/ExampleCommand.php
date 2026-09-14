<?php
namespace Custom\Command;

use Areanet\PIM\Classes\Command\CustomCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A console command of the project — the pattern described in `custom/app.php`.
 *
 * EXTENDS `CustomCommand`, NOT SYMFONY'S `Command` (009-004-0001). Until then it did
 * the latter and expected the application in its constructor. The `ConsoleManager`, however,
 * only accepts `CustomCommand`, and that is why this example was **not registered** in
 * `custom/app.php` — it showed a path the framework does not offer in that form.
 * `technical.md` had recorded the contradiction since Epic 012.
 *
 * THE DECISION WAS TO CONVERT THE EXAMPLE rather than open up the manager. The reason is the
 * `custom:` prefix: `CustomCommand::setName()` prepends it so that a project command never
 * collides with one of the framework's. If the manager were open to any Symfony command, the
 * prefix would no longer be a promise but an offer — and `appcms:install` could be
 * overridden.
 *
 * As a result the command is called `custom:example:command:run`. That is the visible price, and it
 * is intended: the name tells you who the command belongs to.
 *
 * THE APPLICATION NO LONGER COMES FROM THE CONSTRUCTOR but from `anwendung()`. Commands
 * are registered before the application is up — see `ConsoleManager` — so it does not
 * even exist yet at construction time.
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
        // $this->anwendung() returns the application including its container here:
        //
        //     $em = $this->anwendung()['orm.em'];
        //
        $output->writeln('<info>Der Beispiel-Command der Vorlage ist gelaufen.</info>');

        return self::SUCCESS;
    }
}
