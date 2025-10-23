<?php

declare(strict_types=1);

use Framework\Core\Application;
use Framework\Http\ResponseSender;
use GuzzleHttp\Psr7\ServerRequest;

// Register the Composer autoloader...
require __DIR__ . '/../vendor/autoload.php';

// Bootstrap Framework and handle the request...
/** @var Application $app */
$app = require_once __DIR__ . '/../bootstrap/app.php';

$response = $app->handleRequest(ServerRequest::fromGlobals());

(new ResponseSender())->send($response);
