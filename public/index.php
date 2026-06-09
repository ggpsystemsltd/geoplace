<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

use Dotenv\Dotenv;
use GgpSystems\GeoPlace\Config\GeoPlaceConfig;
use GgpSystems\GeoPlace\Controller\DownloadController;
use GgpSystems\GeoPlace\Service\DateRangeValidator;
use GgpSystems\GeoPlace\Service\GeoPlaceClient;
use GuzzleHttp\Client;
use Symfony\Component\HttpFoundation\Request;

try {
    $dotenv = Dotenv::createImmutable(__DIR__.'/../');
    $dotenv->load();
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Environment configuration error.']);
    exit;
}

$request = Request::createFromGlobals();

try {
    $env = array_filter($_ENV, fn ($key) => is_string($key), ARRAY_FILTER_USE_KEY);
    $config = new GeoPlaceConfig($env);
    $guzzle = new Client;
    $client = new GeoPlaceClient($config, $guzzle);
    $validator = new DateRangeValidator;
    $controller = new DownloadController($client, $validator);

    $response = $controller->handle($request);
    $response->send();
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'An unexpected server error occurred.']);
    exit;
}
