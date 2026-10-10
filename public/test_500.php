<?php
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

$request = Illuminate\Http\Request::create('/login', 'GET', [], [], [], [
    'HTTP_HOST' => 'gummicubegreeting.com',
    'SERVER_NAME' => 'gummicubegreeting.com',
]);
try {
    $response = $app->handleRequest($request);
    file_put_contents(__DIR__.'/../storage/logs/capture_500.log', 'status=' . $response->getStatusCode() . PHP_EOL, FILE_APPEND);
} catch (Throwable $e) {
    file_put_contents(__DIR__.'/../storage/logs/capture_500.log', 'exception=' . $e->getMessage() . PHP_EOL . $e->getTraceAsString() . PHP_EOL, FILE_APPEND);
    http_response_code(500);
    echo 'CAPTURED: ' . $e->getMessage();
}
