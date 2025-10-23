<?php

namespace Framework\Core\Providers;

use Closure;
use Framework\Support\ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The callback that should be used to load the application's routes.
     *
     * @var \Closure|null
     */
    protected $loadRoutesUsing;

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->booted(function () {
            $this->loadRoutes();

            $this->app->booted(function () {
                $this->app['router']->getRoutes()->refreshNameLookups();
                $this->app['router']->getRoutes()->refreshActionLookups();
            });
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        //
    }

    /**
     * Register the callback that will be used to load the application's routes.
     *
     * @param  \Closure  $routesCallback
     * @return $this
     */
    protected function routes(Closure $routesCallback)
    {
        $this->loadRoutesUsing = $routesCallback;

        return $this;
    }

    /**
     * Load the application routes.
     *
     * @return void
     */
    protected function loadRoutes()
    {
        if (! is_null($this->loadRoutesUsing)) {
            $this->app->call($this->loadRoutesUsing);
        }
    }
}
