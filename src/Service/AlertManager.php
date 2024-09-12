<?php

namespace App\Service;

use App\Model\Gateway;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface as HttpClientTransportExceptionInterface;

final readonly class AlertManager
{
    public function __construct(
        private ApiClient $apiClient,
        private SmsClient $smsClient,
        private MailService $mailService,
        private int $gatewayLimit,
        private string $gatewayFallbackMail,
        private string $gatewayFallbackPhone,
        private string $gatewayBaseUrl,
    ) {
    }

    /**
     * Check gateways.
     *
     * @param \DateTimeImmutable $now
     *   The timestamp to use for "now" time
     * @param bool $filterOnStatus
     *   Indicates whether to filter gateways based on a specific status
     *
     * @throws \App\Exception\MailException
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws TransportExceptionInterface
     */
    /**
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws \App\Exception\MailException
     * @throws \App\Exception\ParsingException
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     * @throws HttpClientTransportExceptionInterface
     */
    public function checkGateways(\DateTimeImmutable $now, bool $filterOnStatus = true): void
    {
        $gateways = $this->apiClient->getGateways($filterOnStatus);
        foreach ($gateways as $gateway) {
            $diff = $this->timeDiffInSeconds($gateway->lastSeenAt, $now);
            if ($diff >= $this->gatewayLimit) {
                $subject = sprintf(
                    'Gateway %s offline siden %s - %d',
                    $gateway->name,
                    $gateway->lastSeenAt->format(\DateTimeInterface::ATOM),
                    $diff
                );
                // Gateway limit for last seen is reached.
                $this->mailService->sendEmail(
                    to: $this->findGatewayToMailAddress($gateway),
                    context: [
                        'gateway' => $gateway,
                        'diff' => $diff,
                        'url' => $this->gatewayBaseUrl.$gateway->gatewayId,
                    ],
                    subject: $subject,
                    htmlTemplate: 'gateway.html.twig',
                    textTemplate: 'gateway.txt.twig',
                );
            }
        }
    }

    /**
     * Get the time differences between two date objects in seconds.
     *
     * @param \DateTimeImmutable $date
     *   The frist date
     * @param \DateTimeImmutable $now
     *   The next date normally "now"
     *
     * @return int
     *   The interval in seconds
     */
    private function timeDiffInSeconds(\DateTimeImmutable $date, \DateTimeImmutable $now): int
    {
        return $date->getTimestamp() - $now->getTimestamp();
    }

    /**
     * Find the "to" e-mail-address with fallback address.
     *
     * @param Gateway $gateway
     *   The gateway to use
     *
     * @return string
     *   The mail address
     */
    private function findGatewayToMailAddress(Gateway $gateway): string
    {
        return $gateway->responsibleEmail ?? $this->gatewayFallbackMail;
    }
}
