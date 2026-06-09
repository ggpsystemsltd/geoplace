<?php

declare(strict_types=1);

namespace GgpSystems\GeoPlace\Controller;

use GgpSystems\GeoPlace\Exception\ApiException;
use GgpSystems\GeoPlace\Exception\ValidationException;
use GgpSystems\GeoPlace\Service\DateRangeValidator;
use GgpSystems\GeoPlace\Service\GeoPlaceClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class DownloadController
{
    public function __construct(
        private GeoPlaceClient $client,
        private DateRangeValidator $validator,
    ) {}

    public function handle(Request $request): Response
    {
        try {
            $dateFromRaw = $request->query->get('date_from');
            $dateToRaw = $request->query->get('date_to');

            $dateFromRaw = \is_string($dateFromRaw) && $dateFromRaw !== '' ? $dateFromRaw : null;
            $dateToRaw = \is_string($dateToRaw) && $dateToRaw !== '' ? $dateToRaw : null;

            [$dateFrom, $dateTo] = $this->validator->validate($dateFromRaw, $dateToRaw);

            $stream = $this->client->fetchCouXml($dateFrom, $dateTo);

            $filename = sprintf(
                'geoplace_%s.xml',
                $dateTo->format('Y-m-d\TH-i-s.000\Z')
            );

            return new StreamedResponse(
                function () use ($stream): void {
                    while (! $stream->eof()) {
                        echo $stream->read(8192);

                        if (connection_status() !== CONNECTION_NORMAL) {
                            break;
                        }
                    }
                },
                Response::HTTP_OK,
                [
                    'Content-Type' => 'text/xml',
                    'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
                ]
            );
        } catch (ValidationException $e) {
            return new JsonResponse(['error' => $e->getMessage()], $e->getCode() ?: 400);
        } catch (ApiException $e) {
            return new JsonResponse(['error' => $e->getMessage()], $e->getCode() ?: 502);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => 'An unexpected error occurred.'], 500);
        }
    }
}
