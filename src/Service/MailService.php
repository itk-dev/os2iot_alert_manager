<?php

namespace App\Service;

use App\Exception\MailException;
use ItkDev\MetricsBundle\Service\MetricsService;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final readonly class MailService
{
    public function __construct(
        private MailerInterface $mailer,
        private MetricsService $metricsService,
        private string $fromAddress,
        private string $replyAddress,
        private string $defaultAddress,
    ) {
    }

    /**
     * Sends an email and tracks metrics on success or failure.
     *
     * @param string $to
     *   Recipient email address
     * @param string $subject
     *   Subject of the email
     * @param string $htmlMessage
     *   HTML content of the email
     * @param string $textMsg
     *   Plain text content of the email
     *
     * @throws MailException
     *   If the email cannot be sent
     */
    public function sendEmail(string $to, string $subject, string $htmlMessage, string $textMsg): void
    {
        $email = (new Email())
            ->from($this->fromAddress)
            ->to($to)
            ->cc($this->defaultAddress)
            ->replyTo($this->replyAddress)
            ->priority(Email::PRIORITY_HIGH)
            ->subject($subject)
            ->text($textMsg)
            ->html($htmlMessage);

        try {
            $this->mailer->send($email);
            $this->metricsService->counter(
                name: 'mail_sent_total',
                help: 'The total number of messages sent.',
                labels: ['type' => 'info']
            );
        } catch (TransportExceptionInterface $e) {
            $this->metricsService->counter(
                name: 'mail_sent_exception_total',
                help: 'The total number of messages failed to send.',
                labels: ['type' => 'exception']
            );
            throw new MailException('Unable to send notification mail', $e->getCode(), $e);
        }
    }
}
