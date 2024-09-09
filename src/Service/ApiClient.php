<?php

namespace App\Service;

use App\Model\Application;
use App\Model\DataTypes\Status;
use Cerbero\JsonParser\JsonParser;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ApiClient
{
    public function __construct(
        private readonly HttpClientInterface $iotApiClient,
    )
    {
    }

    /**
     * @return array<Application>
     *
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

        $data = [];

        $parse = new JsonParser($content);
        $parse->pointer('/data/-');

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
                status: Status::NONE, //$app['status'],
                contactPerson: $app['contactPerson'],
                contactEmail: $app['contactEmail'],
                contactPhone: $app['contactPhone'],
                devices: $devices,
            );
        }

        return $data;
    }

    private function parseDate(?string $dateString): \DateTimeImmutable {
        // @todo: find better solution.
        if (is_null($dateString)) {
            return new \DateTimeImmutable('1970-01-01 00:00:00');
        }
        $format = 'Y-m-d\TH:i:s.u\Z';
        $date = \DateTimeImmutable::createFromFormat($format, $dateString);
        if ($date === false) {
            // Handle error
            $errors = \DateTime::getLastErrors();
            throw new \Exception($errors['error_message'] ?? '');
        }

        return $date;
    }
}
