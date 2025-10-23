<?php

declare(strict_types=1);

namespace App\Infrastructure\Providers;

use App\Domain\Entity\User;
use Framework\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->bind(User::class, function ($app) {
            $user = $app->make('request')->getAttribute('user');

            return $user ?? new User();
        });
    }
}
