<?php

namespace App\Command;

use App\Service\AlertManager;
use ItkDev\MetricsBundle\Service\MetricsService;
use Psr\Log\LoggerInterface;
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
        private readonly LoggerInterface $logger,
        private readonly MetricsService $metricsService,
        private readonly string $timezone,
        private readonly array $statuses,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('date', null, InputOption::VALUE_REQUIRED, 'Override datetime used in checks for testing purposes. Use the format "'.$this->dateFormat.'")')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Run all checks')
            ->addOption('only-applications', null, InputOption::VALUE_NONE, 'Only check applications')
            ->addOption('only-gateways', null, InputOption::VALUE_NONE, 'Only check gateways')
            ->addOption('only-device', null, InputOption::VALUE_NONE, 'Only check devices - requires the --device-id option')
            ->addOption('device-id', null, InputOption::VALUE_REQUIRED, 'Id of the device to check - requires --only-device option', -1)
            ->addOption('no-mails', null, InputOption::VALUE_NONE, 'Do not send mails')
            ->addOption('no-sms', null, InputOption::VALUE_NONE, 'Do not send sms')
            ->addOption('filter-status', null, InputOption::VALUE_NONE, 'Filter based on configured statuses: '.implode(',', $this->statuses))
            ->addOption('override-mail', null, InputOption::VALUE_REQUIRED, 'Override address to send mails to')
            ->addOption('override-phone', null, InputOption::VALUE_REQUIRED, 'Override phone number to send messages to')
            ->addOption('debug', null, InputOption::VALUE_NONE, 'Enable debug mode')
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

        // Get options.
        $all = $input->getOption('all');
        $date = $input->getOption('date');
        $debug = $input->getOption('debug');
        $deviceId = (int) $input->getOption('device-id');
        $filter = $input->getOption('filter-status');
        $noMail = $input->getOption('no-mails');
        $noSms = $input->getOption('no-sms');
        $onlyApps = $input->getOption('only-applications');
        $onlyDevice = $input->getOption('only-device');
        $onlyGateways = $input->getOption('only-gateways');
        $overrideMail = (string) $input->getOption('override-mail');
        $overridePhone = (string) $input->getOption('override-phone');

        $this->metricsService->counter(
            name: 'command_checks_started_total',
            help: 'Command checks started total',
            labels: ['type' => 'info']
        );

        try {
            $now = $this->getDate($date);
            $output->writeln(sprintf('<info>The date used for checking: %s</info>', $now->format($this->dateFormat)));
        } catch (\Throwable $t) {
            $this->log('DateError', $t);

            // Debug re-throw exception.
            if ($debug) {
                throw $t;
            }

            return Command::FAILURE;
        }

        try {
            if ($onlyApps || $all) {
                $this->alertManager->checkApplications($now, $filter, $overrideMail, $overridePhone, noMail: $noMail, noSms: $noSms);
            }

            if ($onlyGateways || $all) {
                $this->alertManager->checkGateways($now, $filter, $overrideMail, $overridePhone, noMail: $noMail, noSms: $noSms);
            }

            if ($onlyDevice) {
                if (-1 === $deviceId) {
                    $io->error('Device id is required');

                    return Command::FAILURE;
                }
                $this->alertManager->checkDevice($now, $deviceId, overrideMail: $overrideMail, overridePhone: $overridePhone, noMail: $noMail, noSms: $noSms);
            }
        } catch (\Throwable $t) {
            $this->log('CheckError', $t);

            // Debug re-throw exception.
            if ($debug) {
                throw $t;
            }

            return Command::FAILURE;
        }

        $this->metricsService->counter(
            name: 'command_checks_completed_total',
            help: 'Command checks completed total',
            labels: ['type' => 'info']
        );

        return Command::SUCCESS;
    }

    /**
     * Handle error.
     *
     * @param string $checkType
     *   The type of check that triggered the issue
     * @param \Throwable $t
     *   The exception caught
     */
    private function log(string $checkType, \Throwable $t): void
    {
        $msg = sprintf('%s ,type: %s, message: "%s"', $checkType, get_class($t), $t->getMessage());
        $this->logger->error($msg);
        $this->metricsService->counter(
            name: 'command_checks_error_total',
            help: 'Command checks errors/exceptions in total',
            labels: ['type' => 'exception']
        );
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
