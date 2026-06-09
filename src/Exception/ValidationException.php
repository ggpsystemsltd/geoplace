<?php

declare(strict_types=1);

namespace GgpSystems\GeoPlace\Exception;

use RuntimeException;

final class ValidationException extends RuntimeException
{
    public function __construct(string $message, int $code = 400)
    {
        parent::__construct($message, $code);
    }
}
