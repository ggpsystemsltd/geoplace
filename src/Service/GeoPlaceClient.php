<?php

declare(strict_types=1);

namespace GgpSystems\GeoPlace\Service;

use DateTimeImmutable;
use GgpSystems\GeoPlace\Config\GeoPlaceConfig;
use GgpSystems\GeoPlace\Exception\ApiException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\StreamInterface;

readonly class GeoPlaceClient
{
    public function __construct(
        private GeoPlaceConfig $config,
        private ClientInterface $httpClient,
    ) {}

    public function fetchCouXml(DateTimeImmutable $dateFrom, DateTimeImmutable $dateTo): StreamInterface
    {
        $options = [
            'headers' => [
                'usr_name' => $this->config->usrName,
                'usr_pwd' => $this->config->usrPwd,
            ],
            'query' => [
                'format' => 'xml',
                'authcode' => $this->config->authCode,
                'date_from' => $this->formatDate($dateFrom),
                'date_to' => $this->formatDate($dateTo),
            ],
            'stream' => true,
        ];

        try {
            $response = $this->httpClient->request('GET', $this->config->apiUrl, $options);
        } catch (GuzzleException $e) {
            throw new ApiException(
                sprintf('Upstream API error: %s', $e->getMessage()),
                502
            );
        }

        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new ApiException(
                sprintf('Upstream API returned HTTP %d.', $statusCode),
                502
            );
        }

        return $response->getBody();
    }

    private function formatDate(DateTimeImmutable $date): string
    {
        return $date->format('Y-m-d\TH:i:s.000\Z');
    }
}
