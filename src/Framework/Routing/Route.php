<?php

namespace Framework\Routing;

use LogicException;
use Framework\Support\Str;
use Framework\Collection\Arr;
use Framework\Container\Container;
use Framework\Routing\Utils\RouteUri;
use Framework\Routing\Utils\RouteAction;
use Framework\Routing\Resolvers\CallableResolver;
use Framework\Routing\Utils\RouteParameterBinder;
use Framework\Http\Exception\HttpResponseException;
use Framework\Routing\Resolvers\ControllerResolver;

class Route
{
    /**
     * The URI pattern the route responds to.
     *
     * @var string
     */
    protected $uri;

    /**
     * The HTTP methods the route responds to.
     *
     * @var array
     */
    protected $methods;

    /**
     * The route action array.
     *
     * @var array
     */
    protected $action;

    /**
     * The controller instance.
     *
     * @var mixed
     */
    protected $controller;

    /**
     * The regular expression requirements.
     *
     * @var array
     */
    protected $wheres = [];

    /**
     * The array of matched parameters.
     *
     * @var array|null
     */
    protected $parameters;

    /**
     * The parameter names for the route.
     *
     * @var array|null
     */
    protected $parameterNames;

    /**
     * The computed gathered middleware.
     *
     * @var array|null
     */
    protected $computedMiddleware;

    /**
     * The container instance used by the route.
     *
     * @var \Framework\Container\Container
     */
    protected $container;

    /**
     * The fields that implicit binding should use for a given parameter.
     *
     * @var array
     */
    protected $bindingFields = [];

    /**
     * Create a new Route instance.
     *
     * @param  array|string  $methods
     * @param  string  $uri
     * @param  \Closure|array  $action
     */
    public function __construct($methods, $uri, $action)
    {
        $this->uri = $uri;
        $this->methods = (array) $methods;
        $this->action = Arr::except($this->parseAction($action), ['prefix']);

        if (in_array('GET', $this->methods) && ! in_array('HEAD', $this->methods)) {
            $this->methods[] = 'HEAD';
        }

        $this->prefix(is_array($action) ? Arr::get($action, 'prefix') : '');
    }

    /**
     * Parse the route action into a standard array.
     *
     * @param  callable|array|null  $action
     * @return array
     *
     * @throws \UnexpectedValueException
     */
    protected function parseAction($action)
    {
        return RouteAction::parse($this->uri, $action);
    }

    /**
     * Run the route action and return the response.
     *
     * @return mixed
     */
    public function run()
    {
        $this->container = $this->container ?: new Container;

        try {
            if ($this->isControllerAction()) {
                return $this->runController();
            }

            return $this->runCallable();
        } catch (HttpResponseException $e) {
            return $e->getResponse();
        }
    }

    /**
     * Checks whether the route's action is a controller.
     *
     * @return bool
     */
    protected function isControllerAction()
    {
        return is_string($this->action['uses']);
    }

    /**
     * Run the route action and return the response.
     *
     * @return mixed
     */
    protected function runCallable()
    {
        $callable = $this->action['uses'];

        return $this->container[CallableResolver::class]->dispatch($this, $callable);
    }

    /**
     * Run the route action and return the response.
     *
     * @return mixed
     *
     * @throws \Framework\Http\Exception\NotFoundHttpException
     */
    protected function runController()
    {
        return $this->ControllerResolver()->dispatch(
            $this,
            $this->getController(),
            $this->getControllerMethod()
        );
    }

    /**
     * Get the controller instance for the route.
     *
     * @return mixed
     */
    public function getController()
    {
        if (! $this->isControllerAction()) {
            return null;
        }

        if (! $this->controller) {
            $class = $this->getControllerClass();

            $this->controller = $this->container->make(ltrim($class, '\\'));
        }

        return $this->controller;
    }

    /**
     * Get the controller class used for the route.
     *
     * @return string|null
     */
    public function getControllerClass()
    {
        return $this->isControllerAction() ? $this->parseControllerCallback()[0] : null;
    }

    /**
     * Get the controller method used for the route.
     *
     * @return string
     */
    protected function getControllerMethod()
    {
        return $this->parseControllerCallback()[1];
    }

    /**
     * Parse the controller.
     *
     * @return array
     */
    protected function parseControllerCallback()
    {
        return Str::parseCallback($this->action['uses']);
    }

