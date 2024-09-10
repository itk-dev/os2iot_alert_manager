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
    )
    {
    }

    /**
     * @return array<Application>
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

        $this->metricsService->gauge(
            name: 'api_fetched_applications',
            help: 'The number of applications fetched.',
            value: count($data)
        );

        return $data;
    }

    public function getDevices(Application $application, bool $filterOnStatus)
    {

    }

    public function getDevice(int $id): Device
    {
        $response = $this->iotApiClient->request('GET', '/api/v1/iot-device/' . $id);
        $content = $response->getContent();


        $device = $this->apiParser->device($content);

        // @todo: add metrics

        return $device;
    }

}
