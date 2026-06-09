<?php

declare(strict_types=1);

namespace GgpSystems\GeoPlace\Exception;

use RuntimeException;

final class ApiException extends RuntimeException
{
    public function __construct(string $message, int $code = 502)
    {
        parent::__construct($message, $code);
    }
}
