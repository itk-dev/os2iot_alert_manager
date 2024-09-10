<?php

namespace App\Service;

use App\Exception\SmsException;
use ItkDev\MetricsBundle\Service\MetricsService;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberUtil;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * SMS client.
 *
 * @see https://pushapi.ecmr.biz/docs/index.html?url=/swagger/v1/swagger.json#tag/SMS-gateway
 */
final readonly class SmsClient
{
    public function __construct(
        private HttpClientInterface $smsClient,
        private MetricsService $metricsService,
        private string $uri,
        private string $gatewayId,
        private bool $highPriority,
        private bool $dryRun,
    ) {
    }

    /**
     * Send SMS.
     *
     * @param array $to
     *   Array of phone numbers to send SMS to
     * @param string $message
     *   The message to send
     * @param bool $flash
     *   If true, a flash message is sent if support else converted to regular
     *   sms (default: false)
     *
     * @return int
     *   The batch number from the gateway or -1 on dry-runs
     *
     * @throws SmsException
     * @throws \DateMalformedStringException
     */
    public function send(array $to, string $message, bool $flash = false): int
    {
        foreach ($to as $no) {
            if (!$this->validatePhoneNumber($no)) {
                throw new SmsException('Invalid phone number: '.$no);
            }
        }

        if ($this->dryRun) {
            $this->metricsService->counter(
                name: 'sms_send_total',
                help: 'The total number of SMS\'s send',
                labels: ['type' => 'dry-run']
            );

            return -1;
        }

        try {
            $time = new \DateTimeImmutable(timezone: new \DateTimeZone('UTC'));
            $response = $this->smsClient->request('POST', $this->uri.$this->gatewayId, [
                'json' => [
                    'body' => $message,
                    'flash' => $flash ? 'true' : 'false',
                    'highPriority' => $this->highPriority ? 'true' : 'false',
                    'validityMinutes' => 60,
                    'sendAtUtc' => $time->format(\DateTime::ATOM),
                    'to' => $to,
                ],
            ]);
            $batchId = $response->getContent();
        } catch (TransportExceptionInterface|ServerExceptionInterface|RedirectionExceptionInterface|ClientExceptionInterface $e) {
            throw new SmsException($e->getMessage(), $e->getCode(), $e);
        }

        $this->metricsService->counter(
            name: 'sms_send_total',
            help: 'The total number of SMS\'s send',
            labels: ['type' => 'info']
        );

        return (int) $batchId;
    }

    /**
     * Validates a phone number for a given region.
     *
     * @param string $phoneNumber
     *    The phone number to validate
     * @param string $region
     *    The region code, default is 'DK'
     *
     * @return bool
     *    True if the phone number is valid, false otherwise
     */
    private function validatePhoneNumber(string $phoneNumber, string $region = 'DK'): bool
    {
        $phoneUtil = PhoneNumberUtil::getInstance();

        try {
            $numberProto = $phoneUtil->parse($phoneNumber, $region);

            return $phoneUtil->isValidNumber($numberProto);
        } catch (NumberParseException $e) {
            return false;
        }
    }
}
