<?php

namespace Framework\Contracts\Http;

interface Kernel
{
    /**
     * Bootstrap the application for HTTP requests.
     *
     * @return void
     */
    public function bootstrap();

    /**
     * Handle an incoming HTTP request.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function handle($request);

    /**
     * Perform any final actions for the request lifecycle.
     *
     * @param  \Psr\Http\Message\ServerRequestInterface  $request
     * @param  \Psr\Http\Message\ResponseInterface  $response
     * @return void
     */
    public function terminate($request, $response);

    /**
     * Get the Framework application instance.
     *
     * @return \Framework\Contracts\Core\Application
     */
    public function getApplication();
}
