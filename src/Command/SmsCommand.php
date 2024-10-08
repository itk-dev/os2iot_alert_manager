<?php

namespace App\Command;

use App\Service\SmsClientInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:sms:test',
    description: 'Send test SMS',
)]
class SmsCommand extends Command
{
    public function __construct(
        private readonly SmsClientInterface $smsClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('to', InputArgument::REQUIRED, 'The phone number to send SMS to')
            ->addOption('msg', 'm', InputOption::VALUE_REQUIRED, 'Message to send', 'This is an IoT Alert Manager test')
            ->addOption('flash', 'f', InputOption::VALUE_NONE, 'Send as flash message')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $to = (int) $input->getArgument('to');
        $msg = $input->getOption('msg');
        $flash = $input->getOption('flash');

        $id = $this->smsClient->send([$to], $msg, $flash);

        $io->success('Successfully send SMS with id: '.$id);

        return Command::SUCCESS;
    }
}
