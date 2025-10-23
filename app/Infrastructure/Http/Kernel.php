<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use Framework\Core\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        \Framework\Http\Middleware\BodyParsingMiddleware::class,
        \Framework\Http\Middleware\HandleCorsMiddleware::class,
        // \Framework\Core\Http\Middleware\ValidatePostSize::class,
        // \App\Http\Middleware\TrimStrings::class,
        // \Framework\Core\Http\Middleware\ConvertEmptyStringsToNull::class,
    ];


    /**
     * The application's route middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        'web' => [],

        'api' => [
            // \Framework\Routing\Middleware\ThrottleRequests::class.':api',
        ],
    ];

    /**
     * The application's middleware aliases.
     *
     * Aliases may be used to conveniently assign middleware to routes and groups.
     *
     * @var array<string, class-string|string>
     */
    protected $middlewareAliases = [
        'auth' => \App\Infrastructure\Http\Middleware\Authenticate::class,
    ];
}
