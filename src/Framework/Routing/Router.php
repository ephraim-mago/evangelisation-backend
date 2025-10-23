<?php

declare(strict_types=1);

namespace Framework\Routing;

use Closure;
use Framework\Support\Str;
use Framework\Collection\Arr;
use Framework\Container\Container;
use Framework\Http\ResponseFactory;
use Framework\Routing\Utils\RouteGroup;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Framework\Routing\Utils\SortedMiddleware;
use Framework\Routing\Utils\MiddlewareNameResolver;

class Router
{
    /**
     * The IoC container instance.
     *
     * @var \Framework\Container\Container
     */
    protected $container;

    /**
     * The route collection instance.
     *
     * @var \Framework\Routing\RouteCollection
     */
    protected $routes;

    /**
     * The currently dispatched route instance.
     *
     * @var \Framework\Routing\Route|null
     */
    protected $current;

    /**
     * All of the short-hand keys for middlewares.
     *
     * @var array
     */
    protected $middleware = [];

    /**
     * All of the middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [];

    /**
     * The priority-sorted list of middleware.
     *
     * Forces the listed middleware to always be in the given order.
     *
     * @var array
     */
    public $middlewarePriority = [];

    /**
     * The route group attribute stack.
     *
     * @var array
     */
    protected $groupStack = [];

    /**
     * All of the verbs supported by the router.
     *
     * @var string[]
     */
    public static $verbs = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

    /**
     * Create a new Router instance.
     *
     * @param  \Framework\Container\Container|null  $container
     */
    public function __construct(?Container $container = null)
    {
        $this->routes = new RouteCollection;
        $this->container = $container ?: new Container;
    }

    /**
     * Register a new GET route with the router.
     *
     * @param  string  $uri
     * @param  array|string|callable|null  $action
     * @return \Framework\Routing\Route
     */
    public function get($uri, $action = null): Route
    {
        return $this->addRoute(['GET', 'HEAD'], $uri, $action);
    }

    /**
     * Register a new POST route with the router.
     *
     * @param  string  $uri
     * @param  array|string|callable|null  $action
     * @return \Framework\Routing\Route
     */
    public function post($uri, $action = null): Route
    {
        return $this->addRoute('POST', $uri, $action);
    }

    /**
     * Register a new PUT route with the router.
     *
     * @param  string  $uri
     * @param  array|string|callable|null  $action
     * @return \Framework\Routing\Route
     */
    public function put($uri, $action = null): Route
    {
        return $this->addRoute('PUT', $uri, $action);
    }

    /**
     * Register a new PATCH route with the router.
     *
     * @param  string  $uri
     * @param  array|string|callable|null  $action
     * @return \Framework\Routing\Route
     */
    public function patch($uri, $action = null): Route
    {
        return $this->addRoute('PATCH', $uri, $action);
    }

    /**
     * Register a new DELETE route with the router.
     *
     * @param  string  $uri
     * @param  array|string|callable|null  $action
     * @return \Framework\Routing\Route
     */
    public function delete($uri, $action = null): Route
    {
        return $this->addRoute('DELETE', $uri, $action);
    }

    /**
     * Register a new OPTIONS route with the router.
     *
     * @param  string  $uri
     * @param  array|string|callable|null  $action
     * @return \Framework\Routing\Route
     */
    public function options($uri, $action = null): Route
    {
        return $this->addRoute('OPTIONS', $uri, $action);
    }

    /**
     * Register a new route responding to all verbs.
     *
     * @param  string  $uri
     * @param  array|string|callable|null  $action
     * @return \Framework\Routing\Route
     */
    public function any($uri, $action = null): Route
    {
        return $this->addRoute(self::$verbs, $uri, $action);
    }

    /**
     * Register a new route with the given verbs.
     *
     * @param  array|string  $methods
     * @param  string  $uri
     * @param  array|string|callable|null  $action
     * @return \Framework\Routing\Route
     */
    public function match($methods, $uri, $action = null): Route
    {
        return $this->addRoute(
            array_map(strtoupper(...), (array) $methods),
            $uri,
            $action
        );
    }

