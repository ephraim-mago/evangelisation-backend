<?php

namespace Framework\Routing\Utils;

class RouteParameterBinder
{
    /**
     * The route instance.
     *
     * @var \Framework\Routing\Route
     */
    protected $route;

    /**
     * Create a new Route parameter binder instance.
     *
     * @param  \Framework\Routing\Route  $route
     */
    public function __construct($route)
    {
        $this->route = $route;
    }

    /**
     * Get the parameters for the route.
     *
     * @param  array  $parameters
     * @return array
     */
    public function parameters($parameters)
    {
        if (empty($parameterNames = $this->route->parameterNames())) {
            return [];
        }

        $parameters = array_intersect_key($parameters, array_flip($parameterNames));

        return array_filter($parameters, function ($value) {
            return is_string($value) && strlen($value) > 0;
        });
    }
}
