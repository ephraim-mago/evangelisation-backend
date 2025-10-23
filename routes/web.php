<?php

declare(strict_types=1);

use App\Presentation\Controllers\IndexController;

/** @var \Framework\Routing\Router $router */

$router->get('/', IndexController::class);
