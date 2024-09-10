<?php

namespace App\Service;

use App\Exception\ParsingExecption;
use App\Model\Application;
use App\Model\DataTypes\Location;
use App\Model\DataTypes\Message;
use App\Model\DataTypes\ReceivedInfo;
use App\Model\DataTypes\Status;
use App\Model\Device;
use App\Model\Gateway;
use Cerbero\JsonParser\JsonParser;
use ItkDev\MetricsBundle\Service\MetricsService;

final readonly class ApiParser
{
    public function __construct(
        private MetricsService $metricsService,
        private array $applicationStatus,
        private string $fromTimeZone,
        private string $timezone,
        private string $timeformat,
    ) {
    }

    /**
     * Get applications.
     *
     * @param string $content
     *   Application data from the API
     * @param bool $filterOnStatus
     *   Should we filter on the configured status
     *
     * @return array<Application>
     *   All found applications
     *
     * @throws ParsingExecption
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     */
    public function applications(string $content, bool $filterOnStatus = false): array
    {
        $parse = new JsonParser($content);
        $parse->pointer('/data/-');

        $data = [];

        /** @var array<string, mixed> $app */
        foreach ($parse as $app) {
            // Filter out applications with status not in the configuration.
            if ($filterOnStatus) {
                if (!in_array($app['status'], $this->applicationStatus)) {
                    continue;
                }
            }

            $data[] = $this->application(json_encode($app, JSON_UNESCAPED_UNICODE));
        }

        $this->metricsService->gauge(
            name: 'api_parsed_applications',
            help: 'The number of applications fetched.',
            value: count($data),
            labels: ['type' => 'info']
        );

        return $data;
    }

    /**
     * Parse a single application.
     *
     * @param string $content
     *   Raw application data from API
     *
     * @return Application
     *   Parsed application
     *
     * @throws ParsingExecption
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     */
    public function application(string $content): Application
    {
        $parse = new JsonParser($content);
        $data = $parse->toArray();

        $devices = [];
        foreach ($data['iotDevices'] as $dev) {
            $devices[] = $dev['id'];
        }
        $app = new Application(
            id: (int) $data['id'],
            createdAt: $this->parseDate($data['createdAt']),
            updatedAt: $this->parseDate($data['updatedAt']),
            startDate: $data['startDate'] ? $this->parseDate($data['startDate']) : null,
            endDate: $data['endDate'] ? $this->parseDate($data['endDate']) : null,
            name: $data['name'],
            status: $this->statusToEnum($data['status']),
            contactPerson: $data['contactPerson'],
            contactEmail: $data['contactEmail'],
            contactPhone: $data['contactPhone'],
            devices: $devices,
        );

        $this->metricsService->counter(
            name: 'api_parsed_applications_total',
            help: 'The total number of applications parsed.',
            labels: ['type' => 'info']
        );

        return $app;
    }

    /**
     * Parse a single device's information.
     *
     * @param string $content
     *   The device information from the API
     *
     * @return Device
     *   Parsed device information object
     *
     * @throws ParsingExecption
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     */
    public function device(string $content): Device
    {
        $parse = new JsonParser($content);
        $data = $parse->toArray();

        $device = new Device(
            id: $data['id'],
            createdAt: $this->parseDate($data['createdAt']),
            updatedAt: $this->parseDate($data['createdAt']),
            name: $data['name'],
            location: $this->parseLocation($data['location']),
            latestReceivedMessage: $this->parseMessage($data['latestReceivedMessage']),
            statusBattery: $data['lorawanSettings']['deviceStatusBattery'] ?? -1,
            // @todo: Parse metadata when examples is given.
            metadata: $this->parseMetadata($data['metadata']),
        );

        $this->metricsService->counter(
            name: 'api_parsed_devices_total',
            help: 'The total number of devices parsed.',
            labels: ['type' => 'info']
        );

        return $device;
    }

    /**
     * Parse multiple gateway's information.
     *
     * @param string $content
     *   The gateway information from the API
     * @param bool $filterOnStatus
     *   Determines whether to filter out applications with a status not in the configuration
     *
     * @return array
     *   An array of parsed gateway information objects
     *
     * @throws ParsingExecption
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     */
    public function gateways(string $content, bool $filterOnStatus = false): array
    {
        $parse = new JsonParser($content);
        $parse->pointer('/resultList/-');

        $data = [];

        /** @var array<string, mixed> $gateway */
        foreach ($parse as $gateway) {
            // Filter out applications with status not in the configuration.
            if ($filterOnStatus) {
                if (!in_array($gateway['status'], $this->applicationStatus)) {
                    continue;
                }
            }

            $data[] = new Gateway(
                id: $gateway['id'],
                gatewayId: $gateway['gatewayId'],
                createdAt: $this->parseDate($gateway['createdAt']),
                updatedAt: $this->parseDate($gateway['updatedAt']),
                lastSeenAt: $this->parseDate($gateway['lastSeenAt']),
                name: $gateway['name'],
                description: $gateway['description'],
                location: $this->parseLocation($gateway['location']),
                status: $this->statusToEnum($gateway['status']),
            );
        }

        $this->metricsService->gauge(
            name: 'api_parsed_gateways',
            help: 'The number of gateways fetched.',
            value: count($data),
            labels: ['type' => 'info']
        );

        return $data;
    }

    /**
     * Parse metadata.
     *
     * @TODO: better parsing, when better metadata is available.
     *
     * @param string $data
     *   Raw metadata from the API
     *
     * @return mixed
     *   JSON decoded data
     *
     * @throws ParsingExecption
     */
    private function parseMetadata(string $data): mixed
    {
        try {
            return json_decode($data, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->metricsService->counter(
                name: 'api_parse_metadata_error_total',
                help: 'The total number of metadata parsing exceptions',
                labels: ['type' => 'exception']
            );
            throw new ParsingExecption('Error parsing metadata', $e->getCode(), previous: $e);
        }
    }

    /**
     * Parse location information.
     *
     * @param array $data
     *   Raw location data from the API
     *
     * @return Location
     *  Location object
     *
     * @throws ParsingExecption
     */
    private function parseLocation(array $data): Location
    {
        if (isset($data['coordinates']) && is_array($data['coordinates']) && 2 == count($data['coordinates'])) {
            return new Location(
                latitude: end($data['coordinates']),
                longitude: reset($data['coordinates']),
            );
        } elseif (isset($data['latitude']) && isset($data['longitude'])) {
            return new Location(
                latitude: $data['latitude'],
                longitude: $data['longitude'],
            );
        }
        $this->metricsService->counter(
            name: 'api_parse_location_error_total',
            help: 'The total number of location parsing exceptions',
            labels: ['type' => 'exception']
        );
        throw new ParsingExecption('Error parsing location data');
    }

    /**
     * Parse lastest received message.
     *
     * @param array $data
     *   The raw latestReceivedMessage data from the API
     *
     * @return Message
     *   Message object
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
     *   The raw rxInfo array from the API
     *
     * @return array<ReceivedInfo>
     *   All the received information metadata
     *
     * @throws ParsingExecption
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
                location: $this->parseLocation($rxInfo['location']),
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
     *
     * @return \DateTimeImmutable
     *   The parsed date as an object
     *
     * @throws ParsingExecption
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     */
    private function parseDate(?string $dateString): \DateTimeImmutable
    {
        $timezone = new \DateTimeZone($this->timezone);
        if (is_null($dateString)) {
            return new \DateTimeImmutable('1970-01-01 00:00:00', $timezone);
        }

        $date = \DateTimeImmutable::createFromFormat($this->timeformat, $dateString, new \DateTimeZone($this->fromTimeZone));
        if (false === $date) {
            $errorMsg = null;
            $errors = \DateTime::getLastErrors();
            if (is_array($errors)) {
                foreach ($errors['errors'] as $error) {
                    $errorMsg .= $error.PHP_EOL;
                }
            }
            $this->metricsService->counter(
                name: 'api_parse_date_error_total',
                help: 'The total number of date parsing exceptions',
                labels: ['type' => 'exception']
            );
            throw new ParsingExecption($errorMsg ?? 'Unknown data conversion error');
        }

        return $date->setTimezone($timezone);
    }

    /**
     * Convert string to status enum.
     *
     * @param string|null $status
     *   Status from the API
     *
     * @return Status
     *   The status as a status enum
     *
     * @throws ParsingExecption
     *   If the status is unknown
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
