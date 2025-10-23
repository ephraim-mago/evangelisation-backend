<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use Framework\Core\Providers\RouteServiceProvider as ServiceProvider;
use Framework\Routing\Router;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     */
    public function boot(): void
    {
        $this->routes(function (Router $router) {
            $router->group(
                [
                    'middleware' => 'api',
                    'prefix' => 'api'
                ],
                base_path('routes/api.php')
            );

            $router->group(
                [
                    'middleware' => 'web',
                ],
                base_path('routes/web.php')
            );
        });
    }
}
