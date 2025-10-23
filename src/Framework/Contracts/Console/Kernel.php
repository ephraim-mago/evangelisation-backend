<?php

namespace Framework\Contracts\Console;

use Closure;

interface Kernel
{
    /**
     * Bootstrap the application for artisan commands.
     *
     * @return void
     */
    public function bootstrap();

    /**
     * Handle an incoming console command.
     *
     * @param  \Closure  $callback
     * @return int
     */
    public function handle(Closure $callback);
}
