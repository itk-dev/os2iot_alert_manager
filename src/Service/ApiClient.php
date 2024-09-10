<?php

namespace App\Service;

use App\Exception\ParsingExecption;
use App\Model\Application;
use App\Model\Device;
use App\Model\Gateway;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ApiClient
{
    public function __construct(
        private HttpClientInterface $iotApiClient,
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
                'offset' => 0,
                'limit' => 500,
            ],
        ]);
        $content = $response->getContent();

        return $this->apiParser->applications($content, $filterOnStatus);
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

        return $this->apiParser->application($content);
    }

    /**
     * Fetch a single IoT device.
     *
     * @param int $id
     *   Identifier for the IoT device to retrieve
     *
     * @return Device
     *   Parsed IoT device
     *
     * @throws ParsingExecption
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function getDevice(int $id): Device
    {
        $response = $this->iotApiClient->request('GET', '/api/v1/iot-device/'.$id);
        $content = $response->getContent();

        return $this->apiParser->device($content);
    }

    /**
     * @return array<Gateway>
     *
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     */
    public function getGateways(bool $filterOnStatus): array
    {
        $response = $this->iotApiClient->request('GET', '/api/v1/chirpstack/gateway', [
            'query' => [
                'organizationId' => 2,
                'offset' => 0,
                'limit' => 500,
            ],
        ]);
        $content = $response->getContent();

        return $this->apiParser->gateways($content, $filterOnStatus);
    }
}
