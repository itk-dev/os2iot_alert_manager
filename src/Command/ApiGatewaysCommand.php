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
    name: 'app:api:gateways',
    description: 'Get gateways from API server',
)]
class ApiGatewaysCommand extends Command
{
    public function __construct(
        private readonly ApiClient $apiClient,
        private readonly array $applicationStatus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('filterStatus', null, InputOption::VALUE_NONE, 'Filter based on configured statuses: '.implode(',', $this->applicationStatus))
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filter = $input->getOption('filterStatus');

        $gateways = $this->apiClient->getGateways($filter);

        foreach ($gateways as $gateway) {
            $msg = sprintf("<info>%d (%s) \t- %s (Seen: %s) %s</info>", $gateway->id, $gateway->gatewayId, $gateway->name, $gateway->lastSeenAt->format('d-m-Y H:i:s'), $gateway->status->value);
            $io->writeln($msg);
        }

        $msg = count($gateways);
        if ($filter) {
            $msg .= sprintf(' gateways found (filter on status "%s")', implode(',', $this->applicationStatus));
        } else {
            $msg .= ' applications found';
        }
        $io->success($msg);

        return Command::SUCCESS;
    }
}
