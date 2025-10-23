<?php

declare(strict_types=1);

use Framework\Core\Application;

$app = new Application(dirname(__DIR__));

$app->singleton(
    \Framework\Contracts\Http\Kernel::class,
    \App\Infrastructure\Http\Kernel::class
);

$app->singleton(
    \Framework\Contracts\Console\Kernel::class,
    \App\Infrastructure\Console\Kernel::class
);

$app->singleton(
    \Framework\Contracts\Debug\ExceptionHandler::class,
    \Framework\Core\Exceptions\Handler::class
);

return $app;
