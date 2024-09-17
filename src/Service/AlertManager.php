<?php

namespace App\Service;

use App\Exception\MailException;
use App\Exception\ParsingException;
use App\Model\Application;
use App\Model\Gateway;
use ItkDev\MetricsBundle\Service\MetricsService;
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
        private MetricsService $metricsService,
        private bool $applicationCheckStartDate,
        private int $gatewayLimit,
        private string $gatewayFallbackMail,
        private string $gatewayFallbackPhone,
        private string $gatewayBaseUrl,
        private int $deviceFallbackLimit,
        private string $deviceFallbackMail,
        private string $deviceFallbackPhone,
        private string $deviceMetadataFieldLimit,
        private string $deviceMetadataFieldMail,
        private string $deviceMetadataFieldPhone,
        private string $deviceBaseUrl,
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
     * @throws ClientExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     * @throws MailException
     * @throws ParsingException
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
                    'Gateway "%s" offline siden %s',
                    $gateway->name,
                    $gateway->lastSeenAt->format('d-m-y H:i:s')
                );
                // Gateway limit for last seen is reached.
                $this->mailService->sendEmail(
                    to: $this->findGatewayToMailAddress($gateway),
                    context: [
                        'gateway' => $gateway,
                        'diff' => $diff,
                        'since' => [
                            'hours' => floor($diff / 3600),
                            'minutes' => floor(($diff % 3600) / 60),
                        ],
                        'url' => $this->gatewayBaseUrl.$gateway->gatewayId,
                    ],
                    subject: $subject,
                    htmlTemplate: 'gateway.html.twig',
                    textTemplate: 'gateway.txt.twig',
                );

                // @todo: SMS?
            }
        }
    }

    public function checkApplications(\DateTimeImmutable $now, bool $filterOnStatus = true): void
    {
        $apps = $this->apiClient->getApplications($filterOnStatus);
        foreach ($apps as $app) {
            if ($this->skipBasedOnAppStartDate($app)) {
                continue;
            }

            foreach ($app->devices as $deviceId) {
                $this->checkDevice($now, $deviceId);
            }
        }
    }

    public function checkDevice(\DateTimeImmutable $now, int $deviceId, ?Application $application = null): void
    {
        $device = $this->apiClient->getDevice($deviceId);

        // No message sent from a device, hence no last sent to calculate diff
        // from.
        if (is_null($device->latestReceivedMessage)) {
            $this->metricsService->counter(
                name: 'alter_message_missing_total',
                help: 'Device is missing latest received message',
                labels: [ 'type' => 'info', 'id' => $device->id]
            );
            return;
        }

        // Check timeout.
        $limit = $device->metadata[$this->deviceMetadataFieldLimit] ?? $this->deviceFallbackLimit;
        $diff = $this->timeDiffInSeconds($device->latestReceivedMessage->sentTime, $now);
        if ($diff >= $limit) {
            $toMailAddress = $device->metadata[$this->deviceMetadataFieldMail] ?? ($application->contactEmail ?? $this->deviceFallbackMail);
            $phone = $device->metadata[$this->deviceMetadataFieldPhone] ?? ($application->contactEmail ?? $this->deviceFallbackPhone);

            $subject = sprintf(
                'Enhed "%s" offline siden %s',
                $device->name,
                $device->latestReceivedMessage->sentTime->format('d-m-y H:i:s')
            );
            // Gateway limit for last seen is reached.
            $this->mailService->sendEmail(
                to: $toMailAddress,
                context: [
                    'device' => $device,
                    'since' => [
                        'hours' => floor($diff / 3600),
                        'minutes' => floor(($diff % 3600) / 60),
                    ],
                    'url' => !is_null($application) ? sprintf($this->deviceBaseUrl, $application->id, $device->id) : null,
                ],
                subject: $subject,
                htmlTemplate: 'device.html.twig',
                textTemplate: 'device.txt.twig',
            );

            // @todo: send sms
        }
    }

    /**
     * Get the time differences between two date objects in seconds.
     *
     * @param \DateTimeImmutable $date
     *   The first date
     * @param \DateTimeImmutable $now
     *   The next date normally "now"
     *
     * @return int
     *   The interval in seconds
     */
    private function timeDiffInSeconds(\DateTimeImmutable $date, \DateTimeImmutable $now): int
    {
        return $now->getTimestamp() - $date->getTimestamp();
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

    /**
     * Skip checking application based on start date.
     *
     * @param Application $application
     *   Application to test
     *
     * @return bool
     *   If the current time is before the application's start date, it returns
     *   true to indicate skipping, otherwise, it returns false
     */
    private function skipBasedOnAppStartDate(Application $application): bool
    {
        if ($this->applicationCheckStartDate) {
            if (is_null($application->startDate)) {
                // If no application start date is given, we need to not skip as
                // we do not have data to skip on.
                return false;
            }

            return time() <= $application->startDate->getTimestamp();
        }

        return false;
    }
}
