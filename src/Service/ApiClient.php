<?php

namespace App\Service;

use App\Exception\ParsingExecption;
use App\Model\Application;
use App\Model\Device;
use ItkDev\MetricsBundle\Service\MetricsService;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ApiClient
{
    public function __construct(
        private HttpClientInterface $iotApiClient,
        private MetricsService $metricsService,
        private ApiParser $apiParser,
    ) {
    }

    /**
     * Get all applications.
     *
     * @param bool $filterOnStatus
     *   Filter out applications based on statuses given in configuration
     *
     * @return array<Application>
     *   Parsed applications
     *
     * @throws ParsingExecption
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function getApplications(bool $filterOnStatus): array
    {
        $response = $this->iotApiClient->request('GET', '/api/v1/application', [
            'query' => [
                'offset' => '0',
                'limit' => '500',
            ],
        ]);
        $content = $response->getContent();

        $data = $this->apiParser->applications($content, $filterOnStatus);

        return $data;
    }

    /**
     * Get a single application.
     *
     * @param int $id
     *   ID for the application to fetch
     *
     * @return Application
     *   Parsed application
     *
     * @throws ParsingExecption
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function getApplication(int $id): Application
    {
        $response = $this->iotApiClient->request('GET', '/api/v1/application/'.$id);
        $content = $response->getContent();

        $app = $this->apiParser->application($content);

        return $app;
    }

    public function getDevice(int $id): Device
    {
        $response = $this->iotApiClient->request('GET', '/api/v1/iot-device/'.$id);
        $content = $response->getContent();

        $device = $this->apiParser->device($content);

        return $device;
    }
}
