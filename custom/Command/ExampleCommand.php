<?php
namespace Custom\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * ExampleCommand
 */
class ExampleCommand extends Command
{
    private \Silex\Application $app;

    public function __construct(\Silex\Application $app)
    {
        $this->app = $app;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setName('example:command:run')
            ->setDescription('Desc');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // do something
        return 0;
    }
}