    /**
     * Route an API resource to a controller.
     *
     * @param  string  $name
     * @param  string  $controller
     * @param  array{middleware: array|string, where: array}  $options
     * @return void
     */
    public function apiResource(string $name, string $controller, array $options = []): void
    {
        $sigleName = Str::singular($name);

        $this->group(array_merge(
            [
                'prefix' => $name,
                'as' => $name . '.',
                'controller' => $controller
            ],
            $options
        ), function (Router $router) use ($sigleName) {
            $router->get('/', 'index')->name('index');
            $router->post('/', 'store')->name('store');
            $router->get(
                "/{{$sigleName}}",
                'show'
            )->name('show');
            $router->match(
                ['PUT', 'PATCH'],
                "/{{$sigleName}}",
                'update'
            )->name('update');
            $router->delete(
                "/{{$sigleName}}",
                'destroy'
            )->name('destroy');
        });
    }

    /**
     * Create a route group with shared attributes.
     *
     * @param  array  $attributes
     * @param  \Closure|array|string  $routes
     * @return $this
     */
    public function group(array $attributes, Closure|array|string $routes): static
    {
        foreach (Arr::wrap($routes) as $groupRoutes) {
            $this->updateGroupStack($attributes);

            // Once we have updated the group stack, we'll load the provided routes and
            // merge in the group's attributes when the routes are created. After we
            // have created the routes, we will pop the attributes off the stack.
            $this->loadRoutes($groupRoutes);

            array_pop($this->groupStack);
        }

        return $this;
    }

    /**
     * Update the group stack with the given attributes.
     *
     * @param  array  $attributes
     * @return void
     */
    protected function updateGroupStack(array $attributes): void
    {
        if ($this->hasGroupStack()) {
            $attributes = $this->mergeWithLastGroup($attributes);
        }

        $this->groupStack[] = $attributes;
    }

    /**
     * Merge the given array with the last group stack.
     *
     * @param  array  $new
     * @param  bool  $prependExistingPrefix
     * @return array
     */
    public function mergeWithLastGroup($new, $prependExistingPrefix = true): array
    {
        return RouteGroup::merge($new, end($this->groupStack), $prependExistingPrefix);
    }

    /**
     * Load the provided routes.
     *
     * @param  \Closure|string  $routes
     * @return void
     */
    protected function loadRoutes($routes): void
    {
        if ($routes instanceof Closure) {
            $routes($this);
        } else {
            $router = $this;

            require $routes;
        }
    }

    /**
     * Get the prefix from the last group on the stack.
     *
     * @return string
     */
    public function getLastGroupPrefix(): mixed
    {
        if ($this->hasGroupStack()) {
            $last = end($this->groupStack);

            return $last['prefix'] ?? '';
        }

        return '';
    }

    /**
     * Add a route to the underlying route collection.
     *
     * @param  array|string  $methods
     * @param  string  $uri
     * @param  array|string|callable|null  $action
     * @return \Framework\Routing\Route
     */
    public function addRoute($methods, $uri, $action): Route
    {
        return $this->routes->add($this->createRoute($methods, $uri, $action));
    }

    /**
     * Create a new route instance.
     *
     * @param  array|string  $methods
     * @param  string  $uri
     * @param  mixed  $action
     * @return \Framework\Routing\Route
     */
    protected function createRoute($methods, $uri, $action): Route
    {
        // If the route is routing to a controller we will parse the route action into
        // an acceptable array format before registering it and creating this route
        // instance itself. We need to build the Closure that will call this out.
        if ($this->actionReferencesController($action)) {
            $action = $this->convertToControllerAction($action);
        }

        $route = $this->newRoute(
            $methods,
            $this->prefix($uri),
            $action
        );

        // If we have groups that need to be merged, we will merge them now after this
        // route has already been created and is ready to go. After we're done with
        // the merge we will be ready to return the route back out to the caller.
        if ($this->hasGroupStack()) {
            $this->mergeGroupAttributesIntoRoute($route);
        }

        $this->addWhereClausesToRoute($route);

        return $route;
    }

    /**
     * Determine if the action is routing to a controller.
     *
     * @param  mixed  $action
     * @return bool
     */
    protected function actionReferencesController($action): bool
    {
        if (! $action instanceof Closure) {
            return is_string($action) || (isset($action['uses']) && is_string($action['uses']));
        }

        return false;
    }

