<?php

declare(strict_types=1);

namespace GgpSystems\GeoPlace\Tests\Unit;

use DateTimeImmutable;
use GgpSystems\GeoPlace\Controller\DownloadController;
use GgpSystems\GeoPlace\Exception\ApiException;
use GgpSystems\GeoPlace\Exception\ValidationException;
use GgpSystems\GeoPlace\Service\DateRangeValidator;
use GgpSystems\GeoPlace\Service\GeoPlaceClient;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DownloadControllerTest extends TestCase
{
    public function test_valid_request_returns_streamed_response(): void
    {
        $client = $this->createMock(GeoPlaceClient::class);
        $client->method('fetchCouXml')->willReturn(Utils::streamFor('<xml>test</xml>'));

        $validator = $this->createMock(DateRangeValidator::class);
        $validator->method('validate')->willReturn([
            new DateTimeImmutable('2024-01-01'),
            new DateTimeImmutable('2024-01-31'),
        ]);

        $controller = new DownloadController($client, $validator);
        $request = Request::create('/?date_from=2024-01-01&date_to=2024-01-31');

        $response = $controller->handle($request);

        self::assertInstanceOf(StreamedResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/xml', $response->headers->get('Content-Type'));
        self::assertStringContainsString('attachment', $response->headers->get('Content-Disposition') ?? '');
        self::assertStringContainsString('geoplace_2024-01-31T00-00-00.000Z.xml', $response->headers->get('Content-Disposition') ?? '');
    }

    public function test_validation_exception_returns400_json(): void
    {
        $client = $this->createMock(GeoPlaceClient::class);
        $validator = $this->createMock(DateRangeValidator::class);
        $validator->method('validate')->willThrowException(
            new ValidationException('Invalid date format. Use Y-m-d.', 400)
        );

        $controller = new DownloadController($client, $validator);
        $request = Request::create('/?date_from=bad');

        $response = $controller->handle($request);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertSame('{"error":"Invalid date format. Use Y-m-d."}', $response->getContent());
    }

    public function test_api_exception_returns502_json(): void
    {
        $client = $this->createMock(GeoPlaceClient::class);
        $client->method('fetchCouXml')->willThrowException(
            new ApiException('Upstream API returned HTTP 500.', 502)
        );

        $validator = $this->createMock(DateRangeValidator::class);
        $validator->method('validate')->willReturn([
            new DateTimeImmutable('2024-01-01'),
            new DateTimeImmutable('2024-01-31'),
        ]);

        $controller = new DownloadController($client, $validator);
        $request = Request::create('/');

        $response = $controller->handle($request);

        self::assertSame(502, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertSame('{"error":"Upstream API returned HTTP 500."}', $response->getContent());
    }

    public function test_unexpected_exception_returns500_json(): void
    {
        $client = $this->createMock(GeoPlaceClient::class);
        $client->method('fetchCouXml')->willThrowException(new \RuntimeException('Boom'));

        $validator = $this->createMock(DateRangeValidator::class);
        $validator->method('validate')->willReturn([
            new DateTimeImmutable('2024-01-01'),
            new DateTimeImmutable('2024-01-31'),
        ]);

        $controller = new DownloadController($client, $validator);
        $request = Request::create('/');

        $response = $controller->handle($request);

        self::assertSame(500, $response->getStatusCode());
        self::assertSame('application/json', $response->headers->get('Content-Type'));
        self::assertSame('{"error":"An unexpected error occurred."}', $response->getContent());
    }
}
