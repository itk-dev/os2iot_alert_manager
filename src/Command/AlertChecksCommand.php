<?php

namespace App\Command;

use App\Service\AlertManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:alert:checks',
    description: 'Run checks',
)]
class AlertChecksCommand extends Command
{
    private string $dateFormat = 'd-m-y\TH:i:s';

    public function __construct(
        private readonly AlertManager $alertManager,
        private readonly string $timezone,
        private readonly array $statuses,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('date', null, InputOption::VALUE_REQUIRED, 'Overide date used to check (mostly for testing "'.$this->dateFormat.'")')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Run all checks')
            ->addOption('only-applications', null, InputOption::VALUE_NONE, 'Only check applications')
            ->addOption('only-gateways', null, InputOption::VALUE_NONE, 'Only check gateways')
            ->addOption('only-device', null, InputOption::VALUE_NONE, 'Only check devices - requires the --device-id option')
            ->addOption('device-id', null, InputOption::VALUE_REQUIRED, 'Id of the device to check - requires --only-device option', -1)
            ->addOption('only-mails', null, InputOption::VALUE_NONE, 'Only send mails')
            ->addOption('only-sms', null, InputOption::VALUE_NONE, 'Only send sms')
            ->addOption('filter-status', null, InputOption::VALUE_NONE, 'Filter based on configured statuses: '.implode(',', $this->statuses))
            ->addOption('override-mail', null, InputOption::VALUE_REQUIRED, 'Override address to send mails to')
            ->addOption('override-phone', null, InputOption::VALUE_REQUIRED, 'Override phone number to send messages to')
        ;
    }

    /**
     * @todo: Added exception handling and metrics to log errors.
     *
     * @todo: Added metrics on start/completed run.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $date = $input->getOption('date');
        $onlyApps = $input->getOption('only-applications');
        $onlyGateways = $input->getOption('only-gateways');
        $onlyDevice = $input->getOption('only-device');
        $deviceId = (int) $input->getOption('device-id');
        $filter = $input->getOption('filter-status');
        $overrideMail = (string) $input->getOption('override-mail');
        $overridePhone = (string) $input->getOption('override-phone');

        $now = $this->getDate($date);
        $output->writeln(sprintf('<info>The date used for checking: %s</info>', $now->format($this->dateFormat)));

        if ($onlyApps) {
            $this->alertManager->checkApplications($now, $filter, $overrideMail, $overridePhone);
        }

        if ($onlyGateways) {
            $this->alertManager->checkGateways($now, $filter, $overrideMail, $overridePhone);
        }

        if ($onlyDevice) {
            if (-1 === $deviceId) {
                $io->error('Device id is required');

                return Command::FAILURE;
            }
            $this->alertManager->checkDevice($now, $deviceId, null, $overrideMail, $overridePhone);
        }

        return Command::SUCCESS;
    }

    /**
     * Converts a given date string to a \DateTimeImmutable object.
     *
     * @param string|null $date
     *   The date string to convert, or null to use the current date and time
     *
     * @return \DateTimeImmutable
     *   The resulting \DateTimeImmutable object
     *
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     */
    private function getDate(?string $date): \DateTimeImmutable
    {
        $timezone = new \DateTimeZone($this->timezone);
        if (is_null($date)) {
            return new \DateTimeImmutable('now', $timezone);
        }

        return \DateTimeImmutable::createFromFormat($this->dateFormat, $date, $timezone);
    }
}
