<?php

declare(strict_types=1);

namespace GgpSystems\GeoPlace\Service;

use DateTimeImmutable;
use DateTimeZone;
use GgpSystems\GeoPlace\Exception\ValidationException;

class DateRangeValidator
{
    /**
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    public function validate(?string $dateFromRaw, ?string $dateToRaw): array
    {
        $nowUtc = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        if ($dateFromRaw === null || $dateToRaw === null) {
            $dateFrom = $nowUtc->modify('-60 days')->setTime(0, 0, 0);
            $dateTo = $nowUtc->setTime(0, 0, 0);

            return [$dateFrom, $dateTo];
        }

        $dateFrom = $this->parseDate($dateFromRaw, 'date_from');
        $dateTo = $this->parseDate($dateToRaw, 'date_to');

        if ($dateFrom > $dateTo) {
            throw new ValidationException('date_from must be before or equal to date_to.', 400);
        }

        return [$dateFrom, $dateTo];
    }

    private function parseDate(string $value, string $field): DateTimeImmutable
    {
        $dateTime = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if ($dateTime === false || $dateTime->getLastErrors() !== false) {
            throw new ValidationException(
                sprintf('Invalid format for %s. Use Y-m-d.', $field),
                400
            );
        }

        return $dateTime;
    }
}
