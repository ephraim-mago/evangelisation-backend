<?php

declare(strict_types=1);

namespace Framework\Core\Console;

use Closure;
use Throwable;
use Framework\Contracts\Core\Application;
use Framework\Contracts\Console\Kernel as KernelContract;

class Kernel implements KernelContract
{
    /**
     * The application implementation.
     *
     * @var \Framework\Contracts\Core\Application
     */
    protected $app;

    /**
     * The bootstrap classes for the application.
     *
     * @var string[]
     */
    protected $bootstrappers = [
        \Framework\Core\Bootstrap\LoadEnvironmentVariables::class,
        \Framework\Core\Bootstrap\LoadConfiguration::class,
        // \Framework\Core\Bootstrap\HandleExceptions::class,
        // \Framework\Core\Bootstrap\RegisterFacades::class,
        // \Framework\Core\Bootstrap\SetRequestForConsole::class,
        \Framework\Core\Bootstrap\RegisterProviders::class,
        \Framework\Core\Bootstrap\BootProviders::class,
    ];

    /**
     * Create a new console kernel instance.
     *
     * @param  \Framework\Contracts\Core\Application  $app
     * @return void
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Run the console application.
     *
     * @param \Closure $callback
     * @return int
     */
    public function handle(Closure $callback): int
    {
        try {
            $this->bootstrap();

            return $callback($this->app);
        } catch (Throwable $e) {
            echo $e->getMessage();

            return 1;
        } finally {
            $this->app->terminate();
        }
    }

    /**
     * Bootstrap the application for artisan commands.
     *
     * @return void
     */
    public function bootstrap()
    {
        if (! $this->app->hasBeenBootstrapped()) {
            $this->app->bootstrapWith($this->bootstrappers());
        }

        $this->app->loadDeferredProviders();
    }

    /**
     * Get the bootstrap classes for the application.
     *
     * @return array
     */
    protected function bootstrappers()
    {
        return $this->bootstrappers;
    }
}
