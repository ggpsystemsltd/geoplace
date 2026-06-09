<?php

declare(strict_types=1);

namespace GgpSystems\GeoPlace\Tests\Unit;

use GgpSystems\GeoPlace\Config\GeoPlaceConfig;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class GeoPlaceConfigTest extends TestCase
{
    public function test_missing_usr_name_throws(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GEOPLACE_USR_NAME');

        new GeoPlaceConfig([
            'GEOPLACE_USR_PWD' => 'secret',
            'GEOPLACE_AUTHCODE' => '1234',
        ]);
    }

    public function test_missing_usr_pwd_throws(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GEOPLACE_USR_PWD');

        new GeoPlaceConfig([
            'GEOPLACE_USR_NAME' => 'user',
            'GEOPLACE_AUTHCODE' => '1234',
        ]);
    }

    public function test_missing_auth_code_throws(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GEOPLACE_AUTHCODE');

        new GeoPlaceConfig([
            'GEOPLACE_USR_NAME' => 'user',
            'GEOPLACE_USR_PWD' => 'secret',
        ]);
    }

    public function test_empty_value_throws(): void
    {
        $this->expectException(RuntimeException::class);

        new GeoPlaceConfig([
            'GEOPLACE_USR_NAME' => '',
            'GEOPLACE_USR_PWD' => 'secret',
            'GEOPLACE_AUTHCODE' => '1234',
        ]);
    }

    public function test_valid_env_returns_expected_values(): void
    {
        $config = new GeoPlaceConfig([
            'GEOPLACE_API_URL' => 'https://example.com/api',
            'GEOPLACE_USR_NAME' => 'user@example.com',
            'GEOPLACE_USR_PWD' => 'secret',
            'GEOPLACE_AUTHCODE' => '5678',
        ]);

        self::assertSame('https://example.com/api', $config->apiUrl);
        self::assertSame('user@example.com', $config->usrName);
        self::assertSame('secret', $config->usrPwd);
        self::assertSame('5678', $config->authCode);
    }

    public function test_default_api_url_is_used_when_missing(): void
    {
        $config = new GeoPlaceConfig([
            'GEOPLACE_USR_NAME' => 'user',
            'GEOPLACE_USR_PWD' => 'secret',
            'GEOPLACE_AUTHCODE' => '1234',
        ]);

        self::assertSame('https://api.geoplace.co.uk/v1.0/cou', $config->apiUrl);
    }
}
