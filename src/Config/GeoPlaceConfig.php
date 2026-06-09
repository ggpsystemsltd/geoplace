<?php

declare(strict_types=1);

namespace GgpSystems\GeoPlace\Config;

use RuntimeException;

final readonly class GeoPlaceConfig
{
    public string $apiUrl;

    public string $usrName;

    public string $usrPwd;

    public string $authCode;

    /**
     * @param  array<string, mixed>  $env
     */
    public function __construct(array $env)
    {
        $apiUrl = $env['GEOPLACE_API_URL'] ?? null;
        $this->apiUrl = \is_string($apiUrl) && $apiUrl !== '' ? $apiUrl : 'https://api.geoplace.co.uk/v1.0/cou';
        $this->usrName = $this->requireString($env, 'GEOPLACE_USR_NAME');
        $this->usrPwd = $this->requireString($env, 'GEOPLACE_USR_PWD');
        $this->authCode = $this->requireString($env, 'GEOPLACE_AUTHCODE');
    }

    /**
     * @param  array<string, mixed>  $env
     */
    private function requireString(array $env, string $key): string
    {
        $value = $env[$key] ?? null;

        if (! \is_string($value) || $value === '') {
            throw new RuntimeException(
                sprintf('Required environment variable "%s" is missing or empty.', $key)
            );
        }

        return $value;
    }
}
