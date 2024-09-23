<?php

namespace App\Command;

use App\Service\ApiClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:api:application',
    description: 'Get a single application from API server',
)]
class ApiApplicationCommand extends Command
{
    public function __construct(
        private readonly ApiClient $apiClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('id', InputArgument::REQUIRED, 'Application ID')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $id = (int) $input->getArgument('id');

        $app = $this->apiClient->getApplication($id);

        $output->writeln(json_encode($app, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $io->success('Successfully fetch data for application');

        return Command::SUCCESS;
    }
}
