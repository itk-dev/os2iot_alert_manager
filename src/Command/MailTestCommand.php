<?php

namespace App\Command;

use App\Service\MailService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:mail:test',
    description: 'Send test e-mail',
)]
class MailTestCommand extends Command
{
    public function __construct(
        private readonly MailService $mailService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('to', InputArgument::REQUIRED, 'The phone number to send SMS to')
            ->addOption('message', 'm', InputOption::VALUE_REQUIRED, 'Message to send', 'This is an IoT alter manager test')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $to = $input->getArgument('to');
        $msg = $input->getOption('message');

        $date = new \DateTimeImmutable(timezone: new \DateTimeZone('Europe/Copenhagen'));
        $date = $date->modify('-1 hour');

        $this->mailService->sendEmail(
            to: $to,
            context: [
                'name' => '"TEST TEST"',
                'seenAgo' => '3600',
                'lastSeenDate' => $date,
                'description' => $msg,
                'location' => [
                    'latitude' => 56.153540,
                    'longitude' => 10.214136,
                ],
                'battery' => 67.234643,
            ],
        );

        $io->success('Successfully send mail');

        return Command::SUCCESS;
    }
}
