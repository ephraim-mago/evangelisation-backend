#!/usr/bin/env php
<?php

declare(strict_types=1);

use Framework\Core\Application;

// Register the Composer autoloader...
require __DIR__ . '/../vendor/autoload.php';

// Bootstrap Application and handle the command...
/** @var Application $app */
$app = require_once __DIR__ . '/../bootstrap/app.php';

$kernel = $app->make(Framework\Contracts\Console\Kernel::class);

return $kernel;
