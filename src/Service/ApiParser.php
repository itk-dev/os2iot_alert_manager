<?php

namespace App\Service;

use App\Exception\ParsingExecption;
use App\Model\Application;
use App\Model\DataTypes\Location;
use App\Model\DataTypes\Message;
use App\Model\DataTypes\ReceivedInfo;
use App\Model\DataTypes\Status;
use App\Model\Device;
use Cerbero\JsonParser\JsonParser;
use ItkDev\MetricsBundle\Service\MetricsService;

final readonly class ApiParser
{
    public function __construct(
        private MetricsService $metricsService,
        private array $applicationStatus,
        private string $fromTimeZone,
        private string $timezone,
        private string $timeformat
    )
    {
    }

    /**
     * Get applications.
     *
     * @param string $content
     *   Application data from the API.
     * @param bool $filterOnStatus
     *   Should we filter on the configured status.
     *
     * @return Array<Application>
     *   All found applications.
     *
     * @throws ParsingExecption
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     */
    public function applications(string $content, bool $filterOnStatus = false): array {
        $parse = new JsonParser($content);
        $parse->pointer('/data/-');

        $data = [];

        /** @var  $app<string, mixed> */
        foreach ($parse as $app) {
            // Filter out applications with status not in the configuration.
            if ($filterOnStatus) {
                if (!in_array($app['status'], $this->applicationStatus)) {
                    continue;
                }
            }

            $devices = [];
            foreach ($app['iotDevices'] as $dev) {
                $devices[] = $dev['id'];
            }
            $data[] = new Application(
                id: (int) $app['id'],
                createdAt: $this->parseDate($app['createdAt']),
                updatedAt: $this->parseDate($app['updatedAt']),
                startDate: $app['startDate'] ? $this->parseDate($app['startDate']) : null,
                endDate: $app['endDate'] ? $this->parseDate($app['endDate']) : null,
                name: $app['name'],
                status: $this->statusToEnum($app['status']),
                contactPerson: $app['contactPerson'],
                contactEmail: $app['contactEmail'],
                contactPhone: $app['contactPhone'],
                devices: $devices,
            );
        }

        return $data;
    }

    /**
     * Parse a single device's information.
     *
     * @param string $content
     *   The device information from the API.
     *
     * @return Device
     *   Parsed device information object.
     *
     * @throws ParsingExecption
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     */
    public function device(string $content): Device
    {
        $parse = new JsonParser($content);
        $data = $parse->toArray();

        return new Device(
            id: $data['id'],
            createdAt: $this->parseDate($data['createdAt']),
            updatedAt: $this->parseDate($data['createdAt']),
            name: $data['id'],
            location: $this->parseLocation($data['location']),
            latestReceivedMessage: $this->parseMessage($data['latestReceivedMessage']),
            statusBattery: $data['lorawanSettings']['deviceStatusBattery'] ?? -1,
            // @todo: Parse metatdata when exsamples sesor is given.
            metadata: []//$data['metadata'],
        );
    }

    /**
     * Parse location information.
     *
     * @param array $data
     *   Raw location data from the API.
     *
     * @return Location
     *  Location object.
     */
    private function parseLocation(array $data): Location
    {
        return new Location(
            latitude: end($data['coordinates']),
            longitude: reset($data['coordinates']),
        );
    }

    /**
     * Parse lastest received message.
     *
     * @param array $data
     *   The raw latestReceivedMessage data from the API.
     *
     * @return Message
     *   Message object.
     *
     * @throws ParsingExecption
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     */
    private function parseMessage(array $data): Message
    {
        return new Message(
            id: (int) $data['id'],
            createdAt: $this->parseDate($data['createdAt']),
            sentTime: $this->parseDate($data['sentTime']),
            rssi: $data['rssi'],
            snr: $data['snr'],
            rxInfo: $this->parseRxInfo($data['rawData']['rxInfo']),
        );
    }

    /**
     * Parse rx information.
     *
     * @param array $data
     *   The raw rxInfo array from the API.
     *
     * @return array<ReceivedInfo>
     *   All the received information metadata.
     *
     */
    private function parseRxInfo(array $data): array
    {
        $info = [];

        foreach ($data as $rxInfo) {
            $info[] = new ReceivedInfo(
               gatewayId: $rxInfo['gatewayId'],
               rssi: $rxInfo['rssi'],
               snr: $rxInfo['snr'],
               crcStatus: $rxInfo['crcStatus'],
               location: $this->parseLocation(['coordinates' => $rxInfo['location']]),
            );
        }

        return $info;
    }

    /**
     * Parse date into DateTime object.
     *
     * @param string|null $dateString
     *   The date/time from the API. If null, the date is assumed to be unix
     *   zero.
     * @return \DateTimeImmutable
     *   The parsed date as an object.
     *
     * @throws ParsingExecption
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     */
    private function parseDate(?string $dateString): \DateTimeImmutable
    {
        if (is_null($dateString)) {
            return new \DateTimeImmutable('1970-01-01 00:00:00', new \DateTimeZone($this->timezone));
        }

        $date = \DateTimeImmutable::createFromFormat($this->timeformat, $dateString, new \DateTimeZone($this->fromTimeZone));
        if ($date === false) {
            $errorMsg = null;
            $errors = \DateTime::getLastErrors();
            if (is_array($errors)) {
                foreach ($errors['errors'] as $error) {
                    $errorMsg .= $error . PHP_EOL;
                }
            }
            $this->metricsService->counter(
                name: 'api_parse_date_error_total',
                help: 'The total number of date parsing exceptions',
                labels: ['type' => 'exception']
            );
            throw new ParsingExecption($errorMsg ?? 'Unknown data conversion error');
        }

        return $date->setTimezone(new \DateTimeZone($this->timezone));
    }

    /**
     * Convert string to status enum.
     *
     * @param string|null $status
     *   Status from the API.
     *
     * @return Status
     *   The status as a status enum.
     *
     * @throws ParsingExecption
     *   If the status is unknown.
     */
    private function statusToEnum(?string $status): Status
    {
        if (is_null($status)) {
            return Status::NONE;
        }
        $value = Status::tryFrom($status);
        if (is_null($value)) {
            $this->metricsService->counter(
                name: 'api_parse_status_invalid_total',
                help: 'The total number of invalid status exceptions',
                labels: ['type' => 'exception']
            );
            throw new ParsingExecption("Invalid status value: $status");
        }

        return $value;
    }
}