    /**
     * Bind the route to a given parameters for execution.
     *
     * @param  array  $parameters
     * @return $this
     */
    public function bind(array $parameters)
    {
        $this->parameters = (new RouteParameterBinder($this))
            ->parameters($parameters);

        return $this;
    }

    /**
     * Determine if the route has parameters.
     *
     * @return bool
     */
    public function hasParameters()
    {
        return isset($this->parameters);
    }

    /**
     * Determine a given parameter exists from the route.
     *
     * @param  string  $name
     * @return bool
     */
    public function hasParameter($name)
    {
        if ($this->hasParameters()) {
            return array_key_exists($name, $this->parameters());
        }

        return false;
    }

    /**
     * Get a given parameter from the route.
     *
     * @param  string  $name
     * @param  string|object|null  $default
     * @return string|object|null
     */
    public function parameter($name, $default = null)
    {
        return Arr::get($this->parameters(), $name, $default);
    }

    /**
     * Set a parameter to the given value.
     *
     * @param  string  $name
     * @param  string|object|null  $value
     * @return void
     */
    public function setParameter($name, $value)
    {
        $this->parameters();

        $this->parameters[$name] = $value;
    }

    /**
     * Unset a parameter on the route if it is set.
     *
     * @param  string  $name
     * @return void
     */
    public function forgetParameter($name)
    {
        $this->parameters();

        unset($this->parameters[$name]);
    }

    /**
     * Get the key / value list of parameters for the route.
     *
     * @return array
     *
     * @throws \LogicException
     */
    public function parameters()
    {
        if (isset($this->parameters)) {
            return $this->parameters;
        }

        throw new LogicException('Route is not bound.');
    }

    /**
     * Get the key / value list of parameters without null values.
     *
     * @return array
     */
    public function parametersWithoutNulls()
    {
        return array_filter($this->parameters(), fn($p) => ! is_null($p));
    }

    /**
     * Get all of the parameter names for the route.
     *
     * @return array
     */
    public function parameterNames()
    {
        if (isset($this->parameterNames)) {
            return $this->parameterNames;
        }

        return $this->parameterNames = $this->compileParameterNames();
    }

    /**
     * Get the parameter names for the route.
     *
     * @return array
     */
    protected function compileParameterNames()
    {
        preg_match_all('/\{(.*?)\}/', $this->uri, $matches);

        return array_map(fn($m) => trim($m, '?'), $matches[1]);
    }

    /**
     * Set a regular expression requirement on the route.
     *
     * @param  array|string  $name
     * @param  string|null  $expression
     * @return $this
     */
    public function where($name, $expression = null)
    {
        foreach ($this->parseWhere($name, $expression) as $name => $expression) {
            $this->wheres[$name] = $expression;
        }

        return $this;
    }

    /**
     * Parse arguments to the where method into an array.
     *
     * @param  array|string  $name
     * @param  string  $expression
     * @return array
     */
    protected function parseWhere($name, $expression)
    {
        return is_array($name) ? $name : [$name => $expression];
    }

    /**
     * Set a list of regular expression requirements on the route.
     *
     * @param  array  $wheres
     * @return $this
     */
    public function setWheres(array $wheres)
    {
        foreach ($wheres as $name => $expression) {
            $this->where($name, $expression);
        }

        return $this;
    }

    /**
     * Get the HTTP verbs the route responds to.
     *
     * @return array
     */
    public function methods()
    {
        return $this->methods;
    }

    /**
     * Get the prefix of the route instance.
     *
     * @return string|null
     */
    public function getPrefix()
    {
        return $this->action['prefix'] ?? null;
    }

    /**
     * Add a prefix to the route URI.
     *
     * @param  string  $prefix
     * @return $this
     */
    public function prefix($prefix)
    {
        $prefix ??= '';

        $this->updatePrefixOnAction($prefix);

        $uri = rtrim($prefix, '/') . '/' . ltrim($this->uri, '/');

        return $this->setUri($uri !== '/' ? trim($uri, '/') : $uri);
    }

    /**
     * Update the "prefix" attribute on the action array.
     *
     * @param  string  $prefix
     * @return void
     */
    protected function updatePrefixOnAction($prefix)
    {
        if (! empty($newPrefix = trim(rtrim($prefix, '/') . '/' . ltrim($this->action['prefix'] ?? '', '/'), '/'))) {
            $this->action['prefix'] = $newPrefix;
        }
    }

