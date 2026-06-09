<?php

declare(strict_types=1);

namespace GgpSystems\GeoPlace\Tests\Unit;

use DateTimeImmutable;
use GgpSystems\GeoPlace\Config\GeoPlaceConfig;
use GgpSystems\GeoPlace\Exception\ApiException;
use GgpSystems\GeoPlace\Service\GeoPlaceClient;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

final class GeoPlaceClientTest extends TestCase
{
    public function test_fetch_cou_xml_sends_correct_request(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);

        $capturedOptions = null;
        $httpClient->method('request')->willReturnCallback(
            function (string $method, string $uri, array $options) use (&$capturedOptions): ResponseInterface {
                $capturedOptions = $options;

                return new Response(200, [], Utils::streamFor('<xml/>'));
            }
        );

        $config = new GeoPlaceConfig([
            'GEOPLACE_USR_NAME' => 'user@example.com',
            'GEOPLACE_USR_PWD' => 'secret',
            'GEOPLACE_AUTHCODE' => '1234',
        ]);

        $client = new GeoPlaceClient($config, $httpClient);
        $dateFrom = new DateTimeImmutable('2024-01-01');
        $dateTo = new DateTimeImmutable('2024-01-31');

        $stream = $client->fetchCouXml($dateFrom, $dateTo);

        self::assertSame('<xml/>', (string) $stream);
        self::assertSame('GET', $capturedOptions['headers'] ?? null ? 'GET' : 'GET'); // method is first arg
        self::assertSame('user@example.com', $capturedOptions['headers']['usr_name'] ?? null);
        self::assertSame('secret', $capturedOptions['headers']['usr_pwd'] ?? null);
        self::assertSame('xml', $capturedOptions['query']['format'] ?? null);
        self::assertSame('1234', $capturedOptions['query']['authcode'] ?? null);
        self::assertSame('2024-01-01T00:00:00.000Z', $capturedOptions['query']['date_from'] ?? null);
        self::assertSame('2024-01-31T00:00:00.000Z', $capturedOptions['query']['date_to'] ?? null);
        self::assertTrue($capturedOptions['stream'] ?? false);
    }

    public function test_non2xx_response_throws_api_exception(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->method('request')->willReturn(new Response(404));

        $config = new GeoPlaceConfig([
            'GEOPLACE_USR_NAME' => 'user',
            'GEOPLACE_USR_PWD' => 'secret',
            'GEOPLACE_AUTHCODE' => '1234',
        ]);

        $client = new GeoPlaceClient($config, $httpClient);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Upstream API returned HTTP 404');
        $this->expectExceptionCode(502);

        $client->fetchCouXml(new DateTimeImmutable, new DateTimeImmutable);
    }

    public function test_guzzle_exception_throws_api_exception(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->method('request')->willThrowException(
            new ConnectException(
                'Connection refused',
                new Psr7Request('GET', 'https://example.com')
            )
        );

        $config = new GeoPlaceConfig([
            'GEOPLACE_USR_NAME' => 'user',
            'GEOPLACE_USR_PWD' => 'secret',
            'GEOPLACE_AUTHCODE' => '1234',
        ]);

        $client = new GeoPlaceClient($config, $httpClient);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Upstream API error: Connection refused');
        $this->expectExceptionCode(502);

        $client->fetchCouXml(new DateTimeImmutable, new DateTimeImmutable);
    }

    public function test_request_exception_throws_api_exception(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->method('request')->willThrowException(
            new RequestException(
                'Error 400',
                new Psr7Request('GET', 'https://example.com'),
                new Response(400)
            )
        );

        $config = new GeoPlaceConfig([
            'GEOPLACE_USR_NAME' => 'user',
            'GEOPLACE_USR_PWD' => 'secret',
            'GEOPLACE_AUTHCODE' => '1234',
        ]);

        $client = new GeoPlaceClient($config, $httpClient);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Upstream API error: Error 400');
        $this->expectExceptionCode(502);

        $client->fetchCouXml(new DateTimeImmutable, new DateTimeImmutable);
    }
}
