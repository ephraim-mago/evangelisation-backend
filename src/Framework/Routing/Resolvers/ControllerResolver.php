<?php

namespace Framework\Routing\Resolvers;

use Framework\Collection\Arr;
use Framework\Routing\Route;
use Framework\Container\Container;
use Framework\Routing\Utils\FiltersControllerMiddleware;

class ControllerResolver
{
    use FiltersControllerMiddleware, ResolvesRouteDependencies;

    /**
     * The container instance.
     *
     * @var \Framework\Container\Container
     */
    protected $container;

    /**
     * Create a new controller dispatcher instance.
     *
     * @param  \Framework\Container\Container  $container
     * @return void
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Dispatch a request to a given controller and method.
     *
     * @param  \Framework\Routing\Route  $route
     * @param  mixed  $controller
     * @param  string  $method
     * @return mixed
     */
    public function dispatch(Route $route, $controller, $method)
    {
        $parameters = $this->resolveParameters($route, $controller, $method);

        if (method_exists($controller, 'callAction')) {
            return $controller->callAction($method, $parameters);
        }

        return $controller->{$method}(...array_values($parameters));
    }

    /**
     * Resolve the parameters for the controller.
     *
     * @param  \Framework\Routing\Route  $route
     * @param  mixed  $controller
     * @param  string  $method
     * @return array
     */
    protected function resolveParameters(Route $route, $controller, $method)
    {
        return $this->resolveClassMethodDependencies(
            $route->parametersWithoutNulls(),
            $controller,
            $method
        );
    }

    /**
     * Get the middleware for the controller instance.
     *
     * @param  \Framework\Routing\Controller  $controller
     * @param  string  $method
     * @return array
     */
    public function getMiddleware($controller, $method)
    {
        if (! method_exists($controller, 'getMiddleware')) {
            return [];
        }

        $middlewares = Arr::where(
            $controller->getMiddleware(),
            fn($data) => !static::methodExcludedByOptions(
                $method,
                $data['options']
            )
        );

        return Arr::pluck($middlewares, 'middleware');
    }
}
