<?php

declare(strict_types=1);

use App\Presentation\Controllers\IndexController;
use App\Presentation\Controllers\UserController;

/** @var \Framework\Routing\Router $router */

$router->get('/', IndexController::class);

$router->apiResource('users', UserController::class);

$router->group([
    'prefix' => 'auth',
    'as' => 'auth.',
], base_path('/routes/auth.php'));
