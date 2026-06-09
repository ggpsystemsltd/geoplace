<?php

declare(strict_types=1);

namespace GgpSystems\GeoPlace\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use GgpSystems\GeoPlace\Exception\ValidationException;
use GgpSystems\GeoPlace\Service\DateRangeValidator;
use PHPUnit\Framework\TestCase;

final class DateRangeValidatorTest extends TestCase
{
    private DateRangeValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new DateRangeValidator;
    }

    public function test_missing_params_returns_default_range(): void
    {
        [$dateFrom, $dateTo] = $this->validator->validate(null, null);

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $expectedFrom = $now->modify('-60 days')->setTime(0, 0, 0);
        $expectedTo = $now->setTime(0, 0, 0);

        self::assertEquals($expectedFrom->format('Y-m-d'), $dateFrom->format('Y-m-d'));
        self::assertEquals($expectedTo->format('Y-m-d'), $dateTo->format('Y-m-d'));
        self::assertSame('00:00:00', $dateFrom->format('H:i:s'));
        self::assertSame('00:00:00', $dateTo->format('H:i:s'));
    }

    public function test_valid_dates_returned(): void
    {
        [$dateFrom, $dateTo] = $this->validator->validate('2024-01-01', '2024-01-31');

        self::assertSame('2024-01-01', $dateFrom->format('Y-m-d'));
        self::assertSame('2024-01-31', $dateTo->format('Y-m-d'));
    }

    public function test_invalid_date_format_throws(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid format for date_from');

        $this->validator->validate('99-99-99', '2024-01-31');
    }

    public function test_invalid_date_to_format_throws(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid format for date_to');

        $this->validator->validate('2024-01-01', 'not-a-date');
    }

    public function test_reversed_range_throws(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('date_from must be before or equal to date_to');

        $this->validator->validate('2024-02-01', '2024-01-01');
    }
}
