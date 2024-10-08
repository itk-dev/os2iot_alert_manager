<?php

namespace App\Service;

use App\Exception\ParsingException;
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
        private array $statuses,
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
     * @throws ParsingException
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
                if (!in_array($app['status'], $this->statuses)) {
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
     * @throws ParsingException
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
     * @param array<Gateway> $gateways
     *   List of gateways (used to enrich the device information)
     *
     * @return Device
     *   Parsed device information object
     *
     * @throws ParsingException
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     */
    public function device(string $content, array $gateways): Device
    {
        $parse = new JsonParser($content);
        $data = $parse->toArray();

        $device = new Device(
            id: $data['id'],
            applicationId: $data['application']['id'],
            createdAt: $this->parseDate($data['createdAt']),
            updatedAt: $this->parseDate($data['createdAt']),
            name: $data['name'],
            location: $data['location'] ? $this->parseLocation($data['location']) : $this->parseLocation(['latitude' => 0, 'longitude' => 0]),
            latestReceivedMessage: $data['latestReceivedMessage'] ? $this->parseMessage($data['latestReceivedMessage'], $gateways) : null,
            statusBattery: $data['lorawanSettings']['deviceStatusBattery'] ?? -1,
            metadata: $this->parseMetadata($data['metadata'] ?? '[]'),
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
     * @return array<Gateway>
     *   An array of parsed gateway information objects
     *
     * @throws ParsingException
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
                if (!in_array($gateway['status'], $this->statuses)) {
                    continue;
                }
            }

            $data[] = $this->gateway(json_encode(['gateway' => $gateway], JSON_UNESCAPED_UNICODE));
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
     * Parse single gateway.
     *
     * @throws ParsingException
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     */
    public function gateway(string $content): Gateway
    {
        $parse = new JsonParser($content);
        $data = $parse->toArray();
        $gateway = $data['gateway'];

        return new Gateway(
            id: $gateway['id'],
            gatewayId: $gateway['gatewayId'],
            createdAt: $this->parseDate($gateway['createdAt']),
            updatedAt: $this->parseDate($gateway['updatedAt']),
            lastSeenAt: $this->parseDate($gateway['lastSeenAt']),
            name: $gateway['name'],
            description: $gateway['description'],
            location: $this->parseLocation($gateway['location']),
            status: $this->statusToEnum($gateway['status']),
            responsibleName: $gateway['gatewayResponsibleName'],
            responsibleEmail: $gateway['gatewayResponsibleEmail'],
            responsiblePhone: $gateway['gatewayResponsiblePhoneNumber'],
            tags: $gateway['tags'],
        );
    }

    /**
     * Parse metadata.
     *
     * @param string $data
     *   Raw metadata from the API
     *
     * @return mixed
     *   JSON decoded data
     *
     * @throws ParsingException
     */
    private function parseMetadata(string $data): mixed
    {
        try {
            $json = json_decode($data, associative: true, flags: JSON_THROW_ON_ERROR);

            return $json ?? [];
        } catch (\JsonException $e) {
            $this->metricsService->counter(
                name: 'api_parse_metadata_error_total',
                help: 'The total number of metadata parsing exceptions',
                labels: ['type' => 'exception']
            );
            throw new ParsingException('Error parsing metadata', $e->getCode(), previous: $e);
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
     * @throws ParsingException
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
        throw new ParsingException('Error parsing location data');
    }

    /**
     * Parse the latest received message.
     *
     * @param array $data
     *   The raw latestReceivedMessage data from the API
     * @param array<Gateway> $gateways
     *   Information about the gateways
     *
     * @return Message
     *   Message object
     *
     * @throws ParsingException
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     */
    private function parseMessage(array $data, array $gateways): Message
    {
        return new Message(
            id: (int) $data['id'],
            createdAt: $this->parseDate($data['createdAt']),
            sentTime: $this->parseDate($data['sentTime']),
            rssi: $data['rssi'] ?? 0,
            snr: $data['snr'] ?? 0,
            rxInfo: isset($data['rawData']['rxInfo']) ? $this->parseRxInfo($data['rawData']['rxInfo'], $gateways) : [],
        );
    }

    /**
     * Parse rx information.
     *
     * @param array $data
     *   The raw rxInfo array from the API
     * @param array<Gateway> $gateways
     *   Information about the gateways
     *
     * @return array<ReceivedInfo>
     *   All the received information metadata
     *
     * @throws ParsingException
     */
    private function parseRxInfo(array $data, array $gateways): array
    {
        $info = [];

        foreach ($data as $rxInfo) {
            $info[] = new ReceivedInfo(
                gatewayId: $rxInfo['gatewayId'] ?? $rxInfo['gatewayID'],
                gatewayName: $this->findGatewayName($rxInfo['gatewayId'] ?? $rxInfo['gatewayID'], $gateways),
                rssi: $rxInfo['rssi'] ?? 0,
                snr: $rxInfo['snr'] ?? ($rxInfo['loRaSNR'] ?? 0),
                crcStatus: $rxInfo['crcStatus'] ?? 'Unknown',
                location: $this->parseLocation($rxInfo['location']),
            );
        }

        return $info;
    }

    /**
     * Find name of the gateway.
     *
     * @param string $gatewayId
     *   EUI/ID of the gateway to find name for
     * @param array $gateways<Gateway>
     *   List of gateways found
     */
    private function findGatewayName(string $gatewayId, array $gateways): string
    {
        /** @var Gateway $gateway */
        foreach ($gateways as $gateway) {
            if ($gateway->gatewayId === $gatewayId) {
                return $gateway->name;
            }
        }

        // Should never happen.
        return 'Name not found';
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
     * @throws ParsingException
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
            throw new ParsingException($errorMsg ?? 'Unknown date conversion error');
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
     * @throws ParsingException
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
            throw new ParsingException("Invalid status value: $status");
        }

        return $value;
    }
}