    /**
     * Add a controller based route action to the action array.
     *
     * @param  array|string  $action
     * @return array
     */
    protected function convertToControllerAction($action): array|string
    {
        if (is_string($action)) {
            $action = ['uses' => $action];
        }

        // Here we'll merge any group "controller" and "uses" statements if necessary so that
        // the action has the proper clause for this property. Then, we can simply set the
        // name of this controller on the action plus return the action array for usage.
        if ($this->hasGroupStack()) {
            $action['uses'] = $this->prependGroupController($action['uses']);
            $action['uses'] = $this->prependGroupNamespace($action['uses']);
        }

        // Here we will set this controller name on the action array just so we always
        // have a copy of it for reference if we need it. This can be used while we
        // search for a controller name or do some other type of fetch operation.
        $action['controller'] = $action['uses'];

        return $action;
    }

    /**
     * Prepend the last group namespace onto the use clause.
     *
     * @param  string  $class
     * @return string
     */
    protected function prependGroupNamespace($class): string
    {
        $group = end($this->groupStack);

        return isset($group['namespace']) && ! str_starts_with($class, '\\') && ! str_starts_with($class, $group['namespace'])
            ? $group['namespace'] . '\\' . $class : $class;
    }

    /**
     * Prepend the last group controller onto the use clause.
     *
     * @param  string  $class
     * @return string
     */
    protected function prependGroupController($class): string
    {
        $group = end($this->groupStack);

        if (! isset($group['controller'])) {
            return $class;
        }

        if (class_exists($class)) {
            return $class;
        }

        if (str_contains($class, '@')) {
            return $class;
        }

        return $group['controller'] . '@' . $class;
    }

    /**
     * Create a new Route object.
     *
     * @param  array|string  $methods
     * @param  string  $uri
     * @param  mixed  $action
     * @return \Framework\Routing\Route
     */
    public function newRoute($methods, $uri, $action): Route
    {
        return (new Route(
            $methods,
            $uri,
            $action
        ))
            ->setContainer($this->container);
    }

    /**
     * Prefix the given URI with the last prefix.
     *
     * @param  string  $uri
     * @return string
     */
    protected function prefix($uri): string
    {
        return trim(trim($this->getLastGroupPrefix(), '/') . '/' . trim($uri, '/'), '/') ?: '/';
    }

    /**
     * Add the necessary where clauses to the route based on its initial registration.
     *
     * @param  \Framework\Routing\Route  $route
     * @return \Framework\Routing\Route
     */
    protected function addWhereClausesToRoute($route): Route
    {
        $route->where(
            $route->getAction()['where'] ?? []
        );

        return $route;
    }

    /**
     * Merge the group stack with the controller action.
     *
     * @param  \Framework\Routing\Route  $route
     * @return void
     */
    protected function mergeGroupAttributesIntoRoute($route): void
    {
        $route->setAction($this->mergeWithLastGroup(
            $route->getAction(),
            $prependExistingPrefix = false
        ));
    }

    /**
     * Dispatch the request to the application.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function dispatch(ServerRequestInterface $request): ResponseInterface
    {
        return $this->runRoute($request, $this->findRoute($request));
    }

    /**
     * Find the route matching a given request.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @return \Framework\Routing\Route
     */
    protected function findRoute($request): Route
    {
        $this->current = $route = $this->routes->match($request);

        $route->setContainer($this->container);

        $this->container->instance(Route::class, $route);

        return $route;
    }

    /**
     * Return the response for the given route.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @param  \Framework\Routing\Route  $route
     * @return \Psr\Http\Message\ResponseInterface
     */
    protected function runRoute(ServerRequestInterface $request, Route $route): ResponseInterface
    {
        foreach ($route->parameters() as $key => $value) {
            $request = $request->withAttribute($key, $value);
        }

        $request = $request->withAttribute('route', $route);

        $this->container->instance('request', $request);

        return $this->prepareResponse(
            $request,
            $this->runRouteWithinStack($route, $request)
        );
    }

    /**
     * Run the given route within a Stack "onion" instance.
     *
     * @param  \Framework\Routing\Route  $route
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @return mixed
     */
    protected function runRouteWithinStack(Route $route, ServerRequestInterface $request): mixed
    {
        $shouldSkipMiddleware = $this->container->bound('middleware.disable') &&
            $this->container->make('middleware.disable') === true;

        $middleware = $shouldSkipMiddleware ? [] : $this->gatherRouteMiddleware($route);

        return (new Pipeline($this->container))
            ->send($request)
            ->through($middleware)
            ->then(
                function ($request) use ($route) {
                    $this->container->instance('request', $request);

                    return $this->prepareResponse(
                        $request,
                        $route->run()
                    );
                }
            );
    }

