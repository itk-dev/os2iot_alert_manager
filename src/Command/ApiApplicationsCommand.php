<?php

namespace App\Command;

use App\Service\ApiClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:api:applications',
    description: 'Get applications from API server',
)]
class ApiApplicationsCommand extends Command
{
    public function __construct(
        private readonly ApiClient $apiClient,
        private readonly array $statuses,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('filterStatus', null, InputOption::VALUE_NONE, 'Filter based on configured statuses: '.implode(',', $this->statuses))
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filter = $input->getOption('filterStatus');

        $apps = $this->apiClient->getApplications($filter);

        foreach ($apps as $app) {
            $msg = sprintf("<info>%d \t- %s (devices: %d)</info>", $app->id, $app->name, count($app->devices));
            $io->writeln($msg);
        }

        $msg = count($apps);
        if ($filter) {
            $msg .= sprintf(' applications found (filter on status "%s")', implode(',', $this->statuses));
        } else {
            $msg .= ' applications found';
        }
        $io->success($msg);

        return Command::SUCCESS;
    }
}
