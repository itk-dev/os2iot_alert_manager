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
        private readonly array $statuses,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('filter-status', null, InputOption::VALUE_NONE, 'Filter based on configured statuses: '.implode(',', $this->statuses))
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Show details for gateway with this ID', -1)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filter = $input->getOption('filter-status');
        $id = (int) $input->getOption('id');

        $gateways = $this->apiClient->getGateways($filter);

        foreach ($gateways as $gateway) {
            $msg = sprintf("<info>%d (%s) \t- %s (Seen: %s)</info>", $gateway->id, $gateway->gatewayId, $gateway->name, $gateway->lastSeenAt->format('d-m-Y H:i:s'));
            $io->writeln($msg);
        }

        // Show details for one of the gateways.
        if ($id >= 0) {
            $found = false;
            foreach ($gateways as $gateway) {
                if ($gateway->id === $id) {
                    $output->writeln('');
                    $output->writeln(json_encode($gateway, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    $found = true;
                }
            }
            if (!$found) {
                $io->error('Gateway with the id '.$id.' not found');
            }
        }

        $msg = count($gateways);
        if ($filter) {
            $msg .= sprintf(' gateways found (filter on status "%s")', implode(',', $this->statuses));
        } else {
            $msg .= ' applications found';
        }
        $io->success($msg);

        return Command::SUCCESS;
    }
}
