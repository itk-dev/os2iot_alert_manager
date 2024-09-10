<?php

namespace App\Service;

use App\Exception\ParsingExecption;
use App\Model\Application;
use App\Model\DataTypes\Status;
use Cerbero\JsonParser\JsonParser;
use ItkDev\MetricsBundle\Service\MetricsService;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ApiClient
{
    public function __construct(
        private readonly HttpClientInterface $iotApiClient,
        private readonly MetricsService $metricsService,
    )
    {
    }

    /**
     * @return array<Application>
     *
     * @throws ParsingExecption
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function getApplications(): array
    {
        $response = $this->iotApiClient->request('GET', '/api/v1/application', [
            'query' => [
                'offset' => '0',
                'limit' => '500',
            ],
        ]);
        $content = $response->getContent();

        $parse = new JsonParser($content);
        $parse->pointer('/data/-');

        $data = [];

        /** @var  $app<string, mixed> */
        foreach ($parse as $app) {
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

        $this->metricsService->gauge(
            name: 'api_fetched_applications',
            help: 'The number of applications fetched.',
            value: count($data)
        );

        return $data;
    }

    /**
     * Parse date into DateTime object.
     *
     * @TODO: move date format etc. into configuration.
     *
     * @param string|null $dateString
     *   The date/time from the API. If null, the date is assumed to be unix
     *   zero.
     * @return \DateTimeImmutable
     *   The parsed date as an object.
     *
     * @throws ParsingExecption
     */
    private function parseDate(?string $dateString): \DateTimeImmutable {
        if (is_null($dateString)) {
            return new \DateTimeImmutable('1970-01-01 00:00:00');
        }
        $format = 'Y-m-d\TH:i:s.u\Z';
        $date = \DateTimeImmutable::createFromFormat($format, $dateString);
        if ($date === false) {
            $errorMsg = null;
            $errors = \DateTime::getLastErrors();
            if (is_array($errors)) {
                foreach ($errors as $error) {
                    $errorMsg .= reset($error['warnings']) . PHP_EOL;
                }
            }
            $this->metricsService->counter(
                name: 'api_parse_date_error_total',
                help: 'The total number of date parsing exceptions',
                labels: ['type' => 'exception']
            );
            throw new ParsingExecption($errorMsg ?? 'Unknown data conversion error');
        }

        return $date;
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
