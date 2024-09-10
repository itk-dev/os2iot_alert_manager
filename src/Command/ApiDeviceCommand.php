<?php

namespace App\Command;

use App\Service\ApiClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:api:device',
    description: 'Get device from API server',
)]
class ApiDeviceCommand extends Command
{
    public function __construct(
        private readonly ApiClient $apiClient,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('id', null, InputArgument::REQUIRED, 'The id of the device to fetch data for');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $id = $input->getArgument('id');

        $device = $this->apiClient->getDevice($id);
        $output->writeln(json_encode($device, JSON_PRETTY_PRINT));

        $io->success('Successfully fetch data for device');

        return Command::SUCCESS;
    }
}
