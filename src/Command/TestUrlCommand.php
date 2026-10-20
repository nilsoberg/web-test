<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

#[AsCommand(
    name: 'app:test-url',
    description: 'Test the default URI setting is correct'
)]
class TestUrlCommand extends Command
{
    public function __construct(
        private readonly RouterInterface $router,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $ui = new SymfonyStyle($input, $output);
        $url = $this->router->generate('home', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $ui->writeln(sprintf('Generated URL: <info>%s</info>', $url));
        return Command::SUCCESS;
    }
}
