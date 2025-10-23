<?php

namespace Framework\Routing;

use Countable;
use Traversable;
use ArrayIterator;
use IteratorAggregate;
use GuzzleHttp\Psr7\Response;
use Framework\Http\RequestUtils;
use Psr\Http\Message\ServerRequestInterface;
use FastRoute\Dispatcher as FastRouteDispatcher;
use Framework\Http\Exception\NotFoundHttpException;
use Framework\Http\Exception\MethodNotAllowedHttpException;

class RouteCollection implements Countable, IteratorAggregate
{
    /**
     * An array of the routes keyed by method.
     *
     * @var array
     */
    protected $routes = [];

    /**
     * A flattened array of all of the routes.
     *
     * @var \Framework\Routing\Route[]
     */
    protected $allRoutes = [];

    /**
     * A look-up table of routes by their names.
     *
     * @var \Framework\Routing\Route[]
     */
    protected $nameList = [];

    /**
     * A look-up table of routes by controller action.
     *
     * @var \Framework\Routing\Route[]
     */
    protected $actionList = [];

    /**
     * The FastRoute dispatcher.
     *
     * @var \FastRoute\Dispatcher
     */
    protected $dispatcher;

    /**
     * Add a Route instance to the collection.
     *
     * @param  \Framework\Routing\Route  $route
     * @return \Framework\Routing\Route
     */
    public function add(Route $route)
    {
        $this->addToCollections($route);

        $this->addLookups($route);

        return $route;
    }

    /**
     * Add the given route to the arrays of routes.
     *
     * @param  \Framework\Routing\Route  $route
     * @return void
     */
    protected function addToCollections($route)
    {
        $uri = $route->uri();

        foreach ($route->methods() as $method) {
            $this->routes[$method][$uri] = $route;
        }

        $this->allRoutes[$method . $uri] = $route;
    }

    /**
     * Add the route to any look-up tables if necessary.
     *
     * @param  \Framework\Routing\Route  $route
     * @return void
     */
    protected function addLookups($route)
    {
        // If the route has a name, we will add it to the name look-up table so that we
        // will quickly be able to find any route associate with a name and not have
        // to iterate through every route every time we need to perform a look-up.
        if ($name = $route->getName()) {
            $this->nameList[$name] = $route;
        }

        // When the route is routing to a controller we will also store the action that
        // is used by the route. This will let us reverse route to controllers while
        // processing a request and easily generate URLs to the given controllers.
        $action = $route->getAction();

        if (isset($action['controller'])) {
            $this->addToActionList($action, $route);
        }
    }

    /**
     * Add a route to the controller action dictionary.
     *
     * @param  array  $action
     * @param  \Framework\Routing\Route  $route
     * @return void
     */
    protected function addToActionList($action, $route)
    {
        $this->actionList[trim($action['controller'], '\\')] = $route;
    }

    /**
     * Refresh the name look-up table.
     *
     * This is done in case any names are fluently defined or if routes are overwritten.
     *
     * @return void
     */
    public function refreshNameLookups()
    {
        $this->nameList = [];

        foreach ($this->allRoutes as $route) {
            if ($route->getName()) {
                $this->nameList[$route->getName()] = $route;
            }
        }
    }

    /**
     * Refresh the action look-up table.
     *
     * This is done in case any actions are overwritten with new controllers.
     *
     * @return void
     */
    public function refreshActionLookups()
    {
        $this->actionList = [];

        foreach ($this->allRoutes as $route) {
            if (isset($route->getAction()['controller'])) {
                $this->addToActionList($route->getAction(), $route);
            }
        }
    }

    /**
     * Find the first route matching a given request.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @return \Framework\Routing\Route
     *
     * @throws \Framework\Http\Exception\MethodNotAllowedHttpException
     * @throws \Framework\Http\Exception\NotFoundHttpException
     */
    public function match(ServerRequestInterface $request)
    {
        $routeResult = $this->createDispatcher()->dispatch(
            $request->getMethod(),
            RequestUtils::path($request)
        );

        return match ($routeResult[0]) {
            FastRouteDispatcher::NOT_FOUND => throw new NotFoundHttpException(sprintf(
                'The route %s could not be found.',
                $request->getUri()->getPath()
            )),
            FastRouteDispatcher::METHOD_NOT_ALLOWED => $this->getRouteForMethods($request, $routeResult[1]),
            FastRouteDispatcher::FOUND => $this->handleFoundRoute($routeResult)
        };
    }

    /**
     * Get routes from the collection by method.
     *
     * @param  string|null  $method
     * @return \Framework\Routing\Route[]
     */
    public function get($method = null)
    {
        return is_null($method) ? $this->getRoutes() : ($this->routes[$method] ?? []);
    }

    /**
     * Determine if the route collection contains a given named route.
     *
     * @param  string  $name
     * @return bool
     */
    public function hasNamedRoute($name)
    {
        return ! is_null($this->getByName($name));
    }

    /**
     * Get a route instance by its name.
     *
     * @param  string  $name
     * @return \Framework\Routing\Route|null
     */
    public function getByName($name)
    {
        return $this->nameList[$name] ?? null;
    }

    /**
     * Get a route instance by its controller action.
     *
     * @param  string  $action
     * @return \Framework\Routing\Route|null
     */
    public function getByAction($action)
    {
        return $this->actionList[$action] ?? null;
    }

    /**
     * Get all of the routes in the collection.
     *
     * @return \Framework\Routing\Route[]
     */
    public function getRoutes()
    {
        return array_values($this->allRoutes);
    }

    /**
     * Get a route (if necessary) that responds when other available methods are present.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @param  string[]  $methods
     * @return \Framework\Routing\Route
     *
     * @throws \Framework\Http\Exception\MethodNotAllowedHttpException
     */
    protected function getRouteForMethods($request, array $methods)
    {
        if ($request->getMethod() === "OPTIONS") {
            return (new Route(
                'OPTIONS',
                $request->getUri()->getPath(),
                function () use ($methods) {
                    return new Response(
                        200,
                        ['Allow' => implode(',', $methods)],
                        '',
                    );
                }
            ))->bind([]);
        }

        throw new MethodNotAllowedHttpException(
            $methods,
            sprintf(
                'The %s method is not supported for route %s. Supported methods: %s.',
                $request->getMethod(),
                $request->getUri()->getPath(),
                implode(', ', $methods)
            )
        );
    }

    /**
     * Handle a route found by the dispatcher.
     *
     * @param  array  $routeInfo
     * @return \Framework\Routing\Route
     */
    protected function handleFoundRoute($routeInfo): Route
    {
        /**
         * @var \Framework\Routing\Route
         */
        $route = $routeInfo[1];

        return $route->bind($routeInfo[2]);
    }

    /**
     * Create a FastRoute dispatcher instance for the application.
     *
     * @return \FastRoute\Dispatcher
     */
    protected function createDispatcher()
    {
        return $this->dispatcher ?: \FastRoute\simpleDispatcher(function ($r) {
            foreach ($this->getRoutes() as $route) {
                $r->addRoute($route->methods(), $route->uri(), $route);
            }
        });
    }

    /**
     * Get an iterator for the items.
     *
     * @return \ArrayIterator
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->getRoutes());
    }

    /**
     * Count the number of items in the collection.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->getRoutes());
    }
}