    /**
     * Get the URI associated with the route.
     *
     * @return string
     */
    public function uri()
    {
        return $this->uri;
    }

    /**
     * Set the URI that the route responds to.
     *
     * @param  string  $uri
     * @return $this
     */
    public function setUri($uri)
    {
        $this->uri = $this->parseUri($uri);

        return $this;
    }

    /**
     * Parse the route URI and normalize / store any implicit binding fields.
     *
     * @param  string  $uri
     * @return string
     */
    protected function parseUri($uri)
    {
        $this->bindingFields = [];

        return tap(RouteUri::parse($uri), function ($uri) {
            $this->bindingFields = $uri->bindingFields;
        })->uri;
    }

    /**
     * Get the name of the route instance.
     *
     * @return string|null
     */
    public function getName()
    {
        return $this->action['as'] ?? null;
    }

    /**
     * Add or change the route name.
     *
     * @param  string  $name
     * @return $this
     */
    public function name($name)
    {
        $this->action['as'] = isset($this->action['as']) ? $this->action['as'] . $name : $name;

        return $this;
    }

    /**
     * Get the action array or one of its properties for the route.
     *
     * @param  string|null  $key
     * @return mixed
     */
    public function getAction($key = null)
    {
        return Arr::get($this->action, $key);
    }

    /**
     * Set the action array for the route.
     *
     * @param  array  $action
     * @return $this
     */
    public function setAction(array $action)
    {
        $this->action = $action;

        return $this;
    }

    /**
     * Get all middleware, including the ones from the controller.
     *
     * @return array
     */
    public function gatherMiddleware()
    {
        if (! is_null($this->computedMiddleware)) {
            return $this->computedMiddleware;
        }

        $this->computedMiddleware = [];

        return $this->computedMiddleware = Router::uniqueMiddleware(array_merge(
            $this->middleware(),
            $this->controllerMiddleware()
        ));
    }

    /**
     * Get or set the middlewares attached to the route.
     *
     * @param  array|string|null  $middleware
     * @return $this|array
     */
    public function middleware($middleware = null)
    {
        if (is_null($middleware)) {
            return (array) ($this->action['middleware'] ?? []);
        }

        if (! is_array($middleware)) {
            $middleware = func_get_args();
        }

        foreach ($middleware as $index => $value) {
            $middleware[$index] = (string) $value;
        }

        $this->action['middleware'] = array_merge(
            (array) ($this->action['middleware'] ?? []),
            $middleware
        );

        return $this;
    }

    /**
     * Get the middleware for the route's controller.
     *
     * @return array
     */
    public function controllerMiddleware()
    {
        if (! $this->isControllerAction()) {
            return [];
        }

        [$controllerClass, $controllerMethod] = [
            $this->getControllerClass(),
            $this->getControllerMethod(),
        ];

        if (method_exists($controllerClass, 'getMiddleware')) {
            return $this->ControllerResolver()->getMiddleware(
                $this->getController(),
                $controllerMethod
            );
        }

        return [];
    }

    /**
     * Specify middleware that should be removed from the given route.
     *
     * @param  array|string  $middleware
     * @return $this
     */
    public function withoutMiddleware($middleware)
    {
        $this->action['excluded_middleware'] = array_merge(
            (array) ($this->action['excluded_middleware'] ?? []),
            Arr::wrap($middleware)
        );

        return $this;
    }

    /**
     * Get the middleware that should be removed from the route.
     *
     * @return array
     */
    public function excludedMiddleware()
    {
        return (array) ($this->action['excluded_middleware'] ?? []);
    }

    /**
     * Get the resolver for the route's controller.
     *
     * @return \Framework\Routing\Resolvers\ControllerResolver
     */
    public function ControllerResolver(): ControllerResolver
    {
        if ($this->container->bound(ControllerResolver::class)) {
            return $this->container->make(ControllerResolver::class);
        }

        return new ControllerResolver($this->container);
    }

    /**
     * Set the container instance on the route.
     *
     * @param  \Framework\Container\Container  $container
     * @return $this
     */
    public function setContainer(Container $container)
    {
        $this->container = $container;

        return $this;
    }
}
