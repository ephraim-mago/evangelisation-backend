<?php

namespace Framework\Core\Http;

use Throwable;
use Framework\Contracts\Http\Kernel as KernelContract;
use Framework\Routing\Router;
use Framework\Routing\Pipeline;
use Framework\Contracts\Core\Application;
use Framework\Contracts\Debug\ExceptionHandler;

class Kernel implements KernelContract
{
    /**
     * The application implementation.
     *
     * @var \Framework\Contracts\Core\Application
     */
    protected $app;

    /**
     * The router instance.
     *
     * @var \Framework\Routing\Router
     */
    protected $router;

    /**
     * The bootstrap classes for the application.
     *
     * @var string[]
     */
    protected $bootstrappers = [
        \Framework\Core\Bootstrap\LoadEnvironmentVariables::class,
        \Framework\Core\Bootstrap\LoadConfiguration::class,
        // \Framework\Core\Bootstrap\HandleExceptions::class,
        \Framework\Core\Bootstrap\RegisterProviders::class,
        \Framework\Core\Bootstrap\BootProviders::class,
    ];

    /**
     * The application's middleware stack.
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [];

    /**
     * The application's route middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [];

    /**
     * The application's route middleware.
     *
     * @var array<string, class-string|string>
     *
     * @deprecated
     */
    protected $routeMiddleware = [];

    /**
     * The application's middleware aliases.
     *
     * @var array<string, class-string|string>
     */
    protected $middlewareAliases = [];

    /**
     * The priority-sorted list of middleware.
     *
     * Forces non-global middleware to always be in the given order.
     *
     * @var string[]
     */
    protected $middlewarePriority = [];

    /**
     * Create a new HTTP kernel instance.
     *
     * @param  \Framework\Contracts\Core\Application  $app
     * @param  \Framework\Routing\Router  $router
     */
    public function __construct(Application $app, Router $router)
    {
        $this->app = $app;
        $this->router = $router;

        $this->syncMiddlewareToRouter();
    }

    /**
     * Handle an incoming HTTP request.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function handle($request)
    {
        try {
            $response = $this->sendRequestThroughRouter($request);
        } catch (Throwable $e) {
            $this->reportException($e);

            $response = $this->renderException($request, $e);
        }

        return $response;
    }

    /**
     * Send the given request through the middleware / router.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function sendRequestThroughRouter($request)
    {
        $this->app->instance('request', $request);

        $this->bootstrap();

        return (new Pipeline($this->app))
            ->send($request)
            ->through($this->app->shouldSkipMiddleware() ? [] : $this->middleware)
            ->then($this->dispatchToRouter());
    }

    /**
     * Bootstrap the application for HTTP requests.
     *
     * @return void
     */
    public function bootstrap()
    {
        if (! $this->app->hasBeenBootstrapped()) {
            $this->app->bootstrapWith($this->bootstrappers());
        }
    }

    /**
     * Get the route dispatcher callback.
     *
     * @return \Closure
     */
    protected function dispatchToRouter()
    {
        return function ($request) {
            $this->app->instance('request', $request);

            return $this->router->dispatch($request);
        };
    }

    /**
     * Call the terminate method on any terminable middleware.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @param  \Psr\Http\Message\ResponseInterface  $response
     * @return void
     */
    public function terminate($request, $response)
    {
        $this->terminateMiddleware($request, $response);

        $this->app->terminate();
    }

    /**
     * Call the terminate method on any terminable middleware.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @param  \Psr\Http\Message\ResponseInterface  $response
     * @return void
     */
    protected function terminateMiddleware($request, $response)
    {
        $middlewares = $this->app->shouldSkipMiddleware() ? [] : array_merge(
            $this->gatherRouteMiddleware($request),
            $this->middleware
        );

        foreach ($middlewares as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            [$name] = $this->parseMiddleware($middleware);

            $instance = $this->app->make($name);

            if (method_exists($instance, 'terminate')) {
                $instance->terminate($request, $response);
            }
        }
    }

    /**
     * Gather the route middleware for the given request.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @return array
     */
    protected function gatherRouteMiddleware($request)
    {
        if ($route = $request->getAttribute('route')) {
            return $this->router->gatherRouteMiddleware($route);
        }

        return [];
    }

    /**
     * Parse a middleware string to get the name and parameters.
     *
     * @param  string  $middleware
     * @return array
     */
    protected function parseMiddleware($middleware)
    {
        [$name, $parameters] = array_pad(explode(':', $middleware, 2), 2, []);

        if (is_string($parameters)) {
            $parameters = explode(',', $parameters);
        }

        return [$name, $parameters];
    }

    /**
     * Sync the current state of the middleware to the router.
     *
     * @return void
     */
    protected function syncMiddlewareToRouter()
    {
        $this->router->middlewarePriority = $this->middlewarePriority;

        foreach ($this->middlewareGroups as $key => $middleware) {
            $this->router->middlewareGroup($key, $middleware);
        }

        foreach (array_merge($this->routeMiddleware, $this->middlewareAliases) as $key => $middleware) {
            $this->router->aliasMiddleware($key, $middleware);
        }
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

    /**
     * Report the exception to the exception handler.
     *
     * @param  \Throwable  $e
     * @return void
     */
    protected function reportException(Throwable $e)
    {
        $this->app[ExceptionHandler::class]->report($e);
    }

    /**
     * Render the exception to a response.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @param  \Throwable  $e
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function renderException($request, Throwable $e)
    {
        return $this->app[ExceptionHandler::class]->render($request, $e);
    }

    /**
     * Get the Framework application instance.
     *
     * @return \Framework\Contracts\Core\Application
     */
    public function getApplication()
    {
        return $this->app;
    }

    /**
     * Set the Framework application instance.
     *
     * @param  \Framework\Contracts\Core\Application  $app
     * @return $this
     */
    public function setApplication(Application $app)
    {
        $this->app = $app;

        return $this;
    }
}
