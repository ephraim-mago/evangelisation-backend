<?php

declare(strict_types=1);

use App\Presentation\Controllers\Auth\AuthenticateUserController;
use App\Presentation\Controllers\Auth\LoginController;
use App\Presentation\Controllers\Auth\LogoutController;

/** @var \Framework\Routing\Router $router */

$router->post('/login', LoginController::class)->name('login');

$router->group([
    'middleware' => 'auth'
], function () use ($router) {
    $router->get('/user', AuthenticateUserController::class)
        ->name('user');

    $router->post('/logout', LogoutController::class)
        ->name('logout');
});
