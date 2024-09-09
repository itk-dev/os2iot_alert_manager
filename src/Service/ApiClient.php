<?php

namespace App\Service;

use Cerbero\JsonParser\JsonParser;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ApiClient
{
    public function __construct(
        private readonly HttpClientInterface $iotApiClient,
    )
    {
    }

    public function getApplications(): void
    {
        $response = $this->iotApiClient->request('GET', '/api/v1/application', [
            'query' => [
                'offset' => '0',
                'limit' => '500',
            ],
        ]);

        $content = $response->getContent();

        $data = [];

        $parse = new JsonParser($content);
        $parse->pointer('/data/-');

        foreach ($parse as $value) {
            
        }

    }
}
