<?php

namespace App\Service;

use App\Exception\ParsingExecption;
use App\Model\Application;
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
    public function getApplications(bool $filterStatus): array
    {
        $response = $this->iotApiClient->request('GET', '/api/v1/application', [
            'query' => [
                'offset' => '0',
                'limit' => '500',
            ],
        ]);
        $content = $response->getContent();

        $data = $this->apiParser->applications($content, $filterStatus);

        $this->metricsService->gauge(
            name: 'api_fetched_applications',
            help: 'The number of applications fetched.',
            value: count($data)
        );

        return $data;
    }


}