    /**
     * Gather the middleware for the given route with resolved class names.
     *
     * @param  \Framework\Routing\Route  $route
     * @return array
     */
    public function gatherRouteMiddleware(Route $route): array
    {
        return $this->resolveMiddleware(
            $route->gatherMiddleware(),
            $route->excludedMiddleware()
        );
    }

    /**
     * Resolve a flat array of middleware classes from the provided array.
     *
     * @param  array  $middleware
     * @param  array  $excluded
     * @return array
     */
    public function resolveMiddleware(array $middleware, array $excluded = [])
    {
        $excluded = Arr::flatten(
            array_map(function ($name) {
                return (array) MiddlewareNameResolver::resolve(
                    $name,
                    $this->middleware,
                    $this->middlewareGroups
                );
            }, $excluded)
        );

        $middleware = Arr::flatten(
            array_map(function ($name) {
                return (array) MiddlewareNameResolver::resolve(
                    $name,
                    $this->middleware,
                    $this->middlewareGroups
                );
            }, $middleware)
        );

        $middleware = array_diff(array_unique(array_merge(
            $middleware,
            $excluded
        )));

        return $this->sortMiddleware($middleware);
    }

    /**
     * Sort the given middleware by priority.
     *
     * @param  array  $middlewares
     * @return array
     */
    protected function sortMiddleware($middlewares)
    {
        return (new SortedMiddleware($this->middlewarePriority, $middlewares))->all();
    }

    /**
     * Create a response instance from the given value.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @param  mixed  $response
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function prepareResponse($request, $response): ResponseInterface
    {
        return ResponseFactory::prepareResponse($request, $response);
    }

    /**
     * Determine if the router currently has a group stack.
     *
     * @return bool
     */
    public function hasGroupStack(): bool
    {
        return ! empty($this->groupStack);
    }

    /**
     * Get the current group stack for the router.
     *
     * @return array
     */
    public function getGroupStack(): array
    {
        return $this->groupStack;
    }

    /**
     * Get all of the defined middleware short-hand names.
     *
     * @return array
     */
    public function getMiddleware()
    {
        return $this->middleware;
    }

    /**
     * Register a short-hand name for a middleware.
     *
     * @param  string  $name
     * @param  string  $class
     * @return $this
     */
    public function aliasMiddleware($name, $class)
    {
        $this->middleware[$name] = $class;

        return $this;
    }

    /**
     * Check if a middlewareGroup with the given name exists.
     *
     * @param  string  $name
     * @return bool
     */
    public function hasMiddlewareGroup($name)
    {
        return array_key_exists($name, $this->middlewareGroups);
    }

    /**
     * Get all of the defined middleware groups.
     *
     * @return array
     */
    public function getMiddlewareGroups()
    {
        return $this->middlewareGroups;
    }

    /**
     * Register a group of middleware.
     *
     * @param  string  $name
     * @param  array  $middleware
     * @return $this
     */
    public function middlewareGroup($name, array $middleware)
    {
        $this->middlewareGroups[$name] = $middleware;

        return $this;
    }

    /**
     * Get the currently dispatched route instance.
     *
     * @return \Framework\Routing\Route|null
     */
    public function current()
    {
        return $this->current;
    }

    /**
     * Get the underlying route collection.
     *
     * @return \Framework\Routing\RouteCollection
     */
    public function getRoutes(): RouteCollection
    {
        return $this->routes;
    }

    /**
     * Set the route collection instance.
     *
     * @param  \Framework\Routing\RouteCollection  $routes
     * @return void
     */
    public function setRoutes(RouteCollection $routes): void
    {
        foreach ($routes as $route) {
            $route->setContainer($this->container);
        }

        $this->routes = $routes;

        $this->container->instance('routes', $this->routes);
    }

    /**
     * Remove any duplicate middleware from the given array.
     *
     * @param  array  $middleware
     * @return array
     */
    public static function uniqueMiddleware(array $middleware)
    {
        $seen = [];
        $result = [];

        foreach ($middleware as $value) {
            $key = \is_object($value) ? \spl_object_id($value) : $value;

            if (! isset($seen[$key])) {
                $seen[$key] = true;
                $result[] = $value;
            }
        }

        return $result;
    }
}
